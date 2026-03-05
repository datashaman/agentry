<?php

use App\Models\AgentType;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Type')] #[Layout('layouts.app')] class extends Component {
    public AgentType $agentType;

    public ?string $selectedSkillId = null;

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization();
        if (! $org || $this->agentType->organization_id !== $org->id) {
            abort(403);
        }

        $this->agentType->loadCount('agents');
        $this->agentType->load(['agents.team', 'skills' => fn ($q) => $q->orderByPivot('position')]);
    }

    public function attachSkill(): void
    {
        $validated = $this->validate([
            'selectedSkillId' => ['required', 'exists:skills,id'],
        ]);

        $skill = Skill::findOrFail($validated['selectedSkillId']);

        if ($skill->organization_id !== $this->agentType->organization_id) {
            $this->addError('selectedSkillId', __('Skill must belong to the same organization.'));

            return;
        }

        if ($this->agentType->skills()->where('skill_id', $skill->id)->exists()) {
            $this->addError('selectedSkillId', __('Skill is already assigned.'));

            return;
        }

        $maxPosition = $this->agentType->skills()->max('position') ?? -1;
        $this->agentType->skills()->attach($skill->id, ['position' => $maxPosition + 1]);
        $this->selectedSkillId = null;
        $this->agentType->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
        unset($this->availableSkills);
    }

    public function detachSkill(int $skillId): void
    {
        $this->agentType->skills()->detach($skillId);
        $this->reorderPositions();
        $this->agentType->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
        unset($this->availableSkills);
    }

    public function moveSkillUp(int $skillId): void
    {
        $skills = $this->agentType->skills()->orderByPivot('position')->get();
        $index = $skills->search(fn ($s) => $s->id === $skillId);
        if ($index === false || $index === 0) {
            return;
        }
        $current = $skills->get($index);
        $previous = $skills->get($index - 1);
        $this->agentType->skills()->updateExistingPivot($current->id, ['position' => $index - 1]);
        $this->agentType->skills()->updateExistingPivot($previous->id, ['position' => $index]);
        $this->agentType->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
    }

    public function moveSkillDown(int $skillId): void
    {
        $skills = $this->agentType->skills()->orderByPivot('position')->get();
        $index = $skills->search(fn ($s) => $s->id === $skillId);
        if ($index === false || $index >= $skills->count() - 1) {
            return;
        }
        $current = $skills->get($index);
        $next = $skills->get($index + 1);
        $this->agentType->skills()->updateExistingPivot($current->id, ['position' => $index + 1]);
        $this->agentType->skills()->updateExistingPivot($next->id, ['position' => $index]);
        $this->agentType->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
    }

    protected function reorderPositions(): void
    {
        $skills = $this->agentType->skills()->orderByPivot('position')->get();
        foreach ($skills as $index => $skill) {
            $this->agentType->skills()->updateExistingPivot($skill->id, ['position' => $index]);
        }
    }

    #[Computed]
    public function availableSkills(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->agentType->organization;

        if (! $org) {
            return collect();
        }

        $assignedIds = $this->agentType->skills->pluck('id')->toArray();

        return Skill::query()
            ->where('organization_id', $org->id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    public function deleteAgentType(): void
    {
        if ($this->agentType->agents()->count() > 0) {
            return;
        }

        $this->agentType->delete();

        $this->redirect(route('agent-types.index'), navigate: true);
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between" data-test="agent-type-header">
        <div>
            <flux:heading size="xl">{{ $agentType->name }}</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $agentType->slug }}</flux:badge>
                <flux:text class="text-sm">{{ $agentType->agents_count }} {{ Str::plural('agent', $agentType->agents_count) }}</flux:text>
            </div>
            @if ($agentType->description)
                <flux:text class="mt-2">{{ $agentType->description }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('agents.create', ['agent_type' => $agentType->id]) }}" wire:navigate data-test="create-agent-button">
                <flux:button variant="primary">{{ __('New Agent') }}</flux:button>
            </a>
            <a href="{{ route('agent-types.edit', $agentType) }}" wire:navigate data-test="edit-agent-type-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-agent-type-deletion">
                <flux:button variant="danger" data-test="delete-agent-type-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Assigned Skills --}}
    <div data-test="agent-type-skills">
        <flux:heading size="lg">{{ __('Assigned Skills') }} ({{ $agentType->skills->count() }})</flux:heading>
        @if ($this->availableSkills->isNotEmpty())
            <form wire:submit.prevent="attachSkill" class="mt-2 flex gap-2">
                <flux:select wire:model="selectedSkillId" :placeholder="__('Add skill...')" data-test="skill-select" class="min-w-[200px]">
                    @foreach ($this->availableSkills as $skill)
                        <flux:select.option :value="(string) $skill->id">{{ $skill->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm" variant="primary" data-test="attach-skill-button">{{ __('Add') }}</flux:button>
            </form>
            <flux:error name="selectedSkillId" class="mt-1" />
        @endif
        @if ($agentType->skills->isEmpty())
            <flux:text class="mt-2">{{ __('No skills assigned. Add skills to extend agent instructions.') }}</flux:text>
        @else
            <div class="mt-2 space-y-2">
                @foreach ($agentType->skills as $skill)
                    <div class="flex items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" wire:key="skill-{{ $skill->id }}" data-test="assigned-skill-row">
                        <div class="min-w-0 flex-1">
                            <flux:text class="font-medium">{{ $skill->name }}</flux:text>
                            @if ($skill->description)
                                <flux:text class="mt-0.5 block truncate text-sm text-zinc-500 dark:text-zinc-400">{{ Str::limit($skill->description, 60) }}</flux:text>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <flux:button size="xs" variant="ghost" wire:click="moveSkillUp({{ $skill->id }})" wire:loading.attr="disabled" data-test="move-skill-up-{{ $skill->id }}" :disabled="$loop->first">↑</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="moveSkillDown({{ $skill->id }})" wire:loading.attr="disabled" data-test="move-skill-down-{{ $skill->id }}" :disabled="$loop->last">↓</flux:button>
                            <flux:button size="xs" variant="ghost" wire:click="detachSkill({{ $skill->id }})" wire:loading.attr="disabled" data-test="detach-skill-{{ $skill->id }}">×</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Instructions --}}
    <div data-test="agent-type-instructions">
        <flux:heading size="lg">{{ __('Instructions') }}</flux:heading>
        @if (empty($agentType->instructions))
            <flux:text class="mt-2">{{ __('No instructions defined.') }}</flux:text>
        @else
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $agentType->instructions }}</flux:text>
        @endif
    </div>

    {{-- Tools --}}
    <div data-test="agent-type-tools">
        <flux:heading size="lg">{{ __('Tools') }}</flux:heading>
        @if (empty($agentType->tools))
            <flux:text class="mt-2">{{ __('No tools configured.') }}</flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($agentType->tools as $tool)
                    <flux:badge size="sm" variant="pill">{{ $tool }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Default Config --}}
    @if ($agentType->default_model || $agentType->default_provider || $agentType->default_temperature !== null || $agentType->default_max_steps || $agentType->default_max_tokens || $agentType->default_timeout)
        <div data-test="agent-type-default-config">
            <flux:heading size="lg">{{ __('Default Config') }}</flux:heading>
            <div class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @if ($agentType->default_model)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</flux:text>
                        <flux:text class="block">{{ $agentType->default_model }}</flux:text>
                    </div>
                @endif
                @if ($agentType->default_provider)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</flux:text>
                        <flux:text class="block">{{ $agentType->default_provider }}</flux:text>
                    </div>
                @endif
                @if ($agentType->default_temperature !== null)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Temperature') }}</flux:text>
                        <flux:text class="block">{{ $agentType->default_temperature }}</flux:text>
                    </div>
                @endif
                @if ($agentType->default_max_steps)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Steps') }}</flux:text>
                        <flux:text class="block">{{ $agentType->default_max_steps }}</flux:text>
                    </div>
                @endif
                @if ($agentType->default_max_tokens)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Tokens') }}</flux:text>
                        <flux:text class="block">{{ $agentType->default_max_tokens }}</flux:text>
                    </div>
                @endif
                @if ($agentType->default_timeout)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Timeout (seconds)') }}</flux:text>
                        <flux:text class="block">{{ $agentType->default_timeout }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Agents --}}
    <div data-test="agent-type-agents">
        <flux:heading size="lg">{{ __('Agents') }} ({{ $agentType->agents_count }})</flux:heading>
        @if ($agentType->agents->isEmpty())
            <flux:text class="mt-2">{{ __('No agents of this type.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Team') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agentType->agents as $agent)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="agent-{{ $agent->id }}" data-test="agent-row">
                                <td class="px-4 py-3">
                                    <a href="{{ route('agents.show', $agent) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $agent->name }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $agent->team?->name ?? '-' }}</flux:text>
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

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-agent-type-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this agent type?') }}</flux:heading>
                @if ($agentType->agents_count > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This agent type has assigned agents and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The agent type ":name" will be permanently deleted.', ['name' => $agentType->name]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($agentType->agents_count === 0)
                    <flux:button variant="danger" wire:click="deleteAgentType" data-test="confirm-delete-agent-type-button">
                        {{ __('Delete Agent Type') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
