<?php

use App\Models\Attachment;
use App\Models\Bug;
use App\Models\Story;

test('can create an attachment', function () {
    $story = Story::factory()->create();
    $attachment = Attachment::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'filename' => 'screenshot.png',
        'path' => 'attachments/abc123.png',
        'mime_type' => 'image/png',
        'size' => 204800,
    ]);

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->filename)->toBe('screenshot.png')
        ->and($attachment->path)->toBe('attachments/abc123.png')
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->size)->toBe(204800);
});

test('attachment polymorphically belongs to story', function () {
    $story = Story::factory()->create();
    $attachment = Attachment::factory()->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($attachment->workItem)->toBeInstanceOf(Story::class)
        ->and($attachment->workItem->id)->toBe($story->id);
});

test('attachment polymorphically belongs to bug', function () {
    $bug = Bug::factory()->create();
    $attachment = Attachment::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($attachment->workItem)->toBeInstanceOf(Bug::class)
        ->and($attachment->workItem->id)->toBe($bug->id);
});

test('story has many attachments', function () {
    $story = Story::factory()->create();
    Attachment::factory()->count(3)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);

    expect($story->attachments)->toHaveCount(3)
        ->each->toBeInstanceOf(Attachment::class);
});

test('bug has many attachments', function () {
    $bug = Bug::factory()->create();
    Attachment::factory()->count(2)->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($bug->attachments)->toHaveCount(2)
        ->each->toBeInstanceOf(Attachment::class);
});

test('attachment requires filename', function () {
    $story = Story::factory()->create();
    expect(fn () => Attachment::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'path' => 'attachments/abc.pdf',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('attachment requires path', function () {
    $story = Story::factory()->create();
    expect(fn () => Attachment::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'filename' => 'doc.pdf',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('attachment mime_type is nullable', function () {
    $story = Story::factory()->create();
    $attachment = Attachment::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'filename' => 'data.bin',
        'path' => 'attachments/data.bin',
    ]);

    expect($attachment->mime_type)->toBeNull();
});

test('attachment size is nullable', function () {
    $story = Story::factory()->create();
    $attachment = Attachment::create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
        'filename' => 'data.bin',
        'path' => 'attachments/data.bin',
    ]);

    expect($attachment->size)->toBeNull();
});

test('attachment work_item is nullable', function () {
    $attachment = Attachment::create([
        'filename' => 'orphan.txt',
        'path' => 'attachments/orphan.txt',
    ]);

    expect($attachment->work_item_id)->toBeNull()
        ->and($attachment->work_item_type)->toBeNull()
        ->and($attachment->workItem)->toBeNull();
});

test('can update an attachment', function () {
    $attachment = Attachment::factory()->create(['filename' => 'old.pdf']);
    $attachment->update(['filename' => 'new.pdf']);

    expect($attachment->fresh()->filename)->toBe('new.pdf');
});

test('can delete an attachment', function () {
    $attachment = Attachment::factory()->create();
    $id = $attachment->id;
    $attachment->delete();

    expect(Attachment::find($id))->toBeNull();
});

test('can list attachments for a work item', function () {
    $story = Story::factory()->create();
    $bug = Bug::factory()->create();

    Attachment::factory()->count(2)->create([
        'work_item_id' => $story->id,
        'work_item_type' => Story::class,
    ]);
    Attachment::factory()->create([
        'work_item_id' => $bug->id,
        'work_item_type' => Bug::class,
    ]);

    expect($story->attachments)->toHaveCount(2)
        ->and($bug->attachments)->toHaveCount(1);
});

test('factory creates valid attachment', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->filename)->toBeString()
        ->and($attachment->path)->toBeString()
        ->and($attachment->mime_type)->toBeString()
        ->and($attachment->size)->toBeInt()
        ->and($attachment->workItem)->toBeInstanceOf(Story::class);
});
