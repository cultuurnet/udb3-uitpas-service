<?php

use DerAlex\Silex\YamlConfigServiceProvider;
use Silex\Application;

$app = new Application();

if (!isset($appConfigLocation)) {
    $appConfigLocation =  __DIR__;
}
$app->register(new YamlConfigServiceProvider($appConfigLocation . '/config.yml'));

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

$app['mongodb_sagas_collection'] = $app->share(
  function (Application $app) {
    $mongoConf = $app['config']['mongo'] + array(
      'connection' => 'mongodb://127.0.0.1',
      'db' => 'uitpas',
    );

    $client = new MongoClient($mongoConf['connection']);
    $connection = new Doctrine\MongoDB\Connection($client);

    return $connection->selectCollection($mongoConf['db'], 'sagas');
  }
);

return $app;
