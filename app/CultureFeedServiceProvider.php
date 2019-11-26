<?php

declare(strict_types=1);

namespace CultuurNet\UDB3\UiTPASService;

use CultureFeed;
use CultureFeed_DefaultOAuthClient;
use Silex\Application;
use Silex\ServiceProviderInterface;

final class CultureFeedServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['culturefeed'] = $app::share(
            function (Application $app) {
                $oauthClient = new CultureFeed_DefaultOAuthClient(
                    $app['config']['uitid']['consumer']['key'],
                    $app['config']['uitid']['consumer']['secret']
                );
                $oauthClient->setEndpoint($app['config']['uitid']['base_url']);
                return new CultureFeed($oauthClient);
            }
        );

        $app['culturefeed_uitpas_client'] = $app::share(
            function (Application $app) {
                return $app['culturefeed']->uitpas();
            }
        );
    }

    public function boot(Application $app)
    {
    }

}
