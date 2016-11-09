<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event;

class DistributionKeysUpdatedTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DistributionKeysUpdated
     */
    private $distributionKeysUpdated;

    /**
     * @var array
     */
    private $distributionKeysUpdatedAsArray;

    protected function setUp()
    {
        $aggregateId = '04cb234c-a678-11e6-80f5-76304dec7eb7';

        $distributionKeys = ['distribution-key-1', 'distribution-key-2'];

        $this->distributionKeysUpdated = new DistributionKeysUpdated(
            $aggregateId,
            $distributionKeys
        );

        $this->distributionKeysUpdatedAsArray = [
            'uitpas_id' => $aggregateId,
            'distribution_key_ids' => $distributionKeys
        ];
    }

    /**
     * @test
     */
    public function it_can_deserialize()
    {
        $this->assertEquals(
            $this->distributionKeysUpdated,
            DistributionKeysUpdated::deserialize(
                $this->distributionKeysUpdatedAsArray
            )
        );
    }

    /**
     * @test
     */
    public function it_can_serialize()
    {
        $this->assertEquals(
            $this->distributionKeysUpdatedAsArray,
            $this->distributionKeysUpdated->serialize()
        );
    }
}
