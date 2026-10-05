<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Replay;

use LogicException;
use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;

/**
 * One operation's request as the generated OpenAPI examples describe it: the body built from the
 * property examples, the query from the parameter examples, and the path variables a context has to
 * fill with fixture values.
 */
readonly class ReplayableRequest
{
    /**
     * @var non-empty-string
     */
    protected const string PATH_SEPARATOR = '.';

    protected const string URI_VARIABLE_TEMPLATE = '{%s}';

    protected const string JSON_API_KEY_DATA = 'data';

    protected const string JSON_API_KEY_ATTRIBUTES = 'attributes';

    /**
     * @param array<string, mixed>|null $body The JSON:API document, null for an operation without a body.
     * @param array<string, string> $query
     * @param array<string> $uriVariableNames
     */
    public function __construct(
        public ApiOperation $operation,
        public ?array $body,
        public array $query,
        public array $uriVariableNames,
    ) {
    }

    /**
     * The request with the context applied: every path variable substituted and every override
     * written into the body.
     *
     * @throws \LogicException
     *
     * @return array{method: string, uri: string, content: string|null}
     */
    public function resolve(OpenApiExampleReplayContext $context): array
    {
        $uri = $this->operation->uriTemplate;
        foreach ($this->uriVariableNames as $uriVariableName) {
            if (!isset($context->uriVariables[$uriVariableName])) {
                throw new LogicException(sprintf(
                    'The replay of %s needs a value for the path variable {%s}; give it one in createReplayContext().',
                    $this->operation->dispatchKey(),
                    $uriVariableName,
                ));
            }
            $uri = str_replace(sprintf(static::URI_VARIABLE_TEMPLATE, $uriVariableName), rawurlencode($context->uriVariables[$uriVariableName]), $uri);
        }

        if ($this->query !== []) {
            $uri .= '?' . http_build_query($this->query);
        }

        $body = $this->body;
        if ($body !== null) {
            foreach ($context->bodyAttributeOverrides as $path => $value) {
                $body = $this->override($body, $path, $value);
            }
        }

        return [
            'method' => strtoupper($this->operation->verb),
            'uri' => $uri,
            'content' => $body === null ? null : (string)json_encode($body),
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function override(array $body, string $path, mixed $value): array
    {
        $cursor = &$body[static::JSON_API_KEY_DATA][static::JSON_API_KEY_ATTRIBUTES];
        foreach (explode(static::PATH_SEPARATOR, $path) as $segment) {
            if (!is_array($cursor)) {
                $cursor = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor = $value;

        return $body;
    }
}
