<?php

namespace CultuurNet\UDB3\UiTPASService\Broadway\Saga\Metadata;

use Broadway\Saga\Metadata\MetadataFactoryInterface;
use Broadway\Saga\Metadata\StaticallyConfiguredSagaInterface;

/**
 * Copied from Broadway\Saga\Metadata\StaticallyConfiguredSagaMetadataFactory
 * and modified to return an instance of Metadata.
 */
class StaticallyConfiguredSagaMetadataFactory implements MetadataFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create($saga)
    {
        if (!($saga instanceof StaticallyConfiguredSagaInterface)) {
            throw new \RuntimeException(
                sprintf('Provided saga of class %s must implement %s', $saga, StaticallyConfiguredSagaInterface::class)
            );
        }

        $criteria = $saga::configuration();

        return new Metadata($criteria);
    }
}
