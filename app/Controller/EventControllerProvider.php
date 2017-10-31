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
        $app['uitpas.event_card_system_controller'] = $app->share(
            function (Application $app) {
                return new EventCardSystemsController(
                    $app['culturefeed_uitpas_client']
                );
            }
        );

        /** @var ControllerCollection $controllersFactory */
        $controllers = $app['controllers_factory'];

        $controllers->get(
            '/{eventId}/cardSystems/',
            'uitpas.event_card_system_controller:get'
        );

        $controllers->put(
            '/{eventId}/cardSystems/{cardSystemId}',
            'uitpas.event_card_system_controller:add'
        );
        $controllers->put(
            '/{eventId}/cardSystems/{cardSystemId}/{distributionKeyId}',
            'uitpas.event_card_system_controller:add'
        );

        $controllers->delete(
            '/{eventId}/cardSystems/{cardSystemId}',
            'uitpas.event_card_system_controller:delete'
        );

        return $controllers;
    }
}
