<?php

/**
 * Multi-Turn Conversation Example
 *
 * This example demonstrates how to maintain conversation context across multiple turns.
 *
 * Run: php docs/examples/multi-turn-conversation.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use SageGrids\PhpAiSdk\AI;
use SageGrids\PhpAiSdk\Core\Message\AssistantMessage;
use SageGrids\PhpAiSdk\Core\Message\SystemMessage;
use SageGrids\PhpAiSdk\Core\Message\UserMessage;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIConfig;
use SageGrids\PhpAiSdk\Provider\OpenAI\OpenAIProvider;
use SageGrids\PhpAiSdk\Provider\ProviderRegistry;

// Create and register the OpenAI provider
$provider = new OpenAIProvider(
    new OpenAIConfig(
        apiKey: getenv('OPENAI_API_KEY') ?: 'your-api-key-here',
    ),
);

ProviderRegistry::getInstance()->register('openai', $provider);

// ============================================================================
// Example 1: Basic multi-turn conversation
// ============================================================================

echo "Example 1: Basic multi-turn conversation\n";
echo str_repeat('-', 40) . "\n";

// Start with a conversation history
$messages = [
    new UserMessage('Hi! My name is Alice.'),
    new AssistantMessage('Hello Alice! Nice to meet you. How can I help you today?'),
    new UserMessage('I\'m learning PHP programming.'),
    new AssistantMessage('That\'s great! PHP is a powerful language for web development. What would you like to know about PHP?'),
    new UserMessage('What\'s my name again?'), // Test context retention
];

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'messages' => $messages,
]);

echo "Response: {$result->text}\n\n";

// ============================================================================
// Example 2: Conversation with system message
// ============================================================================

echo "Example 2: Conversation with persona\n";
echo str_repeat('-', 40) . "\n";

$messages = [
    new UserMessage('Tell me a fun fact.'),
];

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'system' => 'You are a pirate captain named Captain Codebeard. You love programming and speak in pirate talk. Keep responses brief and entertaining.',
    'messages' => $messages,
]);

echo "Captain Codebeard: {$result->text}\n\n";

// Continue the conversation
$messages[] = new AssistantMessage($result->text);
$messages[] = new UserMessage('What\'s your favorite programming language?');

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'system' => 'You are a pirate captain named Captain Codebeard. You love programming and speak in pirate talk. Keep responses brief and entertaining.',
    'messages' => $messages,
]);

echo "Captain Codebeard: {$result->text}\n\n";

// ============================================================================
// Example 3: Building a conversation loop
// ============================================================================

echo "Example 3: Interactive conversation simulation\n";
echo str_repeat('-', 40) . "\n";

// Simulated user inputs for the example
$userInputs = [
    'I want to learn about arrays in PHP.',
    'How do I add an element to an array?',
    'Thanks, that\'s helpful!',
];

$conversationHistory = [];
$systemPrompt = 'You are a helpful PHP tutor. Provide clear, concise explanations with code examples when appropriate. Keep responses focused and educational.';

foreach ($userInputs as $input) {
    echo "User: {$input}\n";

    // Add user message to history
    $conversationHistory[] = new UserMessage($input);

    // Get AI response
    $result = AI::generateText([
        'model' => 'openai/gpt-4o',
        'system' => $systemPrompt,
        'messages' => $conversationHistory,
        'maxTokens' => 300,
    ]);

    echo "Assistant: {$result->text}\n\n";

    // Add assistant response to history
    $conversationHistory[] = new AssistantMessage($result->text);
}

// ============================================================================
// Example 4: Conversation with context summarization
// ============================================================================

echo "Example 4: Long conversation management\n";
echo str_repeat('-', 40) . "\n";

/**
 * Helper function to summarize conversation when it gets too long.
 * In production, you might use AI to generate this summary.
 *
 * @param array<UserMessage|AssistantMessage> $messages
 * @return string
 */
function summarizeConversation(array $messages): string
{
    $summary = "Previous conversation summary:\n";
    foreach ($messages as $msg) {
        $role = $msg instanceof UserMessage ? 'User' : 'Assistant';
        $content = substr((string) $msg->content, 0, 100);
        $summary .= "- {$role}: {$content}...\n";
    }

    return $summary;
}

// Simulate a long conversation
$longConversation = [
    new UserMessage('What is PHP?'),
    new AssistantMessage('PHP is a server-side scripting language designed for web development.'),
    new UserMessage('What are its main features?'),
    new AssistantMessage('PHP features include: easy database integration, cross-platform support, and a large ecosystem of frameworks.'),
    new UserMessage('What frameworks are popular?'),
    new AssistantMessage('Popular PHP frameworks include Laravel, Symfony, and CodeIgniter.'),
];

// Check if conversation is getting long (in practice, check token count)
$maxMessages = 6;
if (count($longConversation) > $maxMessages) {
    // Summarize older messages
    $oldMessages = array_slice($longConversation, 0, -2);
    $recentMessages = array_slice($longConversation, -2);

    $summary = summarizeConversation($oldMessages);

    // Create new conversation with summary as system context
    $systemWithSummary = "You are a PHP expert. " . $summary;

    echo "System context (with summary):\n{$systemWithSummary}\n\n";
}

// Add new message
$longConversation[] = new UserMessage('Tell me more about Laravel.');

$result = AI::generateText([
    'model' => 'openai/gpt-4o',
    'system' => 'You are a PHP expert helping developers learn.',
    'messages' => array_slice($longConversation, -4), // Keep only recent messages
    'maxTokens' => 200,
]);

echo "Response: {$result->text}\n";
