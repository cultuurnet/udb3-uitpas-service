<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\CommandHandling\Testing\TraceableCommandBus;
use Broadway\EventDispatcher\EventDispatcher;
use Broadway\Saga\State\Criteria;
use Broadway\UuidGenerator\Rfc4122\Version4Generator;
use CommerceGuys\Intl\Currency\CurrencyRepository;
use CommerceGuys\Intl\NumberFormat\NumberFormatRepository;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Address\Locality;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Address\Street;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\CalendarType;
use CultuurNet\UDB3\Cdb\CdbId\EventCdbIdExtractor;
use CultuurNet\UDB3\Cdb\PriceDescriptionParser;
use CultuurNet\UDB3\Event\Events\Concluded;
use CultuurNet\UDB3\Event\Events\EventCopied;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Event\Events\OrganizerDeleted;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\PriceInfoUpdated;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Organizer\Events\LabelAdded;
use CultuurNet\UDB3\PriceInfo\BasePrice;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\PriceInfo\PriceInfo;
use CultuurNet\UDB3\PriceInfo\Tariff;
use CultuurNet\UDB3\Title;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\Metadata\StaticallyConfiguredSagaMetadataFactory;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\MultipleSagaManager;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\State\InMemoryRepository;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\State\StateCopier;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\State\StateCopierInterface;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\State\StateManager;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\Testing\Scenario;
use CultuurNet\UDB3\UiTPASService\OrganizerLabelReadRepository\OrganizerLabelReadRepositoryInterface;
use CultuurNet\UDB3\UiTPASService\Sync\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\UpdateUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\UiTPASAggregateCreated;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use ValueObjects\Geography\Country;
use ValueObjects\Money\Currency;
use ValueObjects\StringLiteral\StringLiteral;

/**
 * @todo Extend SagaScenarioTestCase when we update to Broadway >= 0.9.x
 */
class UiTPASEventSagaTest extends \PHPUnit_Framework_TestCase
{
    const SAGA_TYPE = 'uitpas_sync';

    /**
     * @var Scenario
     */
    private $scenario;

    /**
     * @var string
     */
    private $eventId;

    /**
     * @var EventCreated
     */
    private $eventCreated;

    /**
     * @var string
     */
    private $regularOrganizerId;

    /**
     * @var string
     */
    private $uitpasOrganizerId;

    /**
     * @var string
     */
    private $updatedUitpasOrganizerId;

    /**
     * @var PriceInfo
     */
    private $priceInfo;

    /**
     * @var string[]
     */
    private $distributionKeys;

    /**
     * @var UiTPASAggregateCreated
     */
    private $uitpasAggregateCreated;

    /**
     * @var OrganizerLabelReadRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $organizerLabelReader;

    /**
     * @var \CultureFeed_Uitpas|\PHPUnit_Framework_MockObject_MockObject
     */
    private $cultureFeedUitpas;

    /**
     * @var StateCopierInterface
     */
    private $stateCopier;

    /**
     * @var EventCdbIdExtractor
     */
    private $eventCdbIdExtractor;

    /**
     * @var LabelCollection
     */
    private $uitpasLabels;

    /**
     * @var TestHandler
     */
    private $logHandler;

    /**
     * @var InMemoryRepository
     */
    private $sagaStateRepository;

