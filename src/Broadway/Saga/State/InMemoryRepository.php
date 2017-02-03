<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;

/**
 * Copied from Broadway\Saga\State\InMemoryRepository and modified
 * to use findBy() instead of findOneBy().
 */
class InMemoryRepository implements RepositoryInterface
{
    private $states = [];

    /**
     * {@inheritDoc}
     */
    public function findBy(Criteria $criteria, $sagaId, $excludeRemoved = true)
    {
        if (!isset($this->states[$sagaId])) {
            $states = [];
        } else {
            $states = $this->states[$sagaId];
        }

        foreach ($criteria->getComparisons() as $key => $value) {
            $states = array_filter(
                $states,
                function ($elem) use ($key, $value, $excludeRemoved) {
                    /** @var State $elem */
                    if ($excludeRemoved && $elem->isDone()) {
                        return false;
                    }

                    $stateValue = $elem->get($key);
                    return is_array($stateValue) ? in_array($value, $stateValue) : $value === $stateValue;
                }
            );
        }

        foreach ($states as $state) {
            yield $state;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function save(State $state, $sagaId)
    {
        $this->states[$sagaId][$state->getId()] = $state;
    }
}
