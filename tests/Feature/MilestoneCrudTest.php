<?php

use App\Models\Bug;
use App\Models\Epic;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;
use Livewire\Livewire;

test('milestone detail page shows title, status, due date, description', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create([
        'project_id' => $project->id,
        'title' => 'Sprint 1',
        'description' => 'First sprint deliverables',
        'status' => 'active',
        'due_date' => '2026-04-01',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee('Sprint 1');
    $response->assertSee('First sprint deliverables');
    $response->assertSee('active');
    $response->assertSee('Apr 1, 2026');
});

test('milestone detail page shows assigned stories and bugs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    Story::factory()->create([
        'epic_id' => $epic->id,
        'milestone_id' => $milestone->id,
        'title' => 'Implement login',
    ]);

    Bug::factory()->create([
        'project_id' => $project->id,
        'milestone_id' => $milestone->id,
        'title' => 'Fix header alignment',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee('Implement login');
    $response->assertSee('Fix header alignment');
});

test('milestone detail page has edit and delete buttons', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee(route('projects.milestones.edit', [$project, $milestone]));
    $response->assertSee('Delete');
});

test('create milestone form displays and creates a milestone', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.create', $project));
    $response->assertOk();
    $response->assertSee('New Milestone');

    Livewire::test('pages::projects.milestones.create', ['project' => $project])
        ->set('title', 'Sprint 2')
        ->set('description', 'Second sprint')
        ->set('status', 'open')
        ->set('due_date', '2026-05-01')
        ->call('createMilestone')
        ->assertRedirect();

    $this->assertDatabaseHas('milestones', [
        'project_id' => $project->id,
        'title' => 'Sprint 2',
        'description' => 'Second sprint',
        'status' => 'open',
    ]);
});

test('create milestone validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.milestones.create', ['project' => $project])
        ->set('title', '')
        ->call('createMilestone')
        ->assertHasErrors(['title']);
});

test('edit milestone form displays pre-populated values and updates a milestone', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create([
        'project_id' => $project->id,
        'title' => 'Old Title',
        'description' => 'Old description',
        'status' => 'open',
        'due_date' => '2026-04-15',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.edit', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee('Edit Milestone');

    Livewire::test('pages::projects.milestones.edit', ['project' => $project, 'milestone' => $milestone])
        ->assertSet('title', 'Old Title')
        ->assertSet('description', 'Old description')
        ->assertSet('status', 'open')
        ->assertSet('due_date', '2026-04-15')
        ->set('title', 'New Title')
        ->set('status', 'active')
        ->call('updateMilestone')
        ->assertRedirect();

    $this->assertDatabaseHas('milestones', [
        'id' => $milestone->id,
        'title' => 'New Title',
        'status' => 'active',
    ]);
});

test('delete milestone removes it when no stories or bugs assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.milestones.show', ['project' => $project, 'milestone' => $milestone])
        ->call('deleteMilestone')
        ->assertRedirect(route('projects.milestones.index', $project));

    $this->assertDatabaseMissing('milestones', ['id' => $milestone->id]);
});

test('delete milestone is prevented when stories or bugs are assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    Story::factory()->create([
        'epic_id' => $epic->id,
        'milestone_id' => $milestone->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.milestones.show', ['project' => $project, 'milestone' => $milestone])
        ->call('deleteMilestone')
        ->assertNoRedirect();

    $this->assertDatabaseHas('milestones', ['id' => $milestone->id]);
});
