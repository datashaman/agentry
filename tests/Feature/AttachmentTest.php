<?php

use App\Models\Attachment;
use App\Models\OpsRequest;

test('can create an attachment', function () {
    $opsRequest = OpsRequest::factory()->create();
    $attachment = Attachment::create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
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

test('attachment polymorphically belongs to ops request', function () {
    $opsRequest = OpsRequest::factory()->create();
    $attachment = Attachment::factory()->create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
    ]);

    expect($attachment->workItem)->toBeInstanceOf(OpsRequest::class)
        ->and($attachment->workItem->id)->toBe($opsRequest->id);
});

test('attachment requires filename', function () {
    $opsRequest = OpsRequest::factory()->create();
    expect(fn () => Attachment::create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'path' => 'attachments/abc.pdf',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('attachment requires path', function () {
    $opsRequest = OpsRequest::factory()->create();
    expect(fn () => Attachment::create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'filename' => 'doc.pdf',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('attachment mime_type is nullable', function () {
    $opsRequest = OpsRequest::factory()->create();
    $attachment = Attachment::create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
        'filename' => 'data.bin',
        'path' => 'attachments/data.bin',
    ]);

    expect($attachment->mime_type)->toBeNull();
});

test('attachment size is nullable', function () {
    $opsRequest = OpsRequest::factory()->create();
    $attachment = Attachment::create([
        'work_item_id' => $opsRequest->id,
        'work_item_type' => OpsRequest::class,
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

test('factory creates valid attachment', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment->filename)->toBeString()
        ->and($attachment->path)->toBeString()
        ->and($attachment->mime_type)->toBeString()
        ->and($attachment->size)->toBeInt()
        ->and($attachment->workItem)->toBeInstanceOf(OpsRequest::class);
});
