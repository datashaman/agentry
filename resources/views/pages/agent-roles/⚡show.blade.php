<?php

use App\Models\AgentRole;
use App\Models\EventResponder;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Role')] #[Layout('layouts.app')] class extends Component {
    public AgentRole $agentRole;

    public ?string $selectedSkillId = null;

    public string $responderWorkItemType = '';

    public string $responderStatus = '';

    public string $responderInstructions = '';

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization();
        if (! $org || $this->agentRole->organization_id !== $org->id) {
            abort(403);
        }

        $this->agentRole->loadCount('agents');
        $this->agentRole->load(['agents.team', 'skills' => fn ($q) => $q->orderByPivot('position'), 'eventResponders']);
    }

    public function attachSkill(): void
    {
        $validated = $this->validate([
            'selectedSkillId' => ['required', 'exists:skills,id'],
        ]);

        $skill = Skill::findOrFail($validated['selectedSkillId']);

        if ($skill->organization_id !== $this->agentRole->organization_id) {
            $this->addError('selectedSkillId', __('Skill must belong to the same organization.'));

            return;
        }

        if ($this->agentRole->skills()->where('skill_id', $skill->id)->exists()) {
            $this->addError('selectedSkillId', __('Skill is already assigned.'));

            return;
        }

        $maxPosition = $this->agentRole->skills()->max('position') ?? -1;
        $this->agentRole->skills()->attach($skill->id, ['position' => $maxPosition + 1]);
        $this->selectedSkillId = null;
        $this->agentRole->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
        unset($this->availableSkills);
    }

    public function detachSkill(int $skillId): void
    {
        $this->agentRole->skills()->detach($skillId);
        $this->reorderPositions();
        $this->agentRole->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
        unset($this->availableSkills);
    }

    public function moveSkillUp(int $skillId): void
    {
        $skills = $this->agentRole->skills()->orderByPivot('position')->get();
        $index = $skills->search(fn ($s) => $s->id === $skillId);
        if ($index === false || $index === 0) {
            return;
        }
        $current = $skills->get($index);
        $previous = $skills->get($index - 1);
        $this->agentRole->skills()->updateExistingPivot($current->id, ['position' => $index - 1]);
        $this->agentRole->skills()->updateExistingPivot($previous->id, ['position' => $index]);
        $this->agentRole->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
    }

    public function moveSkillDown(int $skillId): void
    {
        $skills = $this->agentRole->skills()->orderByPivot('position')->get();
        $index = $skills->search(fn ($s) => $s->id === $skillId);
        if ($index === false || $index >= $skills->count() - 1) {
            return;
        }
        $current = $skills->get($index);
        $next = $skills->get($index + 1);
        $this->agentRole->skills()->updateExistingPivot($current->id, ['position' => $index + 1]);
        $this->agentRole->skills()->updateExistingPivot($next->id, ['position' => $index]);
        $this->agentRole->load(['skills' => fn ($q) => $q->orderByPivot('position')]);
    }

    protected function reorderPositions(): void
    {
        $skills = $this->agentRole->skills()->orderByPivot('position')->get();
        foreach ($skills as $index => $skill) {
            $this->agentRole->skills()->updateExistingPivot($skill->id, ['position' => $index]);
        }
    }

    #[Computed]
    public function availableSkills(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->agentRole->organization;

        if (! $org) {
            return collect();
        }

        $assignedIds = $this->agentRole->skills->pluck('id')->toArray();

        return Skill::query()
            ->where('organization_id', $org->id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableStatuses(): array
    {
        if (empty($this->responderWorkItemType)) {
            return [];
        }

        return EventResponder::AVAILABLE_STATUSES[$this->responderWorkItemType] ?? [];
    }

    public function updatedResponderWorkItemType(): void
    {
        $this->responderStatus = '';
        unset($this->availableStatuses);
    }

    public function addEventResponder(): void
    {
        $this->validate([
            'responderWorkItemType' => ['required', 'in:story,bug,ops_request'],
            'responderStatus' => ['required', 'string'],
            'responderInstructions' => ['required', 'string'],
        ]);

        $exists = $this->agentRole->eventResponders()
            ->where('work_item_type', $this->responderWorkItemType)
            ->where('status', $this->responderStatus)
            ->exists();

        if ($exists) {
            $this->addError('responderStatus', __('A responder for this work item type and status already exists.'));

            return;
        }

        $this->agentRole->eventResponders()->create([
            'work_item_type' => $this->responderWorkItemType,
            'status' => $this->responderStatus,
            'instructions' => $this->responderInstructions,
        ]);

        $this->responderWorkItemType = '';
        $this->responderStatus = '';
        $this->responderInstructions = '';
        $this->agentRole->load('eventResponders');
    }

    public function removeEventResponder(int $responderId): void
    {
        $this->agentRole->eventResponders()->where('id', $responderId)->delete();
        $this->agentRole->load('eventResponders');
    }

    public function deleteAgentRole(): void
    {
        if ($this->agentRole->agents()->count() > 0) {
            return;
        }

        $this->agentRole->delete();

        $this->redirect(route('agent-roles.index'), navigate: true);
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
    <div class="flex items-center justify-between" data-test="agent-role-header">
        <div>
            <flux:heading size="xl">{{ $agentRole->name }}</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $agentRole->slug }}</flux:badge>
                <flux:text class="text-sm">{{ $agentRole->agents_count }} {{ Str::plural('agent', $agentRole->agents_count) }}</flux:text>
            </div>
            @if ($agentRole->description)
                <flux:text class="mt-2">{{ $agentRole->description }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('agents.create', ['agent_role' => $agentRole->id]) }}" wire:navigate data-test="create-agent-button">
                <flux:button variant="primary">{{ __('New Agent') }}</flux:button>
            </a>
            <a href="{{ route('agent-roles.edit', $agentRole) }}" wire:navigate data-test="edit-agent-role-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-agent-role-deletion">
                <flux:button variant="danger" data-test="delete-agent-role-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Assigned Skills --}}
    <div data-test="agent-role-skills">
        <flux:heading size="lg">{{ __('Assigned Skills') }} ({{ $agentRole->skills->count() }})</flux:heading>
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
        @if ($agentRole->skills->isEmpty())
            <flux:text class="mt-2">{{ __('No skills assigned. Add skills to extend agent instructions.') }}</flux:text>
        @else
            <div class="mt-2 space-y-2">
                @foreach ($agentRole->skills as $skill)
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

    {{-- Event Responders --}}
    <div data-test="agent-role-event-responders">
        <flux:heading size="lg">{{ __('Event Responders') }} ({{ $agentRole->eventResponders->count() }})</flux:heading>
        <form wire:submit.prevent="addEventResponder" class="mt-2 flex flex-wrap items-end gap-2">
            <flux:select wire:model.live="responderWorkItemType" :placeholder="__('Work item type...')" data-test="responder-work-item-type" class="min-w-[160px]">
                @foreach (array_keys(\App\Models\EventResponder::WORK_ITEM_TYPES) as $type)
                    <flux:select.option :value="$type">{{ str_replace('_', ' ', ucfirst($type)) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="responderStatus" :placeholder="__('Status...')" data-test="responder-status" class="min-w-[160px]">
                @foreach ($this->availableStatuses as $status)
                    <flux:select.option :value="$status">{{ str_replace('_', ' ', $status) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="responderInstructions" :placeholder="__('Instructions...')" data-test="responder-instructions" rows="2" class="min-w-[200px] flex-1" />
            <flux:button type="submit" size="sm" variant="primary" data-test="add-responder-button">{{ __('Add') }}</flux:button>
        </form>
        <flux:error name="responderStatus" class="mt-1" />
        <flux:error name="responderWorkItemType" class="mt-1" />
        <flux:error name="responderInstructions" class="mt-1" />
        @if ($agentRole->eventResponders->isEmpty())
            <flux:text class="mt-2">{{ __('No event responders configured.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Work Item Type') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Instructions') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agentRole->eventResponders as $responder)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="responder-{{ $responder->id }}" data-test="responder-row">
                                <td class="px-4 py-3">
                                    <flux:text>{{ str_replace('_', ' ', ucfirst($responder->work_item_type)) }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $responder->status) }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text class="truncate">{{ Str::limit($responder->instructions, 80) }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:button size="xs" variant="danger" wire:click="removeEventResponder({{ $responder->id }})" data-test="remove-responder-{{ $responder->id }}">{{ __('Remove') }}</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Instructions --}}
    <div data-test="agent-role-instructions">
        <flux:heading size="lg">{{ __('Instructions') }}</flux:heading>
        @if (empty($agentRole->instructions))
            <flux:text class="mt-2">{{ __('No instructions defined.') }}</flux:text>
        @else
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $agentRole->instructions }}</flux:text>
        @endif
    </div>

    {{-- Tools --}}
    <div data-test="agent-role-tools">
        <flux:heading size="lg">{{ __('Tools') }}</flux:heading>
        @if (empty($agentRole->tools))
            <flux:text class="mt-2">{{ __('No tools configured.') }}</flux:text>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($agentRole->tools as $tool)
                    <flux:badge size="sm" variant="pill">{{ $tool }}</flux:badge>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Default Config --}}
    @if ($agentRole->default_model || $agentRole->default_provider || $agentRole->default_temperature !== null || $agentRole->default_max_steps || $agentRole->default_max_tokens || $agentRole->default_timeout)
        <div data-test="agent-role-default-config">
            <flux:heading size="lg">{{ __('Default Config') }}</flux:heading>
            <div class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @if ($agentRole->default_model)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Model') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_model }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_provider)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_provider }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_temperature !== null)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Temperature') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_temperature }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_max_steps)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Steps') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_max_steps }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_max_tokens)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Max Tokens') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_max_tokens }}</flux:text>
                    </div>
                @endif
                @if ($agentRole->default_timeout)
                    <div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Timeout (seconds)') }}</flux:text>
                        <flux:text class="block">{{ $agentRole->default_timeout }}</flux:text>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Agents --}}
    <div data-test="agent-role-agents">
        <flux:heading size="lg">{{ __('Agents') }} ({{ $agentRole->agents_count }})</flux:heading>
        @if ($agentRole->agents->isEmpty())
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
                        @foreach ($agentRole->agents as $agent)
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
    <flux:modal name="confirm-agent-role-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this agent role?') }}</flux:heading>
                @if ($agentRole->agents_count > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This agent role has assigned agents and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The agent role ":name" will be permanently deleted.', ['name' => $agentRole->name]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($agentRole->agents_count === 0)
                    <flux:button variant="danger" wire:click="deleteAgentRole" data-test="confirm-delete-agent-role-button">
                        {{ __('Delete Agent Role') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
