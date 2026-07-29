<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Configures the API Platform encoders to emit the byte form the legacy Glue REST API used
 * (unescaped slashes and unicode), so responses do not need a whole-body decode/re-encode pass
 * at kernel.response. JSON_INVALID_UTF8_IGNORE keeps parity with API Platform's default encoder
 * context, which already ignores malformed UTF-8.
 *
 * Arguments of the existing definitions are mutated in place, so service ids and their
 * `serializer.encoder` tags are untouched.
 */
class JsonEncoderConfigurationPass implements CompilerPassInterface
{
    /**
     * @var list<string>
     */
    protected const array ENCODER_SERVICE_IDS = [
        'api_platform.jsonapi.encoder',
        'api_platform.problem.encoder',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (static::ENCODER_SERVICE_IDS as $serviceId) {
            $this->configureEncoderOptions($container, $serviceId);
        }
    }

    protected function configureEncoderOptions(ContainerBuilder $container, string $serviceId): void
    {
        if (!$container->hasDefinition($serviceId)) {
            return;
        }

        $encoderDefinition = $container->getDefinition($serviceId);

        // Only append while the definition has the single upstream format argument; the optional
        // second argument of \ApiPlatform\Serializer\JsonEncoder is the wrapped Symfony encoder.
        if (count($encoderDefinition->getArguments()) !== 1) {
            return;
        }

        $jsonEncode = new Definition(JsonEncode::class, [
            [JsonEncode::OPTIONS => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE],
        ]);
        $jsonDecode = new Definition(JsonDecode::class, [
            [JsonDecode::ASSOCIATIVE => true],
        ]);

        $encoderDefinition->addArgument(new Definition(JsonEncoder::class, [$jsonEncode, $jsonDecode]));
    }
}
