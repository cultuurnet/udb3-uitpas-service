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
