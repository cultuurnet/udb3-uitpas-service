<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use CultuurNet\UDB3\UiTPASService\Controller\Response\CardSystemsJsonResponse;

class OrganizerCardSystemsController
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
     * @param string $organizerId
     * @return CardSystemsJsonResponse
     */
    public function get($organizerId)
    {
        $cardSystems = $this->uitpas->getCardSystemsForOrganizer($organizerId);
        return new CardSystemsJsonResponse($cardSystems->objects);
    }
}
