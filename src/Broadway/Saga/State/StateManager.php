<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\UuidGenerator\UuidGeneratorInterface;

/**
 * Copied from Broadway\Saga\State\StateManager to use findBy() instead of
 * findOneBy(), and add generateNewState().
 */
class StateManager implements StateManagerInterface
{
    private $repository;
    private $generator;

    public function __construct(RepositoryInterface $repository, UuidGeneratorInterface $generator)
    {
        $this->repository = $repository;
        $this->generator  = $generator;
    }

    /**
     * {@inheritDoc}
     */
    public function findBy($criteria, $sagaId)
    {
        // @todo Use "yield from" when minimum requirement is PHP7.
        foreach ($this->repository->findBy($criteria, $sagaId) as $state) {
            yield $state;
        }
    }

    /**
     * @return State
     */
    public function generateNewState()
    {
        return new State($this->generator->generate());
    }
}
