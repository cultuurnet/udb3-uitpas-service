<?php declare(strict_types=1);

namespace CultuurNet\UDB3\UiTPASService;

use Crell\ApiProblem\ApiProblem;
use CultuurNet\UDB3\HttpFoundation\Response\ApiProblemJsonResponse;
use Exception;

class ErrorHandler
{
    public function __invoke(Exception $exception): ApiProblemJsonResponse
    {
        $problem = new ApiProblem($this->formatMessage($exception));
        $problem->setStatus($exception->getCode() ?: ApiProblemJsonResponse::HTTP_BAD_REQUEST);
        return new ApiProblemJsonResponse($problem);
    }

    /**
     * @param Exception $exception
     * @return string
     */
    private function formatMessage(Exception $exception): string
    {
        $message = $exception->getMessage();
        return preg_replace('/URL CALLED.*/', '', $message);
    }

}
