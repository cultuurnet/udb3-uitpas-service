<?php

namespace CultuurNet\UDB3\UiTPASService;

use Crell\ApiProblem\ApiProblem;
use CultuurNet\UDB3\HttpFoundation\Response\ApiProblemJsonResponse;
use Exception;
use Silex\Application;
use Silex\ServiceProviderInterface;

class ErrorHandlerProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app->error(
            function (Exception $e) {
                $problem = $this->createNewApiProblem($e);
                return new ApiProblemJsonResponse($problem);
            }
        );
    }

    protected function createNewApiProblem(Exception $e): ApiProblem
    {
        $problem = new ApiProblem($e->getMessage());
        $problem->setStatus($e->getCode() ?: ApiProblemJsonResponse::HTTP_BAD_REQUEST);
        return $problem;
    }

    public function boot(Application $app)
    {
    }
}
