<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Command;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Codeception\Test\Unit;
use org\bovigo\vfs\vfsStream;
use Spryker\ApiPlatform\Command\PermissionRegistryDumpCommand;
use Spryker\ApiPlatform\Metadata\PermissionCheckResourceMetadataCollectionFactory;
use Spryker\ApiPlatform\Security\Permission\OperationPermissionIdentifierResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Command
 * @group PermissionRegistryDumpCommandTest
 * Add your own group annotations below this line
 * @group SecurityAuthorization
 */
class PermissionRegistryDumpCommandTest extends Unit
{
    protected const string IDENTIFIER_CARTS_GET = 'api.storefront.carts.get';

    public function testGivenOneOperationWhenCommandRunsThenRegistryFileListsItWithBaseline(): void
    {
        // Arrange
        $root = vfsStream::setup('generated');
        $resource = new ApiResource(shortName: 'carts', operations: [
            'post' => (new Post(uriTemplate: '/carts', security: "is_granted('ROLE_CUSTOMER')"))->withShortName('carts')->withName('post'),
        ]);
        $nameFactory = $this->createNameFactory(['App\\Cart']);
        $metadataFactory = new PermissionCheckResourceMetadataCollectionFactory(
            $this->createMetadataFactory(['App\\Cart' => $resource]),
            $nameFactory,
            new OperationPermissionIdentifierResolver(),
            ['storefront'],
        );
        $command = new PermissionRegistryDumpCommand($nameFactory, $metadataFactory, ['storefront'], vfsStream::url('generated'));

        // Act
        $exitCode = (new CommandTester($command))->execute([]);

        // Assert
        $this->assertSame(0, $exitCode);
        $registry = json_decode((string)file_get_contents(vfsStream::url('generated/PermissionRegistry/Storefront.json')), true);
        $this->assertSame([[
            'identifier' => 'api.storefront.carts.post',
            'api_type' => 'storefront',
            'short_name' => 'carts',
            'operation_name' => 'post',
            'method' => 'POST',
            'uri_template' => '/carts',
            'baseline_security' => "is_granted('ROLE_CUSTOMER')",
            'ownership_owner_uri_variable' => null,
        ]], $registry);
        $this->assertFalse($root->hasChild('Storefront'));
    }

    public function testGivenOperationWithOwnershipDeclarationWhenCommandRunsThenRegistryEntryCarriesTheOwnerUriVariable(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $extraProperties = [
            OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER => 'api.storefront.customers.get',
            OperationPermissionIdentifierResolver::EXTRA_PROPERTY_OWNERSHIP => [
                'ownerUriVariable' => 'customerReference',
            ],
        ];
        $command = new PermissionRegistryDumpCommand(
            $this->createNameFactory(['App\\Customers']),
            $this->createMetadataFactory([
                'App\\Customers' => new ApiResource(operations: ['get' => (new Get(uriTemplate: '/customers/{customerReference}', extraProperties: $extraProperties))->withShortName('customers')]),
            ]),
            ['storefront'],
            vfsStream::url('generated'),
        );

        // Act
        $exitCode = (new CommandTester($command))->execute([]);

        // Assert
        $this->assertSame(0, $exitCode);
        $registry = json_decode((string)file_get_contents(vfsStream::url('generated/PermissionRegistry/Storefront.json')), true);
        $this->assertSame('customerReference', $registry[0]['ownership_owner_uri_variable']);
        $this->assertArrayNotHasKey('ownership_default_scopes', $registry[0]);
    }

