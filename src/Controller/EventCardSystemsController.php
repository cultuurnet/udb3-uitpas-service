<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use CultureFeed_Uitpas;
use CultuurNet\UDB3\UiTPASService\Controller\Response\CardSystemsJsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EventCardSystemsController
{
    /**
     * @var CultureFeed_Uitpas
     */
    private $uitpas;

    public function __construct(CultureFeed_Uitpas $uitpas)
    {
        $this->uitpas = $uitpas;
    }

    public function get(string $eventId): CardSystemsJsonResponse
    {
        $cardSystems = $this->uitpas->getCardSystemsForEvent($eventId);
        return new CardSystemsJsonResponse($cardSystems->objects);
    }

    public function add(string $eventId, string $cardSystemId, string $distributionKeyId = null): Response
    {
        $this->uitpas->addCardSystemToEvent($eventId, $cardSystemId, $distributionKeyId);
        return new Response('OK', 200);
    }

    public function delete(string $eventId, string $cardSystemId): Response
    {
        $this->uitpas->deleteCardSystemFromEvent($eventId, $cardSystemId);
        return new Response('OK', 200);
    }
}
