<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Tool;

use Closure;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;
use SageGrids\PhpAiSdk\Core\Schema\ObjectSchema;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Attributes\Parameter;
use SageGrids\PhpAiSdk\Core\Tool\Attributes\Tool as ToolAttribute;

/**
 * Represents a tool that can be called by the AI model.
 */
final class Tool
{
    private function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly Schema $parameters,
        private readonly ?Closure $execute,
    ) {
    }

    /**
     * Create a new tool with a callable handler.
     *
     * @param string $name The tool name.
     * @param string $description The tool description for the AI model.
     * @param Schema $parameters The parameter schema for validation.
     * @param callable|null $execute The function to execute when the tool is called.
     */
    public static function create(
        string $name,
        string $description,
        Schema $parameters,
        ?callable $execute = null,
    ): self {
        return new self(
            $name,
            $description,
            $parameters,
            $execute !== null ? Closure::fromCallable($execute) : null,
        );
    }

    /**
     * Create a tool from an object method using reflection.
     *
     * The method should be annotated with #[Tool] attribute.
     * Parameters can be annotated with #[Parameter] for descriptions.
     *
     * @param object $instance The object instance containing the method.
     * @param string $method The method name.
     */
    public static function fromMethod(object $instance, string $method): self
    {
        $reflection = new ReflectionMethod($instance, $method);

        // Get Tool attribute
        $toolAttributes = $reflection->getAttributes(ToolAttribute::class);
        $toolAttr = $toolAttributes[0] ?? null;

        /** @var ToolAttribute|null $toolInstance */
        $toolInstance = $toolAttr?->newInstance();

        // Determine name and description
        $name = $toolInstance?->name ?? $method;
        $description = $toolInstance?->description ?? '';

        // If no description from attribute, try to get from docblock
        if ($description === '' && $reflection->getDocComment()) {
            $docComment = $reflection->getDocComment();
            if ($docComment !== false) {
                // Extract first line of docblock as description
                if (preg_match('/^\s*\*\s*([^@\n]+)/m', $docComment, $matches)) {
                    $description = trim($matches[1]);
                }
            }
        }

        // Build parameter schema from method parameters
        $parameters = self::buildParameterSchema($reflection);

        // Create wrapper closure that spreads arguments to method parameters
        $paramNames = array_map(
            fn (ReflectionParameter $p) => $p->getName(),
            $reflection->getParameters()
        );

        $execute = static function (array $arguments) use ($instance, $method, $paramNames): mixed {
            $orderedArgs = [];
            foreach ($paramNames as $paramName) {
                if (array_key_exists($paramName, $arguments)) {
                    $orderedArgs[] = $arguments[$paramName];
                }
            }
            return $instance->$method(...$orderedArgs);
        };

        return new self($name, $description, $parameters, Closure::fromCallable($execute));
    }

    /**
     * Execute the tool with the given arguments.
     *
     * @param array<string, mixed> $arguments The arguments to pass to the tool.
     * @return mixed The result of the tool execution.
     * @throws RuntimeException If the tool is not executable or validation fails.
     */
    public function execute(array $arguments): mixed
    {
        if (!$this->isExecutable()) {
            throw new RuntimeException("Tool '{$this->name}' is not executable (no handler provided)");
        }

        // Validate arguments against schema
        $validation = $this->parameters->validate($arguments);
        if (!$validation->isValid) {
            throw new RuntimeException(
                "Tool '{$this->name}' argument validation failed: " . implode(', ', $validation->errors)
            );
        }

        /** @var Closure $handler */
        $handler = $this->execute;

        return $handler($arguments);
    }

    /**
     * Check if the tool has an execute handler.
     */
    public function isExecutable(): bool
    {
        return $this->execute !== null;
    }

    /**
     * Convert to provider-specific format.
     *
     * @param string $provider The provider name (e.g., 'openai', 'gemini', 'anthropic').
     * @return array<string, mixed>
     */
    public function toProviderFormat(string $provider): array
    {
        return match (strtolower($provider)) {
            'openai', 'azure' => $this->toOpenAIFormat(),
            'gemini', 'google' => $this->toGeminiFormat(),
            'anthropic', 'claude' => $this->toAnthropicFormat(),
            default => $this->toOpenAIFormat(), // Default to OpenAI format
        };
    }

    /**
     * Convert to OpenAI function calling format.
     *
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters->toJsonSchema(),
            ],
        ];
    }

    /**
     * Convert to Gemini function declaration format.
     *
     * @return array{name: string, description: string, parameters: array<string, mixed>}
     */
    public function toGeminiFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters->toJsonSchema(),
        ];
    }

    /**
     * Convert to Anthropic tool format.
     *
     * @return array{name: string, description: string, input_schema: array<string, mixed>}
     */
    public function toAnthropicFormat(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->parameters->toJsonSchema(),
        ];
    }

    /**
     * Legacy toArray method for backwards compatibility.
     *
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function toArray(): array
    {
        return $this->toOpenAIFormat();
    }

    /**
     * Build parameter schema from method reflection.
     */
    private static function buildParameterSchema(ReflectionMethod $method): ObjectSchema
    {
        /** @var array<string, Schema> $properties */
        $properties = [];

        foreach ($method->getParameters() as $param) {
            $schema = self::createSchemaFromParameter($param);
            $properties[$param->getName()] = $schema;
        }

        return Schema::object($properties);
    }

    /**
     * Create a schema from a method parameter.
     */
    private static function createSchemaFromParameter(ReflectionParameter $param): Schema
    {
        $type = $param->getType();

        // Get Parameter attribute if present
        $paramAttrs = $param->getAttributes(Parameter::class);
        $paramAttr = $paramAttrs[0] ?? null;

        /** @var Parameter|null $paramInstance */
        $paramInstance = $paramAttr?->newInstance();

        // Create schema based on type
        $schema = self::createSchemaFromType($type, $param->getName());

        // Apply description from attribute
        if ($paramInstance?->description !== null) {
            $schema->description($paramInstance->description);
        }

        // Handle optional parameters
        if ($param->isOptional() || $param->isDefaultValueAvailable() || ($paramInstance?->optional ?? false)) {
            $schema->optional();
        }

        // Handle default value
        if ($param->isDefaultValueAvailable()) {
            $schema->default($param->getDefaultValue());
        }

        return $schema;
    }

    /**
     * Create schema from reflection type.
     */
    private static function createSchemaFromType(
        \ReflectionType|null $type,
        string $paramName,
    ): Schema {
        if ($type === null) {
            // No type hint, default to string
            return Schema::string();
        }

        if ($type instanceof ReflectionUnionType) {
            $schemas = [];
            $nullable = false;

            foreach ($type->getTypes() as $memberType) {
                if (!$memberType instanceof ReflectionNamedType) {
                    continue;
                }

                if ($memberType->getName() === 'null') {
                    $nullable = true;
                    continue;
                }

                $schemas[] = self::createSchemaFromNamedType($memberType);
            }

            if (empty($schemas)) {
                return Schema::string();
            }

            $schema = count($schemas) === 1 ? $schemas[0] : Schema::union($schemas);

            if ($nullable) {
                $schema = Schema::nullable($schema);
            }

            return $schema;
        }

        if ($type instanceof ReflectionNamedType) {
            $schema = self::createSchemaFromNamedType($type);

            if ($type->allowsNull()) {
                $schema = Schema::nullable($schema);
            }

            return $schema;
        }

        return Schema::string();
    }

    /**
     * Create schema from named type.
     */
    private static function createSchemaFromNamedType(ReflectionNamedType $type): Schema
    {
        $typeName = $type->getName();

        if ($type->isBuiltin()) {
            return match ($typeName) {
                'string' => Schema::string(),
                'int' => Schema::integer(),
                'float' => Schema::number(),
                'bool' => Schema::boolean(),
                'array' => Schema::array(Schema::string()), // Default to string array
                'mixed' => Schema::string(), // Default mixed to string
                default => Schema::string(),
            };
        }

        // Handle enum types
        if (enum_exists($typeName)) {
            /** @var class-string<\UnitEnum> $typeName */
            $reflectionEnum = new \ReflectionEnum($typeName);
            if ($reflectionEnum->isBacked()) {
                $values = array_map(fn ($case) => $case->getBackingValue(), $reflectionEnum->getCases());
                return Schema::enum($values);
            }
            $values = array_map(fn ($case) => $case->name, $reflectionEnum->getCases());
            return Schema::enum($values);
        }

        // Handle class types
        if (class_exists($typeName)) {
            /** @var class-string $typeName */
            return Schema::fromClass($typeName);
        }

        return Schema::string();
    }
}
