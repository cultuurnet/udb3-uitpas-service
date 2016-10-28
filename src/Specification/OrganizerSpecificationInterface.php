<?php

namespace CultuurNet\UDB3\UiTPASService\Specification;

interface OrganizerSpecificationInterface
{
    /**
     * @param string $organizerId
     * @return bool
     */
    public function isSatisfiedBy($organizerId);
}
