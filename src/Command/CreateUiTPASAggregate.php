<?php

namespace CultuurNet\UDB3\UiTPASService\Command;

class CreateUiTPASAggregate
{
    /**
     * @var string
     */
    private $eventId;

    /**
     * @var string[]
     */
    private $distributionKeyIds;

    /**
     * @param string $eventId
     * @param string[] $distributionKeyIds
     */
    public function __construct($eventId, array $distributionKeyIds)
    {
        $this->eventId = $eventId;
        $this->distributionKeyIds = $distributionKeyIds;
    }

    /**
     * @return string
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @return string[]
     */
    public function getDistributionKeyIds()
    {
        return $this->distributionKeyIds;
    }
}
