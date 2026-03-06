<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WorkItem;

test('conversation belongs to a work item', function () {
    $workItem = WorkItem::factory()->create();
    $conversation = Conversation::factory()->create(['work_item_id' => $workItem->id]);

    expect($conversation->workItem->id)->toBe($workItem->id);
});

test('conversation has many messages', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->count(3)->create(['conversation_id' => $conversation->id]);

    expect($conversation->messages)->toHaveCount(3);
});

test('work item has one conversation', function () {
    $workItem = WorkItem::factory()->create();
    $conversation = Conversation::factory()->create(['work_item_id' => $workItem->id]);

    expect($workItem->conversation->id)->toBe($conversation->id);
});

test('deleting a work item cascade deletes its conversation and messages', function () {
    $workItem = WorkItem::factory()->create();
    $conversation = Conversation::factory()->create(['work_item_id' => $workItem->id]);
    Message::factory()->count(2)->create(['conversation_id' => $conversation->id]);

    $workItem->delete();

    expect(Conversation::query()->where('id', $conversation->id)->exists())->toBeFalse();
    expect(Message::query()->where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

test('deleting a conversation cascade deletes its messages', function () {
    $conversation = Conversation::factory()->create();
    Message::factory()->count(2)->create(['conversation_id' => $conversation->id]);

    $conversation->delete();

    expect(Message::query()->where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

test('message factory creates valid messages with expected roles', function () {
    $message = Message::factory()->create();

    expect($message->role)->toBeIn(['system', 'user', 'assistant']);
    expect($message->content)->toBeString()->not->toBeEmpty();
});
