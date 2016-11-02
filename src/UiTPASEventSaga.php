<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;
use Broadway\Saga\Saga;
use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use CultuurNet\UDB3\Cdb\CdbId\EventCdbIdExtractorInterface;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventCreatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromCdbXml;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\PriceInfoUpdated;
use CultuurNet\UDB3\Offer\Events\AbstractEvent;
use CultuurNet\UDB3\PriceInfo\BasePrice;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\PriceInfo\PriceInfo;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\Sync\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\UpdateUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\AbstractUiTPASAggregateEvent;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\UiTPASAggregateCreated;
use CultuurNet\UDB3\UiTPASService\Specification\OrganizerSpecificationInterface;
use ValueObjects\Money\Currency;

class UiTPASEventSaga extends Saga implements StaticallyConfiguredSagaInterface
{
    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    /**
     * @var OrganizerSpecificationInterface
     */
    private $organizerSpecification;

    /**
     * @var EventCdbIdExtractorInterface
     */
    private $eventCdbIdExtractor;

    /**
     * @param CommandBusInterface $commandBus
     * @param OrganizerSpecificationInterface $organizerSpecification
     * @param EventCdbIdExtractorInterface $eventCdbIdExtractor
     */
    public function __construct(
        CommandBusInterface $commandBus,
        OrganizerSpecificationInterface $organizerSpecification,
        EventCdbIdExtractorInterface $eventCdbIdExtractor
    ) {
        $this->commandBus = $commandBus;
        $this->organizerSpecification = $organizerSpecification;
        $this->eventCdbIdExtractor = $eventCdbIdExtractor;
    }

    /**
     * @return array
     */
    public static function configuration()
    {
        $initialEventCallback = function () {
            return null;
        };

        $offerEventCallback = function (AbstractEvent $event) {
            return new Criteria(
                ['uitpasAggregateId' => $event->getItemId()]
            );
        };

        $uitpasAggregateEventCallback = function (AbstractUiTPASAggregateEvent $event) {
            return new Criteria(
                ['uitpasAggregateId' => $event->getAggregateId()]
            );
        };

        $cdbXmlEventCallback = function ($event) {
            /* @var EventUpdatedFromUDB2|EventUpdatedFromCdbXml $event */
            return new Criteria(
                ['uitpasAggregateId' => (string) $event->getEventId()]
            );
        };

        return [
            'EventCreated' => $initialEventCallback,
            'EventImportedFromUDB2' => $initialEventCallback,
            'EventCreatedFromCdbXml' => $initialEventCallback,
            'EventUpdatedFromUDB2' => $cdbXmlEventCallback,
            'EventUpdatedFromCdbXml' => $cdbXmlEventCallback,
            'OrganizerUpdated' => $offerEventCallback,
            'PriceInfoUpdated' => $offerEventCallback,
            'UiTPASAggregateCreated' => $uitpasAggregateEventCallback,
            'DistributionKeysUpdated' => $uitpasAggregateEventCallback,
            'DistributionKeysCleared' => $uitpasAggregateEventCallback,
        ];
    }

    /**
     * @param EventCreated $eventCreated
     * @param State $state
     * @return State
     */
    public function handleEventCreated(EventCreated $eventCreated, State $state)
    {
        $state->set('uitpasAggregateId', $eventCreated->getEventId());
        $state->set('syncCount', 0);
        return $state;
    }

