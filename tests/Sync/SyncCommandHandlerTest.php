<?php

namespace CultuurNet\UDB3\UiTPASService\Sync;

use CultuurNet\UDB3\PriceInfo\BasePrice;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\PriceInfo\PriceInfo;
use CultuurNet\UDB3\PriceInfo\Tariff;
use CultuurNet\UDB3\UiTPASService\Sync\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\UpdateUiTPASEvent;
use Psr\Log\LoggerInterface;
use ValueObjects\Money\Currency;
use ValueObjects\String\String as StringLiteral;

class SyncCommandHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \CultureFeed_Uitpas|\PHPUnit_Framework_MockObject_MockObject
     */
    private $uitpas;

    /**
     * @var SyncCommandHandler
     */
    private $commandHandler;

    /**
     * @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * @var array
     */
    private $logs;

    /**
     * @var string
     */
    private $eventId;

    /**
     * @var string
     */
    private $organizerId;

    /**
     * @var PriceInfo
     */
    private $priceInfo;

    /**
     * @var string[]
     */
    private $distributionKeys;

    /**
     * @var \CultureFeed_Uitpas_Event_CultureEvent
     */
    private $cultureEvent;

    public function setUp()
    {
        $this->uitpas = $this->getMock(\CultureFeed_Uitpas::class);
        $this->commandHandler = new SyncCommandHandler($this->uitpas);

        $this->logger = $this->getMock(LoggerInterface::class);
        $this->commandHandler->setLogger($this->logger);

        $this->logs = [
            'error' => [],
            'info' => [],
        ];

        $this->logger->expects($this->any())
            ->method('error')
            ->willReturnCallback(
                function ($message, array $context) {
                    $this->logs['error'][] = [
                        'message' => $message,
                        'context' => $context,
                    ];
                }
            );

        $this->logger->expects($this->any())
            ->method('info')
            ->willReturnCallback(
                function ($message, array $context) {
                    $this->logs['info'][] = [
                        'message' => $message,
                        'context' => $context,
                    ];
                }
            );

        $this->eventId = 'c30cf0f7-e1a9-43c9-8e03-b1e156d4caab';
        $this->organizerId = 'ad7a31f6-8e59-47f5-bf9c-76e348304ab3';

        $this->priceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat(5.5),
                Currency::fromNative('EUR')
            )
        );

        $this->priceInfo = $this->priceInfo->withExtraTariff(
            new Tariff(
                new StringLiteral('Test tariff'),
                Price::fromFloat(3.2),
                Currency::fromNative('EUR')
            )
        );

        $this->distributionKeys = [
            'distribution-key-1',
            'distribution-key-2',
        ];

        $cfDistributionKey1 = new \CultureFeed_Uitpas_DistributionKey();
        $cfDistributionKey1->id = 'distribution-key-1';

        $cfDistributionKey2 = new \CultureFeed_Uitpas_DistributionKey();
        $cfDistributionKey2->id = 'distribution-key-2';

        $this->cultureEvent = new \CultureFeed_Uitpas_Event_CultureEvent();
        $this->cultureEvent->cdbid = $this->eventId;
        $this->cultureEvent->organiserId = $this->organizerId;
        $this->cultureEvent->postPriceNames = ['Basistarief', 'Test tariff'];
        $this->cultureEvent->postPriceValues = [5.5, 3.2];
        $this->cultureEvent->distributionKey = [
            $cfDistributionKey1,
            $cfDistributionKey2,
        ];
    }

    /**
     * @test
     */
    public function it_registers_an_uitpas_event()
    {
        $command = new RegisterUiTPASEvent(
            $this->eventId,
            $this->organizerId,
            $this->priceInfo,
            $this->distributionKeys
        );

        $this->expectRegistration($this->cultureEvent);
        $this->expectNoUpdate();

        $this->commandHandler->handle($command);

        $this->assertSuccessfulSyncLogged($this->eventId);
    }

    /**
     * @test
     */
    public function it_updates_an_uitpas_event_if_registration_fails()
    {
        $command = new RegisterUiTPASEvent(
            $this->eventId,
            $this->organizerId,
            $this->priceInfo,
            $this->distributionKeys
        );

        $this->expectRegistrationToFail();
        $this->expectUpdate($this->cultureEvent);

        $this->commandHandler->handle($command);

        $this->assertSuccessfulSyncLogged($this->eventId);
    }

    /**
     * @test
     */
    public function it_updates_an_uitpas_event()
    {
        $command = new UpdateUiTPASEvent(
            $this->eventId,
            $this->organizerId,
            $this->priceInfo,
            $this->distributionKeys
        );

        $this->expectUpdate($this->cultureEvent);
        $this->expectNoRegistration();

        $this->commandHandler->handle($command);

        $this->assertSuccessfulSyncLogged($this->eventId);
    }

    /**
     * @test
     */
    public function it_registers_an_uitpas_event_if_updating_fails()
    {
        $command = new UpdateUiTPASEvent(
            $this->eventId,
            $this->organizerId,
            $this->priceInfo,
            $this->distributionKeys
        );

        $this->expectUpdateToFail();
        $this->expectRegistration($this->cultureEvent);

        $this->commandHandler->handle($command);

        $this->assertSuccessfulSyncLogged($this->eventId);
    }

    /**
     * @test
     */
    public function it_logs_errors_if_both_sync_attempts_fail()
    {
        $command = new RegisterUiTPASEvent(
            $this->eventId,
            $this->organizerId,
            $this->priceInfo,
            $this->distributionKeys
        );

        $registrationException = new \Exception('Event already exists.');
        $updateException = new \Exception('Event does not exist.');

        $this->expectRegistrationToFail($registrationException);
        $this->expectUpdateToFail($updateException);

        $this->commandHandler->handle($command);

        $this->assertErrorsLogged(
            $this->eventId,
            [
                $registrationException,
                $updateException
            ]
        );
    }

    /**
     * @param \CultureFeed_Uitpas_Event_CultureEvent $expectedCultureEvent
     */
    private function expectRegistration(\CultureFeed_Uitpas_Event_CultureEvent $expectedCultureEvent)
    {
        $this->uitpas->expects($this->once())
            ->method('registerEvent')
            ->with($expectedCultureEvent);
    }

    private function expectNoRegistration()
    {
        $this->uitpas->expects($this->never())
            ->method('registerEvent');
    }

    /**
     * @param \Exception $exception
     */
    private function expectRegistrationToFail(\Exception $exception = null)
    {
        $exception = is_null($exception) ? new \Exception('Event registration failed.') : $exception;

        $this->uitpas->expects($this->once())
            ->method('registerEvent')
            ->willThrowException($exception);
    }

    /**
     * @param \CultureFeed_Uitpas_Event_CultureEvent $expectedCultureEvent
     */
    private function expectUpdate(\CultureFeed_Uitpas_Event_CultureEvent $expectedCultureEvent)
    {
        $this->uitpas->expects($this->once())
            ->method('updateEvent')
            ->with($expectedCultureEvent);
    }

    private function expectNoUpdate()
    {
        $this->uitpas->expects($this->never())
            ->method('updateEvent');
    }

    /**
     * @param \Exception $exception
     */
    private function expectUpdateToFail(\Exception $exception = null)
    {
        $exception = is_null($exception) ? new \Exception('Event update failed.') : $exception;

        $this->uitpas->expects($this->once())
            ->method('updateEvent')
            ->willThrowException($exception);
    }

    /**
     * @param string $cdbid
     * @param \Exception[] $exceptions
     */
    private function assertErrorsLogged($cdbid, array $exceptions)
    {
        $expectedErrorLogs = [
            [
                'message' => 'Unable to synchronise uitpas event data',
                'context' => ['cdbid' => $cdbid],
            ],
        ];

        foreach ($exceptions as $exception) {
            $expectedErrorLogs[] = [
                'message' => $exception->getMessage(),
                'context' => ['exception' => $exception],
            ];
        }

        $this->assertEquals($expectedErrorLogs, $this->logs['error']);
    }

    /**
     * @param string $cdbid
     */
    private function assertSuccessfulSyncLogged($cdbid)
    {
        $log = [
            'message' => 'Succesfully synchronised uitpas event data',
            'context' => ['cdbid' => $cdbid],
        ];

        $this->assertTrue(in_array($log, $this->logs['info']));
    }
}
