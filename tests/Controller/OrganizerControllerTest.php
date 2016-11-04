<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

class OrganizerControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \CultureFeed_Uitpas|\PHPUnit_Framework_MockObject_MockObject
     */
    private $uitpas;

    /**
     * @var OrganizerController
     */
    private $controller;

    public function setUp()
    {
        $this->uitpas = $this->getMock(\CultureFeed_Uitpas::class);
        $this->controller = new OrganizerController($this->uitpas);
    }

    /**
     * @test
     */
    public function it_responds_with_a_list_of_card_systems_with_distribution_keys_for_a_given_organizer()
    {
        $organizerId = 'db93a8d0-331a-4575-a23d-2c78d4ceb925';

        $cardSystem1 = new \CultureFeed_Uitpas_CardSystem();
        $cardSystem1->id = 'card-system-1';
        $cardSystem1->name = 'Card system 1';

        $distributionKey1 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey1->id = 'distribution-key-1';
        $distributionKey1->name = 'Distribution key 1';

        $distributionKey2 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey2->id = 'distribution-key-2';
        $distributionKey2->name = 'Distribution key 2';

        $cardSystem1->distributionKeys = [
            $distributionKey1,
            $distributionKey2,
        ];

        $cardSystem2 = new \CultureFeed_Uitpas_CardSystem();
        $cardSystem2->id = 'card-system-2';
        $cardSystem2->name = 'Card system 2';

        $distributionKey3 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey3->id = 'distribution-key-3';
        $distributionKey3->name = 'Distribution key 3';

        $distributionKey4 = new \CultureFeed_Uitpas_DistributionKey();
        $distributionKey4->id = 'distribution-key-4';
        $distributionKey4->name = 'Distribution key 4';

        $cardSystem2->distributionKeys = [
            $distributionKey3,
            $distributionKey4,
        ];

        $distributionKeyParent1 = clone $distributionKey1;
        $distributionKeyParent1->cardSystem = $cardSystem1;

        $distributionKeyParent2 = clone $distributionKey2;
        $distributionKeyParent2->cardSystem = $cardSystem1;

        $distributionKeyParent3 = clone $distributionKey3;
        $distributionKeyParent3->cardSystem = $cardSystem2;

        $distributionKeyParent4 = clone $distributionKey4;
        $distributionKeyParent4->cardSystem = $cardSystem2;

        $cardSystems = [
            $distributionKeyParent1,
            $distributionKeyParent2,
            $distributionKeyParent3,
            $distributionKeyParent4,
        ];

        $resultSet = new \CultureFeed_ResultSet();
        $resultSet->objects = $cardSystems;
        $resultSet->total = 2;

        $this->uitpas->expects($this->once())
            ->method('getDistributionKeysForOrganizer')
            ->with($organizerId)
            ->willReturn($resultSet);

        $expectedResponseContent = json_encode(
            [
                [
                    'id' => 'card-system-1',
                    'name' => 'Card system 1',
                    'distributionKeys' => [
                        [
                            'id' => 'distribution-key-1',
                            'name' => 'Distribution key 1',
                        ],
                        [
                            'id' => 'distribution-key-2',
                            'name' => 'Distribution key 2',
                        ],
                    ],
                ],
                [
                    'id' => 'card-system-2',
                    'name' => 'Card system 2',
                    'distributionKeys' => [
                        [
                            'id' => 'distribution-key-3',
                            'name' => 'Distribution key 3',
                        ],
                        [
                            'id' => 'distribution-key-4',
                            'name' => 'Distribution key 4',
                        ],
                    ],
                ],
            ]
        );

        $actualResponseContent = $this->controller->getDistributionKeys($organizerId)
            ->getContent();

        $this->assertEquals($expectedResponseContent, $actualResponseContent);
    }
}
