<?php

use App\Models\AgentType;

test('can create an agent type', function () {
    $agentType = AgentType::factory()->create();

    expect($agentType)->toBeInstanceOf(AgentType::class)
        ->and($agentType->name)->not->toBeEmpty()
        ->and($agentType->slug)->not->toBeEmpty()
        ->and($agentType->description)->not->toBeEmpty()
        ->and($agentType->default_capabilities)->toBeArray();

    $this->assertDatabaseHas('agent_types', [
        'id' => $agentType->id,
        'name' => $agentType->name,
        'slug' => $agentType->slug,
    ]);
});

test('agent type requires a name', function () {
    $agentType = AgentType::factory()->make(['name' => null]);

    expect(fn () => $agentType->save())->toThrow(\Illuminate\Database\QueryException::class);
});

test('agent type requires a unique slug', function () {
    AgentType::factory()->create(['slug' => 'test-type']);

    expect(fn () => AgentType::factory()->create(['slug' => 'test-type']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('agent type casts default_capabilities to array', function () {
    $capabilities = ['read_code', 'write_code', 'run_tests'];
    $agentType = AgentType::factory()->create(['default_capabilities' => $capabilities]);

    $fresh = $agentType->fresh();

    expect($fresh->default_capabilities)->toBe($capabilities);
});

test('agent type allows null description and default_capabilities', function () {
    $agentType = AgentType::factory()->create([
        'description' => null,
        'default_capabilities' => null,
    ]);

    expect($agentType->description)->toBeNull()
        ->and($agentType->default_capabilities)->toBeNull();
});

test('can update an agent type', function () {
    $agentType = AgentType::factory()->create();

    $agentType->update([
        'name' => 'Updated Type',
        'slug' => 'updated-type',
        'description' => 'Updated description',
    ]);

    expect($agentType->fresh())
        ->name->toBe('Updated Type')
        ->slug->toBe('updated-type')
        ->description->toBe('Updated description');
});

test('can delete an agent type', function () {
    $agentType = AgentType::factory()->create();

    $agentType->delete();

    $this->assertDatabaseMissing('agent_types', ['id' => $agentType->id]);
});

test('can list agent types', function () {
    AgentType::factory()->count(3)->create();

    expect(AgentType::count())->toBe(3);
});

test('seeder creates all 10 agent types', function () {
    $this->seed(\Database\Seeders\AgentTypeSeeder::class);

    expect(AgentType::count())->toBe(10);

    $expectedSlugs = [
        'monitoring', 'triage', 'planning', 'spec-critic', 'design-critic',
        'coding', 'review', 'test', 'release', 'ops',
    ];

    foreach ($expectedSlugs as $slug) {
        $this->assertDatabaseHas('agent_types', ['slug' => $slug]);
    }
});
