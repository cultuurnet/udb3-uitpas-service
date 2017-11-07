<?php

use Broadway\EventHandling\SimpleEventBus;
use CultuurNet\BroadwayAMQP\EventBusForwardingConsumerFactory;
use CultuurNet\Deserializer\SimpleDeserializerLocator;
use CultuurNet\SymfonySecurityJwt\Authentication\JwtUserToken;
use DerAlex\Silex\YamlConfigServiceProvider;
use JDesrosiers\Silex\Provider\CorsServiceProvider;
use Lcobucci\JWT\Token as Jwt;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Silex\Application;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use ValueObjects\Number\Natural;
use ValueObjects\StringLiteral\StringLiteral;

$app = new Application();

if (!isset($appConfigLocation)) {
    $appConfigLocation =  __DIR__;
}
$app->register(new YamlConfigServiceProvider($appConfigLocation . '/config.yml'));

$app->register(new CorsServiceProvider(), array(
    "cors.allowOrigin" => implode(" ", $app['config']['cors']['origins']),
    "cors.allowCredentials" => true
));

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

$app['jwt'] = $app->share(
    function(Application $app) {
        try {
            /* @var TokenStorageInterface $tokenStorage */
            $tokenStorage = $app['security.token_storage'];
        } catch (\InvalidArgumentException $e) {
            // Running from CLI.
            return null;
        }

        $token = $tokenStorage->getToken();

        if ($token instanceof JwtUserToken) {
            return $token->getCredentials();
        }

        return null;
    }
);

$app['current_user'] = $app->share(
    function (Application $app) {
        /* @var Jwt|null $jwt */
        $jwt = $app['jwt'];

        if (!is_null($jwt)) {
            $cfUser = new \CultureFeed_User();

            $cfUser->id = $jwt->getClaim('uid');
            $cfUser->nick = $jwt->getClaim('nick');
            $cfUser->mbox = $jwt->getClaim('email');

            return $cfUser;
        } else {
            return null;
        }
    }
);

$app['logger.amqp.uitpas_incoming'] = $app->share(
    function () {
        $logger = new Monolog\Logger('amqp.uitpas_incoming');
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
    function () {
        $deserializerLocator = new SimpleDeserializerLocator();
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
            $app['logger.amqp.uitpas_incoming'],
            $app['deserializer_locator'],
            $app['event_bus.uitpas'],
            new StringLiteral($app['config']['amqp']['consumer_tag'])
        );
    }
);

foreach (['uitpas'] as $consumerId) {
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

$app['event_bus.uitpas'] = $app->share(
    function () {
        $bus =  new SimpleEventBus();
        return $bus;
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

return $app;
