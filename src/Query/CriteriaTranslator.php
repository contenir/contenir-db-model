<?php

declare(strict_types=1);

namespace Contenir\Db\Model\Query;

use Contenir\Db\Model\Exception\QueryException;
use Contenir\Db\Model\Exception\TypeConversionException;
use Contenir\Db\Model\Metadata\EntityMetadata;
use Contenir\Db\Model\Metadata\FieldMetadata;
use Contenir\Db\Model\Type\TypeRegistry;
use PhpDb\Sql\Select;

use function array_key_exists;
use function count;
use function is_array;
use function strtoupper;

/**
 * Translates property-keyed criteria, ordering and identifiers into
 * column-keyed predicates with database-form values. Property names are
 * validated against the mapping, so caller-supplied keys can never reach
 * the SQL as identifiers.
 *
 * @internal
 */
final readonly class CriteriaTranslator
{
    public function __construct(
        private TypeRegistry $types,
    ) {}

    /**
     * Add criteria and ordering to $select, with columns qualified by the
     * entity's table.
     *
     * @template T of object
     *
     * @param EntityMetadata<T>     $metadata
     * @param array<string, mixed>  $criteria
     * @param array<string, string> $orderBy
     *
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function apply(EntityMetadata $metadata, Select $select, array $criteria, array $orderBy): Select
    {
        $where = ColumnQualifier::qualify($metadata, $this->where($metadata, $criteria));
        if ([] !== $where) {
            $select->where($where);
        }

        $order = ColumnQualifier::qualify($metadata, $this->order($metadata, $orderBy));

        return [] === $order ? $select : $select->order($order);
    }

    /**
     * Normalise a find() argument into a column-keyed identifier: a scalar
     * for single-column keys, or an array keyed by property name with
     * exactly the key properties for composite keys.
     *
     * @template T of object
     *
     * @param EntityMetadata<T>               $metadata
     * @param int|string|array<string, mixed> $id
     *
     * @return array<string, int|float|string|bool>
     *
     * @throws QueryException
     * @throws TypeConversionException
     */
    public function identifier(EntityMetadata $metadata, int|string|array $id): array
    {
        $fields = $metadata->identifier;
        if (! is_array($id)) {
            $id = 1 === count($fields) ? [$fields[0]->propertyName => $id] : [];
        }

        $identifier = [];
        foreach ($fields as $field) {
            $value = array_key_exists($field->propertyName, $id)
                ? $this->value($field, $id[$field->propertyName])
                : null;
            if (null === $value || count($id) !== count($fields)) {
                throw QueryException::invalidIdentifier($metadata);
            }

            $identifier[$field->columnName] = $value;
        }

        return $identifier;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T>     $metadata
     * @param array<string, string> $orderBy property => ASC|DESC
     *
     * @return array<string, 'ASC'|'DESC'>
     *
     * @throws QueryException
     */
    public function order(EntityMetadata $metadata, array $orderBy): array
    {
        $order = [];
        foreach ($orderBy as $property => $direction) {
            $order[$this->field($metadata, $property)->columnName] = match (strtoupper($direction)) {
                'ASC'   => 'ASC',
                'DESC'  => 'DESC',
                default => throw QueryException::invalidDirection($metadata->className, $property, $direction),
            };
        }

        return $order;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T>    $metadata
     * @param array<string, mixed> $criteria property => value, list of values (IN), or null (IS NULL)
     *
     * @return array<string, int|float|string|bool|list<int|float|string|bool|null>|null>
     *
     * @throws QueryException
     * @throws TypeConversionException
     *
     * @mago-expect analysis:mixed-assignment Criteria values are arbitrary caller input, validated by conversion.
     */
    public function where(EntityMetadata $metadata, array $criteria): array
    {
        $where = [];
        foreach ($criteria as $property => $value) {
            $field                     = $this->field($metadata, $property);
            $where[$field->columnName] = is_array($value)
                ? $this->values($field, $value)
                : $this->value($field, $value);
        }

        return $where;
    }

    /**
     * @template T of object
     *
     * @param EntityMetadata<T> $metadata
     *
     * @throws QueryException
     */
    private function field(EntityMetadata $metadata, string $property): FieldMetadata
    {
        return $metadata->fields[$property] ?? throw QueryException::unknownProperty($metadata->className, $property);
    }

    /**
     * Normalise a caller-supplied value to database form. Going through the
     * PHP form first lets callers pass either representation (e.g. "5" or
     * 5, an enum case or its backing value).
     *
     * @throws TypeConversionException
     */
    private function value(FieldMetadata $field, mixed $value): int|float|string|bool|null
    {
        return null === $value ? null : $this->types->toDatabase($field, $this->types->toPhp($field, $value));
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<int|float|string|bool|null>
     *
     * @throws TypeConversionException
     *
     * @mago-expect analysis:mixed-assignment Criteria values are arbitrary caller input, validated by conversion.
     */
    private function values(FieldMetadata $field, array $values): array
    {
        $converted = [];
        foreach ($values as $value) {
            $converted[] = $this->value($field, $value);
        }

        return $converted;
    }
}
