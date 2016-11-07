<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

class EventControllerProvider implements ControllerProviderInterface
{
    /**
     * @inheritdoc
     */
    public function connect(Application $app)
    {
        $app['uitpas.event_controller'] = $app->share(
            function (Application $app) {
                return new EventController(
                    $app['uitpas_command_bus_out'],
                    $app['culturefeed_uitpas_client'],
                    $app['udb3.event_permission']
                );
            }
        );

        /** @var ControllerCollection $controllersFactory */
        $controllers = $app['controllers_factory'];

        $controllers->get(
            '/{eventId}/distributionKeys/',
            'uitpas.event_controller' . ':get'
        );

        $controllers->put(
            '/{eventId}/distributionKeys/',
            'uitpas.event_controller' . ':update'
        );

        $controllers->delete(
            '/{eventId}/distributionKeys/',
            'uitpas.event_controller' . ':clear'
        );

        return $controllers;
    }
}
