<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\Normalizer;

use ApiPlatform\Metadata\Operation;
use Psr\Log\LoggerInterface;
use Spryker\ApiPlatform\Schema\LinkSchemaKeys;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ResourceLinksNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    protected const string FORMAT_JSON_API = 'jsonapi';

    protected const string LOG_MESSAGE_LINK_SKIPPED = 'Resource link "%s" was left out: route "%s" cannot be generated. Fix the link declaration in the resource schema.';

    protected const string LOG_MESSAGE_PROPERTY_UNREADABLE = 'Resource link "%s" was left out: property "%s" is not readable on %s. Fix the link declaration in the resource schema.';

    public function __construct(
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly PropertyAccessorInterface $propertyAccessor,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return \ArrayObject<array-key, mixed>|array<string, mixed>|string|float|int|bool|null
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[static::class] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!is_array($data) || !isset($data['data']) || !is_array($data['data']) || !isset($data['data']['type'])) {
            return $data;
        }

        $links = $this->buildLinks($this->getScopedLinkDefinitions($context), $object);

        if ($links === []) {
            return $data;
        }

        $data['data']['links'] = array_merge(
            is_array($data['data']['links'] ?? null) ? $data['data']['links'] : [],
            $links,
        );

        return $data;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[static::class])) {
            return false;
        }

        if ($format !== static::FORMAT_JSON_API) {
            return false;
        }

        return is_object($data);
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            'object' => false,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getScopedLinkDefinitions(array $context): array
    {
        $operation = $context['operation'] ?? null;

        if (!$operation instanceof Operation) {
            return [];
        }

        $linkDefinitions = $operation->getExtraProperties()[LinkSchemaKeys::LINKS] ?? null;

        if (!is_array($linkDefinitions)) {
            return [];
        }

        $scopedLinkDefinitions = [];

        foreach ($linkDefinitions as $linkName => $linkDefinition) {
            if (!is_array($linkDefinition) || !isset($linkDefinition[LinkSchemaKeys::LINK_ROUTE])) {
                continue;
            }

            if (!$this->isInOperationScope($linkDefinition, $operation)) {
                continue;
            }

            $scopedLinkDefinitions[(string)$linkName] = $linkDefinition;
        }

        return $scopedLinkDefinitions;
    }

    /**
     * @param array<string, mixed> $linkDefinition
     */
    protected function isInOperationScope(array $linkDefinition, Operation $operation): bool
    {
        $operations = $linkDefinition[LinkSchemaKeys::LINK_OPERATIONS] ?? null;

        if (!is_array($operations) || $operations === []) {
            return true;
        }

        $operationType = substr((string)strrchr('\\' . $operation::class, '\\'), 1);

        return in_array($operationType, $operations, true) || in_array($operation->getName(), $operations, true);
    }

    /**
     * @param array<string, array<string, mixed>> $linkDefinitions
     *
     * @return array<string, string>
     */
    protected function buildLinks(array $linkDefinitions, mixed $object): array
    {
        $links = [];

        foreach ($linkDefinitions as $linkName => $linkDefinition) {
            if (!$this->matchesCondition((string)$linkName, $linkDefinition, $object)) {
                continue;
            }

            $routeParameters = $this->buildRouteParameters((string)$linkName, $linkDefinition, $object);

            if ($routeParameters === null) {
                continue;
            }

            $link = $this->generateLink((string)$linkName, (string)$linkDefinition[LinkSchemaKeys::LINK_ROUTE], $routeParameters, $object);

            if ($link !== null) {
                $links[$linkName] = $link;
            }
        }

        return $links;
    }

    /**
     * A link that cannot be generated, because its route does not exist or its parameters do not satisfy the route,
     * is a schema defect of the resource and must not turn every response into a 500: it is logged and left out.
     *
     * @param array<string, mixed> $routeParameters
     */
    protected function generateLink(string $linkName, string $route, array $routeParameters, mixed $object): ?string
    {
        try {
            return $this->urlGenerator->generate($route, $routeParameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RoutingExceptionInterface $routingException) {
            $this->logger->error(sprintf(static::LOG_MESSAGE_LINK_SKIPPED, $linkName, $route), [
                'resource' => is_object($object) ? $object::class : get_debug_type($object),
                'routeParameters' => $routeParameters,
                'exception' => $routingException,
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $linkDefinition
     */
    protected function matchesCondition(string $linkName, array $linkDefinition, mixed $object): bool
    {
        $condition = $linkDefinition[LinkSchemaKeys::LINK_CONDITION] ?? null;

        if (!is_array($condition) || !isset($condition[LinkSchemaKeys::CONDITION_PROPERTY])) {
            return true;
        }

        $propertyPath = (string)$condition[LinkSchemaKeys::CONDITION_PROPERTY];

        if (!$this->isReadableProperty($linkName, $object, $propertyPath)) {
            return false;
        }

        return $this->propertyAccessor->getValue($object, $propertyPath) === ($condition[LinkSchemaKeys::CONDITION_EQUALS] ?? null);
    }

    /**
     * @param array<string, mixed> $linkDefinition
     *
     * @return array<string, mixed>|null
     */
    protected function buildRouteParameters(string $linkName, array $linkDefinition, mixed $object): ?array
    {
        $parameters = $linkDefinition[LinkSchemaKeys::LINK_PARAMETERS] ?? null;

        if (!is_array($parameters)) {
            return [];
        }

        $routeParameters = [];

        foreach ($parameters as $routeParameter => $propertyPath) {
            if (!$this->isReadableProperty($linkName, $object, (string)$propertyPath)) {
                return null;
            }

            $value = $this->propertyAccessor->getValue($object, (string)$propertyPath);

            if ($value === null) {
                return null;
            }

            $routeParameters[(string)$routeParameter] = $value;
        }

        return $routeParameters;
    }

    protected function isReadableProperty(string $linkName, mixed $object, string $propertyPath): bool
    {
        if (is_object($object) && $this->propertyAccessor->isReadable($object, $propertyPath)) {
            return true;
        }

        $this->logger->error(sprintf(static::LOG_MESSAGE_PROPERTY_UNREADABLE, $linkName, $propertyPath, get_debug_type($object)), [
            'resource' => get_debug_type($object),
            'propertyPath' => $propertyPath,
        ]);

        return false;
    }
}
