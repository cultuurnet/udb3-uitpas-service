<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\State;

use Broadway\Saga\State;
use Broadway\Saga\State\Criteria;
use Doctrine\MongoDB\Collection;
use Doctrine\MongoDB\Query\Query;

/**
 * Copied from Broadway\Saga\State\MongoDBRepository and updated
 * to implement findBy() instead of findOneBy().
 */
class MongoDBRepository implements RepositoryInterface
{
    private $collection;

    /**
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function findBy(Criteria $criteria, $sagaId)
    {
        $query   = $this->createQuery($criteria, $sagaId);
        $results = $query->execute();

        foreach ($results as $result) {
            yield State::deserialize($result);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function save(State $state, $sagaId)
    {
        $serializedState            = $state->serialize();
        $serializedState['_id']     = $serializedState['id'];
        $serializedState['sagaId']  = $sagaId;
        $serializedState['removed'] = $state->isDone();

        $this->collection->save($serializedState);
    }

    /**
     * @param Criteria $criteria
     * @param string $sagaId
     * @return Query
     */
    private function createQuery(Criteria $criteria, $sagaId)
    {
        $comparisons = $criteria->getComparisons();
        $wheres      = [];

        foreach ($comparisons as $key => $value) {
            $wheres['values.' . $key] = $value;
        }

        $queryBuilder = $this->collection->createQueryBuilder()
            ->addAnd($wheres)
            ->addAnd(['removed' => false, 'sagaId' => $sagaId]);

        return $queryBuilder->getQuery();
    }
}
