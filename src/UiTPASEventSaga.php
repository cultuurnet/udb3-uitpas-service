<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;
use Broadway\Saga\Saga;
use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use CultuurNet\UDB3\Cdb\CdbId\EventCdbIdExtractorInterface;
use CultuurNet\UDB3\Cdb\EventItemFactory;
use CultuurNet\UDB3\Cdb\PriceDescriptionParser;
use CultuurNet\UDB3\Event\Events\EventCreated;
use CultuurNet\UDB3\Event\Events\EventImportedFromUDB2;
use CultuurNet\UDB3\Event\Events\EventUpdatedFromUDB2;
use CultuurNet\UDB3\Event\Events\OrganizerDeleted;
use CultuurNet\UDB3\Event\Events\OrganizerUpdated;
use CultuurNet\UDB3\Event\Events\PriceInfoUpdated;
use CultuurNet\UDB3\Label;
use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Offer\Events\AbstractEvent;
use CultuurNet\UDB3\Organizer\Events\LabelAdded;
use CultuurNet\UDB3\PriceInfo\BasePrice;
use CultuurNet\UDB3\PriceInfo\Price;
use CultuurNet\UDB3\PriceInfo\PriceInfo;
use CultuurNet\UDB3\PriceInfo\Tariff;
use CultuurNet\UDB3\UiTPASService\OrganizerLabelReadRepository\OrganizerLabelReadRepositoryInterface;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\Sync\Command\RegisterUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\Sync\Command\UpdateUiTPASEvent;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\AbstractUiTPASAggregateEvent;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\UiTPASAggregateCreated;
use CultuurNet\UDB3\UiTPASService\Specification\OrganizerSpecificationInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use ValueObjects\Money\Currency;
use ValueObjects\StringLiteral\StringLiteral;

