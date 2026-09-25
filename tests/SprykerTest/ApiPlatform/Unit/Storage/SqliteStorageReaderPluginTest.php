<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Unit\Storage;

use Codeception\Test\Unit;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\PdoConnection;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use Spryker\Client\StorageDatabase\Storage\Reader\StorageReaderInterface;
use SprykerTest\Client\StorageDatabase\Sqlite\SqliteStorageReaderPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Storage
 * @group SqliteStorageReaderPluginTest
 * Add your own group annotations below this line
 */
class SqliteStorageReaderPluginTest extends Unit
{
    protected const string RESOURCE_KEY = 'store:de';

    protected const string RESOURCE_DATA = '{"name":"DE"}';

    protected const string RESOURCE_TABLE_NAME = 'spy_store_storage';

    protected const string SQLITE_ADAPTER = 'sqlite';

    protected ServiceContainerInterface $originalServiceContainer;

    protected function _before(): void
    {
        $this->originalServiceContainer = Propel::getServiceContainer();
    }

    protected function _after(): void
    {
        Propel::setServiceContainer($this->originalServiceContainer);
    }

    public function testGivenAReaderWhenASingleKeyIsRequestedThenTheCallIsDelegatedToIt(): void
    {
        // Arrange
        $storageReaderMock = $this->createMock(StorageReaderInterface::class);
        $storageReaderMock->expects($this->once())
            ->method('get')
            ->with(static::RESOURCE_KEY)
            ->willReturn(static::RESOURCE_DATA);

        // Act
        $resourceData = (new SqliteStorageReaderPlugin($storageReaderMock))->get(static::RESOURCE_KEY);

        // Assert
        $this->assertSame(static::RESOURCE_DATA, $resourceData);
    }

    public function testGivenAReaderWhenSeveralKeysAreRequestedThenTheCallIsDelegatedToIt(): void
    {
        // Arrange
        $storageReaderMock = $this->createMock(StorageReaderInterface::class);
        $storageReaderMock->expects($this->once())
            ->method('getMulti')
            ->with([static::RESOURCE_KEY])
            ->willReturn([static::RESOURCE_KEY => static::RESOURCE_DATA]);

        // Act
        $resourceData = (new SqliteStorageReaderPlugin($storageReaderMock))->getMulti([static::RESOURCE_KEY]);

        // Assert
        $this->assertSame([static::RESOURCE_KEY => static::RESOURCE_DATA], $resourceData);
    }

    public function testGivenNoReaderWhenAKeyIsRequestedThenItIsReadThroughTheOwnSqliteReader(): void
    {
        // Arrange
        $this->givenTheProcessIsOnASqliteConnectionCarryingTheResourceRow();

        // Act
        $resourceData = (new SqliteStorageReaderPlugin())->get(static::RESOURCE_KEY);

        // Assert
        $this->assertSame(static::RESOURCE_DATA, $resourceData);
    }

    public function testGivenNoReaderWhenSeveralKeysAreRequestedThenTheyAreReadThroughTheOwnSqliteReader(): void
    {
        // Arrange
        $this->givenTheProcessIsOnASqliteConnectionCarryingTheResourceRow();

        // Act
        $resourceData = (new SqliteStorageReaderPlugin())->getMulti([static::RESOURCE_KEY]);

        // Assert
        $this->assertSame([static::RESOURCE_KEY => static::RESOURCE_DATA], $resourceData);
    }

    protected function givenTheProcessIsOnASqliteConnectionCarryingTheResourceRow(): void
    {
        $connection = new ConnectionWrapper(new PdoConnection('sqlite::memory:'));
        $connection->exec(sprintf(
            'CREATE TABLE %s (`key` VARCHAR(255), data TEXT, alias_keys TEXT)',
            static::RESOURCE_TABLE_NAME,
        ));

        $statement = $connection->prepare(sprintf(
            'INSERT INTO %s (`key`, data, alias_keys) VALUES (:key, :data, NULL)',
            static::RESOURCE_TABLE_NAME,
        ));
        $statement->bindValue(':key', static::RESOURCE_KEY);
        $statement->bindValue(':data', static::RESOURCE_DATA);
        $statement->execute();

        $this->givenPropelHandsOutTheConnection($connection);
    }

    protected function givenPropelHandsOutTheConnection(ConnectionInterface $connection): void
    {
        $serviceContainer = new StandardServiceContainer();
        $serviceContainer->setAdapterClass(
            ServiceContainerInterface::DEFAULT_DATASOURCE_NAME,
            static::SQLITE_ADAPTER,
        );
        $serviceContainer->setConnection(ServiceContainerInterface::DEFAULT_DATASOURCE_NAME, $connection);

        Propel::setServiceContainer($serviceContainer);
    }
}
