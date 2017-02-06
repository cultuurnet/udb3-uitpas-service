<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\UuidGenerator\UuidGeneratorInterface;

class StateCopier implements StateCopierInterface
{
    /**
     * @var UuidGeneratorInterface
     */
    private $generator;

    /**
     * StateCopier constructor.
     * @param UuidGeneratorInterface $generator
     */
    public function __construct(UuidGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Create a copy with new id.
     *
     * @param State $state
     * @return State
     */
    public function copy(State $state)
    {
        $stateAsArray = $state->serialize();
        $stateAsArray['done'] = false;
        $stateAsArray['id'] = $this->generator->generate();

        return State::deserialize($stateAsArray);
    }
}
