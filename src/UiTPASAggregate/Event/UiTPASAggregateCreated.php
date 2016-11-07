<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event;

class UiTPASAggregateCreated extends AbstractUiTPASAggregateEvent
{
    /**
     * @var string[]
     */
    private $distributionKeyIds;

    /**
     * @param string $aggregateId
     * @param string[] $distributionKeyIds
     */
    public function __construct($aggregateId, array $distributionKeyIds)
    {
        parent::__construct($aggregateId);
        $this->distributionKeyIds = $distributionKeyIds;
    }

    /**
     * @return string[]
     */
    public function getDistributionKeyIds()
    {
        return $this->distributionKeyIds;
    }

    /**
     * @param array $data
     * @return AbstractUiTPASAggregateEvent
     */
    public static function deserialize(array $data)
    {
        return new static(
            $data['uitpas_id'],
            $data['distribution_key_ids']
        );
    }

    /**
     * @return array
     */
    public function serialize()
    {
        return [
            'uitpas_id' => $this->getAggregateId(),
            'distribution_key_ids' => $this->getDistributionKeyIds(),
        ];
    }
}
