# Sensitive data

Columns such as password hashes, API tokens and recovery codes should not
show up in debug dumps, stack traces or error logs. Two complementary tools
handle this.

| Tool | Hides the value from |
| --- | --- |
| `SensitiveString` property type | `var_dump()`, `print_r()`, Xdebug dumps, stack-trace arguments, accidental string casts, **and** this library's messages |
| `#[Column(sensitive: true)]` | this library's exception messages and other output only |

PHP's built-in `#[\SensitiveParameter]` does not help here. It only applies
to function parameters, and it only hides values in stack traces; it has no
effect on properties or on `var_dump()`.

## `SensitiveString`

```php
use Contenir\Db\Model\Mapping\Column;
use Contenir\Db\Model\Value\SensitiveString;

#[Table('accounts')]
final class Account
{
    #[Id(generated: true)]
    public ?int $id = null;

    #[Column('password_hash')]
    public SensitiveString $passwordHash;
}

$account->passwordHash = new SensitiveString(password_hash($plain, PASSWORD_DEFAULT));

password_verify($plain, $account->passwordHash->reveal());

var_dump($account);
// ["passwordHash"]=> object(Contenir\Db\Model\Value\SensitiveString)#3 (1) {
//     ["value"]=> string(10) "[redacted]"
// }
```

- **Storage.** The column is ordinary text. The `SensitiveStringType`
  converter is registered by default under the `SensitiveString` class, so
  no `type:` is needed.
- **Reading the value.** The only way to get the value out is
  `reveal()`. There is deliberately no `__toString()`, so a stray `echo`
  or string interpolation fails instead of leaking.
- **Comparing.** `equals()` compares two values in constant time.
- **Automatic flag.** A `SensitiveString` property is automatically
  treated as `sensitive: true` (see below).
- **Stack traces.** The constructor argument is marked
  `#[\SensitiveParameter]`, so `new SensitiveString($hash)` never shows
  the hash in a trace.

### What `SensitiveString` does not do

It is a guard against accidental *display*, not a security boundary.

- It does not encrypt or hash anything. Store hashes, not plaintext.
- `serialize()`, `var_export()` and reflection still see the raw value.
  Some third-party dumpers ignore `__debugInfo()`.
- Values travelling through generic code, such as the type registry, can
  still appear as arguments in stack traces. Production PHP should run with
  `zend.exception_ignore_args = On`, which is the default in
  `php.ini-production`.

## `#[Column(sensitive: true)]`

Use this for a column that you want to keep as a plain PHP type, but whose
values must not appear in output this library produces:

```php
#[Column('api_token', sensitive: true)]
public ?string $apiToken = null;
```

Currently this affects `TypeConversionException`. Its message normally
includes the offending value:

```
Cannot convert string 'abc' for property $total (column "total") to int
```

For a sensitive column the value is replaced:

```
Cannot convert [redacted] value for property $apiToken (column "api_token") to string
```

Any future debug or logging output from the library (for example, query
logging) will honour the same flag. The flag is available as
`FieldMetadata::$type->sensitive` for your own tooling.

It does **not** change how `var_dump()` shows the entity's `string`
property. Use `SensitiveString` for that.
