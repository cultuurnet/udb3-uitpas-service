<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UiTPASService\Command;

use Broadway\CommandHandling\CommandHandler;
use CultureFeed_Uitpas;
use CultureFeed_Uitpas_DistributionKey;
use CultureFeed_Uitpas_Event_CultureEvent;
use Exception;

class RemotelySyncUiTPASCommandHandler extends CommandHandler
{
    const UPDATE = 'update';
    const REGISTER = 'register';

    /**
     * @var CultureFeed_Uitpas
     */
    private $culturefeedClient;

    public function __construct(CultureFeed_Uitpas $culturefeedClient)
    {
        $this->culturefeedClient = $culturefeedClient;
    }

    /**
     * @var RemotelySyncUiTPASEvent $command
     *
     * @return CultureFeed_Uitpas_Event_CultureEvent
     */
    private function buildEvent(RemotelySyncUiTPASEvent $command)
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

    private function syncEvent(
        CultureFeed_Uitpas_Event_CultureEvent $event,
        array $strategies
    ) {
        $lastException = null;

        foreach ($strategies as $strategy) {
            try {
                call_user_func(
                    array($this->culturefeedClient, $strategy)
                );
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        if ($lastException) {
            throw $lastException;
        }
    }

    protected function handleRemotelyRegisterUiTPASEvent(
        RemotelyRegisterUiTPASEvent $command
    ) {
        $event = $this->buildEvent($command);
        $this->syncEvent($event, ['registerEvent', 'updateEvent']);
    }

    protected function handleRemotelyUpdateUiTPASEvent(
        RemotelyUpdateUiTPASEvent $command
    ) {
        $event = $this->buildEvent($command);
        $this->syncEvent($event, ['updateEvent', 'registerEvent']);
    }
}
