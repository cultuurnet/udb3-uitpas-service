<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

class OrganizerControllerProvider implements ControllerProviderInterface
{

    /**
     * @inheritdoc
     */
    public function connect(Application $app)
    {
        $app['uitpas.organizer_controller'] = $app->share(
            function (Application $app) {
                return new OrganizerController(
                    $app['culturefeed_uitpas_client']
                );
            }
        );

        /** @var ControllerCollection $controllersFactory */
        $controllers = $app['controllers_factory'];

        $controllers->get(
            '/{organizerId}/cardSystems/',
            'uitpas.organizer_controller:getDistributionKeys'
        );

        return $controllers;
    }
}
