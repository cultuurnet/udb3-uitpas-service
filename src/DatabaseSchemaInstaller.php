<?php

namespace CultuurNet\UDB3\UiTPASService;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use CultuurNet\UDB3\Doctrine\DBAL\SchemaConfiguratorInterface;

/**
 * Database schema installer for the UiTPAS service.
 *
 * @todo Refactor this into something generic.
 * @see https://jira.uitdatabank.be/browse/III-1304
 */
class DatabaseSchemaInstaller
{
    /**
     * @var SchemaConfiguratorInterface[]
     */
    protected $schemaConfigurators;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Configuration
     */
    private $migrations;

    public function __construct(Connection $connection, Configuration $migrations)
    {
        $this->connection = $connection;
        $this->migrations = $migrations;

        $this->schemaConfigurators = [];
    }

    public function addSchemaConfigurator(
        SchemaConfiguratorInterface $schemaConfigurator
    ) {
        $this->schemaConfigurators[] = $schemaConfigurator;
    }

    public function installSchema()
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->connection;

        $schemaManager = $connection->getSchemaManager();

        foreach ($this->schemaConfigurators as $configurator) {
            $configurator->configure($schemaManager);
        }

        $this->markVersionsMigrated();
    }

    private function markVersionsMigrated()
    {
        foreach ($this->migrations->getAvailableVersions(
        ) as $versionIdentifier) {
            $version = $this->migrations->getVersion($versionIdentifier);

            $version->markMigrated();
        }
    }
}