    /**
     * @param EventImportedFromUDB2 $eventImportedFromUDB2
     * @param State $state
     * @return State
     */
    public function handleEventImportedFromUDB2(EventImportedFromUDB2 $eventImportedFromUDB2, State $state)
    {
        $state->set('uitpasAggregateId', $eventImportedFromUDB2->getEventId());
        $state->set('syncCount', 0);

        $state = $this->updateStateFromCdbXml(
            $state,
            $eventImportedFromUDB2->getCdbXml(),
            $eventImportedFromUDB2->getCdbXmlNamespaceUri()
        );

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param EventCreatedFromCdbXml $eventCreatedFromCdbXml
     * @param State $state
     * @return State
     */
    public function handleEventCreatedFromCdbXml(EventCreatedFromCdbXml $eventCreatedFromCdbXml, State $state)
    {
        $state->set('uitpasAggregateId', $eventCreatedFromCdbXml->getEventId());
        $state->set('syncCount', 0);

        $state = $this->updateStateFromCdbXml(
            $state,
            (string) $eventCreatedFromCdbXml->getEventXmlString(),
            (string) $eventCreatedFromCdbXml->getCdbXmlNamespaceUri()
        );

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param EventUpdatedFromUDB2 $eventUpdatedFromUDB2
     * @param State $state
     * @return State
     */
    public function handleEventUpdatedFromUDB2(EventUpdatedFromUDB2 $eventUpdatedFromUDB2, State $state)
    {
        $state = $this->updateStateFromCdbXml(
            $state,
            $eventUpdatedFromUDB2->getCdbXml(),
            $eventUpdatedFromUDB2->getCdbXmlNamespaceUri()
        );

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param EventUpdatedFromCdbXml $eventUpdatedFromCdbXml
     * @param State $state
     * @return State
     */
    public function handleEventUpdatedFromCdbXml(EventUpdatedFromCdbXml $eventUpdatedFromCdbXml, State $state)
    {
        $state = $this->updateStateFromCdbXml(
            $state,
            (string) $eventUpdatedFromCdbXml->getEventXmlString(),
            (string) $eventUpdatedFromCdbXml->getCdbXmlNamespaceUri()
        );

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param OrganizerUpdated $organizerUpdated
     * @param State $state
     * @return State
     */
    public function handleOrganizerUpdated(OrganizerUpdated $organizerUpdated, State $state)
    {
        $state->set('organizerId', $organizerUpdated->getOrganizerId());

        $state->set(
            'uitpasOrganizer',
            $this->organizerSpecification->isSatisfiedBy($organizerUpdated->getOrganizerId())
        );

        $state = $this->triggerSyncWhenConditionsAreMet($state);

        if ($this->uitpasAggregateHasBeenCreated($state)) {
            // When the organizer is changed the selected distribution keys
            // become invalid so we should dispatch a command to correct this.
            // This command will trigger an extra sync afterwards IF any
            // changes occurred. (It's possible there were no distribution keys
            // selected to begin with so the aggregate will decide whether an
            // extra event is recorded or not following this command.)
            $this->commandBus->dispatch(
                new ClearDistributionKeys($organizerUpdated->getItemId())
            );
        }

        return $state;
    }

    /**
     * @param PriceInfoUpdated $priceInfoUpdated
     * @param State $state
     * @return State
     */
    public function handlePriceInfoUpdated(PriceInfoUpdated $priceInfoUpdated, State $state)
    {
        $state->set('priceInfo', $priceInfoUpdated->getPriceInfo()->serialize());
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param UiTPASAggregateCreated $aggregateCreated
     * @param State $state
     * @return State
     */
    public function handleUiTPASAggregateCreated(UiTPASAggregateCreated $aggregateCreated, State $state)
    {
        $state->set('distributionKeyIds', $aggregateCreated->getDistributionKeyIds());
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param DistributionKeysUpdated $distributionKeysUpdated
     * @param State $state
     * @return State
     */
    public function handleDistributionKeysUpdated(DistributionKeysUpdated $distributionKeysUpdated, State $state)
    {
        $state->set('distributionKeyIds', $distributionKeysUpdated->getDistributionKeyIds());
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param DistributionKeysCleared $distributionKeysCleared
     * @param State $state
     * @return State
     */
    public function handleDistributionKeysCleared(DistributionKeysCleared $distributionKeysCleared, State $state)
    {
        $state->set('distributionKeyIds', []);
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param State $state
     * @param string $cdbxml
     * @param string $cdbxmlNamespaceUri
     * @return State
     */
    private function updateStateFromCdbXml(State $state, $cdbxml, $cdbxmlNamespaceUri)
    {
        try {
            $event = EventItemFactory::createEventFromCdbXml($cdbxmlNamespaceUri, $cdbxml);
        } catch (\CultureFeed_Cdb_ParseException $e) {
            return $state;
        }

        $organizerCdbId = $this->eventCdbIdExtractor->getRelatedOrganizerCdbId($event);

        if (!is_null($organizerCdbId)) {
            $uitpasOrganizer = $this->organizerSpecification->isSatisfiedBy($organizerCdbId);

            $state->set('organizerId', $organizerCdbId);
            $state->set('uitpasOrganizer', $uitpasOrganizer);
        }

        $eventDetailsList = $event->getDetails();

        $details = $eventDetailsList->getDetailByLanguage('nl');
        if (empty($details) && !empty($eventDetailsList)) {
            $details = $eventDetailsList[0];
        }

        if (!empty($details)) {
            $price = $details->getPrice()->getValue();

            $priceInfo = new PriceInfo(
                new BasePrice(
                    Price::fromFloat((float) $price),
                    Currency::fromNative('EUR')
                )
            );

            $state->set('priceInfo', $priceInfo->serialize());
        }

        return $state;
    }

    /**
     * @param State $state
     * @return State
     */
    private function triggerSyncWhenConditionsAreMet(State $state)
    {
        $aggregateId = $state->get('uitpasAggregateId');
        $organizerId = $state->get('organizerId');
        $uitpasOrganizer = $state->get('uitpasOrganizer');
        $serializedPriceInfo = $state->get('priceInfo');
        $distributionKeyIds = $state->get('distributionKeyIds');
        $syncCount = (int) $state->get('syncCount');

        if (is_null($organizerId) || empty($uitpasOrganizer) || is_null($serializedPriceInfo)) {
            return $state;
        }

        $priceInfo = PriceInfo::deserialize($serializedPriceInfo);
        $distributionKeyIds = !is_null($distributionKeyIds) ? $distributionKeyIds : [];

        if ($syncCount == 0) {
            $this->commandBus->dispatch(
                new CreateUiTPASAggregate($aggregateId, $distributionKeyIds)
            );

            $syncCommand = new RegisterUiTPASEvent(
                $aggregateId,
                $organizerId,
                $priceInfo,
                $distributionKeyIds
            );
        } else {
            $syncCommand = new UpdateUiTPASEvent(
                $aggregateId,
                $organizerId,
                $priceInfo,
                $distributionKeyIds
            );
        }

        $this->commandBus->dispatch($syncCommand);

        $state->set('syncCount', $syncCount + 1);

        return $state;
    }

    /**
     * @param State $state
     * @return bool
     */
    private function uitpasAggregateHasBeenCreated(State $state)
    {
        return !is_null($state->get('distributionKeyIds'));
    }
}
