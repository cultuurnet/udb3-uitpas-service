<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;

interface StateCopierInterface
{
    /**
     * Create a copy with new id.
     *
     * @param State $state
     * @return State
     */
    public function copy(State $state);

    /**
     * Create a copy with new id and applied values.
     *
     * @param State $state
     * @param array $values
     * @return State
     */
    public function copyWithValues(State $state, array $values);
}
