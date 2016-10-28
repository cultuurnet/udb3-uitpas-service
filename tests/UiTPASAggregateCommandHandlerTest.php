<?php

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\CommandHandling\CommandHandlerInterface;
use Broadway\CommandHandling\Testing\CommandHandlerScenarioTestCase;
use Broadway\EventHandling\EventBusInterface;
use Broadway\EventStore\EventStoreInterface;
use CultuurNet\UDB3\UiTPASService\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\Command\UpdateDistributionKeys;
use CultuurNet\UDB3\UiTPASService\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\Event\UiTPASAggregateCreated;

class UiTPASAggregateCommandHandlerTest extends CommandHandlerScenarioTestCase
{
    /**
     * @var string
     */
    private $eventId;

    /**
     * @var string[]
     */
    private $distributionKeyIds;

    /**
     * @var UiTPASAggregateCreated
     */
    private $uitpasAggregateCreated;

    public function setUp()
    {
        parent::setUp();

        $this->eventId = 'f254b13a-94b2-4c7f-aa36-c7500b542998';

        $this->distributionKeyIds = [
            'distribution-key-123',
            'distribution-key-456',
        ];

        $this->uitpasAggregateCreated = new UiTPASAggregateCreated($this->eventId, $this->distributionKeyIds);
    }

    /**
     * @param EventStoreInterface $eventStore
     * @param EventBusInterface $eventBus
     * @return CommandHandlerInterface
     */
    protected function createCommandHandler(EventStoreInterface $eventStore, EventBusInterface $eventBus)
    {
        return new UiTPASAggregateCommandHandler(
            new UiTPASAggregateRepository(
                $eventStore,
                $eventBus
            )
        );
    }

    /**
     * @test
     */
    public function it_creates_a_new_uitpas_aggregate()
    {
        $this->scenario
            ->when(
                new CreateUiTPASAggregate(
                    $this->eventId,
                    $this->distributionKeyIds
                )
            )
            ->then([$this->uitpasAggregateCreated]);
    }

    /**
     * @test
     */
    public function it_updates_the_distribution_keys_if_they_have_changed()
    {
        $updatedDistributionKeyIds = $this->distributionKeyIds;
        $updatedDistributionKeyIds[] = 'distribution-key-798';

        $this->scenario
            ->withAggregateId($this->eventId)
            ->given(
                [$this->uitpasAggregateCreated]
            )
            ->when(
                new UpdateDistributionKeys($this->eventId, $this->distributionKeyIds)
            )
            ->then([])
            ->when(
                new UpdateDistributionKeys($this->eventId, $updatedDistributionKeyIds)
            )
            ->then([new DistributionKeysUpdated($this->eventId, $updatedDistributionKeyIds)]);
    }

    /**
     * @test
     */
    public function it_clears_the_distribution_keys_if_they_are_not_empty_yet()
    {
        $this->scenario
            ->withAggregateId($this->eventId)
            ->given(
                [$this->uitpasAggregateCreated]
            )
            ->when(
                new ClearDistributionKeys($this->eventId)
            )
            ->then([new DistributionKeysCleared($this->eventId)]);
    }

    /**
     * @test
     */
    public function it_does_not_clear_the_distribution_keys_if_they_are_already_empty()
    {
        $this->scenario
            ->withAggregateId($this->eventId)
            ->given(
                [
                    $this->uitpasAggregateCreated,
                    new DistributionKeysCleared($this->eventId),
                ]
            )
            ->when(
                new ClearDistributionKeys($this->eventId)
            )
            ->then([]);
    }
}
