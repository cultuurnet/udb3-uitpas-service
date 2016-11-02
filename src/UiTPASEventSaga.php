<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;
use Broadway\Saga\Saga;
use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\PriceInfoUpdated;
use CultuurNet\UDB3\Offer\Events\AbstractEvent;
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
     * @param CommandBusInterface $commandBus
     * @param OrganizerSpecificationInterface $organizerSpecification
     */
    public function __construct(
        CommandBusInterface $commandBus,
        OrganizerSpecificationInterface $organizerSpecification
    ) {
        $this->commandBus = $commandBus;
        $this->organizerSpecification = $organizerSpecification;
    }

    /**
     * @return array
     */
    public static function configuration()
    {
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

        return [
            'EventCreated' => function (EventCreated $eventCreated) {
                return null;
            },
            'EventImportedFromUDB2' => function (EventImportedFromUDB2 $eventImported) {
                return null;
            },
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
