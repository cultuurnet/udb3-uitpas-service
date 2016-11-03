<?php

namespace CultuurNet\UDB3\UiTPASService\Permissions;

interface EventPermissionInterface
{
    /**
     * @param string $eventId
     * @return bool
     */
    public function hasPermission($eventId);
}
