<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Generator;

/**
 * The array keys of a parsed `.resource.yml` schema and of the OpenAPI context it carries.
 *
 * Held in one place rather than as per-class constants because the generators read the same
 * document: a key renamed in the schema format has to change here once, not in every generator that
 * happens to reach for it.
 */
class SchemaKey
{
    public const string SHORT_NAME = 'shortName';

    public const string SOURCE_FILE = 'sourceFile';

    public const string NAME = 'name';

    public const string TYPE = 'type';

    public const string DESCRIPTION = 'description';

    public const string PROPERTIES = 'properties';

    public const string ITEMS = 'items';

    public const string IDENTIFIER = 'identifier';

    public const string SYNTHETIC_IDENTIFIER = 'syntheticIdentifier';

    public const string RESPONSE_OPTIONAL = 'responseOptional';

    public const string COLLECTION_ONLY = 'collectionOnly';

    public const string ITEM_ONLY = 'itemOnly';

    public const string READABLE = 'readable';

    public const string WRITABLE = 'writable';

    public const string NULLABLE = 'nullable';

    public const string SERIALIZED_NAME = 'serializedName';

    public const string SERIALIZED_PATH = 'serializedPath';

    public const string CONSTRAINTS = 'constraints';

    public const string FIELDS = 'fields';

    public const string OPERATIONS = 'operations';

    public const string URI_TEMPLATE = 'uriTemplate';

    public const string URI_VARIABLES = 'uriVariables';

    public const string EXTRA_PROPERTIES = 'extraProperties';

    public const string CONTROLLER = 'controller';

    public const string INTERNAL = 'internal';

    public const string PROVIDER = 'provider';

    public const string PROCESSOR = 'processor';

    public const string OUTPUT = 'output';

    public const string STATUS = 'status';

    public const string READ = 'read';

    public const string DESERIALIZE = 'deserialize';

    public const string GROUPS = 'groups';

    public const string VALIDATION_GROUPS = 'validationGroups';

    public const string VALIDATION_CONTEXT = 'validationContext';

    public const string NORMALIZATION_CONTEXT = 'normalizationContext';

    public const string INCLUDED_SORT_PRIORITY = 'includedSortPriority';

    public const string RESOURCE_ATTRIBUTES_CLASS_NAME = 'resourceAttributesClassName';

    public const string FROM_CLASS = 'fromClass';

    public const string FROM_PROPERTY = 'fromProperty';

    public const string TO_PROPERTY = 'toProperty';

    public const string IDENTIFIERS = 'identifiers';

    public const string PAGINATION_ENABLED = 'paginationEnabled';

    public const string PAGINATION_ITEMS_PER_PAGE = 'paginationItemsPerPage';

    public const string PAGINATION_MAXIMUM_ITEMS_PER_PAGE = 'paginationMaximumItemsPerPage';

    public const string PAGINATION_CLIENT_ENABLED = 'paginationClientEnabled';

    public const string PAGINATION_CLIENT_ITEMS_PER_PAGE = 'paginationClientItemsPerPage';

    public const string SECURITY = 'security';

    public const string SECURITY_MESSAGE = 'securityMessage';

    public const string SECURITY_POST_DENORMALIZE = 'securityPostDenormalize';

    public const string SECURITY_POST_DENORMALIZE_MESSAGE = 'securityPostDenormalizeMessage';

    public const string SECURITY_POST_VALIDATION = 'securityPostValidation';

    public const string SECURITY_POST_VALIDATION_MESSAGE = 'securityPostValidationMessage';

    public const string SECURITY_CODE = 'securityCode';

    public const string SECURITY_GET_STATUS_CODE = 'securityGetStatusCode';

    public const string SECURITY_ANONYMOUS_AUTH_REQUIRED = 'securityAnonymousAuthRequired';

    public const string SECURITY_BEARER_AUTH_REQUIRED = 'securityBearerAuthRequired';

    public const string OPEN_API = 'openapi';

    public const string OPEN_API_CONTEXT = 'openapiContext';

    public const string TAGS = 'tags';

    public const string RESPONSES = 'responses';

    public const string PARAMETERS = 'parameters';

    public const string REQUEST_BODY = 'requestBody';

    public const string CONTENT = 'content';

    public const string DATA = 'data';

    public const string ATTRIBUTES = 'attributes';

    public const string SCHEMA = 'schema';

    public const string EXAMPLE = 'example';

    public const string IN = 'in';

    public const string REQUIRED = 'required';

    public const string DEPRECATED = 'deprecated';
}
