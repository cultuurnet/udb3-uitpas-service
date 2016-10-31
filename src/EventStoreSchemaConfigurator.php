<?php
/**
 * @file
 */

namespace CultuurNet\UDB3\UiTPASService;

use Broadway\EventStore\DBALEventStore;
use CultuurNet\UDB3\Doctrine\DBAL\SchemaConfiguratorInterface;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class EventStoreSchemaConfigurator implements SchemaConfiguratorInterface
{
    /**
     * @var DBALEventStore
     */
    private $eventStore;

    public function __construct(DBALEventStore $eventStore)
    {
        $this->eventStore = $eventStore;
    }

    public function configure(AbstractSchemaManager $schemaManager)
    {
        $schema = $schemaManager->createSchema();
        
        $table = $this->eventStore->configureSchema($schema);
        
        if ($table) {
            $schemaManager->createTable($table);
        }
    }

}
