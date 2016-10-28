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
use CultuurNet\UDB3\UiTPASService\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Command\UpdateUiTPASEvent;
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
        $eventCallback = function (AbstractEvent $event) {
            return new Criteria(
                ['eventId' => $event->getItemId()]
            );
        };

        return [
            'EventCreated' => function (EventCreated $eventCreated) {
                return null;
            },
            'OrganizerUpdated' => $eventCallback,
            'PriceInfoUpdated' => $eventCallback,
        ];
    }

    /**
     * @param EventCreated $eventCreated
     * @param State $state
     * @return State
     */
    public function handleEventCreated(EventCreated $eventCreated, State $state)
    {
        $state->set('eventId', $eventCreated->getEventId());
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
     * @param State $state
     * @return State
     */
    private function triggerSyncWhenConditionsAreMet(State $state)
    {
        $eventId = $state->get('eventId');
        $organizerId = $state->get('organizerId');
        $uitpasOrganizer = $state->get('uitpasOrganizer');
        $serializedPriceInfo = $state->get('priceInfo');
        $syncCount = (int) $state->get('syncCount');

        if (is_null($organizerId) || empty($uitpasOrganizer) || is_null($serializedPriceInfo)) {
            return $state;
        }

        $priceInfo = PriceInfo::deserialize($serializedPriceInfo);

        if ($syncCount == 0) {
            $command = new RegisterUiTPASEvent(
                $eventId,
                $organizerId,
                $priceInfo
            );
        } else {
            $command = new UpdateUiTPASEvent(
                $eventId,
                $organizerId,
                $priceInfo
            );
        }

        $this->commandBus->dispatch($command);

        $state->set('syncCount', $syncCount + 1);

        return $state;
    }
}
