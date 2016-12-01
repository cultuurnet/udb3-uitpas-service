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

class EventController
{
    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    /**
     * @var \ICultureFeed
     */
    private $cultureFeedUitpas;

    /**
     * @var EventPermissionInterface
     */
    private $eventPermission;

    /**
     * @param CommandBusInterface $commandBus
     * @param \CultureFeed_Uitpas $cultureFeedUitpas
     * @param EventPermissionInterface $eventPermission
     */
    public function __construct(
        CommandBusInterface $commandBus,
        \CultureFeed_Uitpas $cultureFeedUitpas,
        EventPermissionInterface $eventPermission
    ) {
        $this->commandBus = $commandBus;
        $this->cultureFeedUitpas = $cultureFeedUitpas;
        $this->eventPermission = $eventPermission;
    }

    /**
     * @param string $eventId
     * @return JsonResponse
     */
    public function get($eventId)
    {
        $uitpasEvent = $this->cultureFeedUitpas->getEvent($eventId);

        $distributionKeyIds = array_map(
            function (\CultureFeed_Uitpas_DistributionKey $distributionKey) {
                return (string) $distributionKey->id;
            },
            $uitpasEvent->distributionKey
        );

        return new JsonResponse($distributionKeyIds);
    }

    /**
     * @param Request $request
     * @param string $eventId
     * @return JsonResponse
     */
    public function update(Request $request, $eventId)
    {
        if (!$this->eventPermission->hasPermission($eventId)) {
            return $this->createPermissionResponse($eventId);
        }

        $distributionKeyIds = json_decode($request->getContent());
        if (!is_array($distributionKeyIds) || count($distributionKeyIds) < 1) {
            $problem = new ApiProblem('Array of distribution keys required.');
            $problem->setStatus(ApiProblemJsonResponse::HTTP_BAD_REQUEST);

            return new ApiProblemJsonResponse($problem);
        }

        $updateDistributionKeys = new UpdateDistributionKeys(
            $eventId,
            $distributionKeyIds
        );

        $commandId = $this->commandBus->dispatch($updateDistributionKeys);

        return new JsonResponse(['commandId' => $commandId]);
    }

    /**
     * @param string $eventId
     * @return JsonResponse
     */
    public function clear($eventId)
    {
        if (!$this->eventPermission->hasPermission($eventId)) {
            return $this->createPermissionResponse($eventId);
        }

        $clearDistributionKeys = new ClearDistributionKeys($eventId);

        $commandId = $this->commandBus->dispatch($clearDistributionKeys);

        return new JsonResponse(['commandId' => $commandId]);
    }

    /**
     * @param $eventId
     * @return ApiProblemJsonResponse
     */
    private function createPermissionResponse($eventId)
    {
        $problem = new ApiProblem('No permission on event: ' . $eventId);
        $problem->setStatus(ApiProblemJsonResponse::HTTP_FORBIDDEN);

        return new ApiProblemJsonResponse($problem);
    }
}
