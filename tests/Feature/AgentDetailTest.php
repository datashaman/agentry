<?php

use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\AgentRole;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\OpsRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\Team;
use App\Models\User;

test('unauthenticated user cannot access agent detail page', function () {
    $agent = Agent::factory()->create();

    $this->get(route('agents.show', $agent))
        ->assertRedirect(route('login'));
});

test('agent detail displays all sections', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id, 'name' => 'Dev Team']);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id, 'name' => 'Code Writer', 'slug' => 'code-writer']);
    $agent = Agent::factory()->create([
        'team_id' => $team->id,
        'agent_role_id' => $agentRole->id,
        'name' => 'Alpha Agent',
        'model' => 'claude-opus-4-6',
        'provider' => 'anthropic',
        'status' => 'active',
        'confidence_threshold' => 0.9,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agents.show', $agent));
    $response->assertOk();

    $response->assertSee('Alpha Agent');
    $response->assertSee('Code Writer');
    $response->assertSee('Dev Team');
    $response->assertSee('claude-opus-4-6');
    $response->assertSee('anthropic');
    $response->assertSee('Active');
    $response->assertSee('90%');

    $response->assertSee('Currently Assigned');
    $response->assertSee('Stories');
    $response->assertSee('Bugs');
    $response->assertSee('Ops Requests');

    $response->assertSee('Recent Activity');
});

test('agent detail shows overrides when set', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agentRole = AgentRole::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create([
        'team_id' => $team->id,
        'agent_role_id' => $agentRole->id,
        'name' => 'Override Agent',
        'temperature' => 0.7,
        'max_steps' => 15,
        'max_tokens' => 8192,
        'timeout' => 120,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agents.show', $agent));
    $response->assertOk();

    $response->assertSee('Overrides');
    $response->assertSee('0.7');
    $response->assertSee('15');
    $response->assertSee('8192');
    $response->assertSee('120');
});

test('agent detail shows assigned stories, bugs, and ops requests with links', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);

    $story = Story::factory()->create([
        'epic_id' => $epic->id,
        'assigned_agent_id' => $agent->id,
        'title' => 'Auth feature story',
    ]);
    $bug = Bug::factory()->create([
        'project_id' => $project->id,
        'assigned_agent_id' => $agent->id,
        'title' => 'Login button bug',
    ]);
    $opsRequest = OpsRequest::factory()->create([
        'project_id' => $project->id,
        'assigned_agent_id' => $agent->id,
        'title' => 'Deploy to staging',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agents.show', $agent));
    $response->assertOk();

    $response->assertSee('Auth feature story');
    $response->assertSee('Login button bug');
    $response->assertSee('Deploy to staging');

    $response->assertSee(route('projects.stories.show', [$project, $story]));
    $response->assertSee(route('projects.bugs.show', [$project, $bug]));
    $response->assertSee(route('projects.ops-requests.show', [$project, $opsRequest]));
});

test('agent detail shows recent action logs', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id]);

    ActionLog::factory()->create([
        'agent_id' => $agent->id,
        'action' => 'opened_pr',
        'reasoning' => 'PR ready for review after tests passed',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('agents.show', $agent));
    $response->assertOk();

    $response->assertSee('opened_pr');
    $response->assertSee('PR ready for review');
});

test('teams page agent rows link to agent detail page', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $team = Team::factory()->create(['organization_id' => $organization->id]);
    $agent = Agent::factory()->create(['team_id' => $team->id, 'name' => 'Linkable Agent']);

    $this->actingAs($user);

    $response = $this->get(route('teams.index'));
    $response->assertOk();
    $response->assertSee(route('agents.show', $agent));
    $response->assertSee('Linkable Agent');
});
