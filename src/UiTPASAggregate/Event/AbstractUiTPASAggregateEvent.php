<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event;

use Broadway\Serializer\SerializableInterface;

abstract class AbstractUiTPASAggregateEvent implements SerializableInterface
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

    /**
     * @param array $data
     * @return AbstractUiTPASAggregateEvent
     */
    public static function deserialize(array $data)
    {
        return new static($data['uitpas_id']);
    }

    /**
     * @return array
     */
    public function serialize()
    {
        return ['uitpas_id' => $this->getAggregateId()];
    }
}
