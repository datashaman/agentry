<?php

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\User;
use Illuminate\Support\Str;

test('guests are redirected from chat show page', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Test',
    ]);

    $this->get(route('chats.show', $conversation))->assertRedirect(route('login'));
});

test('chat show page loads for authenticated user', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Debug session',
    ]);

    $this->actingAs($user)
        ->get(route('chats.show', $conversation))
        ->assertOk()
        ->assertSee('Debug session');
});

test('chat show page displays messages', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Test chat',
    ]);

    AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'agent' => 'App\Agents\TestAgent',
        'role' => 'user',
        'content' => 'What is the status?',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'agent' => 'App\Agents\TestAgent',
        'role' => 'assistant',
        'content' => 'Everything is running smoothly.',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    $this->actingAs($user)
        ->get(route('chats.show', $conversation))
        ->assertOk()
        ->assertSee('What is the status?')
        ->assertSee('Everything is running smoothly.')
        ->assertSee('TestAgent');
});

test('chat show page displays tool calls', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Tool call chat',
    ]);

    AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'agent' => 'anonymous',
        'role' => 'assistant',
        'content' => 'Let me search for that.',
        'attachments' => [],
        'tool_calls' => [['name' => 'search', 'arguments' => ['query' => 'test']]],
        'tool_results' => [['result' => 'found 3 matches']],
        'usage' => ['input_tokens' => 50, 'output_tokens' => 25],
        'meta' => [],
    ]);

    $this->actingAs($user)
        ->get(route('chats.show', $conversation))
        ->assertOk()
        ->assertSee('Tool Calls')
        ->assertSee('Tool Results')
        ->assertSee('Usage');
});

test('chat show page handles anonymous conversations', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Anonymous session',
    ]);

    $this->actingAs($user)
        ->get(route('chats.show', $conversation))
        ->assertOk()
        ->assertSee('Anonymous');
});

test('chat show page returns 404 for non-existent conversation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chats.show', ['conversation' => 'non-existent-id']))
        ->assertNotFound();
});

test('chat show page shows empty state when no messages', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Empty chat',
    ]);

    $this->actingAs($user)
        ->get(route('chats.show', $conversation))
        ->assertOk()
        ->assertSee('No messages in this conversation.');
});
