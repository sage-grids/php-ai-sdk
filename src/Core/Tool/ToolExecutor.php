<?php

declare(strict_types=1);

namespace SageGrids\PhpAiSdk\Core\Tool;

use SageGrids\PhpAiSdk\Result\ToolCall;
use Throwable;

/**
 * Executes tools and handles errors.
 */
final class ToolExecutor
{
    /**
     * Execute a tool with the given tool call.
     *
     * @param Tool $tool The tool to execute.
     * @param ToolCall $call The tool call containing arguments.
     * @return ToolResult The result of the execution.
     */
    public function execute(Tool $tool, ToolCall $call): ToolResult
    {
        try {
            $result = $tool->execute($call->arguments);
            return ToolResult::success($call->id, $result);
        } catch (Throwable $e) {
            return ToolResult::failure($call->id, $e);
        }
    }

    /**
     * Execute multiple tools from a registry.
     *
     * @param ToolRegistry $registry The tool registry.
     * @param array<ToolCall> $calls The tool calls to execute.
     * @return array<ToolResult> The results of all executions.
     */
    public function executeAll(ToolRegistry $registry, array $calls): array
    {
        $results = [];

        foreach ($calls as $call) {
            $tool = $registry->get($call->name);

            if ($tool === null) {
                $results[] = ToolResult::failure(
                    $call->id,
                    new \RuntimeException("Tool not found: {$call->name}")
                );
                continue;
            }

            $results[] = $this->execute($tool, $call);
        }

        return $results;
    }
}
