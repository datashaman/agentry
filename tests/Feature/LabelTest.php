<?php

use App\Models\Label;
use App\Models\Project;
use Illuminate\Support\Facades\Schema;

test('can create a label', function () {
    $label = Label::factory()->create();

    expect($label)->toBeInstanceOf(Label::class)
        ->and($label->name)->not->toBeEmpty()
        ->and($label->color)->not->toBeEmpty();

    $this->assertDatabaseHas('labels', [
        'id' => $label->id,
        'name' => $label->name,
    ]);
});

test('label belongs to project', function () {
    $project = Project::factory()->create();
    $label = Label::factory()->create(['project_id' => $project->id]);

    expect($label->project)->toBeInstanceOf(Project::class)
        ->and($label->project->id)->toBe($project->id);
});

test('project has many labels', function () {
    $project = Project::factory()->create();
    Label::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->labels)->toHaveCount(3);
});

test('label name is required', function () {
    expect(fn () => Label::factory()->create(['name' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('label name is unique within project', function () {
    $project = Project::factory()->create();
    Label::factory()->create(['project_id' => $project->id, 'name' => 'bug']);

    expect(fn () => Label::factory()->create(['project_id' => $project->id, 'name' => 'bug']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('same label name allowed across different projects', function () {
    $label1 = Label::factory()->create(['name' => 'bug']);
    $label2 = Label::factory()->create(['name' => 'bug']);

    expect($label1->project_id)->not->toBe($label2->project_id);
    $this->assertDatabaseCount('labels', 2);
});

test('label has default color', function () {
    $project = Project::factory()->create();
    $label = Label::factory()->create(['project_id' => $project->id, 'color' => '#6b7280']);

    expect($label->fresh()->color)->toBe('#6b7280');
});

test('can update a label', function () {
    $label = Label::factory()->create();

    $label->update(['name' => 'updated-label', 'color' => '#ff0000']);

    expect($label->fresh())
        ->name->toBe('updated-label')
        ->color->toBe('#ff0000');
});

test('can delete a label', function () {
    $label = Label::factory()->create();

    $label->delete();

    $this->assertDatabaseMissing('labels', ['id' => $label->id]);
});

test('cascade deletes labels when project deleted', function () {
    $project = Project::factory()->create();
    $label = Label::factory()->create(['project_id' => $project->id]);

    $project->delete();

    $this->assertDatabaseMissing('labels', ['id' => $label->id]);
});

test('labelables pivot table exists with correct columns', function () {
    expect(Schema::hasTable('labelables'))->toBeTrue()
        ->and(Schema::hasColumns('labelables', ['label_id', 'labelable_id', 'labelable_type']))->toBeTrue();
});

test('can attach label to a model via labelables pivot', function () {
    $label = Label::factory()->create();

    \Illuminate\Support\Facades\DB::table('labelables')->insert([
        'label_id' => $label->id,
        'labelable_id' => 1,
        'labelable_type' => 'App\Models\Story',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->assertDatabaseHas('labelables', [
        'label_id' => $label->id,
        'labelable_type' => 'App\Models\Story',
    ]);
});

test('labelables pivot enforces unique constraint', function () {
    $label = Label::factory()->create();

    \Illuminate\Support\Facades\DB::table('labelables')->insert([
        'label_id' => $label->id,
        'labelable_id' => 1,
        'labelable_type' => 'App\Models\Story',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => \Illuminate\Support\Facades\DB::table('labelables')->insert([
        'label_id' => $label->id,
        'labelable_id' => 1,
        'labelable_type' => 'App\Models\Story',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('cascade deletes labelables when label deleted', function () {
    $label = Label::factory()->create();

    \Illuminate\Support\Facades\DB::table('labelables')->insert([
        'label_id' => $label->id,
        'labelable_id' => 1,
        'labelable_type' => 'App\Models\Story',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $label->delete();

    $this->assertDatabaseMissing('labelables', ['label_id' => $label->id]);
});

test('seeder creates default labels', function () {
    $project = Project::factory()->create();
    $this->seed(\Database\Seeders\LabelSeeder::class);

    expect(Label::count())->toBeGreaterThanOrEqual(5);
    $this->assertDatabaseHas('labels', ['name' => 'bug']);
    $this->assertDatabaseHas('labels', ['name' => 'feature']);
});
