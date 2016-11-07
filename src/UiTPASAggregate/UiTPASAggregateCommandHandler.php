<?php

namespace CultuurNet\UDB3\UiTPASService\UiTPASAggregate;

use Broadway\CommandHandling\CommandHandlerInterface;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\ClearDistributionKeys;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\CreateUiTPASAggregate;
use CultuurNet\UDB3\UiTPASService\UiTPASAggregate\Command\UpdateDistributionKeys;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class UiTPASAggregateCommandHandler implements CommandHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @param RepositoryInterface $repository
     */
    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->logger = new NullLogger();
    }

    /**
     * @param mixed $command
     *
     * @uses handleCreateUiTPASAggregate
     * @uses handleUpdateDistributionKeys
     * @uses handleClearDistributionKeys
     */
    public function handle($command)
    {
        $handlers = [
            CreateUiTPASAggregate::class => 'handleCreateUiTPASAggregate',
            UpdateDistributionKeys::class => 'handleUpdateDistributionKeys',
            ClearDistributionKeys::class => 'handleClearDistributionKeys',
        ];

        if (array_key_exists(get_class($command), $handlers)) {
            $handler = $handlers[get_class($command)];
            $this->logger->info('UiTPASAggregateCommandHandler: handling UiTPAS command: ' . get_class($command));
            $this->{$handler}($command);
        }
    }

    /**
     * @param CreateUiTPASAggregate $createUiTPASAggregate
     */
    private function handleCreateUiTPASAggregate(CreateUiTPASAggregate $createUiTPASAggregate)
    {
        $aggregate = UiTPASAggregate::create(
            $createUiTPASAggregate->getEventId(),
            $createUiTPASAggregate->getDistributionKeyIds()
        );

        $this->repository->save($aggregate);
    }

    /**
     * @param UpdateDistributionKeys $updateDistributionKeys
     */
    private function handleUpdateDistributionKeys(UpdateDistributionKeys $updateDistributionKeys)
    {
        $this->logger->info(
            'UiTPASAggregateCommandHandler:' .
            ' updating distribution keys for event with id "'. $updateDistributionKeys->getEventId() . '"',
            ['keys' => $updateDistributionKeys->getDistributionKeyIds()]
        );

        $aggregate = $this->loadAggregate($updateDistributionKeys->getEventId());
        $aggregate->updateDistributionKeys($updateDistributionKeys->getDistributionKeyIds());
        $this->repository->save($aggregate);
    }

    /**
     * @param ClearDistributionKeys $clearDistributionKeys
     */
    private function handleClearDistributionKeys(ClearDistributionKeys $clearDistributionKeys)
    {
        $aggregate = $this->loadAggregate($clearDistributionKeys->getEventId());
        $aggregate->clearDistributionKeys();
        $this->repository->save($aggregate);
    }

    /**
     * @param string $aggregateId
     * @return UiTPASAggregate
     */
    private function loadAggregate($aggregateId)
    {
        /* @var UiTPASAggregate $aggregate */
        $aggregate = $this->repository->load($aggregateId);
        return $aggregate;
    }
}
