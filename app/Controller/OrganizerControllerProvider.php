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
        $app['uitpas.organizer_card_systems_controller'] = $app->share(
            function (Application $app) {
                return new OrganizerCardSystemsController(
                    $app['culturefeed_uitpas_client']
                );
            }
        );

        /** @var ControllerCollection $controllersFactory */
        $controllers = $app['controllers_factory'];

        $controllers->get(
            '/{organizerId}/cardSystems/',
            'uitpas.organizer_card_systems_controller:get'
        );

        return $controllers;
    }
}
