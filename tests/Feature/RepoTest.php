<?php

use App\Models\OpsRequest;
use App\Models\Project;
use App\Models\Repo;

test('can create a repo', function () {
    $repo = Repo::factory()->create();

    expect($repo)->toBeInstanceOf(Repo::class)
        ->and($repo->name)->not->toBeEmpty()
        ->and($repo->url)->not->toBeEmpty();
});

test('repo belongs to project', function () {
    $project = Project::factory()->create();
    $repo = Repo::factory()->create(['project_id' => $project->id]);

    expect($repo->project->id)->toBe($project->id);
});

test('project has many repos', function () {
    $project = Project::factory()->create();
    Repo::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->repos)->toHaveCount(3);
});

test('repo name is required', function () {
    expect(fn () => Repo::factory()->create(['name' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('repo url is required', function () {
    expect(fn () => Repo::factory()->create(['url' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('repo primary_language is nullable', function () {
    $repo = Repo::factory()->create(['primary_language' => null]);

    expect($repo->primary_language)->toBeNull();
});

test('repo default_branch defaults to main', function () {
    $repo = Repo::factory()->create();

    expect($repo->default_branch)->toBe('main');
});

test('repo tags is cast to array', function () {
    $tags = ['backend', 'api'];
    $repo = Repo::factory()->create(['tags' => $tags]);

    $repo->refresh();
    expect($repo->tags)->toBe($tags);
});

test('repo tags is nullable', function () {
    $repo = Repo::factory()->create(['tags' => null]);

    $repo->refresh();
    expect($repo->tags)->toBeNull();
});

test('can update a repo', function () {
    $repo = Repo::factory()->create();

    $repo->update([
        'name' => 'updated-repo',
        'url' => 'https://github.com/example/updated-repo.git',
        'primary_language' => 'TypeScript',
        'default_branch' => 'develop',
    ]);

    $repo->refresh();
    expect($repo->name)->toBe('updated-repo')
        ->and($repo->url)->toBe('https://github.com/example/updated-repo.git')
        ->and($repo->primary_language)->toBe('TypeScript')
        ->and($repo->default_branch)->toBe('develop');
});

test('can delete a repo', function () {
    $repo = Repo::factory()->create();
    $repoId = $repo->id;

    $repo->delete();

    expect(Repo::find($repoId))->toBeNull();
});

test('cascade delete when project is deleted', function () {
    $project = Project::factory()->create();
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $repoId = $repo->id;

    $project->delete();

    expect(Repo::find($repoId))->toBeNull();
});

test('can list repos', function () {
    Repo::factory()->count(5)->create();

    expect(Repo::all())->toHaveCount(5);
});

test('ops request belongs to many repos', function () {
    $opsRequest = OpsRequest::factory()->create();
    $repos = Repo::factory()->count(2)->create(['project_id' => $opsRequest->project_id]);

    $opsRequest->repos()->attach($repos->pluck('id'));

    expect($opsRequest->repos)->toHaveCount(2);
});

test('repo belongs to many ops requests', function () {
    $project = Project::factory()->create();
    $repo = Repo::factory()->create(['project_id' => $project->id]);
    $opsRequests = OpsRequest::factory()->count(2)->create(['project_id' => $project->id]);

    $repo->opsRequests()->attach($opsRequests->pluck('id'));

    expect($repo->opsRequests)->toHaveCount(2);
});

test('ops request repo pivot has timestamps', function () {
    $opsRequest = OpsRequest::factory()->create();
    $repo = Repo::factory()->create(['project_id' => $opsRequest->project_id]);

    $opsRequest->repos()->attach($repo->id);

    $pivot = $opsRequest->repos()->first()->pivot;
    expect($pivot->created_at)->not->toBeNull()
        ->and($pivot->updated_at)->not->toBeNull();
});

test('ops request repo pivot prevents duplicates', function () {
    $opsRequest = OpsRequest::factory()->create();
    $repo = Repo::factory()->create(['project_id' => $opsRequest->project_id]);

    $opsRequest->repos()->attach($repo->id);

    expect(fn () => $opsRequest->repos()->attach($repo->id))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('seeder creates expected repo', function () {
    $this->seed(\Database\Seeders\ProjectSeeder::class);
    $this->seed(\Database\Seeders\RepoSeeder::class);

    expect(Repo::where('name', 'pinky-platform')->exists())->toBeTrue();
});
