<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SageGrids\PhpAiSdk\Exception\AIException;
use SageGrids\PhpAiSdk\Exception\InputValidationException;
use SageGrids\PhpAiSdk\Exception\SchemaValidationException;
use SageGrids\PhpAiSdk\Exception\ValidationError;
use SageGrids\PhpAiSdk\Exception\ValidationException;

final class ValidationExceptionTest extends TestCase
{
    public function testIsAIException(): void
    {
        $exception = new ValidationException('Validation failed');

        $this->assertInstanceOf(AIException::class, $exception);
    }

    public function testConstructWithErrors(): void
    {
        $errors = [
            new ValidationError('name', 'Name is required'),
            new ValidationError('email', 'Invalid email format'),
        ];

        $exception = new ValidationException('Validation failed', $errors);

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertCount(2, $exception->errors);
    }

    public function testFromErrors(): void
    {
        $errors = [
            new ValidationError('field1', 'Error 1'),
            new ValidationError('field2', 'Error 2'),
        ];

        $exception = ValidationException::fromErrors($errors);

        $this->assertStringContainsString('2 error(s)', $exception->getMessage());
        $this->assertCount(2, $exception->errors);
    }

    public function testWithError(): void
    {
        $error = new ValidationError('test', 'Test error');

        $exception = ValidationException::withError($error);

        $this->assertCount(1, $exception->errors);
        $this->assertSame($error, $exception->errors[0]);
    }

    public function testHasErrors(): void
    {
        $withErrors = new ValidationException('Failed', [new ValidationError('x', 'y')]);
        $withoutErrors = new ValidationException('No errors');

        $this->assertTrue($withErrors->hasErrors());
        $this->assertFalse($withoutErrors->hasErrors());
    }

    public function testGetFirstError(): void
    {
        $error1 = new ValidationError('first', 'First error');
        $error2 = new ValidationError('second', 'Second error');

        $withErrors = new ValidationException('Failed', [$error1, $error2]);
        $withoutErrors = new ValidationException('No errors');

        $this->assertSame($error1, $withErrors->getFirstError());
        $this->assertNull($withoutErrors->getFirstError());
    }

    public function testGetErrorsForPath(): void
    {
        $errors = [
            new ValidationError('name', 'Error 1'),
            new ValidationError('email', 'Error 2'),
            new ValidationError('name', 'Error 3'),
        ];

        $exception = new ValidationException('Failed', $errors);

        $nameErrors = $exception->getErrorsForPath('name');
        $emailErrors = $exception->getErrorsForPath('email');
        $phoneErrors = $exception->getErrorsForPath('phone');

        $this->assertCount(2, $nameErrors);
        $this->assertCount(1, $emailErrors);
        $this->assertCount(0, $phoneErrors);
    }

    public function testGetErrorPaths(): void
    {
        $errors = [
            new ValidationError('name', 'Error 1'),
            new ValidationError('email', 'Error 2'),
            new ValidationError('name', 'Error 3'),
        ];

        $exception = new ValidationException('Failed', $errors);

        $paths = $exception->getErrorPaths();

        $this->assertCount(2, $paths);
        $this->assertContains('name', $paths);
        $this->assertContains('email', $paths);
    }

    public function testToArray(): void
    {
        $errors = [
            new ValidationError('field', 'Error message', 'value'),
        ];

        $exception = new ValidationException('Failed', $errors);

        $array = $exception->toArray();

        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('errorCount', $array);
        $this->assertEquals(1, $array['errorCount']);
    }
}

final class SchemaValidationExceptionTest extends TestCase
{
    public function testIsValidationException(): void
    {
        $exception = new SchemaValidationException('Schema validation failed');

        $this->assertInstanceOf(ValidationException::class, $exception);
    }

    public function testConstructWithSchemaName(): void
    {
        $exception = new SchemaValidationException('Failed', [], 'UserSchema');

        $this->assertEquals('UserSchema', $exception->schemaName);
    }

    public function testFromSchemaErrors(): void
    {
        $errors = [new ValidationError('name', 'Required')];

        $exception = SchemaValidationException::fromSchemaErrors($errors, 'UserSchema');

        $this->assertStringContainsString('UserSchema', $exception->getMessage());
        $this->assertStringContainsString('1 error(s)', $exception->getMessage());
        $this->assertEquals('UserSchema', $exception->schemaName);
    }

    public function testFromSchemaErrorsWithoutSchemaName(): void
    {
        $errors = [new ValidationError('name', 'Required')];

        $exception = SchemaValidationException::fromSchemaErrors($errors);

        $this->assertStringContainsString('Schema validation failed', $exception->getMessage());
        $this->assertNull($exception->schemaName);
    }

    public function testInvalidJsonOutput(): void
    {
        $exception = SchemaValidationException::invalidJsonOutput('invalid json', 'ResponseSchema');

        $this->assertStringContainsString('not valid JSON', $exception->getMessage());
        $this->assertEquals('ResponseSchema', $exception->schemaName);
        $this->assertCount(1, $exception->errors);
    }

    public function testMissingProperty(): void
    {
        $exception = SchemaValidationException::missingProperty('name', 'UserSchema');

        $this->assertStringContainsString('name', $exception->getMessage());
        $this->assertStringContainsString('missing', $exception->getMessage());
    }

    public function testToArray(): void
    {
        $exception = new SchemaValidationException('Failed', [], 'TestSchema');

        $array = $exception->toArray();

        $this->assertEquals('TestSchema', $array['schemaName']);
    }
}

final class InputValidationExceptionTest extends TestCase
{
    public function testIsValidationException(): void
    {
        $exception = new InputValidationException('Input validation failed');

        $this->assertInstanceOf(ValidationException::class, $exception);
    }

    public function testConstructWithInputName(): void
    {
        $exception = new InputValidationException('Failed', [], 'prompt');

        $this->assertEquals('prompt', $exception->inputName);
    }

    public function testFromInputErrors(): void
    {
        $errors = [new ValidationError('prompt', 'Cannot be empty')];

        $exception = InputValidationException::fromInputErrors($errors, 'prompt');

        $this->assertStringContainsString('prompt', $exception->getMessage());
        $this->assertEquals('prompt', $exception->inputName);
    }

    public function testRequiredParameter(): void
    {
        $exception = InputValidationException::requiredParameter('apiKey');

        $this->assertStringContainsString('apiKey', $exception->getMessage());
        $this->assertStringContainsString('missing', $exception->getMessage());
        $this->assertEquals('apiKey', $exception->inputName);
    }

    public function testInvalidParameterType(): void
    {
        $exception = InputValidationException::invalidParameterType('temperature', 'float', 'not a number');

        $this->assertStringContainsString('temperature', $exception->getMessage());
        $this->assertStringContainsString('invalid type', $exception->getMessage());
    }

    public function testInvalidParameterValue(): void
    {
        $exception = InputValidationException::invalidParameterValue('temperature', 'Must be between 0 and 2', 5.0);

        $this->assertStringContainsString('temperature', $exception->getMessage());
        $this->assertStringContainsString('Must be between 0 and 2', $exception->getMessage());
    }

    public function testEmptyInput(): void
    {
        $exception = InputValidationException::emptyInput('messages');

        $this->assertStringContainsString('messages', $exception->getMessage());
        $this->assertStringContainsString('empty', $exception->getMessage());
    }

    public function testToArray(): void
    {
        $exception = new InputValidationException('Failed', [], 'testInput');

        $array = $exception->toArray();

        $this->assertEquals('testInput', $array['inputName']);
    }
}