    public function testGivenTwoEndpointsWithTheSameIdentifierWhenCommandRunsThenItFailsAndNamesTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $extraProperties = [OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER => 'api.storefront.orders.get-collection'];
        $command = new PermissionRegistryDumpCommand(
            $this->createNameFactory(['App\\Orders', 'App\\CustomersOrders']),
            $this->createMetadataFactory([
                'App\\Orders' => new ApiResource(operations: ['list' => (new GetCollection(uriTemplate: '/orders', extraProperties: $extraProperties))->withShortName('orders')]),
                'App\\CustomersOrders' => new ApiResource(operations: ['list' => (new GetCollection(uriTemplate: '/customers/{customerReference}/orders', extraProperties: $extraProperties))->withShortName('orders')]),
            ]),
            ['storefront'],
            vfsStream::url('generated'),
        );
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('api.storefront.orders.get-collection', $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenTheSameEndpointInTwoResourceClassesWhenCommandRunsThenRegistryListsItOnce(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $extraProperties = [OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER => 'api.storefront.cart-codes.get'];
        $operation = (new Get(uriTemplate: '/cart-codes/{code}{._format}', extraProperties: $extraProperties))->withShortName('cart-codes');
        $command = new PermissionRegistryDumpCommand(
            $this->createNameFactory(['App\\CartCodes', 'App\\GuestCartCodes']),
            $this->createMetadataFactory([
                'App\\CartCodes' => new ApiResource(operations: ['get' => $operation]),
                'App\\GuestCartCodes' => new ApiResource(operations: ['get' => $operation]),
            ]),
            ['storefront'],
            vfsStream::url('generated'),
        );

        // Act
        $exitCode = (new CommandTester($command))->execute([]);

        // Assert
        $this->assertSame(0, $exitCode);
        $registry = json_decode((string)file_get_contents(vfsStream::url('generated/PermissionRegistry/Storefront.json')), true);
        $this->assertSame(['api.storefront.cart-codes.get'], array_column($registry, 'identifier'));
    }

    public function testGivenObjectDeclarationWhenDumpIsCalledThenItIsWrittenWithoutAnOwnerUriVariable(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand([
            'objectType' => 'cart',
            'objectUriVariable' => 'uuid',
            'denial' => ['status' => 404, 'code' => '101', 'detail' => 'Cart with given uuid not found.'],
            'sharedReadOnlyDenial' => ['status' => 403, 'code' => '115', 'detail' => 'Unauthorized cart action.'],
        ]);

        // Act
        $exitCode = (new CommandTester($command))->execute([]);

        // Assert
        $this->assertSame(0, $exitCode);
        $registry = json_decode((string)file_get_contents(vfsStream::url('generated/PermissionRegistry/Storefront.json')), true);
        $this->assertSame([static::IDENTIFIER_CARTS_GET], array_column($registry, 'identifier'));
        $this->assertNull($registry[0]['ownership_owner_uri_variable']);
    }

    public function testGivenCollectionDeclarationWhenDumpIsCalledThenItIsWritten(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand(['collection' => true]);

        // Act
        $exitCode = (new CommandTester($command))->execute([]);

        // Assert
        $this->assertSame(0, $exitCode);
        $registry = json_decode((string)file_get_contents(vfsStream::url('generated/PermissionRegistry/Storefront.json')), true);
        $this->assertSame([static::IDENTIFIER_CARTS_GET], array_column($registry, 'identifier'));
    }

    public function testGivenDeclarationWithTwoFormsWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand(['ownerUriVariable' => 'customerReference', 'collection' => true]);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenDeclarationWithoutAFormWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand(['denial' => ['status' => 404, 'code' => '101', 'detail' => 'Cart with given uuid not found.']]);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenObjectTypeWithoutObjectUriVariableWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand(['objectType' => 'cart']);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenCollectionThatIsNotTrueWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand(['collection' => 'yes']);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenOwnerUriVariableThatIsNotAStringWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand(['ownerUriVariable' => ['customerReference']]);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenDenialWithoutDetailWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand([
            'objectType' => 'cart',
            'objectUriVariable' => 'uuid',
            'denial' => ['status' => 404, 'code' => '101'],
        ]);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    public function testGivenSharedReadOnlyDenialWithStringStatusWhenDumpIsCalledThenItFailsNamingTheIdentifier(): void
    {
        // Arrange
        vfsStream::setup('generated');
        $command = $this->createCartsCommand([
            'objectType' => 'cart',
            'objectUriVariable' => 'uuid',
            'sharedReadOnlyDenial' => ['status' => '403', 'code' => '115', 'detail' => 'Unauthorized cart action.'],
        ]);
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(sprintf('The securityOwnership declaration of %s is malformed', static::IDENTIFIER_CARTS_GET), $commandTester->getDisplay());
        $this->assertFileDoesNotExist(vfsStream::url('generated/PermissionRegistry/Storefront.json'));
    }

    /**
     * @param array<string, mixed> $ownership
     */
    protected function createCartsCommand(array $ownership): PermissionRegistryDumpCommand
    {
        $extraProperties = [
            OperationPermissionIdentifierResolver::EXTRA_PROPERTY_PERMISSION_IDENTIFIER => static::IDENTIFIER_CARTS_GET,
            OperationPermissionIdentifierResolver::EXTRA_PROPERTY_OWNERSHIP => $ownership,
        ];

        return new PermissionRegistryDumpCommand(
            $this->createNameFactory(['App\\Carts']),
            $this->createMetadataFactory([
                'App\\Carts' => new ApiResource(operations: ['get' => (new Get(uriTemplate: '/carts/{uuid}', extraProperties: $extraProperties))->withShortName('carts')]),
            ]),
            ['storefront'],
            vfsStream::url('generated'),
        );
    }

    /**
     * @param array<string, \ApiPlatform\Metadata\ApiResource> $apiResources Keyed by resource class.
     */
    protected function createMetadataFactory(array $apiResources): ResourceMetadataCollectionFactoryInterface
    {
        return new class ($apiResources) implements ResourceMetadataCollectionFactoryInterface {
            /**
             * @param array<string, \ApiPlatform\Metadata\ApiResource> $apiResources
             */
            public function __construct(protected array $apiResources)
            {
            }

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [$this->apiResources[$resourceClass]]);
            }
        };
    }

    /**
     * @param list<string> $resourceClasses
     */
    protected function createNameFactory(array $resourceClasses): ResourceNameCollectionFactoryInterface
    {
        return new class ($resourceClasses) implements ResourceNameCollectionFactoryInterface {
            /**
             * @param list<string> $resourceClasses
             */
            public function __construct(protected array $resourceClasses)
            {
            }

            public function create(): ResourceNameCollection
            {
                return new ResourceNameCollection($this->resourceClasses);
            }
        };
    }
}
