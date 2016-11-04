<?php

namespace CultuurNet\UDB3\UiTPASService\Permissions;

use ValueObjects\Identity\UUID;

class DefaultEventPermissionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_always_returns_false()
    {
        $defaultEventPermission = new DefaultEventPermission();
        $this->assertFalse($defaultEventPermission->hasPermission(new UUID()));
    }
}
