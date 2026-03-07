<?php

use App\Http\Requests\UpdateAgentRoleRequest;
use App\Models\AgentRole;
use App\Models\EventResponder;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Agent Role')] #[Layout('layouts.app')] class extends Component {
    public AgentRole $agentRole;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $instructions = '';

    /** @var list<string> */
    public array $tools = [];

    public string $default_provider = '';

    public string $default_model = '';

    public string $default_temperature = '';

    public string $default_max_steps = '';

    public string $default_max_tokens = '';

    public string $default_timeout = '';

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

        $this->agentRole->load(['skills' => fn ($q) => $q->orderByPivot('position'), 'eventResponders']);

        $this->name = $this->agentRole->name;
        $this->slug = $this->agentRole->slug;
        $this->description = $this->agentRole->description ?? '';
        $this->instructions = $this->agentRole->instructions ?? '';
        $this->tools = $this->agentRole->tools ?? [];
        $this->default_model = $this->agentRole->default_model ?? '';
        $this->default_provider = $this->agentRole->default_provider ?? '';
        $this->default_temperature = $this->agentRole->default_temperature !== null ? (string) $this->agentRole->default_temperature : '';
        $this->default_max_steps = $this->agentRole->default_max_steps !== null ? (string) $this->agentRole->default_max_steps : '';
        $this->default_max_tokens = $this->agentRole->default_max_tokens !== null ? (string) $this->agentRole->default_max_tokens : '';
        $this->default_timeout = $this->agentRole->default_timeout !== null ? (string) $this->agentRole->default_timeout : '';
    }

    public function updateAgentRole(): void
    {
        $org = $this->organization;
        if (! $org || $this->agentRole->organization_id !== $org->id) {
            abort(403);
        }

        $validated = $this->validate(UpdateAgentRoleRequest::getRules($this->agentRole->id, $org->id));

        $this->agentRole->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'instructions' => $validated['instructions'] ?: null,
            'tools' => $validated['tools'] ?? [],
            'default_model' => $validated['default_model'] ?: null,
            'default_provider' => $validated['default_provider'] ?: null,
            'default_temperature' => $validated['default_temperature'] !== '' ? (float) $validated['default_temperature'] : null,
            'default_max_steps' => $validated['default_max_steps'] !== '' ? (int) $validated['default_max_steps'] : null,
            'default_max_tokens' => $validated['default_max_tokens'] !== '' ? (int) $validated['default_max_tokens'] : null,
            'default_timeout' => $validated['default_timeout'] !== '' ? (int) $validated['default_timeout'] : null,
        ]);

        $this->redirect(route('agent-roles.show', $this->agentRole), navigate: true);
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
        $this->agentRole->load('skills');
        unset($this->availableSkills);
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
            'responderWorkItemType' => ['required', 'string'],
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

    public function updatedDefaultProvider(): void
    {
        $this->default_model = '';
    }

    /**
     * @return array<string, array{label: string, models?: array<string, string>}>
     */
    #[Computed]
    public function providers(): array
    {
        return collect(config('agentry.providers'))
            ->filter(fn ($p) => ! empty($p['key']))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function models(): array
    {
        if (! $this->default_provider) {
            return [];
        }

        return config("agentry.providers.{$this->default_provider}.models", []);
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

    <div>
        <flux:heading size="xl">{{ __('Edit Agent Role') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update agent role ":name".', ['name' => $agentRole->name]) }}</flux:text>
    </div>

    <form wire:submit="updateAgentRole" class="max-w-xl space-y-6" data-test="edit-agent-role-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="agent-role-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Slug') }}</flux:label>
            <flux:input wire:model="slug" data-test="agent-role-slug-input" required />
            <flux:error name="slug" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:textarea wire:model="description" data-test="agent-role-description-input" rows="3" />
            <flux:error name="description" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Instructions') }}</flux:label>
            <flux:textarea wire:model="instructions" data-test="agent-role-instructions-input" rows="5" placeholder="System prompt for this agent role..." />
            <flux:description>{{ __('System prompt or instructions that define agent behavior.') }}</flux:description>
            <flux:error name="instructions" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Tools') }}</flux:label>
            <div class="mt-1 space-y-2" data-test="agent-role-tools-input">
                @foreach (\App\Agents\ToolRegistry::getProviderTools() as $toolId => $meta)
                    <flux:checkbox wire:model="tools" :value="$toolId" :label="$toolId" :description="$meta['description']" wire:key="tool-{{ $toolId }}" />
                @endforeach
            </div>
            <flux:description>{{ __('Select tools this agent role can use.') }}</flux:description>
            <flux:error name="tools" />
        </flux:field>

        <flux:heading size="md" class="mt-6">{{ __('Default Config') }}</flux:heading>
        <flux:text class="mb-4 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('Optional defaults that agents of this type can inherit.') }}</flux:text>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Default Provider') }}</flux:label>
                <flux:select wire:model.live="default_provider" :placeholder="__('Select provider...')" data-test="agent-role-default-provider-input">
                    @foreach ($this->providers as $key => $p)
                        <flux:select.option :value="$key">{{ $p['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="default_provider" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Model') }}</flux:label>
                @if (count($this->models) > 0)
                    <flux:select wire:model="default_model" :placeholder="__('Select model...')" data-test="agent-role-default-model-input">
                        @foreach ($this->models as $id => $label)
                            <flux:select.option :value="$id">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:input wire:model="default_model" data-test="agent-role-default-model-input" placeholder="{{ $this->default_provider ? __('Enter model ID...') : __('Select a provider first') }}" :disabled="! $this->default_provider" />
                @endif
                <flux:error name="default_model" />
            </flux:field>
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('Default Temperature') }}</flux:label>
                <flux:input wire:model="default_temperature" type="number" step="0.01" data-test="agent-role-default-temperature-input" placeholder="e.g. 0.7" />
                <flux:error name="default_temperature" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Max Steps') }}</flux:label>
                <flux:input wire:model="default_max_steps" type="number" min="1" data-test="agent-role-default-max-steps-input" placeholder="e.g. 10" />
                <flux:error name="default_max_steps" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Max Tokens') }}</flux:label>
                <flux:input wire:model="default_max_tokens" type="number" min="1" data-test="agent-role-default-max-tokens-input" placeholder="e.g. 4096" />
                <flux:error name="default_max_tokens" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Default Timeout (seconds)') }}</flux:label>
                <flux:input wire:model="default_timeout" type="number" min="1" data-test="agent-role-default-timeout-input" placeholder="e.g. 60" />
                <flux:error name="default_timeout" />
            </flux:field>
        </div>

    </form>

    {{-- Assigned Skills --}}
    <div class="max-w-xl" data-test="agent-role-skills">
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
                        <flux:button size="xs" variant="ghost" wire:click="detachSkill({{ $skill->id }})" wire:loading.attr="disabled" data-test="detach-skill-{{ $skill->id }}">×</flux:button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Event Responders --}}
    <div class="max-w-xl" data-test="agent-role-event-responders">
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

    <div class="flex max-w-xl items-center gap-2">
        <flux:button variant="primary" wire:click="updateAgentRole" data-test="save-agent-role-button">{{ __('Update Agent Role') }}</flux:button>
        <a href="{{ route('agent-roles.show', $agentRole) }}" wire:navigate>
            <flux:button>{{ __('Cancel') }}</flux:button>
        </a>
    </div>
</div>
