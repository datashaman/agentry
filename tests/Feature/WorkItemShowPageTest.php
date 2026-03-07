<?php

use App\Events\WorkItemClassified;
use App\Models\AgentConversation;
use App\Models\AgentConversationMessage;
use App\Models\HitlEscalation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Livewire\Livewire;

function createConversationWithMessages(WorkItem $workItem, array $messages): AgentConversation
{
    $conversation = AgentConversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => null,
        'title' => 'Test conversation',
    ]);

    $workItem->agentConversations()->attach($conversation);

    foreach ($messages as $msg) {
        AgentConversationMessage::create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversation->id,
            'user_id' => null,
            'agent' => 'anonymous',
            'role' => $msg['role'],
            'content' => $msg['content'],
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ]);
    }

    return $conversation;
}

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

    createConversationWithMessages($workItem, [
        ['role' => 'user', 'content' => 'Can you investigate this issue?'],
        ['role' => 'assistant', 'content' => 'I will look into the root cause.'],
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

    createConversationWithMessages($workItem, [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Hello agent!'],
    ]);

    $this->actingAs($user)
        ->get(route('projects.work-items.show', [$project, $workItem]))
        ->assertOk()
        ->assertDontSee('You are a helpful assistant.')
        ->assertSee('Hello agent!');
});

test('user can send a message', function () {
    AnonymousAgent::fake(['This is the agent response.']);

    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    createConversationWithMessages($workItem, []);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->set('newMessage', 'Please prioritize this fix.')
        ->call('sendMessage')
        ->assertSet('newMessage', '');

    $conversation = $workItem->latestConversation();
    expect($conversation->messages()->where('role', 'user')->where('content', 'Please prioritize this fix.')->exists())->toBeTrue();
    expect($conversation->messages()->where('role', 'assistant')->where('content', 'This is the agent response.')->exists())->toBeTrue();
});

test('empty message is rejected', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->set('newMessage', '')
        ->call('sendMessage')
        ->assertHasErrors(['newMessage']);
});

test('sending a message creates conversation if none exists', function () {
    AnonymousAgent::fake(['Agent reply here.']);

    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    expect($workItem->latestConversation())->toBeNull();

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->set('newMessage', 'Starting a new conversation.')
        ->call('sendMessage');

    $workItem->refresh();
    $conversation = $workItem->latestConversation();
    expect($conversation)->not->toBeNull();
    expect($conversation->messages()->where('content', 'Starting a new conversation.')->exists())->toBeTrue();
    expect($conversation->messages()->where('role', 'assistant')->exists())->toBeTrue();
});

test('displays HITL escalations on work item show page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    HitlEscalation::factory()->forWorkItem($workItem)->create([
        'trigger_type' => 'reclassification',
        'reason' => 'AI classified this as Bug',
        'metadata' => ['classified_type' => 'Bug', 'original_type' => 'enhancement'],
    ]);

    $this->actingAs($user)
        ->get(route('projects.work-items.show', [$project, $workItem]))
        ->assertOk()
        ->assertSee('HITL Escalations')
        ->assertSee('AI classified this as Bug');
});

test('confirming reclassification fires WorkItemClassified', function () {
    Event::fake([WorkItemClassified::class]);

    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'classified_type' => 'Bug',
    ]);

    $escalation = HitlEscalation::factory()->forWorkItem($workItem)->create([
        'trigger_type' => 'reclassification',
        'reason' => 'Classified as Bug',
        'metadata' => ['classified_type' => 'Bug', 'original_type' => 'enhancement'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->call('confirmReclassification', $escalation->id);

    expect($escalation->fresh()->isResolved())->toBeTrue();
    Event::assertDispatched(WorkItemClassified::class);
});

test('reverting reclassification updates classified_type and fires WorkItemClassified', function () {
    Event::fake([WorkItemClassified::class]);

    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $workItem = WorkItem::factory()->create([
        'project_id' => $project->id,
        'classified_type' => 'Bug',
    ]);

    $escalation = HitlEscalation::factory()->forWorkItem($workItem)->create([
        'trigger_type' => 'reclassification',
        'reason' => 'Classified as Bug',
        'metadata' => ['classified_type' => 'Bug', 'original_type' => 'enhancement'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->call('revertReclassification', $escalation->id);

    expect($workItem->fresh()->classified_type)->toBe('enhancement');
    expect($escalation->fresh()->isResolved())->toBeTrue();
    Event::assertDispatched(WorkItemClassified::class);
});

test('approving type labels saves them to project config', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create([
        'organization_id' => $organization->id,
        'work_item_provider' => 'jira',
        'work_item_provider_config' => ['project_key' => 'PROJ'],
    ]);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    $escalation = HitlEscalation::factory()->forWorkItem($workItem)->create([
        'trigger_type' => 'type_label_suggestion',
        'reason' => 'No type labels configured',
        'metadata' => ['suggested_labels' => ['Bug', 'Story', 'Task'], 'project_id' => $project->id],
    ]);

    Livewire::actingAs($user)
        ->test('pages::projects.work-items.show', ['project' => $project, 'workItem' => $workItem])
        ->call('approveTypeLabels', $escalation->id);

    expect($escalation->fresh()->isResolved())->toBeTrue();
    expect($project->fresh()->work_item_provider_config['type_labels'])->toBe(['Bug', 'Story', 'Task']);
});
