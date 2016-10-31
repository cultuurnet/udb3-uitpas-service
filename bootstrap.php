<?php

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\EventDispatcher\EventDispatcher;
use Broadway\EventHandling\EventBusInterface;
use Broadway\EventStore\DBALEventStore;
use Broadway\Saga\MultipleSagaManager;
use Broadway\Saga\State\MongoDBRepository;
use Broadway\Serializer\SimpleInterfaceSerializer;
use CultuurNet\BroadwayAMQP\DomainMessageJSONDeserializer;
use CultuurNet\BroadwayAMQP\EventBusForwardingConsumerFactory;
use CultuurNet\Deserializer\SimpleDeserializerLocator;
use CultuurNet\UDB3\EventSourcing\ExecutionContextMetadataEnricher;
use CultuurNet\UDB3\SimpleEventBus;
use CultuurNet\UDB3\UiTPASService\Command\RemotelySyncUiTPASCommandHandler;
use CultuurNet\UDB3\UiTPASService\EventStoreSchemaConfigurator;
use CultuurNet\UDB3\UiTPASService\Specification\IsUiTPASOrganizerAccordingToJSONLD;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregateRepository;
use CultuurNet\UDB3\UiTPASService\UiTPASEventSaga;
use DerAlex\Silex\YamlConfigServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Silex\Application;
use ValueObjects\Number\Natural;
use ValueObjects\String\String as StringLiteral;

$app = new Application();

if (!isset($appConfigLocation)) {
    $appConfigLocation =  __DIR__;
}
$app->register(new YamlConfigServiceProvider($appConfigLocation . '/config.yml'));

/**
 * Turn debug on or off.
 */
$app['debug'] = $app['config']['debug'] === true;

/**
 * Load additional bootstrap files.
 */
foreach ($app['config']['bootstrap'] as $identifier => $enabled) {
    if (true === $enabled) {
        require __DIR__ . "/bootstrap/{$identifier}.php";
    }
}

$app['mongodb_sagas_collection'] = $app->share(
  function (Application $app) {
    $mongoConf = $app['config']['mongo'] + array(
      'connection' => 'mongodb://127.0.0.1',
      'db' => 'uitpas',
    );

    $client = new MongoClient($mongoConf['connection']);
    $connection = new Doctrine\MongoDB\Connection($client);

    return $connection->selectCollection($mongoConf['db'], 'sagas');
  }
);

$app['dbal_connection'] = $app->share(
    function ($app) {
        $eventManager = new \Doctrine\Common\EventManager();
        $sqlMode = 'NO_ENGINE_SUBSTITUTION,STRICT_ALL_TABLES';
        $query = "SET SESSION sql_mode = '{$sqlMode}'";
        $eventManager->addEventSubscriber(
            new \Doctrine\DBAL\Event\Listeners\SQLSessionInit($query)
        );

        $connection = \Doctrine\DBAL\DriverManager::getConnection(
            $app['config']['database'],
            null,
            $eventManager
        );

        return $connection;
    }
);

$app['database.migrations.configuration'] = $app->share(
    function (Application $app) {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $app['dbal_connection'];

        $configuration = new \Doctrine\DBAL\Migrations\Configuration\YamlConfiguration($connection);
        $configuration->load(__DIR__ . '/migrations.yml');

        return $configuration;
    }
);

$app['database.installer'] = $app->share(
    function (Application $app) {
        $installer = new \CultuurNet\UDB3\UiTPASService\DatabaseSchemaInstaller(
            $app['dbal_connection'],
            $app['database.migrations.configuration']
        );
        
        $installer->addSchemaConfigurator(
            new EventStoreSchemaConfigurator(
                $app['event_store']
            )
        );

        return $installer;
    }
);

$app['dbal_connection:keepalive'] = $app->protect(
    function (Application $app) {
        /** @var \Doctrine\DBAL\Connection $db */
        $db = $app['dbal_connection'];

        $db->query('SELECT 1')->execute();
    }
);

$app['logger.amqp.udb3_publisher'] = $app->share(
    function (Application $app) {
        $logger = new Monolog\Logger('amqp.udb3_publisher');
        $logger->pushHandler(new StreamHandler('php://stdout'));

        $logFileHandler = new StreamHandler(
            __DIR__ . '/log/amqp.log',
            Logger::DEBUG
        );
        $logger->pushHandler($logFileHandler);

        return $logger;
    }
);

