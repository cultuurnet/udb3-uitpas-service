<?php

namespace CultuurNet\UDB3\UiTPASService\Permissions;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Lcobucci\JWT\Token as Jwt;
use ValueObjects\Identity\UUID;
use ValueObjects\Web\Url;

class UDB3EventPermissionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $eventId;

    /**
     * @var ClientInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClient;

    /**
     * @var Url
     */
    private $permissionUrl;

    /**
     * @var Request
     */
    private $expectedRequest;

    /**
     * @var UDB3EventPermission
     */
    private $udb3EventPermission;

    protected function setUp()
    {
        $this->httpClient = $this->getMock(ClientInterface::class);

        $this->permissionUrl = Url::fromNative('http://udb-silex.dev/event/%s/permission');

        $this->eventId = new UUID();

        $permissionUrl = sprintf(
            (string)$this->permissionUrl,
            $this->eventId->toNative()
        );

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with($permissionUrl)
            ->willReturn(new Request('GET', $permissionUrl));

        $jwt = new Jwt();

        $this->expectedRequest = new Request('GET', $permissionUrl);
        $this->expectedRequest->addHeader('Authorization', "Bearer $jwt");

        $this->udb3EventPermission = new UDB3EventPermission(
            $this->httpClient,
            $this->permissionUrl,
            $jwt
        );
    }

    /**
     * @test
     */
    public function it_can_check_for_permission_on_event()
    {
        $this->httpClient->expects($this->once())
            ->method('send')
            ->with($this->expectedRequest)
            ->willReturn(new Response(200, null, '{"hasPermission":true}'));

        $hasPermission = $this->udb3EventPermission->hasPermission($this->eventId);

        $this->assertTrue($hasPermission);
    }

    /**
     * @test
     */
    public function it_returns_false_when_http_request_fails()
    {
        $this->httpClient->expects($this->once())
            ->method('send')
            ->with($this->expectedRequest)
            ->willReturn(new Response(400));

        $hasPermission = $this->udb3EventPermission->hasPermission($this->eventId);

        $this->assertFalse($hasPermission);
    }
}
