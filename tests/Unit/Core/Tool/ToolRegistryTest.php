<?php

namespace Tests\Unit\Core\Tool;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SageGrids\PhpAiSdk\Core\Schema\Schema;
use SageGrids\PhpAiSdk\Core\Tool\Attributes\Tool as ToolAttribute;
use SageGrids\PhpAiSdk\Core\Tool\Tool;
use SageGrids\PhpAiSdk\Core\Tool\ToolRegistry;

final class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ToolRegistry();
    }

    public function testRegisterAndGet(): void
    {
        $tool = Tool::create(
            name: 'my_tool',
            description: 'A test tool',
            parameters: Schema::object([]),
        );

        $this->registry->register($tool);

        $retrieved = $this->registry->get('my_tool');

        $this->assertSame($tool, $retrieved);
    }

    public function testRegisterThrowsOnDuplicate(): void
    {
        $tool1 = Tool::create(
            name: 'duplicate',
            description: 'First tool',
            parameters: Schema::object([]),
        );

        $tool2 = Tool::create(
            name: 'duplicate',
            description: 'Second tool',
            parameters: Schema::object([]),
        );

        $this->registry->register($tool1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tool already registered: duplicate');

        $this->registry->register($tool2);
    }

    public function testSetReplacesExisting(): void
    {
        $tool1 = Tool::create(
            name: 'replaceable',
            description: 'First version',
            parameters: Schema::object([]),
        );

        $tool2 = Tool::create(
            name: 'replaceable',
            description: 'Second version',
            parameters: Schema::object([]),
        );

        $this->registry->set($tool1);
        $this->registry->set($tool2);

        $retrieved = $this->registry->get('replaceable');

        $this->assertSame('Second version', $retrieved->description);
    }

    public function testGetReturnsNullForMissing(): void
    {
        $this->assertNull($this->registry->get('nonexistent'));
    }

    public function testHas(): void
    {
        $tool = Tool::create(
            name: 'exists',
            description: 'Test',
            parameters: Schema::object([]),
        );

        $this->assertFalse($this->registry->has('exists'));

        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('exists'));
    }

    public function testRemove(): void
    {
        $tool = Tool::create(
            name: 'removable',
            description: 'Test',
            parameters: Schema::object([]),
        );

        $this->registry->register($tool);
        $this->assertTrue($this->registry->has('removable'));

        $this->registry->remove('removable');
        $this->assertFalse($this->registry->has('removable'));
    }

    public function testAll(): void
    {
        $tool1 = Tool::create('tool1', 'First', Schema::object([]));
        $tool2 = Tool::create('tool2', 'Second', Schema::object([]));

        $this->registry->register($tool1);
        $this->registry->register($tool2);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('tool1', $all);
        $this->assertArrayHasKey('tool2', $all);
    }

    public function testNames(): void
    {
        $this->registry->register(Tool::create('alpha', '', Schema::object([])));
        $this->registry->register(Tool::create('beta', '', Schema::object([])));
        $this->registry->register(Tool::create('gamma', '', Schema::object([])));

        $names = $this->registry->names();

        $this->assertSame(['alpha', 'beta', 'gamma'], $names);
    }

    public function testCount(): void
    {
        $this->assertSame(0, $this->registry->count());

        $this->registry->register(Tool::create('tool1', '', Schema::object([])));
        $this->assertSame(1, $this->registry->count());

        $this->registry->register(Tool::create('tool2', '', Schema::object([])));
        $this->assertSame(2, $this->registry->count());
    }

    public function testClear(): void
    {
        $this->registry->register(Tool::create('tool1', '', Schema::object([])));
        $this->registry->register(Tool::create('tool2', '', Schema::object([])));

        $this->assertSame(2, $this->registry->count());

        $this->registry->clear();

        $this->assertSame(0, $this->registry->count());
        $this->assertEmpty($this->registry->all());
    }

    public function testRegisterObject(): void
    {
        $instance = new class {
            #[ToolAttribute(name: 'add', description: 'Add two numbers')]
            public function addNumbers(int $a, int $b): int
            {
                return $a + $b;
            }

            #[ToolAttribute(name: 'subtract', description: 'Subtract two numbers')]
            public function subtractNumbers(int $a, int $b): int
            {
                return $a - $b;
            }

            public function notATool(): void
            {
                // This should not be registered
            }
        };

        $this->registry->registerObject($instance);

        $this->assertTrue($this->registry->has('add'));
        $this->assertTrue($this->registry->has('subtract'));
        $this->assertFalse($this->registry->has('notATool'));
        $this->assertSame(2, $this->registry->count());
    }

    public function testToProviderFormat(): void
    {
        $this->registry->register(Tool::create(
            name: 'search',
            description: 'Search the web',
            parameters: Schema::object([
                'query' => Schema::string(),
            ]),
        ));

        $openaiFormat = $this->registry->toProviderFormat('openai');

        $this->assertCount(1, $openaiFormat);
        $this->assertSame('function', $openaiFormat[0]['type']);
        $this->assertSame('search', $openaiFormat[0]['function']['name']);
    }

    public function testToArray(): void
    {
        $this->registry->register(Tool::create(
            name: 'tool1',
            description: 'First tool',
            parameters: Schema::object([]),
        ));

        $this->registry->register(Tool::create(
            name: 'tool2',
            description: 'Second tool',
            parameters: Schema::object([]),
        ));

        $array = $this->registry->toArray();

        $this->assertCount(2, $array);
        $this->assertSame('tool1', $array[0]['function']['name']);
        $this->assertSame('tool2', $array[1]['function']['name']);
    }

    public function testFluentInterface(): void
    {
        $result = $this->registry
            ->register(Tool::create('a', '', Schema::object([])))
            ->register(Tool::create('b', '', Schema::object([])))
            ->remove('a')
            ->set(Tool::create('c', '', Schema::object([])));

        $this->assertSame($this->registry, $result);
        $this->assertFalse($this->registry->has('a'));
        $this->assertTrue($this->registry->has('b'));
        $this->assertTrue($this->registry->has('c'));
    }
}
