<?php

namespace Tests\Unit\Core\Schema;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Schema\ValidationResult;

final class SchemaTest extends TestCase
{
    public function testStringSchema(): void
    {
        $schema = Schema::string()
            ->minLength(3)
            ->maxLength(5)
            ->pattern('/^[a-z]+$/');

        $this->assertTrue($schema->validate('abc')->isValid);
        $this->assertTrue($schema->validate('abcde')->isValid);
        
        $this->assertFalse($schema->validate('ab')->isValid); // Too short
        $this->assertFalse($schema->validate('abcdef')->isValid); // Too long
        $this->assertFalse($schema->validate('123')->isValid); // Pattern mismatch
        $this->assertFalse($schema->validate(123)->isValid); // Wrong type
    }

    public function testNumberSchema(): void
    {
        $schema = Schema::number()->minimum(0)->maximum(10);

        $this->assertTrue($schema->validate(5)->isValid);
        $this->assertTrue($schema->validate(0)->isValid);
        $this->assertTrue($schema->validate(10.0)->isValid);

        $this->assertFalse($schema->validate(-1)->isValid);
        $this->assertFalse($schema->validate(11)->isValid);
    }

    public function testArraySchema(): void
    {
        $schema = Schema::array(Schema::integer())->minItems(1)->maxItems(3);

        $this->assertTrue($schema->validate([1])->isValid);
        $this->assertTrue($schema->validate([1, 2, 3])->isValid);

        $this->assertFalse($schema->validate([])->isValid); // Min items
        $this->assertFalse($schema->validate([1, 2, 3, 4])->isValid); // Max items
        $this->assertFalse($schema->validate(['a'])->isValid); // Wrong item type
    }

    public function testObjectSchema(): void
    {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::integer()->optional(),
        ])->additionalProperties(false);

        $this->assertTrue($schema->validate((object)['name' => 'John'])->isValid);
        $this->assertTrue($schema->validate(['name' => 'John', 'age' => 30])->isValid);

        $this->assertFalse($schema->validate(['age' => 30])->isValid); // Missing required
        $this->assertFalse($schema->validate(['name' => 'John', 'extra' => 1])->isValid); // Extra prop
    }

    public function testEnumSchema(): void
    {
        $schema = Schema::enum(['a', 'b']);
        
        $this->assertTrue($schema->validate('a')->isValid);
        $this->assertFalse($schema->validate('c')->isValid);
    }
}
