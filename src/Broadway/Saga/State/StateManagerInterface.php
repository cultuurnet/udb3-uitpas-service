<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;

/**
 * Copied from Broadway\Saga\State\StateManagerInterface to use findBy() instead
 * of findOneBy().
 */
interface StateManagerInterface
{
    /**
     * @param null|Criteria $criteria
     * @param string $sagaId
     * @param bool $excludeRemoved
     * @return State[]|\Generator
     */
    public function findBy($criteria, $sagaId, $excludeRemoved = true);

    /**
     * @return State
     */
    public function generateNewState();
}
