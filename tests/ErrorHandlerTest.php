<?php declare(strict_types=1);

namespace CultuurNet\UDB3\UiTPASService;

use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_api_problem_from_exception()
    {
        $exception = new \Exception('Exception message', 500);

        $errorHandler = new ErrorHandler();
        $apiProblem = $errorHandler->__invoke($exception);
        $this->assertEquals('Exception message', $apiProblem->getTitle());
        $this->assertEquals(500, $apiProblem->getStatus());
    }

    /**
     * @test
     */
    public function it_creates_api_problem_with_400_status_if_exception_has_no_status_code()
    {
        $exception = new \Exception('Exception message');

        $errorHandler = new ErrorHandler();
        $apiProblem = $errorHandler->__invoke($exception);
        $this->assertEquals('Exception message', $apiProblem->getTitle());
        $this->assertEquals(400, $apiProblem->getStatus());
    }

    /**
     * @test
     */
    public function it_strips_URL_CALLED_from_api_problem_message_title()
    {
        // the resulting ApiProblem title should be stripped
        // of everything after URL CALLED
        $exception = new \Exception('Exception message URL CALLED: https://acc.uitid.be/uitid/rest/uitpas/cultureevent/de343e38-d656-4928-96bc-55578e0d94ec/cardsystems POST DATA: cardSystemId=3');

        $errorHandler = new ErrorHandler();
        $apiProblem = $errorHandler->__invoke($exception);
        $this->assertEquals('Exception message ', $apiProblem->getTitle());
    }
}
