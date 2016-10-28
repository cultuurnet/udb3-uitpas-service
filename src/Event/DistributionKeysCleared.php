<?php

namespace CultuurNet\UDB3\UiTPASService\Event;

class DistributionKeysCleared
{
    /**
     * @var string
     */
    private $eventId;

    /**
     * @param string $eventId
     */
    public function __construct($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * @return string
     */
    public function getEventId()
    {
        return $this->eventId;
    }
}
