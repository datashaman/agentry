<?php

use App\Http\Controllers\DownloadAttachmentController;
use App\Http\Controllers\GitHubAppSetupController;
use App\Http\Controllers\GitHubController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\JiraController;
use App\Http\Controllers\SkillExportController;
use App\Http\Controllers\SwitchOrganizationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::post('github/webhook', GitHubWebhookController::class)->name('github.webhook');

Route::get('auth/github/redirect', [GitHubController::class, 'redirect'])->name('github.redirect');
Route::get('auth/github/callback', [GitHubController::class, 'callback'])->name('github.callback');

Route::get('auth/jira/redirect', [JiraController::class, 'redirect'])->name('jira.redirect')->middleware('auth');
Route::get('auth/jira/callback', [JiraController::class, 'callback'])->name('jira.callback')->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('github/setup', GitHubAppSetupController::class)->name('github.app.setup');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('action-logs', 'pages::action-logs.index')->name('action-logs.index');
    Route::livewire('projects', 'pages::projects.index')->name('projects.index');
    Route::livewire('projects/create', 'pages::projects.create')->name('projects.create');
    Route::livewire('projects/{project}', 'pages::projects.show')->name('projects.show');
    Route::livewire('projects/{project}/edit', 'pages::projects.edit')->name('projects.edit');
    Route::livewire('projects/{project}/action-logs', 'pages::projects.action-logs.index')->name('projects.action-logs.index');
    Route::livewire('projects/{project}/work-items', 'pages::projects.work-items.index')->name('projects.work-items.index');
    Route::livewire('projects/{project}/repos', 'pages::projects.repos.index')->name('projects.repos.index');
    Route::livewire('projects/{project}/repos/create', 'pages::projects.repos.create')->name('projects.repos.create');
    Route::livewire('projects/{project}/repos/{repo}', 'pages::projects.repos.show')->name('projects.repos.show');
    Route::livewire('projects/{project}/repos/{repo}/edit', 'pages::projects.repos.edit')->name('projects.repos.edit');
    Route::livewire('projects/{project}/milestones', 'pages::projects.milestones.index')->name('projects.milestones.index');
    Route::livewire('projects/{project}/milestones/create', 'pages::projects.milestones.create')->name('projects.milestones.create');
    Route::livewire('projects/{project}/milestones/{milestone}', 'pages::projects.milestones.show')->name('projects.milestones.show');
    Route::livewire('projects/{project}/milestones/{milestone}/edit', 'pages::projects.milestones.edit')->name('projects.milestones.edit');
    Route::livewire('projects/{project}/labels', 'pages::projects.labels.index')->name('projects.labels.index');
    Route::livewire('projects/{project}/ops-requests', 'pages::projects.ops-requests.index')->name('projects.ops-requests.index');
    Route::livewire('projects/{project}/ops-requests/{opsRequest}', 'pages::projects.ops-requests.show')->name('projects.ops-requests.show');
    Route::livewire('projects/{project}/ops-requests/{opsRequest}/runbooks/{runbook}', 'pages::projects.ops-requests.runbooks.show')->name('projects.ops-requests.runbooks.show');
    Route::livewire('agent-roles', 'pages::agent-roles.index')->name('agent-roles.index');
    Route::livewire('agent-roles/create', 'pages::agent-roles.create')->name('agent-roles.create');
    Route::livewire('agent-roles/{agentRole}', 'pages::agent-roles.show')->name('agent-roles.show');
    Route::livewire('agent-roles/{agentRole}/edit', 'pages::agent-roles.edit')->name('agent-roles.edit');
    Route::livewire('skills', 'pages::skills.index')->name('skills.index');
    Route::livewire('skills/create', 'pages::skills.create')->name('skills.create');
    Route::livewire('skills/import', 'pages::skills.import')->name('skills.import');
    Route::get('skills/{skill}/export', SkillExportController::class)->name('skills.export');
    Route::livewire('skills/{skill}', 'pages::skills.show')->name('skills.show');
    Route::livewire('skills/{skill}/edit', 'pages::skills.edit')->name('skills.edit');
    Route::livewire('agents/create', 'pages::agents.create')->name('agents.create');
    Route::livewire('agents/{agent}', 'pages::agents.show')->name('agents.show');
    Route::livewire('agents/{agent}/edit', 'pages::agents.edit')->name('agents.edit');
    Route::livewire('agent-permissions', 'pages::agent-permissions.index')->name('agent-permissions.index');
    Route::livewire('escalations', 'pages::escalations.index')->name('escalations.index');
    Route::livewire('teams', 'pages::teams.index')->name('teams.index');
    Route::livewire('teams/create', 'pages::teams.create')->name('teams.create');
    Route::livewire('teams/{team}', 'pages::teams.show')->name('teams.show');
    Route::livewire('teams/{team}/edit', 'pages::teams.edit')->name('teams.edit');
    Route::post('switch-organization/{organization}', SwitchOrganizationController::class)->name('switch-organization');
    Route::get('attachments/{attachment}/download', DownloadAttachmentController::class)->name('attachments.download');
});

require __DIR__.'/settings.php';
