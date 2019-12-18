<?php

namespace CultuurNet\UDB3\UiTPASService;

use Silex\Application;
use Silex\ServiceProviderInterface;

class ErrorHandlerProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['error_handler'] = $app::share(
            function () {
                return new ErrorHandler();
            }
        );

        $app->error($app['error_handler']);
    }

    public function boot(Application $app)
    {
    }
}
