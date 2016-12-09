<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga;

use Broadway\Saga\MetadataInterface;

/**
 * Copied from Broadway\Saga\Metadata\Metadata and modified to work with
 * namespaced event names.
 */
class NamespacedEventsMetadata implements MetadataInterface
{
    private $criteria;

    /**
     * @param array $criteria
     */
    public function __construct($criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * {@inheritDoc}
     */
    public function handles($event)
    {
        $eventName = get_class($event);

        return isset($this->criteria[$eventName]);
    }

    /**
     * {@inheritDoc}
     */
    public function criteria($event)
    {
        $eventName = get_class($event);

        if (! isset($this->criteria[$eventName])) {
            throw new \RuntimeException(sprintf("No criteria for event '%s'.", $eventName));
        }

        return $this->criteria[$eventName]($event);
    }
}
