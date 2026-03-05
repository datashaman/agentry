<?php

use App\Models\Agent;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('unauthenticated user cannot access team create page', function () {
    $this->get(route('teams.create'))
        ->assertRedirect(route('login'));
});

test('team create form displays and creates a team', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    $response = $this->get(route('teams.create'));
    $response->assertOk();
    $response->assertSee('New Team');

    Livewire::test('pages::teams.create')
        ->set('name', 'Dev Team')
        ->call('createTeam')
        ->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'name' => 'Dev Team',
        'organization_id' => $organization->id,
    ]);

    $team = Team::where('name', 'Dev Team')->first();
    expect($team->slug)->toBe('dev-team');
});

test('team create validates required name', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();

    $this->actingAs($user);

    Livewire::test('pages::teams.create')
        ->set('name', '')
        ->call('createTeam')
        ->assertHasErrors(['name']);
});

test('unauthenticated user cannot access team edit page', function () {
    $team = Team::factory()->create();

    $this->get(route('teams.edit', $team))
        ->assertRedirect(route('login'));
});

test('team edit form displays pre-populated values and updates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('teams.edit', $team));
    $response->assertOk();
    $response->assertSee('Edit Team');

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->call('updateTeam')
        ->assertRedirect();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'New Name',
    ]);

    expect($team->fresh()->slug)->toBe('new-name');
});

test('team delete removes team and redirects when no agents assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id, 'name' => 'To Delete']);

    $this->actingAs($user);

    Livewire::test('pages::teams.show', ['team' => $team])
        ->call('deleteTeam')
        ->assertRedirect(route('teams.index'));

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

test('team delete is prevented when agents are assigned', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    Agent::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user);

    Livewire::test('pages::teams.show', ['team' => $team])
        ->call('deleteTeam')
        ->assertNoRedirect();

    $this->assertDatabaseHas('teams', ['id' => $team->id]);
});

test('team can attach and detach projects', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $project = Project::factory()->create(['organization_id' => $organization->id, 'name' => 'Test Project']);

    $this->actingAs($user);

    Livewire::test('pages::teams.show', ['team' => $team])
        ->set('selectedProjectId', (string) $project->id)
        ->call('attachProject')
        ->assertHasNoErrors();

    expect($team->fresh()->projects)->toHaveCount(1)
        ->and($team->fresh()->projects->first()->name)->toBe('Test Project');

    Livewire::test('pages::teams.show', ['team' => $team])
        ->call('detachProject', $project->id);

    expect($team->fresh()->projects)->toHaveCount(0);
});

test('teams page has create team button and team rows link to team detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id, 'name' => 'Linkable Team']);

    $this->actingAs($user);

    $response = $this->get(route('teams.index'));
    $response->assertOk();
    $response->assertSee(route('teams.create'));
    $response->assertSee('New Team');
    $response->assertSee(route('teams.show', $team));
    $response->assertSee('Linkable Team');
});
