<?php

use CultuurNet\UDB3\Log\ContextEnrichingLogger;

require_once 'vendor/autoload.php';

Resque_Event::listen(
    'beforePerform',
    function (Resque_Job $job) {
        /** @var \Silex\Application $app */
        $app = require __DIR__ . '/bootstrap.php';

        $app->boot();

        $args = $job->getArguments();

        $context = unserialize(base64_decode($args['context']));
        // @todo Add back impersonator.
        //$app['impersonator']->impersonate($context);

        $app['logger.fatal_job_error'] = new ContextEnrichingLogger(
            $app['logger.command_bus'],
            array('job_id' => $job->payload['id'])
        );

        $errorLoggingShutdownHandler = function () use ($app) {
            $error = error_get_last();

            $fatalErrors = E_ERROR | E_RECOVERABLE_ERROR;

            $wasFatal = $fatalErrors & $error['type'];

            if ($wasFatal) {
                $app['logger.fatal_job_error']->error('job_failed');

                $app['logger.fatal_job_error']->debug(
                    'error caused job failure',
                    ['error' => $error]
                );
            }
        };

        register_shutdown_function($errorLoggingShutdownHandler);

        // Command bus service name is based on queue name + _command_bus_out.
        // Eg. Queue "event" => command bus "event_command_bus_out".
        $commandBusServiceName = getenv('QUEUE') . '_command_bus_out';

        // Allows to access the command bus in perform() of jobs that
        // come out of the queue.
        \CultuurNet\UDB3\CommandHandling\QueueJob::setCommandBus(
            $app[$commandBusServiceName]
        );
    }
);