$app['deserializer_locator'] = $app->share(
    function (Application $app) {
        $deserializerLocator = new SimpleDeserializerLocator();
        $maps =
            \CultuurNet\UDB3\Event\Events\ContentTypes::map() +
            \CultuurNet\UDB3\Place\Events\ContentTypes::map() +
            \CultuurNet\UDB3\Label\Events\ContentTypes::map() +
            \CultuurNet\UDB3\Organizer\Events\ContentTypes::map();

        foreach ($maps as $payloadClass => $contentType) {
            $deserializerLocator->registerDeserializer(
                new StringLiteral($contentType),
                new DomainMessageJSONDeserializer($payloadClass)
            );
        }
        return $deserializerLocator;
    }
);

$app->register(
    new \CultuurNet\SilexAMQP\AMQPConnectionServiceProvider(),
    [
        'amqp.connection.host' => $app['config']['amqp']['host'],
        'amqp.connection.port' => $app['config']['amqp']['port'],
        'amqp.connection.user' => $app['config']['amqp']['user'],
        'amqp.connection.password' => $app['config']['amqp']['password'],
        'amqp.connection.vhost' => $app['config']['amqp']['vhost'],
    ]
);

$app['event_bus_forwarding_consumer_factory'] = $app->share(
    function (Application $app) {
        return new EventBusForwardingConsumerFactory(
            Natural::fromNative($app['config']['consumerExecutionDelay']),
            $app['amqp.connection'],
            $app['logger.amqp.udb3_publisher'],
            $app['deserializer_locator'],
            $app['event_bus.udb3-core'],
            new StringLiteral($app['config']['amqp']['consumer_tag'])
        );
    }
);

foreach (['udb3-core'] as $consumerId) {
    $app['amqp.' . $consumerId] = $app->share(
        function (Application $app) use ($consumerId) {
            $consumerConfig = $app['config']['amqp']['consumers'][$consumerId];
            $exchange = new StringLiteral($consumerConfig['exchange']);
            $queue = new StringLiteral($consumerConfig['queue']);

            /** @var EventBusForwardingConsumerFactory $consumerFactory */
            $consumerFactory = $app['event_bus_forwarding_consumer_factory'];

            return $consumerFactory->create($exchange, $queue);
        }
    );
}

// Incoming event-stream from UDB3.
$app['event_bus.udb3-core'] = $app->share(
    function (Application $app) {
        $bus =  new SimpleEventBus();

        $bus->beforeFirstPublication(function (EventBusInterface $eventBus) use ($app) {
            $subscribers = [
                'saga_manager',
            ];

            // Allow to override event bus subscribers through configuration.
            if (isset($app['config']['event_bus']) &&
                isset($app['config']['event_bus']['subscribers'])) {

                $subscribers = $app['config']['event_bus']['subscribers'];
            }

            foreach ($subscribers as $subscriberServiceId) {
                $eventBus->subscribe($app[$subscriberServiceId]);
            }
        });

        return $bus;
    }
);

$app['event_bus.uitpas'] = $app->share(
    function (Application $app) {
        $bus =  new SimpleEventBus();

        $bus->beforeFirstPublication(function (EventBusInterface $eventBus) {
            $subscribers = [];

            // Allow to override event bus subscribers through configuration.
            if (isset($app['config']['event_bus']) &&
                isset($app['config']['event_bus']['subscribers'])) {

                $subscribers = $app['config']['event_bus']['subscribers'];
            }

            foreach ($subscribers as $subscriberServiceId) {
                $eventBus->subscribe($app[$subscriberServiceId]);
            }
        });

        return $bus;
    }
);

$app['saga_repository'] = $app->share(
    function (Application $app) {
        return new MongoDBRepository(
            $app['mongodb_sagas_collection']
        );
    }
);

$app->register(new \CultuurNet\UDB3\UiTPASService\Resque\CommandBusServiceProvider());

$app['execution_context_metadata_enricher'] = $app->share(
    function (Application $app) {
        return new ExecutionContextMetadataEnricher();
    }
);

$app['command_bus_event_dispatcher'] = $app->share(
    function ($app) {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            \CultuurNet\UDB3\CommandHandling\ResqueCommandBus::EVENT_COMMAND_CONTEXT_SET,
            function ($context) use ($app) {
                $app['execution_context_metadata_enricher']->setContext(
                    $context
                );
            }
        );

        return $dispatcher;
    }
);

