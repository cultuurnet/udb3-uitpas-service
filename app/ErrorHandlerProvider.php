<?php

namespace CultuurNet\UDB3\UiTPASService;

use Exception;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ErrorHandlerProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app->error(
            function (Exception $e) {
                return (new ErrorHandler())->__invoke($e);
            }
        );
    }

    public function boot(Application $app)
    {
    }
}
