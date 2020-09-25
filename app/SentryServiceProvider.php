<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\UiTPASService;

use Sentry\ClientBuilder;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Silex\Application;
use Silex\ServiceProviderInterface;

class SentryServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app): void
    {
        $app[HubInterface::class] = $app->share(
            function ($app) {
                return new Hub(
                    ClientBuilder::create([
                        'dsn' => $app['config']['sentry']['dsn'],
                        'environment' => $app['config']['sentry']['environment'],
                    ])->getClient()
                );
            }
        );
    }

    public function boot(Application $app): void
    {
    }
}
