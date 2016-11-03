<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\UpdateDistributionKeys;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DistributionKeysController
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
     * DistributionKeysController constructor.
     * @param CommandBusInterface $commandBus
     * @param \CultureFeed_Uitpas $cultureFeedUitpas
     */
    public function __construct(
        CommandBusInterface $commandBus,
        \CultureFeed_Uitpas $cultureFeedUitpas
    ) {
        $this->commandBus = $commandBus;
        $this->cultureFeedUitpas = $cultureFeedUitpas;
    }

    /**
     * @param string $eventId
     * @return JsonResponse
     */
    public function get($eventId)
    {
        $uitpasEvent = $this->cultureFeedUitpas->getEvent($eventId);
        $distributionKeys = $uitpasEvent->distributionKey;

        $distributionKeyIds = [];
        foreach ($distributionKeys as $distributionKey) {
            $distributionKeyIds[] = (string) $distributionKey->id;
        }

        return new JsonResponse(json_encode($distributionKeyIds));
    }

    /**
     * @param Request $request
     * @param string $eventId
     * @return JsonResponse
     */
    public function update(Request $request, $eventId)
    {
        // TODO: allowed to update the event => UDB3 permission endpoint?

        // TODO: array provided?
        $distributionKeyIds = json_decode($request->getContent());

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
        // TODO: allowed to update the event => UDB3 permission endpoint?

        $clearDistributionKeys = new ClearDistributionKeys($eventId);

        $commandId = $this->commandBus->dispatch($clearDistributionKeys);

        return new JsonResponse(['commandId' => $commandId]);
    }
}
