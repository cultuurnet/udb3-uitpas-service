<?php

use CultuurNet\SymfonySecurityJwt\Authentication\JwtUserToken;
use CultuurNet\UDB3\UiTPASService\CultureFeedServiceProvider;
use DerAlex\Silex\YamlConfigServiceProvider;
use JDesrosiers\Silex\Provider\CorsServiceProvider;
use Lcobucci\JWT\Token as Jwt;
use Silex\Application;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

$app = new Application();

if (!isset($appConfigLocation)) {
    $appConfigLocation =  __DIR__;
}
$app->register(new YamlConfigServiceProvider($appConfigLocation . '/config.yml'));

$app->register(new CorsServiceProvider(), array(
    'cors.allowOrigin' => implode(' ', $app['config']['cors']['origins']),
    'cors.allowCredentials' => true
));

$app->register(new UrlGeneratorServiceProvider());

/**
 * Turn debug on or off.
 */
$app['debug'] = $app['config']['debug'] === true;

$app['jwt'] = $app::share(
    function(Application $app) {
        try {
            /* @var TokenStorageInterface $tokenStorage */
            $tokenStorage = $app['security.token_storage'];
        } catch (\InvalidArgumentException $e) {
            // Running from CLI.
            return null;
        }

        $token = $tokenStorage->getToken();

        if ($token instanceof JwtUserToken) {
            return $token->getCredentials();
        }

        return null;
    }
);

$app['current_user'] = $app::share(
    function (Application $app) {
        /* @var Jwt|null $jwt */
        $jwt = $app['jwt'];

        if ($jwt instanceof Jwt) {
            $cfUser = new CultureFeed_User();

            $cfUser->id = $jwt->getClaim('uid');
            $cfUser->nick = $jwt->getClaim('nick');
            $cfUser->mbox = $jwt->getClaim('email');

            return $cfUser;
        }

        return null;
    }
);

$app->register(new CultureFeedServiceProvider());

return $app;
