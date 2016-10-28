<?php

namespace CultuurNet\UDB3\UiTPASService\Command;

use CultuurNet\UDB3\PriceInfo\PriceInfo;
use ValueObjects\String\String as StringLiteral;

class RegisterUiTPASEvent
{
    /**
     * @var StringLiteral
     */
    private $eventId;

    /**
     * @var StringLiteral
     */
    private $organizerId;

    /**
     * @var PriceInfo
     */
    private $priceInfo;

    /**
     * @param StringLiteral $eventId
     * @param StringLiteral $organizerId
     * @param PriceInfo $priceInfo
     */
    public function __construct(
        StringLiteral $eventId,
        StringLiteral $organizerId,
        PriceInfo $priceInfo
    ) {
        $this->eventId = $eventId;
        $this->organizerId = $organizerId;
        $this->priceInfo = $priceInfo;
    }

    /**
     * @return StringLiteral
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @return StringLiteral
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
