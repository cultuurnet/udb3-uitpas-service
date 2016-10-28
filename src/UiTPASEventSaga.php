<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;
use Broadway\Saga\Saga;
use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\PriceInfoUpdated;
use CultuurNet\UDB3\Offer\Events\AbstractEvent;
use CultuurNet\UDB3\PriceInfo\PriceInfo;
use CultuurNet\UDB3\UiTPASService\Command\RemotelyRegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Command\RemotelyUpdateUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Event\AbstractUiTPASAggregateEvent;
use CultuurNet\UDB3\UiTPASService\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\Event\UiTPASAggregateCreated;
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
            $command = new RemotelyRegisterUiTPASEvent(
                $aggregateId,
                $organizerId,
                $priceInfo,
                $distributionKeyIds
            );
        } else {
            $command = new RemotelyUpdateUiTPASEvent(
                $aggregateId,
                $organizerId,
                $priceInfo,
                $distributionKeyIds
            );
        }

        $this->commandBus->dispatch($command);

        $state->set('syncCount', $syncCount + 1);

        return $state;
    }
}
