<?php

namespace CultuurNet\UDB3\UiTPASService\Event;

abstract class AbstractUiTPASAggregateEvent
{
    /**
     * @var string
     */
    private $aggregateId;

    /**
     * @param string $aggregateId
     */
    public function __construct($aggregateId)
    {
        $this->aggregateId = $aggregateId;
    }

    /**
     * @return string
     */
    public function getAggregateId()
    {
        return $this->aggregateId;
    }
}
