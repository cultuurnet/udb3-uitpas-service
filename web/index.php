<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CultuurNet\UDB3\UiTPASService\Controller\UiTPASControllerProvider;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;

/** @var Application $app */
$app = require __DIR__ . '/../bootstrap.php';

/**
 * Allow to use services as controllers.
 */
$app->register(new ServiceControllerServiceProvider());

$app->get('/', function(Application $app) {
  /** @var \Doctrine\MongoDB\Collection $collection */
  $collection = $app['mongodb_sagas_collection'];
  return new \Symfony\Component\HttpFoundation\Response(
    $collection->count()
  );
});

$app->get('labels', function(Application $app) {
  return new \Symfony\Component\HttpFoundation\JsonResponse(
    $app['config']['labels']
  );
});

$app->mount('/uitpas', new UiTPASControllerProvider());

$app->run();
