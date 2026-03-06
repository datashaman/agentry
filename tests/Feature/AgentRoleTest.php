<?php

use App\Models\AgentRole;
use App\Models\Organization;

test('can create an agent role', function () {
    $agentRole = AgentRole::factory()->create();

    expect($agentRole)->toBeInstanceOf(AgentRole::class)
        ->and($agentRole->name)->not->toBeEmpty()
        ->and($agentRole->slug)->not->toBeEmpty()
        ->and($agentRole->description)->not->toBeEmpty()
        ->and($agentRole->tools)->toBeArray();

    $this->assertDatabaseHas('agent_roles', [
        'id' => $agentRole->id,
        'name' => $agentRole->name,
        'slug' => $agentRole->slug,
    ]);
});

test('agent role requires a name', function () {
    $agentRole = AgentRole::factory()->make(['name' => null]);

    expect(fn () => $agentRole->save())->toThrow(\Illuminate\Database\QueryException::class);
});

test('agent role requires unique slug per organization', function () {
    $organization = Organization::factory()->create();
    AgentRole::factory()->create(['organization_id' => $organization->id, 'slug' => 'test-type']);

    expect(fn () => AgentRole::factory()->create(['organization_id' => $organization->id, 'slug' => 'test-type']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('agent role casts tools to array', function () {
    $tools = ['read_code', 'write_code', 'run_tests'];
    $agentRole = AgentRole::factory()->create(['tools' => $tools]);

    $fresh = $agentRole->fresh();

    expect($fresh->tools)->toBe($tools);
});

test('agent role allows null description and tools', function () {
    $agentRole = AgentRole::factory()->create([
        'description' => null,
        'instructions' => null,
        'tools' => null,
    ]);

    expect($agentRole->description)->toBeNull()
        ->and($agentRole->instructions)->toBeNull()
        ->and($agentRole->tools)->toBeNull();
});

test('can update an agent role', function () {
    $agentRole = AgentRole::factory()->create();

    $agentRole->update([
        'name' => 'Updated Type',
        'slug' => 'updated-type',
        'description' => 'Updated description',
    ]);

    expect($agentRole->fresh())
        ->name->toBe('Updated Type')
        ->slug->toBe('updated-type')
        ->description->toBe('Updated description');
});

test('can delete an agent role', function () {
    $agentRole = AgentRole::factory()->create();

    $agentRole->delete();

    $this->assertDatabaseMissing('agent_roles', ['id' => $agentRole->id]);
});

test('can list agent roles', function () {
    AgentRole::factory()->count(3)->create();

    expect(AgentRole::count())->toBe(3);
});

test('seeder creates all 10 agent roles', function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
        \Database\Seeders\AgentRoleSeeder::class,
    ]);

    expect(AgentRole::count())->toBe(10);

    $expectedSlugs = [
        'monitoring', 'triage', 'planning', 'spec-critic', 'design-critic',
        'coding', 'review', 'test', 'release', 'ops',
    ];

    foreach ($expectedSlugs as $slug) {
        $this->assertDatabaseHas('agent_roles', ['slug' => $slug]);
    }
});
