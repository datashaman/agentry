<?php

use App\Models\Attachment;
use App\Models\Bug;
use App\Models\Epic;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('story detail lists attachments with filename mime size and upload date', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $path = 'attachments/stories/'.$story->id.'/test.pdf';
    Storage::disk('local')->put($path, 'content');
    Attachment::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'filename' => 'screenshot.png',
        'path' => $path,
        'mime_type' => 'image/png',
        'size' => 2048,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.stories.show', [$project, $story]));
    $response->assertOk();
    $response->assertSee('Attachments');
    $response->assertSee('screenshot.png');
    $response->assertSee('image/png');
    $response->assertSee('2.0 KB');
});

test('upload attachment on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->set('upload', $file)
        ->call('uploadAttachment');

    $this->assertDatabaseHas('attachments', [
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'filename' => 'doc.pdf',
    ]);
});

test('download attachment from story', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $path = 'attachments/stories/'.$story->id.'/test.pdf';
    Storage::disk('local')->put($path, 'pdf content');
    $attachment = Attachment::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'filename' => 'test.pdf',
        'path' => $path,
        'mime_type' => 'application/pdf',
        'size' => 11,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('attachments.download', $attachment));

    $response->assertOk();
    $response->assertDownload('test.pdf');
});

test('delete attachment on story detail', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $epic = Epic::factory()->create(['project_id' => $project->id]);
    $story = Story::factory()->create(['epic_id' => $epic->id]);
    $path = 'attachments/stories/'.$story->id.'/test.pdf';
    Storage::disk('local')->put($path, 'content');
    $attachment = Attachment::factory()->create([
        'work_item_type' => Story::class,
        'work_item_id' => $story->id,
        'filename' => 'test.pdf',
        'path' => $path,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.stories.show', ['project' => $project, 'story' => $story])
        ->call('deleteAttachment', $attachment->id);

    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
});

test('bug detail lists and uploads attachments', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->withOrganization($organization)->create();
    $project = Project::factory()->create(['organization_id' => $organization->id]);
    $bug = Bug::factory()->create(['project_id' => $project->id]);
    $path = 'attachments/bugs/'.$bug->id.'/log.txt';
    Storage::disk('local')->put($path, 'log content');
    Attachment::factory()->create([
        'work_item_type' => Bug::class,
        'work_item_id' => $bug->id,
        'filename' => 'log.txt',
        'path' => $path,
        'mime_type' => 'text/plain',
        'size' => 1024,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('projects.bugs.show', [$project, $bug]));
    $response->assertOk();
    $response->assertSee('Attachments');
    $response->assertSee('log.txt');

    $file = UploadedFile::fake()->create('trace.log', 50, 'text/plain');
    Livewire::test('pages::projects.bugs.show', ['project' => $project, 'bug' => $bug])
        ->set('upload', $file)
        ->call('uploadAttachment');

    $this->assertDatabaseHas('attachments', [
        'work_item_type' => Bug::class,
        'work_item_id' => $bug->id,
        'filename' => 'trace.log',
    ]);
});
