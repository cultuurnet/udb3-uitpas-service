<?php

namespace CultuurNet\UDB3\UiTPASService;

use Exception;
use Sentry\State\HubInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ErrorHandlerProvider implements ServiceProviderInterface
{
    public function register(Application $app): void
    {
        $app[SentryErrorHandler::class] = $app->share(
            function ($app) {
                return new SentryErrorHandler(
                    $app[HubInterface::class],
                    $app['jwt'] ?? null
                );
            }
        );

        $app->error(function (Exception $e) use ($app) {
            return (new ApiErrorHandler($app[SentryErrorHandler::class]))->handle($e);
        });
    }

    public function boot(Application $app): void
    {
    }
}
