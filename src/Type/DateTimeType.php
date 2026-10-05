<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Type;

use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use DateMalformedStringException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;

use function is_string;

/**
 * Converts between date/time strings and DateTimeImmutable (or DateTime,
 * when the property is declared as the mutable class).
 *
 * Database strings are parsed with $format first and fall back to PHP's
 * general date parser, so fractional seconds or offsets returned by some
 * platforms still load. When $timezone is given, parsed values are read
 * in it and written values are converted to it before formatting.
 *
 * @api
 */
final readonly class DateTimeType implements TypeConverterInterface
{
    public function __construct(
        private string $format = 'Y-m-d H:i:s',
        private ?DateTimeZone $timezone = null,
    ) {}

    #[Override]
    public function toDatabase(mixed $value, FieldMetadata $field): string
    {
        if (! $value instanceof DateTimeInterface) {
            throw TypeConversionException::invalidValue($field, $value, DateTimeInterface::class);
        }

        $immutable = DateTimeImmutable::createFromInterface($value);
        if (null !== $this->timezone) {
            $immutable = $immutable->setTimezone($this->timezone);
        }

        return $immutable->format($this->format);
    }

    #[Override]
    public function toPhp(mixed $value, FieldMetadata $field): DateTimeInterface
    {
        $immutable = $this->parse($value, $field);

        return DateTime::class === $field->type->phpType ? DateTime::createFromImmutable($immutable) : $immutable;
    }

    /**
     * @throws TypeConversionException
     */
    private function parse(mixed $value, FieldMetadata $field): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (! is_string($value)) {
            throw TypeConversionException::invalidValue($field, $value, DateTimeInterface::class);
        }

        $parsed = DateTimeImmutable::createFromFormat("!{$this->format}", $value, $this->timezone);
        if (false !== $parsed) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($value, $this->timezone);
        } catch (DateMalformedStringException) {
            throw TypeConversionException::invalidValue($field, $value, DateTimeInterface::class);
        }
    }
}
