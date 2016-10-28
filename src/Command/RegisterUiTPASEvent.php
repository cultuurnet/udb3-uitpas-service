<?php

namespace CultuurNet\UDB3\UiTPASService\Command;

use CultuurNet\UDB3\PriceInfo\PriceInfo;

class RegisterUiTPASEvent
{
    /**
     * @var string
     */
    private $eventId;

    /**
     * @var string
     */
    private $organizerId;

    /**
     * @var PriceInfo
     */
    private $priceInfo;

    /**
     * @param $eventId
     * @param $organizerId
     * @param PriceInfo $priceInfo
     */
    public function __construct(
        $eventId,
        $organizerId,
        PriceInfo $priceInfo
    ) {
        $this->eventId = $eventId;
        $this->organizerId = $organizerId;
        $this->priceInfo = $priceInfo;
    }

    /**
     * @return string
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @return string
     */
    public function getOrganizerId()
    {
        return $this->organizerId;
    }

    /**
     * @return PriceInfo
     */
    public function getPriceInfo()
    {
        return $this->priceInfo;
    }
}