$app['logger.command_bus'] = $app->share(
    function ($app) {
        $logger = new Logger('command_bus');

        $handlers = $app['config']['log.command_bus'];
        foreach ($handlers as $handler_config) {
            switch ($handler_config['type']) {
                case 'hipchat':
                    $handler = new \Monolog\Handler\HipChatHandler(
                        $handler_config['token'],
                        $handler_config['room']
                    );
                    break;
                case 'file':
                    $handler = new \Monolog\Handler\StreamHandler(
                        __DIR__ . '/web/' . $handler_config['path']
                    );
                    break;
                case 'socketioemitter':
                    $redisConfig = isset($handler_config['redis']) ? $handler_config['redis'] : array();
                    $redisConfig += array(
                        'host' => '127.0.0.1',
                        'port' => 6379,
                    );
                    if (extension_loaded('redis')) {
                        $redis = new \Redis();
                        $redis->connect(
                            $redisConfig['host'],
                            $redisConfig['port']
                        );
                    } else {
                        $redis = new Predis\Client(
                            [
                                'host' => $redisConfig['host'],
                                'port' => $redisConfig['port']
                            ]
                        );
                        $redis->connect();
                    }

                    $emitter = new \SocketIO\Emitter($redis);

                    if (isset($handler_config['namespace'])) {
                        $emitter->of($handler_config['namespace']);
                    }

                    if (isset($handler_config['room'])) {
                        $emitter->in($handler_config['room']);
                    }

                    $handler = new \CultuurNet\UDB3\Monolog\SocketIOEmitterHandler(
                        $emitter
                    );
                    break;
                default:
                    continue 2;
            }

            $handler->setLevel($handler_config['level']);
            $handler->pushProcessor(
                new \Monolog\Processor\PsrLogMessageProcessor()
            );

            $logger->pushHandler($handler);
        }

        return $logger;
    }
);

/**
 * "uitpas" command bus.
 */
$app['resque_command_bus_factory']('uitpas');

/**
 * Tie command handlers to event command bus.
 */
$app->extend(
    'uitpas_command_bus_out',
    function (CommandBusInterface $commandBus, Application $app) {
        // @todo Subscribe command handlers here.

        $commandBus->subscribe($app['uitpas_sync_command_handler']);

        return $commandBus;
    }
);

$app['culturefeed_uitpas_client'] = $app->share(
    function (Application $app) {
        $oauthClient = new CultureFeed_DefaultOAuthClient(
            $app['config']['uitid']['consumer']['key'],
            $app['config']['uitid']['consumer']['secret']
        );

        $oauthClient->setEndpoint($app['config']['uitid']['base_url']);

        $cf = new CultureFeed($oauthClient);
        return $cf->uitpas();
    }
);

$app['uitpas_sync_command_handler'] = $app->share(
    function (Application $app) {
        return new RemotelySyncUiTPASCommandHandler(
            $app['culturefeed_uitpas_client']
        );
    }
);

$app['saga_manager'] = $app->share(
    function (Application $app) {
        return new MultipleSagaManager(
            $app['saga_repository'],
            [
                'uitpas_sync' => $app['uitpas_event_saga'],
            ],
            new \Broadway\Saga\State\StateManager(
                $app['saga_repository'],
                new Broadway\UuidGenerator\Rfc4122\Version4Generator()
            ),
            new \Broadway\Saga\Metadata\StaticallyConfiguredSagaMetadataFactory(),
            new EventDispatcher()
        );
    }
);

$app['uitpas_event_saga'] = $app->share(
    function (Application $app) {
        return new UiTPASEventSaga(
            $app['uitpas_command_bus'],
            $app['uitpas_organizer_spec']
        );
    }
);

$app['uitpas_organizer_spec'] = $app->share(
    function (Application $app) {
        $uitpasLabels = (array) $app['config']['labels'];

        $spec = new IsUiTPASOrganizerAccordingToJSONLD(
            $app['config']['udb3_organizer_base_url'],
            array_values($uitpasLabels)
        );

        $logger = new Logger('uitpas_organizer_spec');

        $stdOut = new StreamHandler('php://stdout');
        //$stdOut->setFormatter(new NormalizerFormatter());
        $logger->pushHandler($stdOut);

        $logFile = new StreamHandler(
            __DIR__ . '/log/uitpas_organizer_spec.log',
            Logger::DEBUG
        );
        //$logFile->setFormatter(new NormalizerFormatter());
        $logger->pushHandler($logFile);

        $spec->setLogger($logger);

        return $spec;
    }
);

$app['event_store'] = $app->share(
    function ($app) {
        return new DBALEventStore(
            $app['dbal_connection'],
            new SimpleInterfaceSerializer(),
            new SimpleInterfaceSerializer(),
            'events'
        );
    }
);

$app['uitpas_repository'] = $app->share(
    function (Application $app) {
        return new UiTPASAggregateRepository(
            $app['event_store'],
            $app['event_bus.uitpas']
        );
    }
);

return $app;
