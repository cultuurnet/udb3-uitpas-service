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

        $this->triggerSyncWhenConditionsAreMet($state);

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

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param State $state
     */
    private function triggerSyncWhenConditionsAreMet(State $state)
    {
        $eventId = $state->get('eventId');
        $organizerId = $state->get('organizerId');
        $uitpasOrganizer = $state->get('uitpasOrganizer');
        $serializedPriceInfo = $state->get('priceInfo');

        if (is_null($organizerId) || empty($uitpasOrganizer) || is_null($serializedPriceInfo)) {
            return;
        }

        $priceInfo = PriceInfo::deserialize($serializedPriceInfo);

        $register = new RegisterUiTPASEvent(
            $eventId,
            $organizerId,
            $priceInfo
        );

        $this->commandBus->dispatch($register);
    }
}
