<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class EventController
{
    /**
     * @var \ICultureFeed
     */
    private $cultureFeedUitpas;

    /**
     * @param \CultureFeed_Uitpas $cultureFeedUitpas
     */
    public function __construct(
        \CultureFeed_Uitpas $cultureFeedUitpas
    ) {
        $this->cultureFeedUitpas = $cultureFeedUitpas;
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
}
