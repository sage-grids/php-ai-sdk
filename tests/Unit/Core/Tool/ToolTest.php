<?php

namespace Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Attributes\Parameter;
use SageGrids\PhpAiSdk\Core\Tool\Attributes\Tool as ToolAttribute;
use SageGrids\PhpAiSdk\Core\Tool\Tool;

final class ToolTest extends TestCase
{
    public function testCreateWithCallable(): void
    {
        $tool = Tool::create(
            name: 'get_weather',
            description: 'Get the current weather for a location',
            parameters: Schema::object([
                'location' => Schema::string()->description('City name'),
            ]),
            execute: fn (array $args) => "Weather in {$args['location']}: Sunny, 22°C",
        );

        $this->assertSame('get_weather', $tool->name);
        $this->assertSame('Get the current weather for a location', $tool->description);
        $this->assertTrue($tool->isExecutable());
    }

    public function testCreateWithoutCallable(): void
    {
        $tool = Tool::create(
            name: 'get_weather',
            description: 'Get the current weather',
            parameters: Schema::object([]),
        );

        $this->assertFalse($tool->isExecutable());
    }

    public function testExecuteWithValidArguments(): void
    {
        $tool = Tool::create(
            name: 'add_numbers',
            description: 'Add two numbers',
            parameters: Schema::object([
                'a' => Schema::integer(),
                'b' => Schema::integer(),
            ]),
            execute: fn (array $args) => $args['a'] + $args['b'],
        );

        $result = $tool->execute(['a' => 5, 'b' => 3]);

        $this->assertSame(8, $result);
    }

