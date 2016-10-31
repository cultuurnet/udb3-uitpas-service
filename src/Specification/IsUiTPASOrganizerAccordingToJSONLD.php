<?php

namespace CultuurNet\UDB3\UiTPASService\Specification;

class IsUiTPASOrganizerAccordingToJSONLD implements OrganizerSpecificationInterface
{
    /**
     * @var string[]
     */
    protected $uitpasLabels;

    /**
     * @param string[] $uitpasLabels
     */
    public function __construct($uitpasLabels)
    {
        $this->uitpasLabels = $uitpasLabels;
    }

    /**
     * @todo Implement.
     */
    public function isSatisfiedBy($organizerId)
    {
        return TRUE;
    }
}
