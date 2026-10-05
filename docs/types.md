# Type conversion

Every column-mapped property is converted on the way in (database to PHP,
during hydration) and on the way out (PHP to a bound statement parameter).
Conversion is done by a `TypeConverterInterface` chosen per field by an
immutable `TypeRegistry`.

```php
use Contenir\Db\Model\Type\TypeRegistry;

$types = TypeRegistry::withDefaults();
```

## Built-in converters

| Name | Used for | Database to PHP | PHP to database |
| --- | --- | --- | --- |
| `int` | `int` properties | `int`, or an integer string such as `'-7'`. Floats, booleans and decimal strings are rejected. | `int` |
| `float` | `float` properties | `int`, `float` or a numeric string | `float` |
| `string` | `string` properties | `string`, `int` or `float` (some drivers return numeric columns as numbers) | `string` |
| `bool` | `bool` properties | `true`/`false`, `1`/`0`, `'1'`/`'0'`, `'t'`/`'f'`, `'true'`/`'false'` | `bool`, which phpdb binds as `PDO::PARAM_BOOL` |
| `datetime` | any `DateTimeInterface` property | a `Y-m-d H:i:s` string, falling back to PHP's general parser (so fractional seconds and offsets work). A `DateTimeInterface` from the driver is accepted too. | a `Y-m-d H:i:s` string |
| `date` | `#[Column(type: 'date')]` | a `Y-m-d` string with the time set to `00:00:00` | a `Y-m-d` string |
| `json` | `#[Column(type: 'json')]` | JSON text decoded with objects as associative arrays | JSON text, with slashes and Unicode unescaped and `1.0` kept as `1.0` |
| `SensitiveString` | any `SensitiveString` property (registered by class name) | `SensitiveString` wrapping the text | the revealed `string`; see [sensitive data](sensitive-data.md) |
| `enum` | any `BackedEnum` property | the case whose backing value matches. Values are compared as strings, so `'2'` matches an int-backed case `2`. | the case's backing value |

`datetime` returns `DateTimeImmutable`, unless the property is declared as
the mutable `DateTime`, in which case it returns `DateTime`.

## Resolution order

For each field, the registry picks the converter in this order:

1. The name given in `#[Column(type: '...')]`, if any.
2. A converter registered under the property's declared type. That is the
   builtin name (`int`, `float`, `string`, `bool`) or a fully qualified
   class name.
3. `enum`, if the declared type is a `BackedEnum`.
4. `datetime`, if the declared type implements `DateTimeInterface`.

If nothing matches, the registry throws `TypeConversionException`. That
happens, for example, with an `array` property that lacks
`#[Column(type: 'json')]`, or a union-typed property that lacks a type name.

## Nulls

Converters never receive `null`. The registry handles it first:

- **Reading:** a database `NULL` becomes `null` when the property type is
  nullable. Otherwise it throws `TypeConversionException` ("Column "x" is
  NULL but property $x is not nullable").
- **Writing:** `null` is written as `NULL`.

## Custom converters

Implement `TypeConverterInterface` and register it under a name, a class
name, or both:

```php
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Type\TypeConverterInterface;

final readonly class MoneyType implements TypeConverterInterface
{
    public function toPhp(mixed $value, FieldMetadata $field): Money
    {
        return is_numeric($value)
            ? Money::ofMinor((int) $value)
            : throw TypeConversionException::invalidValue($field, $value, Money::class);
    }

    public function toDatabase(mixed $value, FieldMetadata $field): int
    {
        return $value instanceof Money
            ? $value->minor
            : throw TypeConversionException::invalidValue($field, $value, Money::class);
    }
}

$types = TypeRegistry::withDefaults()
    ->withConverter(Money::class, new MoneyType())   // every Money-typed property
    ->withConverter('money', new MoneyType());       // or #[Column(type: 'money')]
```

`withConverter()` returns a new registry. It replaces any converter with the
same name and leaves the original registry unchanged.

To change a default, register over its name. For example, to use
microsecond timestamps stored and read in UTC:

```php
$types = TypeRegistry::withDefaults()
    ->withConverter('datetime', new DateTimeType('Y-m-d H:i:s.u', new DateTimeZone('UTC')));
```

When `DateTimeType` is given a timezone, it parses database strings in that
zone, and converts values to that zone before formatting them for writing.

## Errors

Every conversion failure throws
`Contenir\Db\Model\Exception\TypeConversionException`, a subclass of the
package's `RuntimeException`. The message names the offending value, the
property and the column. For example:

```
Cannot convert string 'abc' for property $total (column "total") to int
```
