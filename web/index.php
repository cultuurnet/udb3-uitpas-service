<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CultuurNet\SymfonySecurityJwt\Authentication\JwtAuthenticationEntryPoint;
use CultuurNet\UDB3\HttpFoundation\RequestMatcher\MultiRouteRequestMatcher;
use CultuurNet\UDB3\HttpFoundation\RequestMatcher\PreflightRequestMatcher;
use CultuurNet\UDB3\HttpFoundation\RequestMatcher\Route;
use CultuurNet\UDB3\UiTPASService\Controller\EventControllerProvider;
use CultuurNet\UDB3\UiTPASService\Controller\OrganizerControllerProvider;
use CultuurNet\UDB3\UiTPASService\ErrorHandlerProvider;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;

/** @var Application $app */
$app = require __DIR__ . '/../bootstrap.php';

/**
 * Allow to use services as controllers.
 */
$app->register(new ServiceControllerServiceProvider());

/**
 * Handle errors by returning an API problem response.
 */
$app->register(new ErrorHandlerProvider());

/**
 * Security & firewall
 */
$app->register(new \Silex\Provider\SecurityServiceProvider());
$app->register(new \CultuurNet\SilexServiceProviderJwt\JwtServiceProvider());

$app['cors_preflight_request_matcher'] = $app->share(
    function () {
        return new PreflightRequestMatcher();
    }
);

$app['security.firewalls'] = array(
    'public' => array(
        'pattern' => (new MultiRouteRequestMatcher())
            ->matching(new Route('^/labels', 'GET'))
            ->matching(new Route('^/organizers', 'GET'))
            ->matching(new Route('^/events', 'GET'))
    ),
    'cors-preflight' => array(
        'pattern' => $app['cors_preflight_request_matcher'],
    ),
    'secured' => array(
        'pattern' => '^.*$',
        'jwt' => [
            'validation' => $app['config']['jwt']['validation'],
            'required_claims' => [
                'uid',
                'nick',
                'email',
            ],
            'public_key' => 'file://' . __DIR__ . '/../' . $app['config']['jwt']['keys']['public']['file'],
        ],
        'stateless' => true,
    ),
);

$app['security.entry_point.form._proto'] = $app->protect(
    function () use ($app) {
        return $app->share(
            function () {
                return new JwtAuthenticationEntryPoint();
            }
        );
    }
);

$app->get(
    'labels',
    function(Application $app) {
        return new \Symfony\Component\HttpFoundation\JsonResponse(
            $app['config']['labels']
        );
    }
);

$app->mount('/events', new EventControllerProvider());
$app->mount('/organizers', new OrganizerControllerProvider());

$app->after($app["cors"]);

$app->run();
