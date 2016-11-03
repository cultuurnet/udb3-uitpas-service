<?php

namespace CultuurNet\UDB3\UiTPASService\Permissions;

use Guzzle\Http\ClientInterface;
use Lcobucci\JWT\Token as Jwt;
use ValueObjects\Web\Url;

class UDB3EventPermission implements EventPermissionInterface
{
    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var Url
     */
    private $permissionUrl;

    /**
     * @var Jwt
     */
    private $jwt;

    /**
     * UDB3EventPermission constructor.
     * @param ClientInterface $httpClient
     * @param Url $permissionUrl
     * @param Jwt $jwt
     */
    public function __construct(
        ClientInterface $httpClient,
        Url $permissionUrl,
        Jwt $jwt
    ) {
        $this->httpClient = $httpClient;
        $this->permissionUrl = $permissionUrl;
        $this->jwt = $jwt;
    }

    /**
     * @param string $eventId
     * @return bool
     */
    public function hasPermission($eventId)
    {
        $hasPermission = false;

        $permissionUrlAsString = sprintf(
            (string)$this->permissionUrl,
            $eventId
        );

        $request = $this->httpClient->get($permissionUrlAsString);
        $request->addHeader('Authorization', "Bearer {$this->jwt}");

        $response = $this->httpClient->send($request);

        if ($response->getStatusCode() === 200) {
            $body = json_decode($response->getBody(true));
            $hasPermission = $body->hasPermission;
        }

        return $hasPermission;
    }
}
