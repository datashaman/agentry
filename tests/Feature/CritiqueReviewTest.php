<?php

use App\Models\Agent;
use App\Models\Bug;
use App\Models\Critique;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('story detail displays critiques with type, severity, disposition, and revision', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Critic Bot']);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    Critique::factory()->forStory($story)->create([
        'agent_id' => $agent->id,
        'critic_type' => 'spec',
        'severity' => 'major',
        'disposition' => 'pending',
        'revision' => 2,
        'issues' => ['Missing edge case handling'],
        'questions' => ['What about null input?'],
        'recommendations' => ['Add validation'],
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('spec');
    $response->assertSee('major');
    $response->assertSee('pending');
    $response->assertSee('Rev 2');
    $response->assertSee('Missing edge case handling');
    $response->assertSee('What about null input?');
    $response->assertSee('Add validation');
    $response->assertSee('Critic Bot');
});

test('user can accept a critique on a story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $critique = Critique::factory()->forStory($story)->create([
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('updateCritiqueDisposition', $critique->id, 'accepted')
        ->assertHasNoErrors();

    $critique->refresh();
    expect($critique->disposition)->toBe('accepted');
});

test('user can reject a critique on a story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $critique = Critique::factory()->forStory($story)->create([
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('updateCritiqueDisposition', $critique->id, 'rejected')
        ->assertHasNoErrors();

    $critique->refresh();
    expect($critique->disposition)->toBe('rejected');
});

test('user can defer a critique on a story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    $critique = Critique::factory()->forStory($story)->create([
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('updateCritiqueDisposition', $critique->id, 'deferred')
        ->assertHasNoErrors();

    $critique->refresh();
    expect($critique->disposition)->toBe('deferred');
});

test('blocking critique with pending disposition is highlighted on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    Critique::factory()->forStory($story)->create([
        'severity' => 'blocking',
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('border-red-400');
});

test('user can accept a critique on a bug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $critique = Critique::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->call('updateCritiqueDisposition', $critique->id, 'accepted')
        ->assertHasNoErrors();

    $critique->refresh();
    expect($critique->disposition)->toBe('accepted');
});

test('user can reject a critique on a bug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    $critique = Critique::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->call('updateCritiqueDisposition', $critique->id, 'rejected')
        ->assertHasNoErrors();

    $critique->refresh();
    expect($critique->disposition)->toBe('rejected');
});

test('blocking critique with pending disposition is highlighted on bug detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);

    Critique::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
        'severity' => 'blocking',
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('border-red-400');
});

test('story critique shows accept reject and defer buttons for pending disposition', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    Critique::factory()->forStory($story)->create([
        'disposition' => 'pending',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Accept');
    $response->assertSee('Reject');
    $response->assertSee('Defer');
});

test('accepted critique does not show accept button', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);

    Critique::factory()->forStory($story)->create([
        'disposition' => 'accepted',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->assertDontSeeHtml('data-test="accept-critique-button"')
        ->assertSeeHtml('data-test="reject-critique-button"')
        ->assertSeeHtml('data-test="defer-critique-button"');
});
