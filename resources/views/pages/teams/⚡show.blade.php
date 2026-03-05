<?php

use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Team')] #[Layout('layouts.app')] class extends Component {
    public Team $team;

    public ?string $selectedProjectId = null;

    public function mount(): void
    {
        $this->team->load(['agents.agentType', 'projects']);
    }

    public function attachProject(): void
    {
        $validated = $this->validate([
            'selectedProjectId' => ['required', 'exists:projects,id'],
        ]);

        $project = Project::findOrFail($validated['selectedProjectId']);

        if ($project->organization_id !== $this->team->organization_id) {
            $this->addError('selectedProjectId', __('Project must belong to the same organization.'));

            return;
        }

        if ($this->team->projects()->where('project_id', $project->id)->exists()) {
            $this->addError('selectedProjectId', __('Project is already assigned.'));

            return;
        }

        $this->team->projects()->attach($project->id);
        $this->selectedProjectId = null;
        unset($this->availableProjects);
    }

    public function detachProject(int $projectId): void
    {
        $this->team->projects()->detach($projectId);
        unset($this->availableProjects);
    }

    public function deleteTeam(): void
    {
        if ($this->team->agents()->count() > 0) {
            return;
        }

        $this->team->delete();

        $this->redirect(route('teams.index'), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function availableProjects(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->team->organization;

        if (! $org) {
            return collect();
        }

        $assignedIds = $this->team->projects->pluck('id')->toArray();

        return Project::query()
            ->where('organization_id', $org->id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between" data-test="team-header">
        <div>
            <flux:heading size="xl">{{ $team->name }}</flux:heading>
            <flux:text class="mt-1">{{ $team->agents->count() }} {{ Str::plural('agent', $team->agents->count()) }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('teams.edit', $team) }}" wire:navigate data-test="edit-team-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-team-deletion">
                <flux:button variant="danger" data-test="delete-team-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-team-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this team?') }}</flux:heading>
                @if ($team->agents()->count() > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This team has assigned agents and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The team ":name" will be permanently deleted.', ['name' => $team->name]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($team->agents()->count() === 0)
                    <flux:button variant="danger" wire:click="deleteTeam" data-test="confirm-delete-team-button">
                        {{ __('Delete Team') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Project Assignments --}}
    <div data-test="team-project-assignments">
        <flux:heading size="lg">{{ __('Project Assignments') }}</flux:heading>
        @if ($team->projects->isEmpty())
            <flux:text class="mt-2">{{ __('No projects assigned to this team.') }}</flux:text>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($team->projects as $project)
                    <li class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-2 dark:border-zinc-700" data-test="project-row">
                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $project->name }}</a>
                        <flux:button size="sm" variant="ghost" wire:click="detachProject({{ $project->id }})" data-test="detach-project-button">{{ __('Remove') }}</flux:button>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($this->availableProjects->isNotEmpty())
            <form wire:submit="attachProject" class="mt-3 flex items-end gap-2" data-test="attach-project-form">
                <flux:select wire:model="selectedProjectId" :placeholder="__('Select a project...')" data-test="project-select">
                    @foreach ($this->availableProjects as $project)
                        <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm" variant="primary" data-test="attach-project-button">{{ __('Add') }}</flux:button>
            </form>
            <flux:error name="selectedProjectId" />
        @endif
    </div>

    {{-- Agents --}}
    <div data-test="team-agents">
        <flux:heading size="lg">{{ __('Agents') }} ({{ $team->agents->count() }})</flux:heading>
        @if ($team->agents->isEmpty())
            <flux:text class="mt-2">{{ __('No agents in this team.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($team->agents as $agent)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="agent-{{ $agent->id }}" data-test="agent-row">
                                <td class="px-4 py-3">
                                    <a href="{{ route('agents.show', $agent) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $agent->name }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ $agent->agentType?->name ?? '-' }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $agent->model }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill" :color="match($agent->status) { 'active' => 'green', 'idle' => 'zinc', 'error' => 'red', default => 'amber' }">{{ ucfirst($agent->status) }}</flux:badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
