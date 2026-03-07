<?php

use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\User;
use Illuminate\Support\Str;

test('guests are redirected from chats index page', function () {
    $this->get(route('chats.index'))->assertRedirect(route('login'));
});

test('chats index page loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertSee('Chats');
});

test('chats index shows conversations', function () {
    $user = User::factory()->create();

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Debug login flow',
    ]);

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertSee('Debug login flow');
});

test('chats index displays message count', function () {
    $user = User::factory()->create();

    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Test chat',
    ]);

    foreach (range(1, 3) as $i) {
        AgentConversationMessage::create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
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

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertSee('3');
});

test('chats index filters by search', function () {
    $user = User::factory()->create();

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Debug login flow',
    ]);

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Payment processing',
    ]);

    $this->actingAs($user)
        ->get(route('chats.index', ['search' => 'login']))
        ->assertOk()
        ->assertSee('Debug login flow')
        ->assertDontSee('Payment processing');
});

test('chats index filters by user', function () {
    $user1 = User::factory()->create(['name' => 'Alice']);
    $user2 = User::factory()->create(['name' => 'Bob']);

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user1->id,
        'title' => 'Alice chat',
    ]);

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user2->id,
        'title' => 'Bob chat',
    ]);

    $this->actingAs($user1)
        ->get(route('chats.index', ['userId' => $user1->id]))
        ->assertOk()
        ->assertSee('Alice chat')
        ->assertDontSee('Bob chat');
});

test('chats index filters anonymous conversations', function () {
    $user = User::factory()->create();

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Anonymous chat',
    ]);

    AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'User chat',
    ]);

    $this->actingAs($user)
        ->get(route('chats.index', ['userId' => 'anonymous']))
        ->assertOk()
        ->assertSee('Anonymous chat')
        ->assertDontSee('User chat');
});

test('chats index shows empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertSee('No Chats');
});
