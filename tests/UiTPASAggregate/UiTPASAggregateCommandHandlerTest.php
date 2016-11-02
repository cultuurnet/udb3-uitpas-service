<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate;

use Broadway\CommandHandling\CommandHandlerInterface;
use Broadway\CommandHandling\Testing\CommandHandlerScenarioTestCase;
use Broadway\EventHandling\EventBusInterface;
use Broadway\EventStore\EventStoreInterface;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\UpdateDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysCleared;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\DistributionKeysUpdated;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Event\UiTPASAggregateCreated;

class UiTPASAggregateCommandHandlerTest extends CommandHandlerScenarioTestCase
{
    /**
     * @var string
     */
    private $aggregateId;

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

        $this->aggregateId = 'f254b13a-94b2-4c7f-aa36-c7500b542998';

        $this->distributionKeyIds = [
            'distribution-key-123',
            'distribution-key-456',
        ];

        $this->uitpasAggregateCreated = new UiTPASAggregateCreated($this->aggregateId, $this->distributionKeyIds);
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
                    $this->aggregateId,
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
            ->withAggregateId($this->aggregateId)
            ->given(
                [$this->uitpasAggregateCreated]
            )
            ->when(
                new UpdateDistributionKeys($this->aggregateId, $this->distributionKeyIds)
            )
            ->then([])
            ->when(
                new UpdateDistributionKeys($this->aggregateId, $updatedDistributionKeyIds)
            )
            ->then([new DistributionKeysUpdated($this->aggregateId, $updatedDistributionKeyIds)]);
    }

    /**
     * @test
     */
    public function it_clears_the_distribution_keys_if_they_are_not_empty_yet()
    {
        $this->scenario
            ->withAggregateId($this->aggregateId)
            ->given(
                [$this->uitpasAggregateCreated]
            )
            ->when(
                new ClearDistributionKeys($this->aggregateId)
            )
            ->then([new DistributionKeysCleared($this->aggregateId)]);
    }

    /**
     * @test
     */
    public function it_does_not_clear_the_distribution_keys_if_they_are_already_empty()
    {
        $this->scenario
            ->withAggregateId($this->aggregateId)
            ->given(
                [
                    $this->uitpasAggregateCreated,
                    new DistributionKeysCleared($this->aggregateId),
                ]
            )
            ->when(
                new ClearDistributionKeys($this->aggregateId)
            )
            ->then([]);
    }
}
