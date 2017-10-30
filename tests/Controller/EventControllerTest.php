<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\UiTPASService\Permissions\EventPermissionInterface;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\UpdateDistributionKeys;
use ValueObjects\Identity\UUID;

class EventControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CommandBusInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $commandBus;

    /**
     * @var \CultureFeed_Uitpas|\PHPUnit_Framework_MockObject_MockObject
     */
    private $cultureFeedUitpas;

    /**
     * @var EventController
     */
    private $eventController;

    protected function setUp()
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);

        $this->cultureFeedUitpas = $this->createMock(\CultureFeed_Uitpas::class);

        $this->eventController = new EventController(
            $this->commandBus,
            $this->cultureFeedUitpas
        );
    }

    /**
     * @test
     */
    public function it_can_get_distribution_key_ids_from_an_event()
    {
        $eventId = new UUID();

        $distributionKey1 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey1->id = 'distribution-key-1';

        $distributionKey2 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey2->id = 'distribution-key-2';

        $uitpasEvent = new \CultureFeed_Uitpas_Event_CultureEvent();
        $uitpasEvent->cdbid = $eventId;
        $uitpasEvent->distributionKey = [
            $distributionKey1,
            $distributionKey2,
        ];

        $this->cultureFeedUitpas->expects($this->once())
            ->method('getEvent')
            ->with($eventId)
            ->willReturn($uitpasEvent);

        $expectedResponseContent = json_encode(
            [$distributionKey1->id, $distributionKey2->id]
        );

        $response = $this->eventController->get($eventId);

        $this->assertEquals($expectedResponseContent, $response->getContent());
    }
}
