<?php

/**
 * Overrides Symfony's default English translations for validator constraint messages
 * to preserve backward compatibility with the legacy Glue REST API error responses.
 *
 * Symfony's validators.en.xlf rewrites certain constraint message templates into
 * different English wording (e.g. "This is not a valid UUID." → "This value is not a valid UUID.").
 * The legacy Glue REST API returned the original constraint message templates.
 */

return [
    'This is not a valid UUID.' => 'This is not a valid UUID.',
];
