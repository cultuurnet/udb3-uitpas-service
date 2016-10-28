<?php

namespace CultuurNet\UDB3\UiTPASService\Event;

class DistributionKeysUpdated extends AbstractUiTPASAggregateEvent
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
}
