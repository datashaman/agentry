<?php

use App\Models\Milestone;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

test('milestone detail displays header with title, description, status, and due date', function () {
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

    $this->actingAs($user);

    $response = $this->get(route('projects.milestones.show', [$project, $milestone]));
    $response->assertOk();
    $response->assertSee('Release 1.0');
    $response->assertSee('First major release');
    $response->assertSee('active');
    $response->assertSee('Jun 1, 2026');
});
