<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Broadway\CommandHandling\CommandBusInterface;
use Crell\ApiProblem\ApiProblem;
use CultuurNet\UDB3\HttpFoundation\Response\ApiProblemJsonResponse;
use CultuurNet\UDB3\UiTPASService\Permissions\EventPermissionInterface;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\UpdateDistributionKeys;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * @var EventPermissionInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventPermission;

    protected function setUp()
    {
        $this->commandBus = $this->getMock(CommandBusInterface::class);

        $this->cultureFeedUitpas = $this->getMock(\CultureFeed_Uitpas::class);

        $this->eventPermission = $this->getMock(EventPermissionInterface::class);

        $this->eventController = new EventController(
            $this->commandBus,
            $this->cultureFeedUitpas,
            $this->eventPermission
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

    /**
     * @test
     */
    public function it_dispatches_update_command_and_returns_command_id()
    {
        $eventId = new UUID();
        $commandId = "b4b23dec5049d1dd7a5a66f5948dcf8c";
        $distributionKeys = ["distribution-key-1", "distribution-key-2"];
        $request = Request::create(
            '',
            'GET',
            [],
            [],
            [],
            [],
            json_encode($distributionKeys)
        );

        $this->eventPermission->expects($this->once())
            ->method('hasPermission')
            ->with($eventId)
            ->willReturn(true);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(new UpdateDistributionKeys($eventId, $distributionKeys))
            ->willReturn($commandId);

        $expectedResponse = new JsonResponse(['commandId' => $commandId]);

        $response = $this->eventController->update($request, $eventId);

        $this->assertEquals(
            $expectedResponse->getContent(),
            $response->getContent()
        );
    }

    /**
     * @test
     */
    public function it_returns_403_forbidden_when_no_permission_for_update()
    {
        $eventId = new UUID();
        $distributionKeys = ["distribution-key-1", "distribution-key-2"];
        $request = Request::create(
            '',
            'GET',
            [],
            [],
            [],
            [],
            json_encode($distributionKeys)
        );

        $this->eventPermission->expects($this->once())
            ->method('hasPermission')
            ->with($eventId)
            ->willReturn(false);

        $problem = new ApiProblem('No permission on event: ' . $eventId);
        $problem->setStatus(ApiProblemJsonResponse::HTTP_FORBIDDEN);
        $expectedResponse = new ApiProblemJsonResponse($problem);

        $response = $this->eventController->update($request, $eventId);

        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     */
    public function it_returns_400_bad_request_when_no_distribution_keys_provided_for_update()
    {
        $eventId = new UUID();
        $request = Request::create('');

        $this->eventPermission->expects($this->once())
            ->method('hasPermission')
            ->with($eventId)
            ->willReturn(true);

        $problem = new ApiProblem('Array of distribution keys required.');
        $problem->setStatus(ApiProblemJsonResponse::HTTP_BAD_REQUEST);
        $expectedResponse = new ApiProblemJsonResponse($problem);

        $response = $this->eventController->update($request, $eventId);

        $this->assertEquals($expectedResponse, $response);
    }

    /**
     * @test
     */
    public function it_dispatches_clear_command_and_returns_command_id()
    {
        $eventId = new UUID();
        $commandId = "b4b23dec5049d1dd7a5a66f5948dcf8c";

        $this->eventPermission->expects($this->once())
            ->method('hasPermission')
            ->with($eventId)
            ->willReturn(true);

        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with(new ClearDistributionKeys($eventId))
            ->willReturn($commandId);

        $expectedResponse = new JsonResponse(['commandId' => $commandId]);

        $response = $this->eventController->clear($eventId);

        $this->assertEquals(
            $expectedResponse->getContent(),
            $response->getContent()
        );
    }

    /**
     * @test
     */
    public function it_returns_403_when_no_permission_for_clear()
    {
        $eventId = new UUID();

        $this->eventPermission->expects($this->once())
            ->method('hasPermission')
            ->with($eventId)
            ->willReturn(false);

        $problem = new ApiProblem('No permission on event: ' . $eventId);
        $problem->setStatus(ApiProblemJsonResponse::HTTP_FORBIDDEN);
        $expectedResponse = new ApiProblemJsonResponse($problem);

        $response = $this->eventController->clear($eventId);

        $this->assertEquals($expectedResponse, $response);
    }
}
