<?php

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('agent conversation belongs to a user', function () {
    $user = User::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Test conversation',
    ]);

    expect($conversation->user->id)->toBe($user->id);
});

test('agent conversation has many messages', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Test conversation',
    ]);

    foreach (range(1, 3) as $i) {
        AgentConversationMessage::create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'agent' => 'anonymous',
            'role' => 'user',
            'content' => "Message $i",
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);
    }

    expect($conversation->messages)->toHaveCount(3);
});

test('agent conversation message belongs to a conversation', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Test conversation',
    ]);

    $message = AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => null,
        'agent' => 'anonymous',
        'role' => 'user',
        'content' => 'Hello',
        'attachments' => [],
        'tool_calls' => [],
        'tool_results' => [],
        'usage' => [],
        'meta' => [],
    ]);

    expect($message->conversation->id)->toBe($conversation->id);
});

test('work item belongs to many agent conversations via pivot', function () {
    $workItem = WorkItem::factory()->create();
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Work item conversation',
    ]);

    $workItem->agentConversations()->attach($conversation);

    expect($workItem->agentConversations)->toHaveCount(1);
    expect($workItem->agentConversations->first()->id)->toBe($conversation->id);
});

test('agent conversation belongs to many work items via pivot', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Shared conversation',
    ]);

    $workItem1 = WorkItem::factory()->create();
    $workItem2 = WorkItem::factory()->create();

    $conversation->workItems()->attach([$workItem1->id, $workItem2->id]);

    expect($conversation->workItems)->toHaveCount(2);
});

test('work item latestConversation returns most recent', function () {
    $workItem = WorkItem::factory()->create();

    $olderId = (string) Str::uuid7();
    $newerId = (string) Str::uuid7();

    $yesterday = now()->subDay();
    $today = now();

    DB::table('agent_conversations')->insert([
        ['id' => $olderId, 'user_id' => null, 'title' => 'Older', 'created_at' => $yesterday, 'updated_at' => $yesterday],
        ['id' => $newerId, 'user_id' => null, 'title' => 'Newer', 'created_at' => $today, 'updated_at' => $today],
    ]);

    $workItem->agentConversations()->attach([$olderId, $newerId]);

    expect($workItem->latestConversation()->id)->toBe($newerId);
});

test('work item latestConversation returns null when no conversations', function () {
    $workItem = WorkItem::factory()->create();

    expect($workItem->latestConversation())->toBeNull();
});

test('agent conversation message casts JSON fields to arrays', function () {
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Test',
    ]);

    $message = AgentConversationMessage::create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => null,
        'agent' => 'anonymous',
        'role' => 'assistant',
        'content' => 'Hello',
        'attachments' => ['file.pdf'],
        'tool_calls' => [['name' => 'search', 'args' => []]],
        'tool_results' => [['result' => 'found']],
        'usage' => ['tokens' => 100],
        'meta' => ['key' => 'value'],
    ]);

    $message->refresh();

    expect($message->attachments)->toBe(['file.pdf']);
    expect($message->tool_calls)->toBe([['name' => 'search', 'args' => []]]);
    expect($message->tool_results)->toBe([['result' => 'found']]);
    expect($message->usage)->toBe(['tokens' => 100]);
    expect($message->meta)->toBe(['key' => 'value']);
});