    public function setUp()
    {
        $this->logHandler = new TestHandler();

        $this->eventId = 'e1122fff-0f67-4042-82c3-6b5ca7af02d7';

        $this->eventCreated = $this->generateEventCreatedEvent($this->eventId);

        $this->regularOrganizerId = '72de67fb-d85c-4d3a-b464-b1157b83ed95';
        $this->uitpasOrganizerId = '6c1ac534-cd05-4ddb-a6d1-ba076aea9275';
        $this->updatedUitpasOrganizerId = 'd5c5303b-c9c4-4fb3-8c1f-d58b5f680b9a';

        $this->priceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(5.5),
                Currency::fromNative('EUR')
            )
        );

        $this->priceInfo = $this->priceInfo
            ->withExtraTariff(
                new Tariff(
                    new StringLiteral('Werkloze dodo kwekers'),
                    Price::fromFloat(2.75),
                    Currency::fromNative('EUR')
                )
            );

        $this->distributionKeys = [
            'distribution-key-123',
            'distribution-key-456',
        ];

        $this->uitpasAggregateCreated = new UiTPASAggregateCreated(
            $this->eventId,
            $this->distributionKeys
        );

        $this->organizerLabelReader = $this->createMock(OrganizerLabelReadRepositoryInterface::class);
        $this->uitpasLabels = new LabelCollection(
            [
                new Label('UiTPAS Gent'),
                new Label('UiTPAS Mechelen'),
            ]
        );

        $this->organizerLabelReader->expects($this->any())
            ->method('getLabels')
            ->willReturnCallback(
                function ($organizerId) {
                    $uitpasOrganizerIds = [$this->uitpasOrganizerId, $this->updatedUitpasOrganizerId];
                    if (in_array($organizerId, $uitpasOrganizerIds)) {
                        return new LabelCollection([new Label('UiTPAS Mechelen')]);
                    } else {
                        return new LabelCollection([new Label('foo')]);
                    }
                }
            );

        $this->cultureFeedUitpas = $this->createMock(\CultureFeed_Uitpas::class);

        $this->stateCopier = new StateCopier(new Version4Generator());

        $this->eventCdbIdExtractor = new EventCdbIdExtractor();

        $this->sagaStateRepository = new InMemoryRepository();
        $this->scenario = $this->createScenario();
    }

    /**
     * @return Scenario
     */
    protected function createScenario()
    {
        $traceableCommandBus = new TraceableCommandBus();
        $saga                = $this->createSaga($traceableCommandBus);
        $sagaManager         = new MultipleSagaManager(
            $this->sagaStateRepository,
            [self::SAGA_TYPE => $saga],
            new StateManager($this->sagaStateRepository, new Version4Generator()),
            new StaticallyConfiguredSagaMetadataFactory(),
            new EventDispatcher()
        );
        return new Scenario($this, $sagaManager, $traceableCommandBus);
    }

    /**
     * @test
     * @group issue-III-1807
     */
    public function it_starts_the_saga_when_an_event_is_created()
    {
        $eventId = '';
        $this->scenario
            ->when(
                $this->generateEventCreatedEvent($eventId)
            )
            ->then([]);

        $states = $this->sagaStateRepository->findBy(
            new Criteria(['uitpasAggregateId' => $eventId]),
            self::SAGA_TYPE
        );

        $statesArray = iterator_to_array($states);
        $this->assertCount(1, $statesArray);
    }

    /**
     * @param CommandBusInterface $commandBus
     * @return UiTPASEventSaga
     */
    protected function createSaga(CommandBusInterface $commandBus)
    {
        $saga = new UiTPASEventSaga(
            $commandBus,
            $this->eventCdbIdExtractor,
            new PriceDescriptionParser(
                new NumberFormatRepository(),
                new CurrencyRepository()
            ),
            $this->uitpasLabels,
            $this->organizerLabelReader,
            $this->cultureFeedUitpas,
            $this->stateCopier
        );

        $saga->setLogger(new Logger('uitpas saga', [$this->logHandler]));

        return $saga;
    }

    /**
     * @test
     */
    public function it_creates_an_uitpas_aggregate_and_registers_an_uitpas_event_when_an_uitpas_organizer_has_been_selected_and_price_info_is_entered()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                ]
            )
            ->when(new PriceInfoUpdated($this->eventId, $this->priceInfo))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo
                    ),
                ]
            );

        $expectedLogContext = [
            'organizer' => $this->uitpasOrganizerId,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
            'extracted_organizer_labels' => ['UiTPAS Mechelen'],
            'organizer_uitpas_labels' => ['UiTPAS Mechelen'],
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'uitpas labels present on organizer',
            $expectedLogContext,
            0
        );
    }

    /**
     * @test
     */
    public function it_creates_an_uitpas_aggregate_and_registers_an_uitpas_event_when_price_info_has_been_entered_and_an_uitpas_organizer_is_selected()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo
                    ),
                ]
            );

        $expectedLogContext = [
            'organizer' => $this->uitpasOrganizerId,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
            'extracted_organizer_labels' => ['UiTPAS Mechelen'],
            'organizer_uitpas_labels' => ['UiTPAS Mechelen'],
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'uitpas labels present on organizer',
            $expectedLogContext,
            0
        );
    }

    /**
     * @test
     */
    public function it_does_not_register_an_uitpas_event_when_price_info_has_been_entered_and_a_regular_organizer_is_selected()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(new OrganizerUpdated($this->eventId, $this->regularOrganizerId))
            ->then([]);

        $expectedLogContext = [
            'organizer' => $this->regularOrganizerId,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
            'extracted_organizer_labels' => ['foo'],
            'organizer_uitpas_labels' => [],
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'no uitpas labels present on organizer',
            $expectedLogContext,
            0
        );
    }

    /**
     * @test
     */
    public function it_does_not_register_an_uitpas_event_when_a_regular_organizer_has_been_selected_and_price_info_is_entered()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->regularOrganizerId),
                ]
            )
            ->when(new PriceInfoUpdated($this->eventId, $this->priceInfo))
            ->then([]);

        $expectedLogContext = [
            'organizer' => $this->regularOrganizerId,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
            'extracted_organizer_labels' => ['foo'],
            'organizer_uitpas_labels' => [],
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'no uitpas labels present on organizer',
            $expectedLogContext,
            0
        );
    }

    /**
     * @test
     */
    public function it_updates_an_uitpas_event_when_it_has_a_new_uitpas_organizer()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(new OrganizerUpdated($this->eventId, $this->updatedUitpasOrganizerId))
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->updatedUitpasOrganizerId,
                        $this->priceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_updates_an_uitpas_event_when_it_has_new_price_info()
    {
        $updatedPriceInfo = $this->priceInfo
            ->withExtraTariff(
                new Tariff(
                    new StringLiteral('Extra tariff'),
                    Price::fromFloat(1.5),
                    Currency::fromNative('EUR')
                )
            );

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(new PriceInfoUpdated($this->eventId, $updatedPriceInfo))
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $updatedPriceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_never_updates_an_uitpas_event_when_it_no_longer_has_an_uitpas_organizer()
    {
        $updatedPriceInfo = $this->priceInfo
            ->withExtraTariff(
                new Tariff(
                    new StringLiteral('Extra tariff'),
                    Price::fromFloat(1.5),
                    Currency::fromNative('EUR')
                )
            );

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(new OrganizerUpdated($this->eventId, $this->regularOrganizerId))
            ->then([])
            ->when(new PriceInfoUpdated($this->eventId, $updatedPriceInfo))
            ->then([]);
    }

    /**
     * @test
     */
    public function it_never_updates_an_uitpas_event_when_it_no_longer_has_an_any_organizer()
    {
        $updatedPriceInfo = $this->priceInfo
            ->withExtraTariff(
                new Tariff(
                    new StringLiteral('Extra tariff'),
                    Price::fromFloat(1.5),
                    Currency::fromNative('EUR')
                )
            );

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                    $this->uitpasAggregateCreated,
                    new OrganizerDeleted($this->eventId, $this->uitpasOrganizerId),
                ]
            )
            ->when(
                new PriceInfoUpdated($this->eventId, $updatedPriceInfo)
            )
            ->then([]);
    }

    /**
     * @test
     */
    public function it_updates_the_uitpas_event_with_distribution_keys_when_the_uitpas_aggregate_is_created()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(
                $this->uitpasAggregateCreated
            )
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo,
                        $this->distributionKeys
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_updates_the_uitpas_event_when_the_distribution_keys_on_the_uitpas_aggregate_have_been_updated()
    {
        $updatedDistributionKeys = $this->distributionKeys;
        $updatedDistributionKeys[] = 'distribution-key-789';

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                    $this->uitpasAggregateCreated,
                ]
            )
            ->when(
                new DistributionKeysUpdated($this->eventId, $updatedDistributionKeys)
            )
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo,
                        $updatedDistributionKeys
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_updates_the_uitpas_event_when_the_distribution_keys_on_the_uitpas_aggregate_have_been_cleared()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                    $this->uitpasAggregateCreated,
                ]
            )
            ->when(
                new DistributionKeysCleared($this->eventId)
            )
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo,
                        []
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_clears_the_distribution_keys_if_the_organizer_is_changed()
    {
        // Clearing the distribution keys might trigger an extra sync depending
        // on the state of the aggregate, but this is not tracked by the test
        // scenario either way.
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                    $this->uitpasAggregateCreated,
                ]
            )
            ->when(
                new OrganizerUpdated($this->eventId, $this->updatedUitpasOrganizerId)
            )
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->updatedUitpasOrganizerId,
                        $this->priceInfo,
                        $this->distributionKeys
                    ),
                    new ClearDistributionKeys($this->eventId),
                ]
            );
    }

    /**
     * @test
     */
    public function it_creates_a_new_uitpas_aggregate_and_registers_an_uitpas_event_for_events_imported_from_udb2()
    {
        $this->mockGetEvent();

        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer-and-price.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $expectedPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(5.5),
                Currency::fromNative('EUR')
            )
        );

        $this->scenario
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $expectedPriceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_takes_into_account_existing_distribution_keys_on_uitpas_for_events_imported_from_udb2()
    {
        $distributionKey1 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey1->id = 'distribution-key-1';

        $distributionKey2 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey2->id = 'distribution-key-2';

        $this->mockGetEvent(
            [
                $distributionKey1,
                $distributionKey2,
            ]
        );

        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer-and-price.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $expectedPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(5.5),
                Currency::fromNative('EUR')
            )
        );

        $this->scenario
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new CreateUiTPASAggregate(
                        $this->eventId,
                        [
                            $distributionKey1->id,
                            $distributionKey2->id,
                        ]
                    ),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $expectedPriceInfo,
                        [
                            $distributionKey1->id,
                            $distributionKey2->id,
                        ]
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_creates_a_new_uitpas_aggregate_and_registers_an_uitpas_event_for_events_imported_from_udb2_with_price_description()
    {
        $this->mockGetEvent();

        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer-and-price-description.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $expectedPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(15.00),
                Currency::fromNative('EUR')
            )
        );
        $expectedPriceInfo = $expectedPriceInfo->withExtraTariff(
            new Tariff(
                new StringLiteral('Studenten'),
                Price::fromFloat(10.00),
                Currency::fromNative('EUR')
            )
        );
        $expectedPriceInfo = $expectedPriceInfo->withExtraTariff(
            new Tariff(
                new StringLiteral('Gepensioneerden'),
                Price::fromFloat(12.00),
                Currency::fromNative('EUR')
            )
        );

        $this->scenario
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $expectedPriceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_creates_a_new_uitpas_aggregate_and_registers_an_uitpas_event_for_events_imported_from_udb2_with_other_price_and_wrong_price_description()
    {
        $this->mockGetEvent();

        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer-and-other-price-and-wrong-price-description.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $expectedPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(10.00),
                Currency::fromNative('EUR')
            )
        );

        $this->scenario
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $expectedPriceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_updates_an_uitpas_event_when_updated_from_udb2()
    {
        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer-and-price.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $expectedPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(5.5),
                Currency::fromNative('EUR')
            )
        );

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                    $this->uitpasAggregateCreated,
                ]
            )
            ->when(new EventUpdatedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new UpdateUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $expectedPriceInfo,
                        $this->distributionKeys
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_falls_back_to_price_details_in_other_languages_when_handling_cdbxml_changes()
    {
        $this->mockGetEvent();

        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer-and-price-fr.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $expectedPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(5.5),
                Currency::fromNative('EUR')
            )
        );

        $this->scenario
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $expectedPriceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_does_not_require_price_details_when_handling_cdbxml_changes()
    {
        $this->mockGetEvent();

        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-with-uitpas-organizer.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $this->scenario
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then([])
            ->when(new PriceInfoUpdated($this->eventId, $this->priceInfo))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_ignores_cdbxml_and_udb2_events_with_invalid_cdbxml()
    {
        $cdbXml = file_get_contents(__DIR__ . '/cdbxml-samples/event-without-calendar.xml');

        $cdbXmlNamespaceUri = 'http://www.cultuurdatabank.com/XMLSchema/CdbXSD/3.3/FINAL';

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $this->uitpasOrganizerId),
                ]
            )
            ->when(new EventUpdatedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then([]);
    }

    /**
     * @test
     * @group issue-III-1804
     */
    public function it_registers_multiple_uitpas_events_when_an_uitpas_label_is_added_to_their_related_organizer()
    {
        $organizerId = '750aaaab-e25b-4654-85f5-b5386279d48b';

        $event1Id = '707cdc16-746f-4bde-8440-6d0134d96e95';
        $event2Id = '03297490-6cd2-49a5-84b9-207e65616b8c';

        $eventCreated1 = $this->generateEventCreatedEvent($event1Id);
        $eventCreated2 = $this->generateEventCreatedEvent($event2Id);

        $this->scenario
            ->given(
                [
                    $eventCreated1,
                    new OrganizerUpdated($event1Id, $organizerId),
                    new PriceInfoUpdated($event1Id, $this->priceInfo),
                    $eventCreated2,
                    new OrganizerUpdated($event2Id, $organizerId),
                    new PriceInfoUpdated($event2Id, $this->priceInfo),
                ]
            )
            ->when(
                new LabelAdded($organizerId, new Label('UiTPAS Mechelen'))
            )
            ->then(
                [
                    new CreateUiTPASAggregate($event1Id, []),
                    new RegisterUiTPASEvent(
                        $event1Id,
                        $organizerId,
                        $this->priceInfo,
                        []
                    ),
                    new CreateUiTPASAggregate($event2Id, []),
                    new RegisterUiTPASEvent(
                        $event2Id,
                        $organizerId,
                        $this->priceInfo,
                        []
                    ),
                ]
            );

        $expectedLogContext1 = [
            'organizer' => $organizerId,
            'label' => 'UiTPAS Mechelen',
            'event' => $event1Id,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
        ];

        $expectedLogContext2 = [
            'organizer' => $organizerId,
            'label' => 'UiTPAS Mechelen',
            'event' => $event2Id,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'uitpas label was added to organizer',
            $expectedLogContext1,
            2
        );

        $this->assertLogged(
            Logger::DEBUG,
            'uitpas label was added to organizer',
            $expectedLogContext2,
            3
        );
    }

    /**
     * @test
     * @group issue-III-1804
     */
    public function it_does_not_register_an_uitpas_event_when_a_label_not_relevant_for_uitpas_is_added_to_the_organizer()
    {
        $organizerId = '750aaaab-e25b-4654-85f5-b5386279d48b';

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated($this->eventId, $organizerId),
                    new PriceInfoUpdated($this->eventId, $this->priceInfo),
                ]
            )
            ->when(
                new LabelAdded($organizerId, new Label('bar'))
            )
            ->then([]);

        $expectedLogContext = [
            'organizer' => $organizerId,
            'label' => 'bar',
            'event' => $this->eventId,
            'uitpas_labels' => ['UiTPAS Gent', 'UiTPAS Mechelen'],
        ];

        $this->assertLogged(
            Logger::DEBUG,
            'label was added to organizer, but it is not an uitpas label',
            $expectedLogContext,
            1
        );
    }

    /**
     * @test
     * @group issue-III-1807
     */
    public function it_finishes_the_saga_state_when_an_event_is_concluded()
    {
        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                ]
            )
            ->when(
                new Concluded($this->eventId)
            )
            ->then([]);

        $states = $this->sagaStateRepository->findBy(
            new Criteria(['uitpasAggregateId' => $this->eventId]),
            self::SAGA_TYPE
        );

        $statesArray = iterator_to_array($states);
        $this->assertEmpty($statesArray);
    }

    /**
     * @test
     */
    public function it_creates_and_registers_an_uitpas_event_on_copy_event()
    {
        $eventCopied = new EventCopied(
            '9211e93b-bba5-4828-9936-f8fc47e29102',
            $this->eventId,
            new Calendar(CalendarType::PERMANENT())
        );

        $this->scenario
            ->given(
                [
                    $this->eventCreated,
                    new OrganizerUpdated(
                        $this->eventId,
                        $this->uitpasOrganizerId
                    ),
                    new PriceInfoUpdated(
                        $this->eventId,
                        $this->priceInfo
                    ),
                    new DistributionKeysUpdated(
                        $this->eventId,
                        $this->distributionKeys
                    ),
                    new CreateUiTPASAggregate(
                        $this->eventId,
                        $this->distributionKeys
                    ),
                    new RegisterUiTPASEvent(
                        $this->eventId,
                        $this->uitpasOrganizerId,
                        $this->priceInfo,
                        $this->distributionKeys
                    ),
                ]
            )
            ->when(
                $eventCopied
            )
            ->then(
                [
                    new CreateUiTPASAggregate(
                        $eventCopied->getItemId(),
                        $this->distributionKeys
                    ),
                    new RegisterUiTPASEvent(
                        $eventCopied->getItemId(),
                        $this->uitpasOrganizerId,
                        $this->priceInfo,
                        $this->distributionKeys
                    ),
                ]
            );
    }

    /**
     * @param string $eventId
     * @return EventCreated
     */
    private function generateEventCreatedEvent($eventId)
    {
        return new EventCreated(
            $eventId,
            new Title('title'),
            new EventType('id', 'label'),
            new Location(
                '335be568-aaf0-4147-80b6-9267daafe23b',
                new StringLiteral('Repeteerkot'),
                new Address(
                    new Street('Kerkstraat 69'),
                    new PostalCode('9630'),
                    new Locality('Zottegem'),
                    Country::fromNative('BE')
                )
            ),
            new Calendar(CalendarType::PERMANENT())
        );
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @param int $index
     */
    private function assertLogged($level, $message, array $context, $index)
    {
        $logs = $this->logHandler->getRecords();

        $this->assertArrayHasKey($index, $logs);

        $this->assertLog($level, $message, $context, $logs[$index]);
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @param array $log
     */
    private function assertLog($level, $message, array $context, array $log)
    {
        $this->assertEquals($level, $log['level']);
        $this->assertEquals($message, $log['message']);
        $this->assertEquals($context, $log['context']);
    }

    /**
     * @param $distributionKeys \CultureFeed_Uitpas_DistributionKey[]
     */
    private function mockGetEvent(array $distributionKeys = [])
    {
        $uitpasEvent = new \CultureFeed_Uitpas_Event_CultureEvent();
        $uitpasEvent->cdbid = $this->eventId;
        $uitpasEvent->distributionKey = $distributionKeys;

        $this->cultureFeedUitpas->expects($this->once())
            ->method('getEvent')
            ->with($this->eventId)
            ->willReturn($uitpasEvent);
    }
}
