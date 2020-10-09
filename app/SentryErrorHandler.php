<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\UiTPASService;

use CultuurNet\UDB3\Jwt\Udb3Token;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

class SentryErrorHandler
{
    /** @var HubInterface */
    private $sentryHub;

    /** @var Udb3Token|null */
    private $udb3Token;

    public function __construct(HubInterface $sentryHub, ?Udb3Token $udb3Token)
    {
        $this->sentryHub = $sentryHub;
        $this->udb3Token = $udb3Token;
    }

    public function handle(Throwable $throwable): void
    {
        if ($throwable->getCode() === 404) {
            return;
        }

        $this->sentryHub->configureScope(function (Scope $scope) {
            $scope->setUser($this->createUser($this->udb3Token));
        });

        $this->sentryHub->captureException($throwable);
    }

    private function createUser(?Udb3Token $udb3Token): array
    {
        if ($udb3Token === null) {
            return ['id' => 'anonymous'];
        }

        return [
            'id' => $udb3Token->id(),
            'uid' => $udb3Token->jwtToken()->getClaim('uid', 'null'),
            'uitidv1id' => $udb3Token->jwtToken()->getClaim('https://publiq.be/uitidv1id', 'null'),
            'sub' => $udb3Token->jwtToken()->getClaim('sub', 'null'),
        ];
    }
}