    public function testExecuteThrowsOnMissingHandler(): void
    {
        $tool = Tool::create(
            name: 'no_handler',
            description: 'Tool without handler',
            parameters: Schema::object([]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Tool 'no_handler' is not executable");

        $tool->execute([]);
    }

    public function testExecuteThrowsOnInvalidArguments(): void
    {
        $tool = Tool::create(
            name: 'greet',
            description: 'Greet a person',
            parameters: Schema::object([
                'name' => Schema::string(),
            ]),
            execute: fn (array $args) => "Hello, {$args['name']}!",
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("validation failed");

        $tool->execute([]); // Missing required 'name'
    }

    public function testFromMethodWithAttribute(): void
    {
        $instance = new class {
            #[ToolAttribute(name: 'calculator_add', description: 'Add two numbers together')]
            public function add(
                #[Parameter(description: 'First number')]
                int $a,
                #[Parameter(description: 'Second number')]
                int $b,
            ): int {
                return $a + $b;
            }
        };

        $tool = Tool::fromMethod($instance, 'add');

        $this->assertSame('calculator_add', $tool->name);
        $this->assertSame('Add two numbers together', $tool->description);
        $this->assertTrue($tool->isExecutable());
    }

    public function testFromMethodUsesMethodNameWhenNoNameAttribute(): void
    {
        $instance = new class {
            #[ToolAttribute(description: 'Multiply two numbers')]
            public function multiply(int $a, int $b): int
            {
                return $a * $b;
            }
        };

        $tool = Tool::fromMethod($instance, 'multiply');

        $this->assertSame('multiply', $tool->name);
        $this->assertSame('Multiply two numbers', $tool->description);
    }

    public function testFromMethodWithOptionalParameters(): void
    {
        $instance = new class {
            #[ToolAttribute(description: 'Greet someone')]
            public function greet(
                #[Parameter(description: 'Name to greet')]
                string $name,
                #[Parameter(description: 'Custom greeting', optional: true)]
                string $greeting = 'Hello',
            ): string {
                return "$greeting, $name!";
            }
        };

        $tool = Tool::fromMethod($instance, 'greet');
        $result = $tool->execute(['name' => 'World']);

        $this->assertSame('Hello, World!', $result);
    }

    public function testFromMethodExecutesCorrectly(): void
    {
        $instance = new class {
            #[ToolAttribute(name: 'divide', description: 'Divide two numbers')]
            public function divide(float $a, float $b): float
            {
                return $a / $b;
            }
        };

        $tool = Tool::fromMethod($instance, 'divide');
        $result = $tool->execute(['a' => 10.0, 'b' => 2.0]);

        $this->assertSame(5.0, $result);
    }

    public function testToOpenAIFormat(): void
    {
        $tool = Tool::create(
            name: 'search',
            description: 'Search for information',
            parameters: Schema::object([
                'query' => Schema::string()->description('Search query'),
            ]),
        );

        $format = $tool->toOpenAIFormat();

        $this->assertSame('function', $format['type']);
        $this->assertSame('search', $format['function']['name']);
        $this->assertSame('Search for information', $format['function']['description']);
        $this->assertArrayHasKey('parameters', $format['function']);
    }

    public function testToGeminiFormat(): void
    {
        $tool = Tool::create(
            name: 'search',
            description: 'Search for information',
            parameters: Schema::object([
                'query' => Schema::string(),
            ]),
        );

        $format = $tool->toGeminiFormat();

        $this->assertSame('search', $format['name']);
        $this->assertSame('Search for information', $format['description']);
        $this->assertArrayHasKey('parameters', $format);
        $this->assertArrayNotHasKey('type', $format);
        $this->assertArrayNotHasKey('function', $format);
    }

    public function testToAnthropicFormat(): void
    {
        $tool = Tool::create(
            name: 'search',
            description: 'Search for information',
            parameters: Schema::object([
                'query' => Schema::string(),
            ]),
        );

        $format = $tool->toAnthropicFormat();

        $this->assertSame('search', $format['name']);
        $this->assertSame('Search for information', $format['description']);
        $this->assertArrayHasKey('input_schema', $format);
        $this->assertArrayNotHasKey('parameters', $format);
    }

    public function testToProviderFormatSelectsCorrectFormat(): void
    {
        $tool = Tool::create(
            name: 'test',
            description: 'Test tool',
            parameters: Schema::object([]),
        );

        // OpenAI format
        $openai = $tool->toProviderFormat('openai');
        $this->assertArrayHasKey('type', $openai);
        $this->assertArrayHasKey('function', $openai);

        // Gemini format
        $gemini = $tool->toProviderFormat('gemini');
        $this->assertArrayNotHasKey('type', $gemini);
        $this->assertArrayNotHasKey('function', $gemini);
        $this->assertArrayHasKey('parameters', $gemini);

        // Anthropic format
        $anthropic = $tool->toProviderFormat('anthropic');
        $this->assertArrayHasKey('input_schema', $anthropic);

        // Unknown defaults to OpenAI
        $unknown = $tool->toProviderFormat('unknown_provider');
        $this->assertArrayHasKey('type', $unknown);
    }

    public function testToArrayIsBackwardsCompatible(): void
    {
        $tool = Tool::create(
            name: 'test',
            description: 'Test tool',
            parameters: Schema::object([]),
        );

        $array = $tool->toArray();

        $this->assertSame($tool->toOpenAIFormat(), $array);
    }

    public function testCreateWithReturnSchema(): void
    {
        $tool = Tool::create(
            name: 'get_user',
            description: 'Get user data',
            parameters: Schema::object([
                'id' => Schema::integer(),
            ]),
            execute: fn (array $args) => ['id' => $args['id'], 'name' => 'John'],
            returnSchema: Schema::object([
                'id' => Schema::integer(),
                'name' => Schema::string(),
            ]),
        );

        $this->assertNotNull($tool->returnSchema);
    }

    public function testExecuteWithValidReturnSchema(): void
    {
        $tool = Tool::create(
            name: 'get_user',
            description: 'Get user data',
            parameters: Schema::object([
                'id' => Schema::integer(),
            ]),
            execute: fn (array $args) => ['id' => $args['id'], 'name' => 'John'],
            returnSchema: Schema::object([
                'id' => Schema::integer(),
                'name' => Schema::string(),
            ]),
        );

        $result = $tool->execute(['id' => 42]);

        $this->assertSame(['id' => 42, 'name' => 'John'], $result);
    }

    public function testExecuteThrowsOnInvalidReturnValue(): void
    {
        $tool = Tool::create(
            name: 'get_user',
            description: 'Get user data',
            parameters: Schema::object([
                'id' => Schema::integer(),
            ]),
            execute: fn (array $args) => ['id' => 'not-an-integer', 'name' => 'John'],
            returnSchema: Schema::object([
                'id' => Schema::integer(),
                'name' => Schema::string(),
            ]),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Tool 'get_user' return value validation failed");

        $tool->execute(['id' => 42]);
    }

    public function testExecuteWithoutReturnSchemaSkipsValidation(): void
    {
        $tool = Tool::create(
            name: 'get_anything',
            description: 'Get any data',
            parameters: Schema::object([]),
            execute: fn (array $args) => ['any' => 'value', 'number' => 123],
        );

        // Should not throw, no return schema validation
        $result = $tool->execute([]);

        $this->assertSame(['any' => 'value', 'number' => 123], $result);
    }

    public function testExecuteWithPrimitiveReturnSchema(): void
    {
        $tool = Tool::create(
            name: 'get_count',
            description: 'Get a count',
            parameters: Schema::object([]),
            execute: fn (array $args) => 42,
            returnSchema: Schema::integer(),
        );

        $result = $tool->execute([]);

        $this->assertSame(42, $result);
    }

    public function testExecuteWithArrayReturnSchema(): void
    {
        $tool = Tool::create(
            name: 'get_numbers',
            description: 'Get array of numbers',
            parameters: Schema::object([]),
            execute: fn (array $args) => [1, 2, 3, 4, 5],
            returnSchema: Schema::array(Schema::integer()),
        );

        $result = $tool->execute([]);

        $this->assertSame([1, 2, 3, 4, 5], $result);
    }
}
