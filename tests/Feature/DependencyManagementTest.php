<?php

use App\Models\Bug;
use App\Models\Dependency;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;
use Livewire\Livewire;

test('story detail shows dependencies with type status and resolved indicator', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $blockerStory = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Setup Database',
        'status' => 'closed_done',
    ]);

    Dependency::factory()->create([
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
        'blocker_type' => Story::class,
        'blocker_id' => $blockerStory->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Dependencies');
    $response->assertSee('Setup Database');
    $response->assertSee('Story');
    $response->assertSee('closed done');
    $response->assertSee('Resolved');
});

test('add dependency on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $blockerStory = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Prerequisite Story',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->set('selectedDependencyId', 'story-'.$blockerStory->id)
        ->call('attachDependency');

    $this->assertDatabaseHas('dependencies', [
        'blocker_type' => Story::class,
        'blocker_id' => $blockerStory->id,
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
    ]);
});

test('remove dependency on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $blockerStory = Story::factory()->create(['epic_id' => $epic->id]);

    $dependency = Dependency::factory()->create([
        'blocked_type' => Story::class,
        'blocked_id' => $story->id,
        'blocker_type' => Story::class,
        'blocker_id' => $blockerStory->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('removeDependency', $dependency->id);

    $this->assertDatabaseMissing('dependencies', ['id' => $dependency->id]);
});

test('add dependency on bug detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $blockerStory = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Blocking Story',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->set('selectedDependencyId', 'story-'.$blockerStory->id)
        ->call('attachDependency');

    $this->assertDatabaseHas('dependencies', [
        'blocker_type' => Story::class,
        'blocker_id' => $blockerStory->id,
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
    ]);
});

test('remove dependency on bug detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $blockerBug = Bug::factory()->create(['project_id' => $project->id]);

    $dependency = Dependency::factory()->create([
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
        'blocker_type' => Bug::class,
        'blocker_id' => $blockerBug->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->call('removeDependency', $dependency->id);

    $this->assertDatabaseMissing('dependencies', ['id' => $dependency->id]);
});

test('bug detail shows dependencies with unresolved indicator', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $blockerStory = Story::factory()->create([
        'epic_id' => $epic->id,
        'title' => 'Blocking Story',
        'status' => 'in_development',
    ]);

    Dependency::factory()->create([
        'blocked_type' => Bug::class,
        'blocked_id' => $bug->id,
        'blocker_type' => Story::class,
        'blocker_id' => $blockerStory->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Dependencies');
    $response->assertSee('Blocking Story');
    $response->assertSee('Story');
    $response->assertSee('Unresolved');
});
