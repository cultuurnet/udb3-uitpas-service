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
        $stateAsArray['id'] = $this->generator->generate();

        return State::deserialize($stateAsArray);
    }

    /**
     * Create a copy with new id and applied values.
     *
     * @param State $state
     * @param array $values
     * @return State
     */
    public function copyWithValues(State $state, array $values)
    {
        $copiedState = $this->copy($state);

        foreach ($values as $key => $value) {
            $copiedState->set($key, $value);
        }

        return $copiedState;
    }
}
