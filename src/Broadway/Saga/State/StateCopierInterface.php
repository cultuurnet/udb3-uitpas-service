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
}
