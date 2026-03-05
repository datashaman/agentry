<?php

use App\Models\Organization;
use App\Models\Project;

test('can create a project', function () {
    $project = Project::factory()->create();

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->name)->not->toBeEmpty()
        ->and($project->slug)->not->toBeEmpty()
        ->and($project->organization_id)->not->toBeNull();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => $project->name,
        'slug' => $project->slug,
    ]);
});

test('project belongs to organization', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    expect($project->organization)->toBeInstanceOf(Organization::class)
        ->and($project->organization->id)->toBe($organization->id);
});

test('organization has many projects', function () {
    $organization = Organization::factory()->create();
    Project::factory()->count(3)->create(['organization_id' => $organization->id]);

    expect($organization->projects)->toHaveCount(3)
        ->each->toBeInstanceOf(Project::class);
});

test('project requires a name', function () {
    $project = Project::factory()->make(['name' => null]);

    expect(fn () => $project->save())->toThrow(\Illuminate\Database\QueryException::class);
});

test('project requires unique slug within organization', function () {
    $organization = Organization::factory()->create();
    Project::factory()->create(['organization_id' => $organization->id, 'slug' => 'my-project']);

    expect(fn () => Project::factory()->create(['organization_id' => $organization->id, 'slug' => 'my-project']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same slug allowed in different organizations', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $project1 = Project::factory()->create(['organization_id' => $org1->id, 'slug' => 'my-project']);
    $project2 = Project::factory()->create(['organization_id' => $org2->id, 'slug' => 'my-project']);

    expect($project1->slug)->toBe($project2->slug)
        ->and($project1->organization_id)->not->toBe($project2->organization_id);
});

test('can update a project', function () {
    $project = Project::factory()->create();

    $project->update(['name' => 'Updated Project', 'slug' => 'updated-project']);

    expect($project->fresh())
        ->name->toBe('Updated Project')
        ->slug->toBe('updated-project');
});

test('can delete a project', function () {
    $project = Project::factory()->create();

    $project->delete();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

test('deleting organization cascades to projects', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);

    $organization->delete();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});
