<?php

use App\Models\Label;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('labels page displays project labels', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    Label::factory()->create(['project_id' => $project->id, 'name' => 'Feature', 'color' => '#ff0000']);

    $this->actingAs($user);

    $response = $this->get(route('projects.labels.index', $project));
    $response->assertOk();
    $response->assertSee('Feature');
    $response->assertSee('#ff0000');
});

test('labels page shows empty state when no labels exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    $response = $this->get(route('projects.labels.index', $project));
    $response->assertOk();
    $response->assertSee('No Labels');
});

test('create label via inline form', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.labels.index', ['project' => $project])
        ->set('newName', 'Enhancement')
        ->set('newColor', '#00ff00')
        ->call('createLabel')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('labels', [
        'project_id' => $project->id,
        'name' => 'Enhancement',
        'color' => '#00ff00',
    ]);
});

test('create label validates required fields', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.labels.index', ['project' => $project])
        ->set('newName', '')
        ->call('createLabel')
        ->assertHasErrors(['newName']);
});

test('edit label inline', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $label = Label::factory()->create(['project_id' => $project->id, 'name' => 'Old Name', 'color' => '#111111']);

    $this->actingAs($user);

    Livewire::test('pages::projects.labels.index', ['project' => $project])
        ->call('startEditing', $label->id)
        ->assertSet('editingLabelId', $label->id)
        ->assertSet('editName', 'Old Name')
        ->assertSet('editColor', '#111111')
        ->set('editName', 'New Name')
        ->set('editColor', '#222222')
        ->call('updateLabel')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('labels', [
        'id' => $label->id,
        'name' => 'New Name',
        'color' => '#222222',
    ]);
});

test('delete label removes the label', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $label = Label::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.labels.index', ['project' => $project])
        ->call('deleteLabel', $label->id);

    $this->assertDatabaseMissing('labels', ['id' => $label->id]);
});
