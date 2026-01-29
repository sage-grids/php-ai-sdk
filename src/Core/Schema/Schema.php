<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Schema;

use ReflectionClass;

abstract class Schema
{
    protected ?string $description = null;
    protected mixed $defaultValue = null;
    protected bool $isOptional = false;

    /**
     * @return array<string, mixed>
     */
    abstract public function toJsonSchema(): array;

    abstract public function validate(mixed $value): ValidationResult;

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function optional(): static
    {
        $this->isOptional = true;
        return $this;
    }

    public function required(): static
    {
        $this->isOptional = false;
        return $this;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    // Static Factory Methods

    public static function string(): StringSchema
    {
        return new StringSchema();
    }

    public static function number(): NumberSchema
    {
        return new NumberSchema();
    }

    public static function integer(): IntegerSchema
    {
        return new IntegerSchema();
    }

    public static function boolean(): BooleanSchema
    {
        return new BooleanSchema();
    }

    public static function array(Schema $items): ArraySchema
    {
        return new ArraySchema($items);
    }

    /**
     * @param array<string, Schema> $properties
     */
    public static function object(array $properties): ObjectSchema
    {
        return new ObjectSchema($properties);
    }

    /**
     * @param array<string|int|float|bool> $values
     */
    public static function enum(array $values): EnumSchema
    {
        return new EnumSchema($values);
    }

    public static function nullable(Schema $schema): NullableSchema
    {
        return new NullableSchema($schema);
    }

    /**
     * @param array<Schema> $schemas
     */
    public static function union(array $schemas): UnionSchema
    {
        return new UnionSchema($schemas);
    }

    /**
     * @param class-string $className
     */
    public static function fromClass(string $className): ObjectSchema
    {
        $ref = new ReflectionClass($className);
        $properties = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $schema = self::createSchemaFromProperty($prop);
            $properties[$prop->getName()] = $schema;
        }

        $schema = self::object($properties);

        // Class level attributes
        $description = $ref->getAttributes(Attributes\Description::class)[0] ?? null;
        if ($description) {
            $schema->description($description->newInstance()->description);
        }

        return $schema;
    }

    private static function createSchemaFromProperty(\ReflectionProperty $prop): Schema
    {
        $type = $prop->getType();

        if ($type === null) {
            throw new \RuntimeException("Property {$prop->getName()} must have a type for schema generation.");
        }

        $nullable = false;

        if ($type instanceof \ReflectionUnionType) {
            $schemas = [];
            foreach ($type->getTypes() as $memberType) {
                if (!$memberType instanceof \ReflectionNamedType) {
                    throw new \RuntimeException("Intersection types are not supported in fromClass. Property: {$prop->getName()}");
                }
                if ($memberType->getName() === 'null') {
                    $nullable = true;
                    continue;
                }
                $schemas[] = self::createSchemaFromNamedType($memberType, $prop);
            }

            if (empty($schemas)) {
                throw new \RuntimeException("Union type for {$prop->getName()} must include at least one non-null type.");
            }

            $schema = count($schemas) === 1 ? $schemas[0] : self::union($schemas);
        } elseif ($type instanceof \ReflectionNamedType) {
            $schema = self::createSchemaFromNamedType($type, $prop);
            $nullable = $type->allowsNull();
        } else {
            throw new \RuntimeException("Unsupported type for {$prop->getName()}.");
        }

        // Apply Attributes
        $attributes = $prop->getAttributes();
        foreach ($attributes as $attr) {
            $inst = $attr->newInstance();
            if ($inst instanceof Attributes\Description) {
                $schema->description($inst->description);
            } elseif ($inst instanceof Attributes\Format && $schema instanceof StringSchema) {
                $schema->format($inst->format);
            } elseif ($inst instanceof Attributes\Minimum && ($schema instanceof NumberSchema)) {
                $schema->minimum($inst->minimum);
            } elseif ($inst instanceof Attributes\Maximum && ($schema instanceof NumberSchema)) {
                $schema->maximum($inst->maximum);
            } elseif ($inst instanceof Attributes\Optional) {
                $schema->optional();
            }
        }

        // Handle Nullable
        if ($nullable) {
            $schema = self::nullable($schema);
            // If property has default null, it is also optional
            if ($prop->hasDefaultValue() && $prop->getDefaultValue() === null) {
                $schema->optional();
            }
        }

        // Handle Default Value
        if ($prop->hasDefaultValue()) {
            $schema->default($prop->getDefaultValue());
        }

        return $schema;
    }

    private static function createSchemaFromNamedType(\ReflectionNamedType $type, \ReflectionProperty $prop): Schema
    {
        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            return match ($typeName) {
                'string' => self::string(),
                'int' => self::integer(),
                'float' => self::number(),
                'bool' => self::boolean(),
                'array' => self::createArraySchema($prop),
                default => throw new \RuntimeException("Unsupported builtin type: $typeName"),
            };
        }

        if (enum_exists($typeName)) {
            $reflectionEnum = new \ReflectionEnum($typeName);
            if ($reflectionEnum->isBacked()) {
                $values = array_map(fn ($case) => $case->getBackingValue(), $reflectionEnum->getCases());
                return self::enum($values);
            }
            // Unit enum names
            $values = array_map(fn ($case) => $case->name, $reflectionEnum->getCases());
            return self::enum($values);
        }

        if (class_exists($typeName)) {
            // Recursive object
            return self::fromClass($typeName);
        }

        throw new \RuntimeException("Unknown type: $typeName");
    }

    private static function createArraySchema(\ReflectionProperty $prop): ArraySchema
    {
        $attr = $prop->getAttributes(Attributes\ArrayItems::class)[0] ?? null;
        if (!$attr) {
            throw new \RuntimeException("Array property {$prop->getName()} must have #[ArrayItems] attribute");
        }

        $inst = $attr->newInstance();
        $itemsSchema = null;

        if ($inst->items instanceof Schema) {
            $itemsSchema = $inst->items;
        } elseif (is_string($inst->items)) {
            if (class_exists($inst->items)) {
                $itemsSchema = self::fromClass($inst->items);
            } elseif (in_array($inst->items, ['string', 'int', 'float', 'bool'])) {
                $itemsSchema = match($inst->items) {
                    'string' => self::string(),
                    'int' => self::integer(),
                    'float' => self::number(),
                    'bool' => self::boolean(),
                };
            }
        }

        if (!$itemsSchema) {
            throw new \RuntimeException("Invalid items definition in #[ArrayItems] for {$prop->getName()}");
        }

        $schema = self::array($itemsSchema);
        if ($inst->minItems !== null) {
            $schema->minItems($inst->minItems);
        }
        if ($inst->maxItems !== null) {
            $schema->maxItems($inst->maxItems);
        }

        return $schema;
    }
}
