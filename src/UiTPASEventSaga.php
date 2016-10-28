<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;
use Broadway\Saga\Saga;
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
        return [];
    }
}
