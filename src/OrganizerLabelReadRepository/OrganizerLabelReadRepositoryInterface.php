<?php

namespace CultuurNet\UDB3\UiTPASService\OrganizerLabelReadRepository;

use CultuurNet\UDB3\LabelCollection;

interface OrganizerLabelReadRepositoryInterface
{
    /**
     * @param string $organizerId
     * @return LabelCollection $labels
     */
    public function getLabels($organizerId);
}
