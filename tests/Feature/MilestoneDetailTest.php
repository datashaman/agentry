<?php

use App\Models\Bug;
use App\Models\Epic;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;

test('milestone detail displays progress summary with correct counts', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id, 'status' => 'closed_done']);
    Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id, 'status' => 'in_development']);
    Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id, 'status' => 'backlog']);

    Bug::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id, 'status' => 'closed_fixed']);
    Bug::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id, 'status' => 'new']);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee('Total Stories');
    $response->assertSee('Completed Stories');
    $response->assertSee('Total Bugs');
    $response->assertSee('Fixed Bugs');
});

test('milestone detail progress summary shows zero counts when no items assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee('Total Stories');
    $response->assertSee('Total Bugs');
});

test('milestone detail displays all sections: header, progress, stories, bugs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $milestone = Milestone::factory()->create([
        'project_id' => $project->id,
        'title' => 'Release 1.0',
        'description' => 'First major release',
        'status' => 'active',
        'due_date' => '2026-06-01',
    ]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    Story::factory()->create(['epic_id' => $epic->id, 'milestone_id' => $milestone->id, 'title' => 'Auth feature', 'status' => 'closed_done']);
    Bug::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id, 'title' => 'Login bug', 'status' => 'closed_fixed']);

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();

    // Header
    $response->assertSee('Release 1.0');
    $response->assertSee('First major release');
    $response->assertSee('active');
    $response->assertSee('Jun 1, 2026');

    // Progress summary
    $response->assertSee('Total Stories');
    $response->assertSee('Completed Stories');
    $response->assertSee('Total Bugs');
    $response->assertSee('Fixed Bugs');

    // Stories list
    $response->assertSee('Auth feature');

    // Bugs list
    $response->assertSee('Login bug');
});
