<?php

namespace CultuurNet\UDB3\UiTPASService\Command;

use CultuurNet\UDB3\PriceInfo\PriceInfo;

class UpdateUiTPASEvent
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
     * @var string[]
     */
    private $distributionKeyIds;

    /**
     * @param $eventId
     * @param $organizerId
     * @param PriceInfo $priceInfo
     * @param string[] $distributionKeyIds
     */
    public function __construct(
        $eventId,
        $organizerId,
        PriceInfo $priceInfo,
        array $distributionKeyIds = []
    ) {
        $this->eventId = $eventId;
        $this->organizerId = $organizerId;
        $this->priceInfo = $priceInfo;
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
