<?php

use App\Http\Controllers\DownloadAttachmentController;
use App\Http\Controllers\SwitchOrganizationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('action-logs', 'pages::action-logs.index')->name('action-logs.index');
    Route::livewire('projects', 'pages::projects.index')->name('projects.index');
    Route::livewire('projects/{project}', 'pages::projects.show')->name('projects.show');
    Route::livewire('projects/{project}/action-logs', 'pages::projects.action-logs.index')->name('projects.action-logs.index');
    Route::livewire('projects/{project}/epics', 'pages::projects.epics.index')->name('projects.epics.index');
    Route::livewire('projects/{project}/stories', 'pages::projects.stories.index')->name('projects.stories.index');
    Route::livewire('projects/{project}/stories/{story}', 'pages::projects.stories.show')->name('projects.stories.show');
    Route::livewire('projects/{project}/bugs', 'pages::projects.bugs.index')->name('projects.bugs.index');
    Route::livewire('projects/{project}/bugs/{bug}', 'pages::projects.bugs.show')->name('projects.bugs.show');
    Route::livewire('projects/{project}/repos', 'pages::projects.repos.index')->name('projects.repos.index');
    Route::livewire('projects/{project}/repos/create', 'pages::projects.repos.create')->name('projects.repos.create');
    Route::livewire('projects/{project}/repos/{repo}', 'pages::projects.repos.show')->name('projects.repos.show');
    Route::livewire('projects/{project}/repos/{repo}/edit', 'pages::projects.repos.edit')->name('projects.repos.edit');
    Route::livewire('projects/{project}/repos/{repo}/branches', 'pages::projects.repos.branches.index')->name('projects.repos.branches.index');
    Route::livewire('projects/{project}/repos/{repo}/worktrees', 'pages::projects.repos.worktrees.index')->name('projects.repos.worktrees.index');
    Route::livewire('projects/{project}/milestones', 'pages::projects.milestones.index')->name('projects.milestones.index');
    Route::livewire('projects/{project}/milestones/create', 'pages::projects.milestones.create')->name('projects.milestones.create');
    Route::livewire('projects/{project}/milestones/{milestone}', 'pages::projects.milestones.show')->name('projects.milestones.show');
    Route::livewire('projects/{project}/milestones/{milestone}/edit', 'pages::projects.milestones.edit')->name('projects.milestones.edit');
    Route::livewire('projects/{project}/labels', 'pages::projects.labels.index')->name('projects.labels.index');
    Route::livewire('projects/{project}/ops-requests', 'pages::projects.ops-requests.index')->name('projects.ops-requests.index');
    Route::livewire('projects/{project}/ops-requests/{opsRequest}', 'pages::projects.ops-requests.show')->name('projects.ops-requests.show');
    Route::livewire('projects/{project}/ops-requests/{opsRequest}/runbooks/{runbook}', 'pages::projects.ops-requests.runbooks.show')->name('projects.ops-requests.runbooks.show');
    Route::livewire('agent-types', 'pages::agent-types.index')->name('agent-types.index');
    Route::livewire('agent-types/create', 'pages::agent-types.create')->name('agent-types.create');
    Route::livewire('agent-types/{agentType}', 'pages::agent-types.show')->name('agent-types.show');
    Route::livewire('agent-types/{agentType}/edit', 'pages::agent-types.edit')->name('agent-types.edit');
    Route::livewire('agents/create', 'pages::agents.create')->name('agents.create');
    Route::livewire('agents/{agent}', 'pages::agents.show')->name('agents.show');
    Route::livewire('agents/{agent}/edit', 'pages::agents.edit')->name('agents.edit');
    Route::livewire('escalations', 'pages::escalations.index')->name('escalations.index');
    Route::livewire('teams', 'pages::teams.index')->name('teams.index');
    Route::livewire('teams/create', 'pages::teams.create')->name('teams.create');
    Route::livewire('teams/{team}', 'pages::teams.show')->name('teams.show');
    Route::livewire('teams/{team}/edit', 'pages::teams.edit')->name('teams.edit');
    Route::post('switch-organization/{organization}', SwitchOrganizationController::class)->name('switch-organization');
    Route::get('attachments/{attachment}/download', DownloadAttachmentController::class)->name('attachments.download');
});

require __DIR__.'/settings.php';
