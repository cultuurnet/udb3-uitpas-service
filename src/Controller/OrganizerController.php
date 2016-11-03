<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrganizerController
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
     * @return Response
     */
    public function getDistributionKeys($organizerId)
    {
        // Method is called getDistributionKeysForOrganizer() but returns
        // card systems. (Historically it returned distribution keys.)
        $resultSet = $this->uitpas->getDistributionKeysForOrganizer($organizerId);

        $cardSystems = array_map(
            function (\CultureFeed_Uitpas_CardSystem $cardSystem) {
                return [
                    'id' => $cardSystem->id,
                    'name' => $cardSystem->name,
                    'distributionKeys' => array_map(
                        function (\CultureFeed_Uitpas_DistributionKey $distributionKey) {
                            return [
                                'id' => $distributionKey->id,
                                'name' => $distributionKey->name,
                            ];
                        },
                        $cardSystem->distributionKeys
                    ),
                ];
            },
            $resultSet->objects
        );

        return new JsonResponse($cardSystems);
    }
}
