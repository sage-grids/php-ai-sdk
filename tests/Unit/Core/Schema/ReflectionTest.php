<?php

namespace Tests\Unit\Core\Schema;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Schema\Attributes\ArrayItems;
use SageGrids\PhpAiSdk\Core\Schema\Attributes\Description;
use SageGrids\PhpAiSdk\Core\Schema\Attributes\Minimum;
use SageGrids\PhpAiSdk\Core\Schema\Schema;

class TestUser
{
    #[Description('User Name')]
    public string $name;

    #[Minimum(18)]
    public int $age;

    #[ArrayItems('string', minItems: 1)]
    public array $tags;

    public ?string $bio = null;
}

#[Description('Described User')]
class DescribedUser
{
    public string $name;
}

class UnionUser
{
    public string|int $id;
    public string|int|null $token = null;
}

class MissingArrayItems
{
    public array $items;
}

class UntypedUser
{
    public $name;
}

final class ReflectionTest extends TestCase
{
    public function testFromClass(): void
    {
        $schema = Schema::fromClass(TestUser::class);
        $json = $schema->toJsonSchema();

        $this->assertEquals('object', $json['type']);
        $this->assertArrayHasKey('required', $json);
        $this->assertContains('name', $json['required']);
        $this->assertContains('age', $json['required']);
        $this->assertContains('tags', $json['required']);
        $this->assertNotContains('bio', $json['required']); // Optional because of default null

        $props = $json['properties'];
        
        // Name
        $this->assertEquals('string', $props['name']['type']);
        $this->assertEquals('User Name', $props['name']['description']);

        // Age
        $this->assertEquals('integer', $props['age']['type']);
        $this->assertEquals(18, $props['age']['minimum']);

        // Tags
        $this->assertEquals('array', $props['tags']['type']);
        $this->assertEquals('string', $props['tags']['items']['type']);
        $this->assertEquals(1, $props['tags']['minItems']);
        
        // Bio
        // Since it's ?string, it might be wrapped in nullable schema logic
        // But our implementation handles nullable by wrapping. 
        // NullableSchema::toJsonSchema handles "type": ["string", "null"] or anyOf with null.
        
        // Let's verify structure
        // Implementation logic:
        // if type->allowsNull() -> Schema::nullable($schema)
        // NullableSchema->toJsonSchema() tries to append 'null' to type array
        
        $this->assertContains('null', (array)$props['bio']['type']);
    }

    public function testClassDescription(): void
    {
        $schema = Schema::fromClass(DescribedUser::class);
        $json = $schema->toJsonSchema();

        $this->assertEquals('Described User', $json['description']);
    }

    public function testUnionTypes(): void
    {
        $schema = Schema::fromClass(UnionUser::class);
        $json = $schema->toJsonSchema();

        $props = $json['properties'];

        $this->assertArrayHasKey('anyOf', $props['id']);
        $this->assertCount(2, $props['id']['anyOf']);

        $this->assertArrayHasKey('anyOf', $props['token']);
        $hasNull = false;
        foreach ($props['token']['anyOf'] as $option) {
            if (($option['type'] ?? null) === 'null') {
                $hasNull = true;
                break;
            }
        }
        $this->assertTrue($hasNull);
    }

    public function testMissingArrayItemsThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Array property items must have #[ArrayItems] attribute');
        Schema::fromClass(MissingArrayItems::class);
    }

    public function testUntypedPropertyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Property name must have a type for schema generation.');
        Schema::fromClass(UntypedUser::class);
    }
}
