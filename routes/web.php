<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('projects', 'pages::projects.index')->name('projects.index');
    Route::livewire('projects/{project}', 'pages::projects.show')->name('projects.show');
    Route::livewire('projects/{project}/stories', 'pages::projects.stories.index')->name('projects.stories.index');
    Route::livewire('projects/{project}/stories/{story}', 'pages::projects.stories.show')->name('projects.stories.show');
    Route::livewire('projects/{project}/bugs', 'pages::projects.bugs.index')->name('projects.bugs.index');
    Route::livewire('projects/{project}/bugs/{bug}', 'pages::projects.bugs.show')->name('projects.bugs.show');
    Route::livewire('projects/{project}/ops-requests', 'pages::projects.ops-requests.index')->name('projects.ops-requests.index');
    Route::livewire('projects/{project}/ops-requests/{opsRequest}', 'pages::projects.ops-requests.show')->name('projects.ops-requests.show');
    Route::livewire('escalations', 'pages::escalations.index')->name('escalations.index');
});

require __DIR__.'/settings.php';
