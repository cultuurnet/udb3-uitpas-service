<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

class UiTPASControllerProvider implements ControllerProviderInterface
{
    /**
     * @inheritdoc
     */
    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllersFactory */
        $controllers = $app['controllers_factory'];

        $controllers->get(
            '/{id}/distributionKeys',
            'uitpas.distribution_keys_controller' . ':get'
        );

        $controllers->put(
            '/{id}/distributionKeys',
            'uitpas.distribution_keys_controller' . ':update'
        );

        $controllers->delete(
            '/{id}/distributionKeys',
            'uitpas.distribution_keys_controller' . ':clear'
        );

        return $controllers;
    }
}
