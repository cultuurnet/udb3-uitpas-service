<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga;

use Broadway\Domain\DomainMessage;
use Broadway\EventDispatcher\EventDispatcherInterface;
use Broadway\Saga\Metadata\MetadataFactoryInterface;
use Broadway\Saga\SagaInterface;
use Broadway\Saga\SagaManagerInterface;
use Broadway\Saga\State;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\State\RepositoryInterface;
use CultuurNet\UDB3\UiTPASService\Broadway\Saga\State\StateManagerInterface;

/**
 * SagaManager that manages multiple sagas with multiple states.
 * Copied and adjusted from \Broadway\Saga\MultipleSagaManager.
 */
class MultipleSagaManager implements SagaManagerInterface
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var array
     */
    private $sagas;

    /**
     * @var StateManagerInterface
     */
    private $stateManager;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param RepositoryInterface $repository
     * @param array $sagas
     * @param StateManagerInterface $stateManager
     * @param MetadataFactoryInterface $metadataFactory
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        RepositoryInterface $repository,
        array $sagas,
        StateManagerInterface $stateManager,
        MetadataFactoryInterface $metadataFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->repository      = $repository;
        $this->sagas           = $sagas;
        $this->stateManager    = $stateManager;
        $this->metadataFactory = $metadataFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Handles the event by delegating it to Saga('s) related to the event.
     * @param DomainMessage $domainMessage
     */
    public function handle(DomainMessage $domainMessage)
    {
        $event = $domainMessage->getPayload();

        foreach ($this->sagas as $sagaType => $saga) {
            $metadata = $this->metadataFactory->create($saga);

            if (!$metadata->handles($event)) {
                continue;
            }

            $criteria = $metadata->criteria($event);

            if (is_null($criteria)) {
                // If the saga returns null as criteria, it wants to handle the
                // event with a new state.
                $state = $this->stateManager->generateNewState();
                $this->handleEventBySagaWithState($sagaType, $saga, $event, $state);
            } else {
                // If actual criteria are given, fetch all matching states and
                // update them one by one.
                foreach ($this->stateManager->findBy($criteria, $sagaType) as $state) {
                    $this->handleEventBySagaWithState($sagaType, $saga, $event, $state);
                }
            }
        }
    }

    /**
     * @param string $sagaType
     * @param SagaInterface $saga
     * @param mixed $event
     * @param State $state
     */
    private function handleEventBySagaWithState($sagaType, SagaInterface $saga, $event, State $state)
    {
        $this->eventDispatcher->dispatch(
            SagaManagerInterface::EVENT_PRE_HANDLE,
            [$sagaType, $state->getId()]
        );

        $newState = $saga->handle($event, $state);

        $this->eventDispatcher->dispatch(
            SagaManagerInterface::EVENT_POST_HANDLE,
            [$sagaType, $state->getId()]
        );

        $this->repository->save($newState, $sagaType);
    }
}
