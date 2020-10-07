<?php

namespace CultuurNet\UDB3\UiTPASService;

use Sentry\State\HubInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ErrorHandlerProvider implements ServiceProviderInterface
{
    public function register(Application $app): void
    {
        $app[SentryErrorHandler::class] = $app->share(
            function ($app) {
                return new SentryErrorHandler($app[HubInterface::class]);
            }
        );

        $app[ApiErrorHandler::class] = $app->share(
            function () use ($app) {
                return new ApiErrorHandler($app[SentryErrorHandler::class]);
            }
        );
        $app->error($app[ApiErrorHandler::class]);
    }

    public function boot(Application $app): void
    {
    }
}
