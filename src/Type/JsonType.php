<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use JsonException;
use Override;

use function is_string;
use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Stores any JSON-encodable value as text. Objects decode to associative
 * arrays.
 *
 * @api
 */
final readonly class JsonType implements TypeConverterInterface
{
    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException) {
            throw TypeConversionException::invalidValue($field, $value, 'JSON-encodable value');
        }
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): mixed
    {
        if (! is_string($value)) {
            throw TypeConversionException::invalidValue($field, $value, 'JSON text');
        }

        try {
            return json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw TypeConversionException::invalidValue($field, $value, 'JSON text');
        }
    }
}
