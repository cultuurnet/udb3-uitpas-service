<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;

interface StateCopierInterface
{
    /**
     * Create a copy with new id.
     * When the state was done, the copied state will no longer be done.
     *
     * @param State $state
     * @return State
     */
    public function copy(State $state);
}
