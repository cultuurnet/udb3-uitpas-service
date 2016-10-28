<?php

namespace CultuurNet\UDB3\UiTPASService\Specification;

use ValueObjects\String\String as StringLiteral;

interface OrganizerSpecificationInterface
{
    /**
     * @param StringLiteral $organizerId
     * @return bool
     */
    public function isSatisfiedBy(StringLiteral $organizerId);
}
