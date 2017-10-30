#!/usr/bin/env php
<?php

use CultuurNet\SilexAMQP\Console\ConsumeCommand;
use Knp\Provider\ConsoleServiceProvider;

require_once __DIR__ . '/../vendor/autoload.php';

/** @var \Silex\Application $app */
$app = require __DIR__ . '/../bootstrap.php';

$app->register(
    new ConsoleServiceProvider(),
    [
        'console.name'              => 'UiTPAS service',
        'console.version'           => '1.0.0',
        'console.project_directory' => __DIR__.'/..'
    ]
);

/** @var \Knp\Console\Application $consoleApp */
$consoleApp = $app['console'];

$consoleApp->add(
    (new ConsumeCommand('consume-uitpas', 'amqp.uitpas'))
        ->setDescription('Process messages from UiTPAS')
);

$consoleApp->run();
