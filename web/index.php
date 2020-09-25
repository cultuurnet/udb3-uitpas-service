<?php

require_once __DIR__ . '/../vendor/autoload.php';
use CultuurNet\UDB3\Jwt\Silex\JwtServiceProvider;
use CultuurNet\UDB3\Jwt\Symfony\Authentication\JwtAuthenticationEntryPoint;
use CultuurNet\UDB3\HttpFoundation\RequestMatcher\AnyOfRequestMatcher;
use CultuurNet\UDB3\HttpFoundation\RequestMatcher\PreflightRequestMatcher;
use CultuurNet\UDB3\UiTPASService\ApiGuardServiceProvider;
use CultuurNet\UDB3\UiTPASService\Controller\EventControllerProvider;
use CultuurNet\UDB3\UiTPASService\Controller\OrganizerControllerProvider;
use CultuurNet\UDB3\UiTPASService\ErrorHandlerProvider;
use CultuurNet\UDB3\UiTPASService\SentryServiceProvider;
use CultuurNet\UDB3\UiTPASService\UncaughtErrorHandler;
use Silex\Application;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestMatcher;

/** @var Application $app */
$app = require __DIR__ . '/../bootstrap.php';

/**
 * Allow to use services as controllers.
 */
$app->register(new ServiceControllerServiceProvider());

/**
 * Check api keys for requests.
 */
$app->register(new ApiGuardServiceProvider());

/**
 * Setup Sentry to capture uncaught exceptions.
 */
$app->register(new SentryServiceProvider());

/**
 * Handle errors by returning an API problem response.
 */
$app->register(new ErrorHandlerProvider());

/**
 * Security & firewall
 */
$app->register(new SecurityServiceProvider());
$app->register(new JwtServiceProvider());

$app['cors_preflight_request_matcher'] = $app::share(
    function () {
        return new PreflightRequestMatcher();
    }
);

$app['security.firewalls'] = array(
    'public' => array(
        'pattern' => (new AnyOfRequestMatcher())
            ->with(new RequestMatcher('^/labels', null, 'GET'))
            ->with(new RequestMatcher('^/organizers', null, 'GET'))
            ->with(new RequestMatcher('^/events', null, 'GET'))
    ),
    'cors-preflight' => array(
        'pattern' => $app['cors_preflight_request_matcher'],
    ),
    'secured' => array(
        'pattern' => '^.*$',
        'jwt' => [
            'uitid' => [
                'validation' => $app['config']['jwt']['uitid']['validation'],
                'required_claims' => [
                    'uid',
                    'nick',
                    'email',
                ],
                'public_key' => 'file://' . __DIR__ . '/../' . $app['config']['jwt']['uitid']['keys']['public']['file']
            ],
            'auth0' => [
                'validation' => $app['config']['jwt']['auth0']['validation'],
                'required_claims' => [
                    'email',
                    'sub',
                ],
                'public_key' => 'file://' . __DIR__ . '/../' . $app['config']['jwt']['auth0']['keys']['public']['file']
            ],
        ],
        'stateless' => true,
    ),
);

$app['security.entry_point.form._proto'] = $app::protect(
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
        return new JsonResponse(
            $app['config']['labels']
        );
    }
);

$app->mount('/events', new EventControllerProvider());
$app->mount('/organizers', new OrganizerControllerProvider());

$app->after($app['cors']);

try {
    $app->run();
} catch (Throwable $throwable) {
    $app[UncaughtErrorHandler::class]->handle($throwable);
    throw $throwable;
}
