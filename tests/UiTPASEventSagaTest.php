<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\CommandHandling\Testing\TraceableCommandBus;
use Broadway\EventDispatcher\EventDispatcher;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaMetadataFactory;
use Broadway\Saga\MultipleSagaManager;
use Broadway\Saga\State\InMemoryRepository;
use Broadway\Saga\State\StateManager;
use Broadway\Saga\Testing\Scenario;
use Broadway\UuidGenerator\Rfc4122\Version4Generator;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Address\Locality;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Address\Street;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\CalendarType;
use CultuurNet\UDB3\Cdb\CdbId\EventCdbIdExtractor;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventCreatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Event\Events\OrganizerDeleted;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\PriceInfoUpdated;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\EventXmlString;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\PriceInfo\BasePrice;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\PriceInfo\PriceInfo;
use CultuurNet\UDB3\PriceInfo\Tariff;
use CultuurNet\UDB3\Title;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\Sync\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\UpdateUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\UiTPASAggregateCreated;
use CultuurNet\UDB3\UiTPASService\Specification\OrganizerSpecificationInterface;
use ValueObjects\Geography\Country;
use ValueObjects\Money\Currency;
use ValueObjects\String\String as StringLiteral;

/**
 * @todo Extend SagaScenarioTestCase when we update to Broadway >= 0.9.x
 */
class UiTPASEventSagaTest extends \PHPUnit_Framework_TestCase
{
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
     * @var OrganizerSpecificationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $organizerSpecification;

    /**
     * @var EventCdbIdExtractor
     */
    private $eventCdbIdExtractor;

    public function setUp()
    {
        $this->eventId = 'e1122fff-0f67-4042-82c3-6b5ca7af02d7';

        $this->eventCreated = new EventCreated(
            $this->eventId,
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

        $this->organizerSpecification = $this->getMock(OrganizerSpecificationInterface::class);

        $this->organizerSpecification->expects($this->any())
            ->method('isSatisfiedBy')
            ->willReturnCallback(
                function ($organizerId) {
                    $uitpasOrganizerIds = [$this->uitpasOrganizerId, $this->updatedUitpasOrganizerId];
                    return in_array($organizerId, $uitpasOrganizerIds);
                }
            );

        $this->eventCdbIdExtractor = new EventCdbIdExtractor();

        $this->scenario = $this->createScenario();
    }

    /**
     * @return Scenario
     */
    protected function createScenario()
    {
        $traceableCommandBus = new TraceableCommandBus();
        $saga                = $this->createSaga($traceableCommandBus);
        $sagaStateRepository = new InMemoryRepository();
        $sagaManager         = new MultipleSagaManager(
            $sagaStateRepository,
            [$saga],
            new StateManager($sagaStateRepository, new Version4Generator()),
            new StaticallyConfiguredSagaMetadataFactory(),
            new EventDispatcher()
        );
        return new Scenario($this, $sagaManager, $traceableCommandBus);
    }

    /**
     * @param CommandBusInterface $commandBus
     * @return UiTPASEventSaga
     */
    protected function createSaga(CommandBusInterface $commandBus)
    {
        return new UiTPASEventSaga(
            $commandBus,
            $this->organizerSpecification,
            $this->eventCdbIdExtractor
        );
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
                        new StringLiteral($this->eventId),
                        new StringLiteral($this->uitpasOrganizerId),
                        $this->priceInfo
                    ),
                ]
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
                        new StringLiteral($this->eventId),
                        new StringLiteral($this->uitpasOrganizerId),
                        $this->priceInfo
                    ),
                ]
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
    public function it_creates_a_new_uitpas_aggregate_and_registers_an_uitpas_event_for_imported_events_from_udb2_if_they_have_an_uitpas_organizer_and_a_price()
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
            ->when(new EventImportedFromUDB2($this->eventId, $cdbXml, $cdbXmlNamespaceUri))
            ->then(
                [
                    new CreateUiTPASAggregate($this->eventId, []),
                    new RegisterUiTPASEvent(
                        new StringLiteral($this->eventId),
                        new StringLiteral($this->uitpasOrganizerId),
                        $expectedPriceInfo
                    ),
                ]
            );
    }

    /**
     * @test
     */
    public function it_creates_a_new_uitpas_aggregate_and_registers_an_uitpas_event_for_events_created_from_cdbxml()
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
            ->when(
                new EventCreatedFromCdbXml(
                    new StringLiteral($this->eventId),
                    new EventXmlString($cdbXml),
                    new StringLiteral($cdbXmlNamespaceUri)
                )
            )
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
    public function it_updates_an_uitpas_event_when_updated_from_cdbxml()
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
            ->when(
                new EventUpdatedFromCdbXml(
                    new StringLiteral($this->eventId),
                    new EventXmlString($cdbXml),
                    new StringLiteral($cdbXmlNamespaceUri)
                )
            )
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
}
