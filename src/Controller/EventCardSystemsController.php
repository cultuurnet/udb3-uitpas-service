<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use CultuurNet\UDB3\UiTPASService\Controller\Response\CardSystemsJsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EventCardSystemsController
{
    /**
     * @var \CultureFeed_Uitpas
     */
    private $uitpas;

    /**
     * @param \CultureFeed_Uitpas $uitpas
     */
    public function __construct(\CultureFeed_Uitpas $uitpas)
    {
        $this->uitpas = $uitpas;
    }

    /**
     * @param string $eventId
     * @return CardSystemsJsonResponse
     */
    public function get($eventId)
    {
        $cardSystems = $this->uitpas->getCardSystemsForEvent($eventId);
        return new CardSystemsJsonResponse($cardSystems->objects);
    }

    /**
     * @param string $eventId
     * @param string $cardSystemId
     * @param string|null $distributionKeyId
     * @return Response
     */
    public function add($eventId, $cardSystemId, $distributionKeyId = null)
    {
        $this->uitpas->addCardSystemToEvent($eventId, $cardSystemId, $distributionKeyId);
        return new Response('OK', 200);
    }

    /**
     * @param string $eventId
     * @param string $cardSystemId
     * @return Response
     */
    public function delete($eventId, $cardSystemId)
    {
        $this->uitpas->deleteCardSystemFromEvent($eventId, $cardSystemId);
        return new Response('OK', 200);
    }
}
