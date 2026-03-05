<?php

use App\Models\AgentType;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Skill')] #[Layout('layouts.app')] class extends Component {
    public Skill $skill;

    public ?string $selectedAgentTypeId = null;

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization();
        if (! $org || $this->skill->organization_id !== $org->id) {
            abort(403);
        }

        $this->skill->loadCount('agentTypes');
        $this->skill->load('agentTypes');
    }

    public function attachAgentType(): void
    {
        $validated = $this->validate([
            'selectedAgentTypeId' => ['required', 'exists:agent_types,id'],
        ]);

        $agentType = AgentType::findOrFail($validated['selectedAgentTypeId']);

        if ($agentType->organization_id !== $this->skill->organization_id) {
            $this->addError('selectedAgentTypeId', __('Agent type must belong to the same organization.'));

            return;
        }

        if ($this->skill->agentTypes()->where('agent_types.id', $agentType->id)->exists()) {
            $this->addError('selectedAgentTypeId', __('Agent type is already assigned.'));

            return;
        }

        $maxPosition = $this->skill->agentTypes()->max('position') ?? -1;
        $this->skill->agentTypes()->attach($agentType->id, ['position' => $maxPosition + 1]);
        $this->selectedAgentTypeId = null;
        $this->skill->load('agentTypes');
        unset($this->availableAgentTypes);
    }

    public function detachAgentType(int $agentTypeId): void
    {
        $this->skill->agentTypes()->detach($agentTypeId);
        $this->skill->load('agentTypes');
        unset($this->availableAgentTypes);
    }

    #[Computed]
    public function availableAgentTypes(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->skill->organization;

        if (! $org) {
            return collect();
        }

        $assignedIds = $this->skill->agentTypes->pluck('id')->toArray();

        return AgentType::query()
            ->where('organization_id', $org->id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }

    public function deleteSkill(): void
    {
        if ($this->skill->agentTypes()->count() > 0) {
            return;
        }

        $this->skill->delete();

        $this->redirect(route('skills.index'), navigate: true);
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
    <div class="flex items-center justify-between" data-test="skill-header">
        <div>
            <flux:heading size="xl">{{ $skill->name }}</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge size="sm" variant="pill">{{ $skill->slug }}</flux:badge>
                <flux:text class="text-sm">{{ $skill->agent_types_count }} {{ Str::plural('agent type', $skill->agent_types_count) }}</flux:text>
            </div>
            @if ($skill->description)
                <flux:text class="mt-2">{{ $skill->description }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('skills.edit', $skill) }}" wire:navigate data-test="edit-skill-button">
                <flux:button>{{ __('Edit') }}</flux:button>
            </a>
            <flux:modal.trigger name="confirm-skill-deletion">
                <flux:button variant="danger" data-test="delete-skill-button">{{ __('Delete') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    {{-- Context Triggers --}}
    @if ($skill->context_triggers && count($skill->context_triggers) > 0)
        <div data-test="skill-context-triggers">
            <flux:heading size="lg">{{ __('Context Triggers') }}</flux:heading>
            <flux:text class="mt-2 font-mono text-sm">{{ json_encode($skill->context_triggers, JSON_PRETTY_PRINT) }}</flux:text>
        </div>
    @endif

    {{-- Content --}}
    <div data-test="skill-content">
        <flux:heading size="lg">{{ __('Content (Instructions)') }}</flux:heading>
        @if (empty(trim($skill->content ?? '')))
            <flux:text class="mt-2">{{ __('No content defined.') }}</flux:text>
        @else
            <div class="mt-2 whitespace-pre-wrap rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:text class="whitespace-pre-wrap">{{ $skill->content }}</flux:text>
            </div>
        @endif
    </div>

    {{-- Assigned Agent Types --}}
    <div data-test="skill-agent-types">
        <flux:heading size="lg">{{ __('Assigned Agent Types') }} ({{ $skill->agent_types_count }})</flux:heading>
        @if ($this->availableAgentTypes->isNotEmpty())
            <form wire:submit.prevent="attachAgentType" class="mt-2 flex gap-2">
                <flux:select wire:model="selectedAgentTypeId" :placeholder="__('Add agent type...')" data-test="agent-type-select" class="min-w-[200px]">
                    @foreach ($this->availableAgentTypes as $agentType)
                        <flux:select.option :value="(string) $agentType->id">{{ $agentType->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" size="sm" variant="primary" data-test="attach-agent-type-button">{{ __('Add') }}</flux:button>
            </form>
            <flux:error name="selectedAgentTypeId" class="mt-1" />
        @endif
        @if ($skill->agentTypes->isEmpty())
            <flux:text class="mt-2">{{ __('No agent types use this skill.') }}</flux:text>
        @else
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Slug') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($skill->agentTypes as $agentType)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="agent-type-{{ $agentType->id }}" data-test="agent-type-row">
                                <td class="px-4 py-3">
                                    <a href="{{ route('agent-types.show', $agentType) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $agentType->name }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" variant="pill">{{ $agentType->slug }}</flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:button size="xs" variant="ghost" wire:click="detachAgentType({{ $agentType->id }})" wire:loading.attr="disabled" data-test="detach-agent-type-{{ $agentType->id }}">×</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-skill-deletion" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete this skill?') }}</flux:heading>
                @if ($skill->agent_types_count > 0)
                    <flux:text class="mt-2 text-red-600">{{ __('This skill is assigned to agent types and cannot be deleted.') }}</flux:text>
                @else
                    <flux:text class="mt-2">{{ __('This action cannot be undone. The skill ":name" will be permanently deleted.', ['name' => $skill->name]) }}</flux:text>
                @endif
            </div>
            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($skill->agent_types_count === 0)
                    <flux:button variant="danger" wire:click="deleteSkill" data-test="confirm-delete-skill-button">
                        {{ __('Delete Skill') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
