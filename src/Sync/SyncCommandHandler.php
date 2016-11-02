<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UiTPASService\Sync;

use Broadway\CommandHandling\CommandHandler;
use CultureFeed_Uitpas;
use CultureFeed_Uitpas_DistributionKey;
use CultureFeed_Uitpas_Event_CultureEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\SyncUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\UpdateUiTPASEvent;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class SyncCommandHandler extends CommandHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var CultureFeed_Uitpas
     */
    private $culturefeedClient;

    public function __construct(CultureFeed_Uitpas $culturefeedClient)
    {
        $this->culturefeedClient = $culturefeedClient;
        $this->logger = new NullLogger();
    }

    /**
     * @param RegisterUiTPASEvent $command
     */
    protected function handleRemotelyRegisterUiTPASEvent(
        RegisterUiTPASEvent $command
    ) {
        $event = $this->buildEvent($command);
        $this->syncEvent($event, ['registerEvent', 'updateEvent']);
    }

    /**
     * @param UpdateUiTPASEvent $command
     */
    protected function handleRemotelyUpdateUiTPASEvent(
        UpdateUiTPASEvent $command
    ) {
        $event = $this->buildEvent($command);
        $this->syncEvent($event, ['updateEvent', 'registerEvent']);
    }

    /**
     * @var SyncUiTPASEvent $command
     *
     * @return CultureFeed_Uitpas_Event_CultureEvent
     */
    private function buildEvent(SyncUiTPASEvent $command)
    {
        $event = new CultureFeed_Uitpas_Event_CultureEvent();
        $event->cdbid = $command->getEventId();
        $event->organiserId = $command->getOrganizerId();

        $event->postPriceNames = [];
        $event->postPriceValues = [];

        $distributionKeys = $command->getDistributionKeyIds();
        $event->distributionKey = array_map(
            function ($keyId) {
                $key = new CultureFeed_Uitpas_DistributionKey();
                $key->id = $keyId;

                return $key;
            },
            $distributionKeys
        );

        $prices = $command->getPriceInfo();

        $event->postPriceValues[] = $prices->getBasePrice()->getPrice(
        )->toFloat();
        $event->postPriceNames[] = 'Basisprijs';

        foreach ($prices->getTariffs() as $tariff) {
            $event->postPriceNames[] = $tariff->getName()->toNative();
            $event->postPriceValues[] = $tariff->getPrice()->toFloat();
        }

        return $event;
    }

    /**
     * @param CultureFeed_Uitpas_Event_CultureEvent $event
     * @param array $strategies
     */
    private function syncEvent(
        CultureFeed_Uitpas_Event_CultureEvent $event,
        array $strategies
    ) {
        /** @var Exception[] $exceptions */
        $exceptions = [];
        $succeeded = false;

        foreach ($strategies as $strategy) {
            try {
                call_user_func(
                    array($this->culturefeedClient, $strategy),
                    $event
                );

                $succeeded = true;
                break;
            } catch (Exception $e) {
                $exceptions[] = $e;
            }
        }

        if (!$succeeded) {
            $this->logger->error(
                'Unable to synchronise uitpas event data',
                [
                    'cdbid' => $event->cdbid,
                ]
            );
            foreach ($exceptions as $exception) {
                $this->logger->error(
                    $exception->getMessage(),
                    [
                        'exception' => $exception,
                    ]
                );
            }
        } else {
            $this->logger->info(
                'Succesfully synchronised uitpas event data',
                [
                    'cdbid' => $event->cdbid,
                ]
            );
        }
    }
}
