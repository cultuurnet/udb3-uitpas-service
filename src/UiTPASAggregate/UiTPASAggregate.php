<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate;

use Broadway\EventSourcing\EventSourcedAggregateRoot;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\UiTPASAggregateCreated;

class UiTPASAggregate extends EventSourcedAggregateRoot
{
    /**
     * @var string
     */
    private $aggregateId;

    /**
     * @var string[]
     */
    private $distributionKeyIds;

    final public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getAggregateRootId()
    {
        return $this->aggregateId;
    }

    /**
     * @param string $eventId
     * @param array $distributionKeyIds
     *
     * @return UiTPASAggregate
     *
     * @uses handleUiTPASAggregateCreated
     */
    public static function create($eventId, array $distributionKeyIds)
    {
        $aggregate = new self();

        $aggregate->apply(
            new UiTPASAggregateCreated($eventId, $distributionKeyIds)
        );

        return $aggregate;
    }

    /**
     * @param array $distributionKeyIds
     *
     * @uses handleDistributionKeysUpdated
     */
    public function updateDistributionKeys(array $distributionKeyIds)
    {
        if ($distributionKeyIds != $this->distributionKeyIds) {
            $this->apply(
                new DistributionKeysUpdated($this->aggregateId, $distributionKeyIds)
            );
        }
    }

    /**
     * @uses handleDistributionKeysCleared
     */
    public function clearDistributionKeys()
    {
        if (!empty($this->distributionKeyIds)) {
            $this->apply(
                new DistributionKeysCleared($this->aggregateId)
            );
        }
    }

    /**
     * @param UiTPASAggregateCreated $created
     */
    protected function applyUiTPASAggregateCreated(UiTPASAggregateCreated $created)
    {
        $this->aggregateId = $created->getAggregateId();
        $this->distributionKeyIds = $created->getDistributionKeyIds();
    }

    /**
     * @param DistributionKeysUpdated $distributionKeysUpdated
     */
    protected function applyDistributionKeysUpdated(DistributionKeysUpdated $distributionKeysUpdated)
    {
        $this->distributionKeyIds = $distributionKeysUpdated->getDistributionKeyIds();
    }

    /**
     * @param DistributionKeysCleared $distributionKeysCleared
     */
    protected function applyDistributionKeysCleared(DistributionKeysCleared $distributionKeysCleared)
    {
        $this->distributionKeyIds = [];
    }
}
