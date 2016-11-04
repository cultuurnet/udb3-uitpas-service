<?php

namespace CultuurNet\UDB3\UiTPASService\Permissions;

class DefaultEventPermission implements EventPermissionInterface
{
    /**
     * @inheritdoc
     */
    public function hasPermission($eventId)
    {
        return false;
    }
}
