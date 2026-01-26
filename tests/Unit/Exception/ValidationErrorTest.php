<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\ValidationError;

final class ValidationErrorTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $error = new ValidationError('users[0].name', 'Name is required', null);

        $this->assertEquals('users[0].name', $error->path);
        $this->assertEquals('Name is required', $error->message);
        $this->assertNull($error->value);
    }

    public function testConstructWithValue(): void
    {
        $error = new ValidationError('age', 'Must be positive', -5);

        $this->assertEquals(-5, $error->value);
    }

    public function testRequired(): void
    {
        $error = ValidationError::required('email');

        $this->assertEquals('email', $error->path);
        $this->assertStringContainsString('email', $error->message);
        $this->assertStringContainsString('required', $error->message);
    }

    public function testInvalidType(): void
    {
        $error = ValidationError::invalidType('count', 'int', 'not a number');

        $this->assertEquals('count', $error->path);
        $this->assertStringContainsString('int', $error->message);
        $this->assertStringContainsString('string', $error->message);
        $this->assertEquals('not a number', $error->value);
    }

    public function testInvalidTypeWithArray(): void
    {
        $error = ValidationError::invalidType('data', 'string', ['a', 'b']);

        $this->assertStringContainsString('array', $error->message);
    }

    public function testOutOfRange(): void
    {
        $error = ValidationError::outOfRange('temperature', 0, 2, 5.5);

        $this->assertEquals('temperature', $error->path);
        $this->assertStringContainsString('0', $error->message);
        $this->assertStringContainsString('2', $error->message);
        $this->assertStringContainsString('5.5', $error->message);
        $this->assertEquals(5.5, $error->value);
    }

    public function testLengthOutOfRange(): void
    {
        $error = ValidationError::lengthOutOfRange('password', 8, 100, 'short');

        $this->assertStringContainsString('8', $error->message);
        $this->assertStringContainsString('100', $error->message);
        $this->assertStringContainsString('5', $error->message); // actual length
    }

    public function testInvalidEnumValue(): void
    {
        $error = ValidationError::invalidEnumValue('role', ['admin', 'user', 'guest'], 'superadmin');

        $this->assertStringContainsString('admin', $error->message);
        $this->assertStringContainsString('user', $error->message);
        $this->assertStringContainsString('guest', $error->message);
        $this->assertEquals('superadmin', $error->value);
    }

    public function testPatternMismatch(): void
    {
        $error = ValidationError::patternMismatch('email', '^[a-z]+@[a-z]+\\.[a-z]+$', 'invalid-email');

        $this->assertStringContainsString('pattern', $error->message);
        $this->assertEquals('invalid-email', $error->value);
    }

    public function testToArray(): void
    {
        $error = new ValidationError('field', 'Error message', 'invalid-value');

        $array = $error->toArray();

        $this->assertEquals('field', $array['path']);
        $this->assertEquals('Error message', $array['message']);
        $this->assertEquals('invalid-value', $array['value']);
    }

    public function testToString(): void
    {
        $error = new ValidationError('users[0].name', 'Name is required');

        $string = (string) $error;

        $this->assertEquals('[users[0].name] Name is required', $string);
    }

    public function testIsReadonly(): void
    {
        $error = new ValidationError('test', 'message', 'value');

        $reflection = new \ReflectionClass($error);

        $this->assertTrue($reflection->isReadOnly());
    }
}
