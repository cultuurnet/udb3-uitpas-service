<?php

use CultuurNet\SymfonySecurityJwt\Authentication\JwtUserToken;
use DerAlex\Silex\YamlConfigServiceProvider;
use JDesrosiers\Silex\Provider\CorsServiceProvider;
use Lcobucci\JWT\Token as Jwt;
use Silex\Application;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

$app = new Application();

if (!isset($appConfigLocation)) {
    $appConfigLocation =  __DIR__;
}
$app->register(new YamlConfigServiceProvider($appConfigLocation . '/config.yml'));

$app->register(new CorsServiceProvider(), array(
    "cors.allowOrigin" => implode(" ", $app['config']['cors']['origins']),
    "cors.allowCredentials" => true
));

/**
 * Turn debug on or off.
 */
$app['debug'] = $app['config']['debug'] === true;

/**
 * Load additional bootstrap files.
 */
foreach ($app['config']['bootstrap'] as $identifier => $enabled) {
    if (true === $enabled) {
        require __DIR__ . "/bootstrap/{$identifier}.php";
    }
}

$app['jwt'] = $app->share(
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

$app['current_user'] = $app->share(
    function (Application $app) {
        /* @var Jwt|null $jwt */
        $jwt = $app['jwt'];

        if (!is_null($jwt)) {
            $cfUser = new \CultureFeed_User();

            $cfUser->id = $jwt->getClaim('uid');
            $cfUser->nick = $jwt->getClaim('nick');
            $cfUser->mbox = $jwt->getClaim('email');

            return $cfUser;
        } else {
            return null;
        }
    }
);

$app['culturefeed_uitpas_client'] = $app->share(
    function (Application $app) {
        $oauthClient = new CultureFeed_DefaultOAuthClient(
            $app['config']['uitid']['consumer']['key'],
            $app['config']['uitid']['consumer']['secret']
        );
        $oauthClient->setEndpoint($app['config']['uitid']['base_url']);
        $cf = new CultureFeed($oauthClient);
        return $cf->uitpas();
    }
);

return $app;
