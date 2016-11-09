<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event;

class DistributionKeysClearedTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DistributionKeysCleared
     */
    private $distributionKeysCleared;

    /**
     * @var array
     */
    private $distributionKeysClearedAsArray;

    protected function setUp()
    {
        $aggregateId = '04cb234c-a678-11e6-80f5-76304dec7eb7';

        $this->distributionKeysCleared = new DistributionKeysCleared(
            $aggregateId
        );

        $this->distributionKeysClearedAsArray = [
            'uitpas_id' => $aggregateId,
        ];
    }

    /**
     * @test
     */
    public function it_can_deserialize()
    {
        $this->assertEquals(
            $this->distributionKeysCleared,
            DistributionKeysCleared::deserialize(
                $this->distributionKeysClearedAsArray
            )
        );
    }

    /**
     * @test
     */
    public function it_can_serialize()
    {
        $this->assertEquals(
            $this->distributionKeysClearedAsArray,
            $this->distributionKeysCleared->serialize()
        );
    }
}
