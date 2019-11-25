<?php

namespace CultuurNet\UDB3\UiTPASService\Controller;

use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

class EventControllerProvider implements ControllerProviderInterface
{
    const EVENT_DETAIL = 'event.detail';
    const EVENT_CARD_SYSTEMS = 'event.card_systems';

    public function connect(Application $app)
    {
        $app['uitpas.event_detail_controller'] = $app->share(
            function (Application $app) {
                return new EventDetailController(
                    $app['culturefeed_uitpas_client'],
                    $app['url_generator'],
                    self::EVENT_DETAIL,
                    self::EVENT_CARD_SYSTEMS
                );
            }
        );

        $app['uitpas.event_card_systems_controller'] = $app->share(
            function (Application $app) {
                return new EventCardSystemsController(
                    $app['culturefeed_uitpas_client']
                );
            }
        );

        /** @var ControllerCollection $controllersFactory */
        $controllers = $app['controllers_factory'];

        $controllers->get(
            '/{eventId}',
            'uitpas.event_detail_controller:get'
        )->bind(self::EVENT_DETAIL);

        $controllers->get(
            '/{eventId}/cardSystems/',
            'uitpas.event_card_systems_controller:get'
        )->bind(self::EVENT_CARD_SYSTEMS);

        $controllers->put(
            '/{eventId}/cardSystems/',
            'uitpas.event_card_systems_controller:set'
        );
        $controllers->put(
            '/{eventId}/cardSystems/{cardSystemId}',
            'uitpas.event_card_systems_controller:add'
        );
        $controllers->put(
            '/{eventId}/cardSystems/{cardSystemId}/distributionKey/{distributionKeyId}',
            'uitpas.event_card_systems_controller:add'
        );

        $controllers->delete(
            '/{eventId}/cardSystems/{cardSystemId}',
            'uitpas.event_card_systems_controller:delete'
        );

        return $controllers;
    }
}
