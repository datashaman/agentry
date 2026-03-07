<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('guests are redirected from work item show page', function () {
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    $response = $this->get(route('projects.work-items.show', [$project, $workItem]));

    $response->assertRedirect(route('login'));
});

test('work item show page loads for authenticated user', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'title' => 'Fix the login bug',
        'type' => 'bug',
        'status' => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('projects.work-items.show', [$project, $workItem]))
        ->assertOk()
        ->assertSee('Fix the login bug')
        ->assertSee('bug')
        ->assertSee('open');
});

test('work item show page displays description', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'description' => 'Users cannot log in with their email address.',
    ]);

    $this->actingAs($user)
        ->get(route('projects.work-items.show', [$project, $workItem]))
        ->assertOk()
        ->assertSee('Users cannot log in with their email address.');
});

test('work item show page displays conversation messages', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    $conversation = Conversation::factory()->create(['work_item_id' => $workItem->id]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Can you investigate this issue?',
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'I will look into the root cause.',
    ]);

    $this->actingAs($user)
        ->get(route('projects.work-items.show', [$project, $workItem]))
        ->assertOk()
        ->assertSee('Can you investigate this issue?')
        ->assertSee('I will look into the root cause.');
});

test('system messages are hidden from conversation', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    $conversation = Conversation::factory()->create(['work_item_id' => $workItem->id]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'system',
        'content' => 'You are a helpful assistant.',
    ]);
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Hello agent!',
    ]);

    $this->actingAs($user)
        ->get(route('projects.work-items.show', [$project, $workItem]))
        ->assertOk()
        ->assertDontSee('You are a helpful assistant.')
        ->assertSee('Hello agent!');
});

test('user can send a message', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    Conversation::factory()->create(['work_item_id' => $workItem->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->set('newMessage', 'Please prioritize this fix.')
        ->call('sendMessage')
        ->assertSet('newMessage', '');

    expect($workItem->conversation->messages()->where('role', 'user')->where('content', 'Please prioritize this fix.')->exists())->toBeTrue();
});

test('empty message is rejected', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    Conversation::factory()->create(['work_item_id' => $workItem->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->set('newMessage', '')
        ->call('sendMessage')
        ->assertHasErrors(['newMessage']);
});

test('sending a message creates conversation if none exists', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    expect($workItem->conversation)->toBeNull();

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->set('newMessage', 'Starting a new conversation.')
        ->call('sendMessage');

    $workItem->refresh();
    expect($workItem->conversation)->not->toBeNull();
    expect($workItem->conversation->messages()->where('content', 'Starting a new conversation.')->exists())->toBeTrue();
});
