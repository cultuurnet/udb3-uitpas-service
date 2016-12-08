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
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Event\Events\OrganizerDeleted;
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

        $updateFromUdb2Callback = function (EventUpdatedFromUDB2 $event) {
            return new Criteria(
                ['uitpasAggregateId' => (string) $event->getEventId()]
            );
        };

        return [
            'EventCreated' => $initialEventCallback,
            'EventImportedFromUDB2' => $initialEventCallback,
            'EventUpdatedFromUDB2' => $updateFromUdb2Callback,
            'OrganizerUpdated' => $offerEventCallback,
            'OrganizerDeleted' => $offerEventCallback,
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
     * @param OrganizerUpdated $organizerUpdated
     * @param State $state
     * @return State
     */
    public function handleOrganizerUpdated(OrganizerUpdated $organizerUpdated, State $state)
    {
        $state = $this->updateOrganizerState($organizerUpdated->getOrganizerId(), $state);

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
     * @param OrganizerDeleted $organizerDeleted
     * @param State $state
     * @return State
     */
    public function handleOrganizerDeleted(OrganizerDeleted $organizerDeleted, State $state)
    {
        $state = $this->resetOrganizerState($state);
        return $state;
    }

    /**
     * @param PriceInfoUpdated $priceInfoUpdated
     * @param State $state
     * @return State
     */
    public function handlePriceInfoUpdated(PriceInfoUpdated $priceInfoUpdated, State $state)
    {
        $state = $this->updatePriceInfoState($priceInfoUpdated->getPriceInfo(), $state);
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
     * @param string $cdbXml
     * @param string $cdbXmlNamespaceUri
     * @return State
     */
    private function updateStateFromCdbXml(State $state, $cdbXml, $cdbXmlNamespaceUri)
    {
        try {
            $event = EventItemFactory::createEventFromCdbXml($cdbXmlNamespaceUri, $cdbXml);
        } catch (\CultureFeed_Cdb_ParseException $e) {
            return $state;
        }

        $organizerCdbId = $this->eventCdbIdExtractor->getRelatedOrganizerCdbId($event);

        if (!is_null($organizerCdbId)) {
            $state = $this->updateOrganizerState($organizerCdbId, $state);
        }

        $state = $this->updatePriceInfoStateFromCdbEvent($event, $state);

        return $state;
    }

    /**
     * @param \CultureFeed_Cdb_Item_Event $cdbEvent
     * @param State $state
     * @return State
     */
    private function updatePriceInfoStateFromCdbEvent(\CultureFeed_Cdb_Item_Event $cdbEvent, State $state)
    {
        $eventDetailsList = $cdbEvent->getDetails();

        $details = $eventDetailsList->getDetailByLanguage('nl');
        if (empty($details) && !empty($eventDetailsList)) {
            $eventDetailsList->rewind();
            $details = $eventDetailsList->current();
        }

        if (empty($details) || empty($details->getPrice()) || is_null($details->getPrice()->getValue())) {
            // Do nothing if no price info is found on the event.
            return $state;
        }

        $cdbPrice = $details->getPrice()->getValue();
        $cdbPriceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat((float) $cdbPrice),
                Currency::fromNative('EUR')
            )
        );

        $previousPriceInfo = $this->getPriceInfoFromState($state);

        if (is_null($previousPriceInfo) || $previousPriceInfo->getBasePrice()->getPrice()->toFloat() !== $cdbPrice) {
            // Only update the stored price info if the base price has been
            // changed or there was no price info before. CdbXml never contains
            // tariffs so we'll lose any previously defined tariffs, but this is
            // intended on a price change.
            $state = $this->updatePriceInfoState($cdbPriceInfo, $state);
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
        $priceInfo = $this->getPriceInfoFromState($state);
        $distributionKeyIds = $state->get('distributionKeyIds');
        $syncCount = (int) $state->get('syncCount');

        if (is_null($organizerId) || empty($uitpasOrganizer) || is_null($priceInfo)) {
            return $state;
        }

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
     * @param $organizerId
     * @param State $state
     * @return State
     */
    private function updateOrganizerState($organizerId, State $state)
    {
        $uitpasOrganizer = $this->organizerSpecification->isSatisfiedBy($organizerId);
        $state->set('organizerId', $organizerId);
        $state->set('uitpasOrganizer', $uitpasOrganizer);
        return $state;
    }

    /**
     * @param State $state
     * @return State
     */
    private function resetOrganizerState(State $state)
    {
        $state->set('organizerId', null);
        $state->set('uitpasOrganizer', null);
        return $state;
    }

    /**
     * @param PriceInfo $priceInfo
     * @param State $state
     * @return State
     */
    private function updatePriceInfoState(PriceInfo $priceInfo, State $state)
    {
        $state->set('priceInfo', $priceInfo->serialize());
        return $state;
    }

    /**
     * @param State $state
     * @return PriceInfo|null
     */
    private function getPriceInfoFromState(State $state)
    {
        $serializedPriceInfo = $state->get('priceInfo');

        if (!is_null($serializedPriceInfo)) {
            return PriceInfo::deserialize($serializedPriceInfo);
        } else {
            return null;
        }
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
