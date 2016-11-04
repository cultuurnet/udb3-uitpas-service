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

        $cardSystems = array_reduce(
            $resultSet->objects,
            function(
                array $cardSystemsCarry,
                \CultureFeed_Uitpas_DistributionKey $distributionKey) {
                    return $this->addNewCardSystem($cardSystemsCarry, $distributionKey);
                },
            []
        );

        return new JsonResponse(array_values($cardSystems));
    }

    /**
     * @param array $cardSystemsCarry
     * @param \CultureFeed_Uitpas_DistributionKey $distributionKey
     * @return array
     */
    private function addNewCardSystem(
        array $cardSystemsCarry,
        \CultureFeed_Uitpas_DistributionKey $distributionKey
    ) {
        $cardSystemId = $distributionKey->cardSystem->id;

        if (!isset($cardSystemsCarry[$cardSystemId])) {
            $cardSystem = [
                'id' => $cardSystemId,
                'name' => $distributionKey->cardSystem->name,
                'distributionKeys' => array_map(
                    function (\CultureFeed_Uitpas_DistributionKey $distributionKey) {
                        return [
                            'id' => $distributionKey->id,
                            'name' => $distributionKey->name,
                        ];
                    },
                    $distributionKey->cardSystem->distributionKeys
                ),
            ];

            $cardSystemsCarry[$cardSystemId] = $cardSystem;
        }

        return $cardSystemsCarry;
    }
}
