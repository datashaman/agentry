<?php

use App\Models\Agent;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Team')] #[Layout('layouts.app')] class extends Component {
    public Team $team;

    public string $name = '';

    public string $workflow_type = 'none';

    /** @var list<string> */
    public array $workflow_agent_ids = [];

    public string $cumulative = '0';

    public string $fan_in_agent_id = '';

    public string $router_agent_id = '';

    public string $planner_agent_id = '';

    public string $max_iterations = '10';

    public string $generator_agent_id = '';

    public string $evaluator_agent_id = '';

    public string $max_refinements = '3';

    public string $min_rating = 'good';

    public function mount(): void
    {
        $this->name = $this->team->name;
        $this->workflow_type = $this->team->workflow_type ?? 'none';

        $config = $this->team->workflow_config ?? [];
        $this->workflow_agent_ids = array_map('strval', $config['agents'] ?? []);
        $this->cumulative = ($config['cumulative'] ?? false) ? '1' : '0';
        $this->fan_in_agent_id = (string) ($config['fan_in_agent_id'] ?? '');
        $this->router_agent_id = (string) ($config['router_agent_id'] ?? '');
        $this->planner_agent_id = (string) ($config['planner_agent_id'] ?? '');
        $this->max_iterations = (string) ($config['max_iterations'] ?? 10);
        $this->generator_agent_id = (string) ($config['generator_agent_id'] ?? '');
        $this->evaluator_agent_id = (string) ($config['evaluator_agent_id'] ?? '');
        $this->max_refinements = (string) ($config['max_refinements'] ?? 3);
        $this->min_rating = $config['min_rating'] ?? 'good';
    }

    public function updateTeam(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'workflow_type' => ['required', Rule::in(['none', 'chain', 'parallel', 'router', 'orchestrator', 'evaluator_optimizer'])],
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (Team::where('organization_id', $this->team->organization_id)->where('slug', $slug)->where('id', '!=', $this->team->id)->exists()) {
            $slug = $baseSlug.'-'.$i++;
        }

        $this->team->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'workflow_type' => $validated['workflow_type'],
            'workflow_config' => $this->buildWorkflowConfig($validated['workflow_type']),
        ]);

        $this->redirect(route('teams.show', $this->team), navigate: true);
    }

    protected function buildWorkflowConfig(string $type): ?array
    {
        $agentIds = array_map('intval', array_filter($this->workflow_agent_ids));

        return match ($type) {
            'chain' => ['agents' => $agentIds, 'cumulative' => $this->cumulative === '1'],
            'parallel' => array_filter([
                'agents' => $agentIds,
                'fan_in_agent_id' => $this->fan_in_agent_id !== '' ? (int) $this->fan_in_agent_id : null,
            ], fn ($v) => $v !== null),
            'router' => [
                'router_agent_id' => (int) $this->router_agent_id,
                'agents' => $agentIds,
            ],
            'orchestrator' => [
                'planner_agent_id' => (int) $this->planner_agent_id,
                'agents' => $agentIds,
                'max_iterations' => (int) $this->max_iterations,
            ],
            'evaluator_optimizer' => [
                'generator_agent_id' => (int) $this->generator_agent_id,
                'evaluator_agent_id' => (int) $this->evaluator_agent_id,
                'max_refinements' => (int) $this->max_refinements,
                'min_rating' => $this->min_rating,
            ],
            default => null,
        };
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function teamAgents(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->team->agents()->orderBy('name')->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div>
        <flux:heading size="xl">{{ __('Edit Team') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Rename team ":name".', ['name' => $team->name]) }}</flux:text>
    </div>

    <form wire:submit="updateTeam" class="max-w-xl space-y-6" data-test="edit-team-form">
        <flux:field>
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" data-test="team-name-input" required />
            <flux:error name="name" />
        </flux:field>

        <flux:separator />

        <flux:heading size="lg">{{ __('Workflow') }}</flux:heading>
        <flux:text>{{ __('Configure how agents in this team collaborate on tasks.') }}</flux:text>

        <flux:field>
            <flux:label>{{ __('Workflow Type') }}</flux:label>
            <flux:select wire:model.live="workflow_type" data-test="workflow-type-select">
                <flux:select.option value="none">{{ __('None (independent agents)') }}</flux:select.option>
                <flux:select.option value="chain">{{ __('Chain (sequential)') }}</flux:select.option>
                <flux:select.option value="parallel">{{ __('Parallel (fan-out)') }}</flux:select.option>
                <flux:select.option value="router">{{ __('Router (dynamic routing)') }}</flux:select.option>
                <flux:select.option value="orchestrator">{{ __('Orchestrator (planner + workers)') }}</flux:select.option>
                <flux:select.option value="evaluator_optimizer">{{ __('Evaluator-Optimizer (refine loop)') }}</flux:select.option>
            </flux:select>
            <flux:error name="workflow_type" />
        </flux:field>

        @if ($workflow_type === 'chain')
            <flux:field>
                <flux:label>{{ __('Agent Execution Order') }}</flux:label>
                <div class="space-y-2" data-test="workflow-agents-select">
                    @forelse ($this->teamAgents as $agent)
                        <flux:checkbox wire:model="workflow_agent_ids" :value="(string) $agent->id" :label="$agent->name" wire:key="workflow-agent-{{ $agent->id }}" />
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No agents in this team yet.') }}</flux:text>
                    @endforelse
                </div>
                <flux:description>{{ __('Each agent receives the previous agent\'s output as input.') }}</flux:description>
                <flux:error name="workflow_agent_ids" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Cumulative Mode') }}</flux:label>
                <flux:switch wire:model="cumulative" data-test="workflow-cumulative-switch" />
                <flux:description>{{ __('When enabled, each agent sees all prior outputs instead of just the previous one.') }}</flux:description>
            </flux:field>
        @elseif ($workflow_type === 'parallel')
            <flux:field>
                <flux:label>{{ __('Parallel Agents') }}</flux:label>
                <div class="space-y-2" data-test="workflow-agents-select">
                    @forelse ($this->teamAgents as $agent)
                        <flux:checkbox wire:model="workflow_agent_ids" :value="(string) $agent->id" :label="$agent->name" wire:key="workflow-agent-{{ $agent->id }}" />
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No agents in this team yet.') }}</flux:text>
                    @endforelse
                </div>
                <flux:description>{{ __('All selected agents receive the same request simultaneously.') }}</flux:description>
                <flux:error name="workflow_agent_ids" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Fan-in Agent (optional)') }}</flux:label>
                <flux:select wire:model="fan_in_agent_id" :placeholder="__('No fan-in (return all results)')" data-test="workflow-fan-in-select">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->teamAgents as $agent)
                        <flux:select.option :value="(string) $agent->id">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('Synthesizes all parallel outputs into a single response.') }}</flux:description>
                <flux:error name="fan_in_agent_id" />
            </flux:field>
        @elseif ($workflow_type === 'router')
            <flux:field>
                <flux:label>{{ __('Router Agent') }}</flux:label>
                <flux:select wire:model="router_agent_id" :placeholder="__('Select router agent...')" data-test="workflow-router-select" required>
                    @foreach ($this->teamAgents as $agent)
                        <flux:select.option :value="(string) $agent->id">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('This agent decides which worker agent handles each request.') }}</flux:description>
                <flux:error name="router_agent_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Routable Agents') }}</flux:label>
                <div class="space-y-2" data-test="workflow-agents-select">
                    @forelse ($this->teamAgents as $agent)
                        <flux:checkbox wire:model="workflow_agent_ids" :value="(string) $agent->id" :label="$agent->name" wire:key="workflow-agent-{{ $agent->id }}" />
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No agents in this team yet.') }}</flux:text>
                    @endforelse
                </div>
                <flux:description>{{ __('Agents the router can delegate requests to.') }}</flux:description>
                <flux:error name="workflow_agent_ids" />
            </flux:field>
        @elseif ($workflow_type === 'orchestrator')
            <flux:field>
                <flux:label>{{ __('Planner Agent') }}</flux:label>
                <flux:select wire:model="planner_agent_id" :placeholder="__('Select planner agent...')" data-test="workflow-planner-select" required>
                    @foreach ($this->teamAgents as $agent)
                        <flux:select.option :value="(string) $agent->id">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('Plans tasks and delegates to worker agents.') }}</flux:description>
                <flux:error name="planner_agent_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Worker Agents') }}</flux:label>
                <div class="space-y-2" data-test="workflow-agents-select">
                    @forelse ($this->teamAgents as $agent)
                        <flux:checkbox wire:model="workflow_agent_ids" :value="(string) $agent->id" :label="$agent->name" wire:key="workflow-agent-{{ $agent->id }}" />
                    @empty
                        <flux:text class="text-sm text-zinc-500">{{ __('No agents in this team yet.') }}</flux:text>
                    @endforelse
                </div>
                <flux:description>{{ __('Agents the planner can assign tasks to.') }}</flux:description>
                <flux:error name="workflow_agent_ids" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Max Iterations') }}</flux:label>
                <flux:input wire:model="max_iterations" type="number" min="1" max="100" data-test="workflow-max-iterations-input" />
                <flux:description>{{ __('Maximum plan-execute cycles before stopping.') }}</flux:description>
                <flux:error name="max_iterations" />
            </flux:field>
        @elseif ($workflow_type === 'evaluator_optimizer')
            <flux:field>
                <flux:label>{{ __('Generator Agent') }}</flux:label>
                <flux:select wire:model="generator_agent_id" :placeholder="__('Select generator agent...')" data-test="workflow-generator-select" required>
                    @foreach ($this->teamAgents as $agent)
                        <flux:select.option :value="(string) $agent->id">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('Produces and refines responses.') }}</flux:description>
                <flux:error name="generator_agent_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Evaluator Agent') }}</flux:label>
                <flux:select wire:model="evaluator_agent_id" :placeholder="__('Select evaluator agent...')" data-test="workflow-evaluator-select" required>
                    @foreach ($this->teamAgents as $agent)
                        <flux:select.option :value="(string) $agent->id">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('Rates response quality and provides feedback.') }}</flux:description>
                <flux:error name="evaluator_agent_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Max Refinements') }}</flux:label>
                <flux:input wire:model="max_refinements" type="number" min="1" max="20" data-test="workflow-max-refinements-input" />
                <flux:description>{{ __('Maximum generate-evaluate cycles.') }}</flux:description>
                <flux:error name="max_refinements" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Minimum Rating') }}</flux:label>
                <flux:select wire:model="min_rating" data-test="workflow-min-rating-select">
                    <flux:select.option value="excellent">{{ __('Excellent') }}</flux:select.option>
                    <flux:select.option value="good">{{ __('Good') }}</flux:select.option>
                    <flux:select.option value="adequate">{{ __('Adequate') }}</flux:select.option>
                    <flux:select.option value="poor">{{ __('Poor') }}</flux:select.option>
                </flux:select>
                <flux:description>{{ __('Quality threshold to stop refinement.') }}</flux:description>
                <flux:error name="min_rating" />
            </flux:field>
        @endif

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-team-button">{{ __('Update Team') }}</flux:button>
            <a href="{{ route('teams.show', $team) }}" wire:navigate>
                <flux:button>{{ __('Cancel') }}</flux:button>
            </a>
        </div>
    </form>
</div>
