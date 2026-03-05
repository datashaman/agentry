<?php

use App\Models\Bug;
use App\Models\Epic;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;

test('milestones list displays project milestones with all columns', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create([
        'project_id' => $project->id,
        'title' => 'Sprint 1 Release',
        'status' => 'active',
        'due_date' => '2026-04-15',
    ]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    Story::factory()->count(3)->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id]);
    Bug::factory()->count(2)->create(['project_id' => $project->id, 'milestone_id' => $milestone->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.index', $project));
    $response->assertOk();
    $response->assertSee('Sprint 1 Release');
    $response->assertSee('active');
    $response->assertSee('Apr 15, 2026');
    $response->assertSee('3'); // stories count
    $response->assertSee('2'); // bugs count
});

test('milestones list shows empty state when no milestones exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.index', $project));
    $response->assertOk();
    $response->assertSee('No Milestones');
});

test('milestones list has link to milestone detail page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.milestones.show', [$project, $milestone]));
});

test('milestones list has link to create new milestone', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.index', $project));
    $response->assertOk();
    $response->assertSee(route('projects.milestones.create', $project));
});

test('milestones list filters by status', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Open Milestone', 'status' => 'open']);
    Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Active Milestone', 'status' => 'active']);
    Milestone::factory()->create(['project_id' => $project->id, 'title' => 'Closed Milestone', 'status' => 'closed']);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::projects.milestones.index', ['project' => $project])
        ->set('statusFilter', 'active')
        ->assertSee('Active Milestone')
        ->assertDontSee('Open Milestone')
        ->assertDontSee('Closed Milestone');
});
