<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;

/**
 * Copied from Broadway\Saga\State\RepositoryInterface and updated to use
 * findBy() instead of findOneBy().
 */
interface RepositoryInterface
{
    /**
     * @param Criteria $criteria
     * @param string $sagaId
     * @param bool $excludeRemoved
     * @return State[]|\Generator
     */
    public function findBy(Criteria $criteria, $sagaId, $excludeRemoved = true);

    /**
     * @param State $state
     * @param string $sagaId
     */
    public function save(State $state, $sagaId);
}
