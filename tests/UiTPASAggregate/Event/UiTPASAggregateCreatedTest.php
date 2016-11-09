<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event;

class UiTPASAggregateCreatedTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UiTPASAggregateCreated
     */
    private $aggregateCreated;

    /**
     * @var array
     */
    private $aggregateCreatedAsArray;

    protected function setUp()
    {
        $aggregateId = '04cb234c-a678-11e6-80f5-76304dec7eb7';

        $distributionKeys = ['distribution-key-1', 'distribution-key-2'];

        $this->aggregateCreated = new UiTPASAggregateCreated(
            $aggregateId,
            $distributionKeys
        );

        $this->aggregateCreatedAsArray = [
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
            $this->aggregateCreated,
            UiTPASAggregateCreated::deserialize($this->aggregateCreatedAsArray)
        );
    }

    /**
     * @test
     */
    public function it_can_serialize()
    {
        $this->assertEquals(
            $this->aggregateCreatedAsArray,
            $this->aggregateCreated->serialize()
        );
    }
}