class UiTPASEventSaga extends Saga implements StaticallyConfiguredSagaInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    /**
     * @var EventCdbIdExtractorInterface
     */
    private $eventCdbIdExtractor;

    /**
     * @var PriceDescriptionParser
     */
    private $priceDescriptionParser;

    /**
     * @var \CultuurNet\UDB3\LabelCollection
     */
    private $uitpasLabels;

    /**
     * @var OrganizerLabelReadRepositoryInterface
     */
    private $organizerLabelRepository;

    /**
     * @param CommandBusInterface $commandBus
     * @param EventCdbIdExtractorInterface $eventCdbIdExtractor
     * @param PriceDescriptionParser $priceDescriptionParser
     * @param LabelCollection $uitpasLabels
     * @param OrganizerLabelReadRepositoryInterface $organizerLabelRepository
     */
    public function __construct(
        CommandBusInterface $commandBus,
        EventCdbIdExtractorInterface $eventCdbIdExtractor,
        PriceDescriptionParser $priceDescriptionParser,
        LabelCollection $uitpasLabels,
        OrganizerLabelReadRepositoryInterface $organizerLabelRepository
    ) {
        $this->commandBus = $commandBus;
        $this->eventCdbIdExtractor = $eventCdbIdExtractor;
        $this->priceDescriptionParser = $priceDescriptionParser;
        $this->uitpasLabels = $uitpasLabels;
        $this->organizerLabelRepository = $organizerLabelRepository;

        $this->logger = new NullLogger();
    }

    /**
     * @return array
     */
    public static function configuration()
    {
        $initialEventCallback = function () {
            return null;
        };

        $offerEventCallback = function (AbstractEvent $event) {
            return new Criteria(
                ['uitpasAggregateId' => $event->getItemId()]
            );
        };

        $uitpasAggregateEventCallback = function (AbstractUiTPASAggregateEvent $event) {
            return new Criteria(
                ['uitpasAggregateId' => $event->getAggregateId()]
            );
        };

        $updateFromUdb2Callback = function (EventUpdatedFromUDB2 $event) {
            return new Criteria(
                ['uitpasAggregateId' => (string) $event->getEventId()]
            );
        };

        $labelAddedToOrganizerCallback = function (LabelAdded $event) {
            return new Criteria(
                ['organizerId' => (string) $event->getOrganizerId()]
            );
        };

        return [
            EventCreated::class => $initialEventCallback,
            EventImportedFromUDB2::class => $initialEventCallback,
            EventUpdatedFromUDB2::class => $updateFromUdb2Callback,
            OrganizerUpdated::class => $offerEventCallback,
            OrganizerDeleted::class => $offerEventCallback,
            PriceInfoUpdated::class => $offerEventCallback,
            UiTPASAggregateCreated::class => $uitpasAggregateEventCallback,
            DistributionKeysUpdated::class => $uitpasAggregateEventCallback,
            DistributionKeysCleared::class => $uitpasAggregateEventCallback,
            LabelAdded::class => $labelAddedToOrganizerCallback,
        ];
    }

    /**
     * @param EventCreated $eventCreated
     * @param State $state
     * @return State
     */
    public function handleEventCreated(EventCreated $eventCreated, State $state)
    {
        $state->set('uitpasAggregateId', $eventCreated->getEventId());
        $state->set('syncCount', 0);
        return $state;
    }

    /**
     * @param EventImportedFromUDB2 $eventImportedFromUDB2
     * @param State $state
     * @return State
     */
    public function handleEventImportedFromUDB2(EventImportedFromUDB2 $eventImportedFromUDB2, State $state)
    {
        $state->set('uitpasAggregateId', $eventImportedFromUDB2->getEventId());
        $state->set('syncCount', 0);

        $state = $this->updateStateFromCdbXml(
            $state,
            $eventImportedFromUDB2->getCdbXml(),
            $eventImportedFromUDB2->getCdbXmlNamespaceUri()
        );

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param EventUpdatedFromUDB2 $eventUpdatedFromUDB2
     * @param State $state
     * @return State
     */
    public function handleEventUpdatedFromUDB2(EventUpdatedFromUDB2 $eventUpdatedFromUDB2, State $state)
    {
        $state = $this->updateStateFromCdbXml(
            $state,
            $eventUpdatedFromUDB2->getCdbXml(),
            $eventUpdatedFromUDB2->getCdbXmlNamespaceUri()
        );

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param OrganizerUpdated $organizerUpdated
     * @param State $state
     * @return State
     */
    public function handleOrganizerUpdated(OrganizerUpdated $organizerUpdated, State $state)
    {
        $isUitpasOrganizer = $this->isOrganizerTaggedWithUiTPASLabels($organizerUpdated->getOrganizerId());
        $state = $this->updateOrganizerState($organizerUpdated->getOrganizerId(), $isUitpasOrganizer, $state);

        $state = $this->triggerSyncWhenConditionsAreMet($state);

        if ($this->hasUitpasAggregateBeenCreated($state)) {
            // When the organizer is changed the selected distribution keys
            // become invalid so we should dispatch a command to correct this.
            // This command will trigger an extra sync afterwards IF any
            // changes occurred. (It's possible there were no distribution keys
            // selected to begin with so the aggregate will decide whether an
            // extra event is recorded or not following this command.)
            $this->commandBus->dispatch(
                new ClearDistributionKeys($organizerUpdated->getItemId())
            );
        }

        return $state;
    }

    /**
     * @param OrganizerDeleted $organizerDeleted
     * @param State $state
     * @return State
     */
    public function handleOrganizerDeleted(OrganizerDeleted $organizerDeleted, State $state)
    {
        $state = $this->resetOrganizerState($state);
        return $state;
    }

    /**
     * @param PriceInfoUpdated $priceInfoUpdated
     * @param State $state
     * @return State
     */
    public function handlePriceInfoUpdated(PriceInfoUpdated $priceInfoUpdated, State $state)
    {
        $state = $this->updatePriceInfoState($priceInfoUpdated->getPriceInfo(), $state);
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param UiTPASAggregateCreated $aggregateCreated
     * @param State $state
     * @return State
     */
    public function handleUiTPASAggregateCreated(UiTPASAggregateCreated $aggregateCreated, State $state)
    {
        $state->set('distributionKeyIds', $aggregateCreated->getDistributionKeyIds());
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param DistributionKeysUpdated $distributionKeysUpdated
     * @param State $state
     * @return State
     */
    public function handleDistributionKeysUpdated(DistributionKeysUpdated $distributionKeysUpdated, State $state)
    {
        $state->set('distributionKeyIds', $distributionKeysUpdated->getDistributionKeyIds());
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param DistributionKeysCleared $distributionKeysCleared
     * @param State $state
     * @return State
     */
    public function handleDistributionKeysCleared(DistributionKeysCleared $distributionKeysCleared, State $state)
    {
        $state->set('distributionKeyIds', []);
        $state = $this->triggerSyncWhenConditionsAreMet($state);
        return $state;
    }

    /**
     * @param LabelAdded $labelAdded
     * @param State $state
     * @return State
     */
    public function handleLabelAdded(LabelAdded $labelAdded, State $state)
    {
        // This is only relevant if the organizer is not yet an uitpas organizer.
        if (true === $state->get('uitpasOrganizer')) {
            return $state;
        }

        $logContext = [
            'uitpas_labels' => $this->mapLabelCollectionToStrings($this->uitpasLabels),
            'organizer' => $labelAdded->getOrganizerId(),
            'label' => (string) $labelAdded->getLabel(),
            'event' => $state->get('uitpasAggregateId'),
        ];

        if ($this->uitpasLabels->contains($labelAdded->getLabel())) {
            $this->logger->debug(
                'uitpas label was added to organizer',
                $logContext
            );
            $state = $this->updateOrganizerState($labelAdded->getOrganizerId(), true, $state);
        } else {
            $this->logger->debug(
                'label was added to organizer, but it is not an uitpas label',
                $logContext
            );
        }

        $this->triggerSyncWhenConditionsAreMet($state);

        return $state;
    }

    /**
     * @param State $state
     * @param string $cdbXml
     * @param string $cdbXmlNamespaceUri
     * @return State
     */
    private function updateStateFromCdbXml(State $state, $cdbXml, $cdbXmlNamespaceUri)
    {
        try {
            $event = EventItemFactory::createEventFromCdbXml($cdbXmlNamespaceUri, $cdbXml);
        } catch (\CultureFeed_Cdb_ParseException $e) {
            return $state;
        }

        $organizerCdbId = $this->eventCdbIdExtractor->getRelatedOrganizerCdbId($event);

        if (!is_null($organizerCdbId)) {
            $uitpasOrganizer = $this->isOrganizerTaggedWithUiTPASLabels($organizerCdbId);
            $state = $this->updateOrganizerState($organizerCdbId, $uitpasOrganizer, $state);
        }

        $state = $this->updatePriceInfoStateFromCdbEvent($event, $state);

        return $state;
    }

    /**
     * @param \CultureFeed_Cdb_Item_Event $cdbEvent
     * @param State $state
     * @return State
     */
    private function updatePriceInfoStateFromCdbEvent(\CultureFeed_Cdb_Item_Event $cdbEvent, State $state)
    {
        $eventDetailsList = $cdbEvent->getDetails();

        $details = $eventDetailsList->getDetailByLanguage('nl');
        if (empty($details) && !empty($eventDetailsList)) {
            $eventDetailsList->rewind();
            $details = $eventDetailsList->current();
        }

        if (empty($details) || empty($details->getPrice()) || is_null($details->getPrice()->getValue())) {
            // Do nothing if no price info is found on the event.
            return $state;
        }

        $cdbPrice = $details->getPrice()->getValue();
        $priceInfo = new PriceInfo(
            new BasePrice(
                Price::fromFloat((float) $cdbPrice),
                Currency::fromNative('EUR')
            )
        );

        $prices = $this->priceDescriptionParser->parse(
            $details->getPrice()->getDescription()
        );
        foreach ($prices as $key => $price) {
            if ($key !== 'Basistarief') {
                $priceInfo = $priceInfo->withExtraTariff(
                    new Tariff(
                        new StringLiteral($key),
                        Price::fromFloat($price),
                        Currency::fromNative('EUR')
                    )
                );
            }
        }

        // Always update the price info.
        // Even when less information is present or a wrong format was used in the description.
        $state = $this->updatePriceInfoState($priceInfo, $state);

        return $state;
    }

    /**
     * @param State $state
     * @return State
     */
    private function triggerSyncWhenConditionsAreMet(State $state)
    {
        $aggregateId = $state->get('uitpasAggregateId');
        $organizerId = $state->get('organizerId');
        $uitpasOrganizer = $state->get('uitpasOrganizer');
        $priceInfo = $this->getPriceInfoFromState($state);
        $distributionKeyIds = $state->get('distributionKeyIds');
        $syncCount = (int) $state->get('syncCount');

        if (is_null($organizerId) || empty($uitpasOrganizer) || is_null($priceInfo)) {
            return $state;
        }

        $distributionKeyIds = !is_null($distributionKeyIds) ? $distributionKeyIds : [];

        if ($syncCount == 0) {
            $this->commandBus->dispatch(
                new CreateUiTPASAggregate($aggregateId, $distributionKeyIds)
            );

            $syncCommand = new RegisterUiTPASEvent(
                $aggregateId,
                $organizerId,
                $priceInfo,
                $distributionKeyIds
            );
        } else {
            $syncCommand = new UpdateUiTPASEvent(
                $aggregateId,
                $organizerId,
                $priceInfo,
                $distributionKeyIds
            );
        }

        $this->commandBus->dispatch($syncCommand);

        $state->set('syncCount', $syncCount + 1);

        return $state;
    }

    /**
     * @param string $organizerId
     * $param bool $uitpasOrganizer
     * @param State $state
     * @return State
     */
    private function updateOrganizerState($organizerId, $uitpasOrganizer, State $state)
    {
        $state->set('organizerId', $organizerId);
        $state->set('uitpasOrganizer', $uitpasOrganizer);
        return $state;
    }

    private function isOrganizerTaggedWithUiTPASLabels($organizerId)
    {
        $labels = $this->organizerLabelRepository->getLabels($organizerId);

        $uitpasLabelsPresentOnOrganizer = $this->uitpasLabels->intersect($labels);

        $labelLogContext = [
            'organizer' => $organizerId,
            'uitpas_labels' => $this->mapLabelCollectionToStrings($this->uitpasLabels),
            'extracted_organizer_labels' => $this->mapLabelCollectionToStrings($labels),
            'organizer_uitpas_labels' => $this->mapLabelCollectionToStrings($uitpasLabelsPresentOnOrganizer),
        ];

        if (count($uitpasLabelsPresentOnOrganizer) > 0) {
            $this->logger->debug('uitpas labels present on organizer', $labelLogContext);
            return true;
        } else {
            $this->logger->debug('no uitpas labels present on organizer', $labelLogContext);
            return false;
        }
    }

    /**
     * @param State $state
     * @return State
     */
    private function resetOrganizerState(State $state)
    {
        $state->set('organizerId', null);
        $state->set('uitpasOrganizer', null);
        return $state;
    }

    /**
     * @param PriceInfo $priceInfo
     * @param State $state
     * @return State
     */
    private function updatePriceInfoState(PriceInfo $priceInfo, State $state)
    {
        $state->set('priceInfo', $priceInfo->serialize());
        return $state;
    }

    /**
     * @param State $state
     * @return PriceInfo|null
     */
    private function getPriceInfoFromState(State $state)
    {
        $serializedPriceInfo = $state->get('priceInfo');

        if (!is_null($serializedPriceInfo)) {
            return PriceInfo::deserialize($serializedPriceInfo);
        } else {
            return null;
        }
    }

    /**
     * @param State $state
     * @return bool
     */
    private function hasUitpasAggregateBeenCreated(State $state)
    {
        return !is_null($state->get('distributionKeyIds'));
    }

    /**
     * @param LabelCollection $labelCollection
     * @return string[]
     */
    private function mapLabelCollectionToStrings(LabelCollection $labelCollection)
    {
        return array_map(
            function (Label $label) {
                return (string) $label;
            },
            $labelCollection->asArray()
        );
    }
}
