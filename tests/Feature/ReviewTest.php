<?php

use App\Models\Agent;
use App\Models\PullRequest;
use App\Models\Review;
use Carbon\CarbonImmutable;

test('can create a review', function () {
    $review = Review::factory()->create();

    expect($review)->toBeInstanceOf(Review::class)
        ->and($review->status)->not->toBeEmpty();
});

test('review belongs to pull request', function () {
    $pullRequest = PullRequest::factory()->create();
    $review = Review::factory()->create(['pull_request_id' => $pullRequest->id]);

    expect($review->pullRequest)->toBeInstanceOf(PullRequest::class)
        ->and($review->pullRequest->id)->toBe($pullRequest->id);
});

test('review belongs to agent', function () {
    $agent = Agent::factory()->create();
    $review = Review::factory()->create(['agent_id' => $agent->id]);

    expect($review->agent)->toBeInstanceOf(Agent::class)
        ->and($review->agent->id)->toBe($agent->id);
});

test('pull request has many reviews', function () {
    $pullRequest = PullRequest::factory()->create();
    $reviews = Review::factory()->count(3)->create(['pull_request_id' => $pullRequest->id]);

    expect($pullRequest->reviews)->toHaveCount(3);
});

test('agent has many reviews', function () {
    $agent = Agent::factory()->create();
    $reviews = Review::factory()->count(3)->create(['agent_id' => $agent->id]);

    expect($agent->reviews)->toHaveCount(3);
});

test('review status defaults to pending', function () {
    $pullRequest = PullRequest::factory()->create();
    $review = Review::create(['pull_request_id' => $pullRequest->id]);
    $review->refresh();

    expect($review->status)->toBe('pending');
});

test('review supports all status values', function () {
    $statuses = ['pending', 'approved', 'changes_requested', 'commented'];

    foreach ($statuses as $status) {
        $review = Review::factory()->create(['status' => $status]);
        expect($review->status)->toBe($status);
    }
});

test('review body is nullable', function () {
    $review = Review::factory()->create(['body' => null]);

    expect($review->body)->toBeNull();
});

test('review submitted_at is nullable', function () {
    $review = Review::factory()->create(['submitted_at' => null]);

    expect($review->submitted_at)->toBeNull();
});

test('review submitted_at is cast to datetime', function () {
    $review = Review::factory()->create(['submitted_at' => '2026-03-01 10:00:00']);

    expect($review->submitted_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('review agent_id is nullable', function () {
    $review = Review::factory()->create(['agent_id' => null]);

    expect($review->agent_id)->toBeNull()
        ->and($review->agent)->toBeNull();
});

test('can update a review', function () {
    $review = Review::factory()->create(['status' => 'pending']);
    $review->update(['status' => 'approved', 'body' => 'Looks good!']);

    expect($review->fresh()->status)->toBe('approved')
        ->and($review->fresh()->body)->toBe('Looks good!');
});

test('can delete a review', function () {
    $review = Review::factory()->create();
    $reviewId = $review->id;

    $review->delete();

    expect(Review::find($reviewId))->toBeNull();
});

test('review cascades on pull request delete', function () {
    $pullRequest = PullRequest::factory()->create();
    $review = Review::factory()->create(['pull_request_id' => $pullRequest->id]);

    $pullRequest->delete();

    expect(Review::find($review->id))->toBeNull();
});

test('review agent nullified on agent delete', function () {
    $agent = Agent::factory()->create();
    $review = Review::factory()->create(['agent_id' => $agent->id]);

    $agent->delete();

    expect($review->fresh()->agent_id)->toBeNull();
});

test('can list reviews for a pull request', function () {
    $pullRequest = PullRequest::factory()->create();
    Review::factory()->count(5)->create(['pull_request_id' => $pullRequest->id]);
    Review::factory()->count(3)->create();

    expect(Review::where('pull_request_id', $pullRequest->id)->count())->toBe(5);
});
