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
use Spryker\Client\StorageDatabase\Connection\ConnectionProviderInterface;
use Spryker\Client\StorageDatabase\StorageTableNameResolver\StorageTableNameResolverInterface;
use SprykerTest\Client\StorageDatabase\Sqlite\SqliteStorageReader;

/**
 * Drives the SQLite reader against a real in-memory SQLite database rather than a mocked
 * connection: the point of this reader is its SQL dialect, and a mock would assert the string
 * instead of the behaviour.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Storage
 * @group SqliteStorageReaderTest
 * Add your own group annotations below this line
 */
class SqliteStorageReaderTest extends Unit
{
    protected const string TABLE_NAME = 'spy_test_storage';

    protected const string RESOURCE_KEY = 'test:de_de:1';

    protected const string ALIAS_RESOURCE_KEY = 'test:de_de:alias-slug';

    protected const string SECOND_RESOURCE_KEY = 'test:de_de:2';

    protected const string MISSING_RESOURCE_KEY = 'test:de_de:absent';

    protected const string RESOURCE_DATA = '{"id":1,"name":"first"}';

    protected const string SECOND_RESOURCE_DATA = '{"id":2,"name":"second"}';

    protected ConnectionInterface $connection;

    protected function _before(): void
    {
        // The reader takes a Propel connection, not a bare PDO: only the wrapper hands back
        // statements that satisfy Propel's StatementInterface.
        $this->connection = new ConnectionWrapper(new PdoConnection('sqlite::memory:'));
        $this->connection->exec(sprintf(
            'CREATE TABLE %s (`key` VARCHAR(255), data TEXT, alias_keys TEXT)',
            static::TABLE_NAME,
        ));

        $this->insertRow(static::RESOURCE_KEY, static::RESOURCE_DATA, json_encode([
            static::ALIAS_RESOURCE_KEY => static::RESOURCE_DATA,
        ]));
        $this->insertRow(static::SECOND_RESOURCE_KEY, static::SECOND_RESOURCE_DATA, null);
    }

    public function testGivenAResourceKeyWhenTheRowIsReadThenItsDataIsReturned(): void
    {
        // Act
        $resourceData = $this->createReader()->get(static::RESOURCE_KEY);

        // Assert
        $this->assertSame(static::RESOURCE_DATA, $resourceData);
    }

    public function testGivenAnAliasKeyWhenTheRowIsReadThenTheAliasedDataIsReturned(): void
    {
        // Act
        $resourceData = $this->createReader()->get(static::ALIAS_RESOURCE_KEY);

        // Assert
        $this->assertSame(static::RESOURCE_DATA, $resourceData);
    }

    public function testGivenAnUnknownKeyWhenTheRowIsReadThenAnEmptyStringIsReturned(): void
    {
        // Act
        $resourceData = $this->createReader()->get(static::MISSING_RESOURCE_KEY);

        // Assert
        $this->assertSame('', $resourceData);
    }

    public function testGivenSeveralKeysWhenTheyAreReadAtOnceThenEachResolvesToItsOwnData(): void
    {
        // Act
        $resourceData = $this->createReader()->getMulti([
            static::RESOURCE_KEY,
            static::SECOND_RESOURCE_KEY,
            static::MISSING_RESOURCE_KEY,
        ]);

        // Assert
        $this->assertSame(
            [
                static::RESOURCE_KEY => static::RESOURCE_DATA,
                static::SECOND_RESOURCE_KEY => static::SECOND_RESOURCE_DATA,
            ],
            $resourceData,
        );
    }

    protected function insertRow(string $key, string $data, ?string $aliasKeys): void
    {
        $statement = $this->connection->prepare(sprintf(
            'INSERT INTO %s (`key`, data, alias_keys) VALUES (:key, :data, :alias_keys)',
            static::TABLE_NAME,
        ));
        $statement->bindValue(':key', $key);
        $statement->bindValue(':data', $data);
        $statement->bindValue(':alias_keys', $aliasKeys);
        $statement->execute();
    }

    protected function createReader(): SqliteStorageReader
    {
        $connectionProviderMock = $this->createMock(ConnectionProviderInterface::class);
        $connectionProviderMock->method('getConnection')->willReturn($this->connection);

        $tableNameResolverMock = $this->createMock(StorageTableNameResolverInterface::class);
        $tableNameResolverMock->method('resolveByResourceKey')->willReturn(static::TABLE_NAME);

        return new SqliteStorageReader($connectionProviderMock, $tableNameResolverMock);
    }
}
