<?php

use App\Models\Bug;
use App\Models\Epic;
use App\Models\Label;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;
use Livewire\Livewire;

test('story detail page displays attached labels', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Feature', 'color' => '#ff0000']);
    $story->labels()->attach($label);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Feature');
});

test('attach label to story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Enhancement']);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->set('selectedLabelId', $label->id)
        ->call('attachLabel')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('labelables', [
        'label_id' => $label->id,
        'labelable_id' => $story->id,
        'labelable_type' => Story::class,
    ]);
});

test('detach label from story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Bug']);
    $story->labels()->attach($label);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('detachLabel', $label->id);

    $this->assertDatabaseMissing('labelables', [
        'label_id' => $label->id,
        'labelable_id' => $story->id,
        'labelable_type' => Story::class,
    ]);
});

test('bug detail page displays attached labels', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Critical', 'color' => '#ff0000']);
    $bug->labels()->attach($label);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Critical');
});

test('attach label to bug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Regression']);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->set('selectedLabelId', $label->id)
        ->call('attachLabel')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('labelables', [
        'label_id' => $label->id,
        'labelable_id' => $bug->id,
        'labelable_type' => Bug::class,
    ]);
});

test('detach label from bug', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Won\'t Fix']);
    $bug->labels()->attach($label);

    $this->actingAs($user);

    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->call('detachLabel', $label->id);

    $this->assertDatabaseMissing('labelables', [
        'label_id' => $label->id,
        'labelable_id' => $bug->id,
        'labelable_type' => Bug::class,
    ]);
});
