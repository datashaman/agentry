<?php

use App\Models\Organization;

function signPayload(string $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $payload, $secret);
}

function webhookRequest(array $payload, string $event, ?string $secret = null): \Illuminate\Testing\TestResponse
{
    $secret ??= 'test-webhook-secret';
    config(['services.github.webhook_secret' => $secret]);

    $json = json_encode($payload);

    return test()->postJson(route('github.webhook'), $payload, [
        'X-GitHub-Event' => $event,
        'X-Hub-Signature-256' => signPayload($json, $secret),
    ]);
}

test('rejects requests with invalid signature', function () {
    config(['services.github.webhook_secret' => 'real-secret']);

    $this->postJson(route('github.webhook'), ['action' => 'created'], [
        'X-GitHub-Event' => 'installation',
        'X-Hub-Signature-256' => 'sha256=invalidsignature',
    ])->assertForbidden();
});

test('rejects requests with missing signature', function () {
    config(['services.github.webhook_secret' => 'real-secret']);

    $this->postJson(route('github.webhook'), ['action' => 'created'], [
        'X-GitHub-Event' => 'installation',
    ])->assertForbidden();
});

test('rejects requests when webhook secret is not configured', function () {
    config(['services.github.webhook_secret' => null]);

    $this->postJson(route('github.webhook'), ['action' => 'created'], [
        'X-GitHub-Event' => 'installation',
        'X-Hub-Signature-256' => 'sha256=anything',
    ])->assertForbidden();
});

test('handles installation deleted event', function () {
    $organization = Organization::factory()->create([
        'github_installation_id' => 12345,
        'github_account_login' => 'acme-org',
        'github_account_type' => 'Organization',
    ]);

    webhookRequest([
        'action' => 'deleted',
        'installation' => ['id' => 12345],
    ], 'installation')->assertOk();

    $organization->refresh();
    expect($organization->github_installation_id)->toBeNull();
    expect($organization->github_account_login)->toBeNull();
    expect($organization->github_account_type)->toBeNull();
});

test('handles installation suspend event', function () {
    $organization = Organization::factory()->create([
        'github_installation_id' => 12345,
        'github_account_login' => 'acme-org',
        'github_account_type' => 'Organization',
    ]);

    webhookRequest([
        'action' => 'suspend',
        'installation' => ['id' => 12345],
    ], 'installation')->assertOk();

    $organization->refresh();
    expect($organization->github_installation_id)->toBeNull();
});

test('handles installation unsuspend event', function () {
    $organization = Organization::factory()->create([
        'github_installation_id' => 12345,
        'github_account_login' => 'acme-org',
        'github_account_type' => 'Organization',
    ]);

    webhookRequest([
        'action' => 'unsuspend',
        'installation' => ['id' => 12345],
    ], 'installation')->assertOk();

    $organization->refresh();
    expect($organization->github_installation_id)->toBe(12345);
});

test('handles installation_repositories event', function () {
    webhookRequest([
        'action' => 'added',
        'installation' => ['id' => 12345],
        'repositories_added' => [
            ['id' => 1, 'full_name' => 'acme/repo1'],
        ],
        'repositories_removed' => [],
    ], 'installation_repositories')->assertOk();
});

test('ignores unknown events', function () {
    webhookRequest([
        'action' => 'completed',
    ], 'check_run')->assertOk();
});

test('returns 422 when installation id is missing from installation event', function () {
    webhookRequest([
        'action' => 'deleted',
        'installation' => [],
    ], 'installation')->assertUnprocessable();
});
