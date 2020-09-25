<?php declare(strict_types=1);

namespace CultuurNet\UDB3\UiTPASService;

use Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Sentry\State\HubInterface;

class ApiErrorHandlerTest extends TestCase
{
    /** @var HubInterface|PHPUnit_Framework_MockObject_MockObject  */
    private $sentryHub;

    /** @var UncaughtErrorHandler */
    private $uncaughtErrorHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentryHub = $this->createMock(HubInterface::class);
        $this->uncaughtErrorHandler = new UncaughtErrorHandler($this->sentryHub);
    }

    /**
     * @test
     */
    public function it_creates_api_problem_from_exception()
    {
        $exception = new Exception('Exception message', 500);

        $errorHandler = new ApiErrorHandler($this->uncaughtErrorHandler);
        $this->sentryHub->expects($this->once())
            ->method('captureException');

        $apiProblemResponse = $errorHandler->__invoke($exception);

        $this->assertEquals(
            '{"title":"Exception message","type":"about:blank","status":500}',
            $apiProblemResponse->getContent()
        );
        $this->assertEquals(500, $apiProblemResponse->getStatusCode());
    }

    /**
     * @test
     */
    public function it_creates_api_problem_with_400_status_if_exception_has_no_status_code()
    {
        $exception = new Exception('Exception message');

        $errorHandler = new ApiErrorHandler($this->uncaughtErrorHandler);
        $this->sentryHub->expects($this->once())
            ->method('captureException');

        $apiProblemResponse = $errorHandler->__invoke($exception);

        $this->assertEquals(
            '{"title":"Exception message","type":"about:blank","status":400}',
            $apiProblemResponse->getContent()
        );
        $this->assertEquals(400, $apiProblemResponse->getStatusCode());
    }

    /**
     * @test
     */
    public function it_does_not_capture_404_to_sentry()
    {
        $exception = new Exception('Exception message', 404);

        $errorHandler = new ApiErrorHandler($this->uncaughtErrorHandler);
        $this->sentryHub->expects($this->never())
            ->method('captureException');

        $apiProblemResponse = $errorHandler->__invoke($exception);

        $this->assertEquals(
            '{"title":"Exception message","type":"about:blank","status":404}',
            $apiProblemResponse->getContent()
        );
        $this->assertEquals(404, $apiProblemResponse->getStatusCode());
    }

    /**
     * @test
     */
    public function it_strips_URL_CALLED_from_api_problem_message_title()
    {
        // the resulting ApiProblem title should be stripped
        // of everything after URL CALLED
        $exception = new Exception('Exception message URL CALLED: https://acc.uitid.be/uitid/rest/uitpas/cultureevent/de343e38-d656-4928-96bc-55578e0d94ec/cardsystems POST DATA: cardSystemId=3');

        $errorHandler = new ApiErrorHandler($this->uncaughtErrorHandler);
        $this->sentryHub->expects($this->once())
            ->method('captureException');
        
        $apiProblemResponse = $errorHandler->__invoke($exception);

        $this->assertEquals(
            '{"title":"Exception message ","type":"about:blank","status":400}',
            $apiProblemResponse->getContent()
        );
    }
}
