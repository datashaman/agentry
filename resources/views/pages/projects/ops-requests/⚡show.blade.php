<?php

use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use App\Models\Project;
use App\Services\GitHubAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ops Request Detail')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    public OpsRequest $opsRequest;

    public ?int $resolvingEscalationId = null;

    public string $resolutionNotes = '';

    public function mount(): void
    {
        $this->opsRequest->load([
            'assignedAgent',
            'runbooks.steps',
            'hitlEscalations.raisedByAgent',
        ]);
    }

    public function startResolving(int $escalationId): void
    {
        $this->resolvingEscalationId = $escalationId;
        $this->resolutionNotes = '';
    }

    public function cancelResolving(): void
    {
        $this->resolvingEscalationId = null;
        $this->resolutionNotes = '';
    }

    public function resolveEscalation(int $escalationId): void
    {
        $this->validate([
            'resolutionNotes' => 'required|string|min:1',
        ]);

        $escalation = HitlEscalation::findOrFail($escalationId);
        $escalation->update([
            'resolution' => $this->resolutionNotes,
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->resolvingEscalationId = null;
        $this->resolutionNotes = '';
        $this->opsRequest->load('hitlEscalations.raisedByAgent');
    }

    public function deferEscalation(int $escalationId): void
    {
        $escalation = HitlEscalation::findOrFail($escalationId);
        $escalation->update([
            'resolution' => 'Deferred',
            'resolved_by' => Auth::user()->name,
            'resolved_at' => now(),
        ]);

        $this->opsRequest->load('hitlEscalations.raisedByAgent');
    }

    #[Computed]
    public function pullRequests(): array
    {
        $github = app(GitHubAppService::class);
        $branchName = 'ops/ops-' . $this->opsRequest->id;
        $prs = [];

        foreach ($this->project->repos as $repo) {
            foreach ($github->listPullRequests($repo, $branchName) as $pr) {
                $pr['_repo_name'] = $repo->name;
                $pr['_reviews'] = $github->listPullRequestReviews($repo, $pr['number']);
                $prs[] = $pr;
            }
        }

        return $prs;
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    {{-- Header --}}
    <div data-test="ops-request-header">
        <flux:heading size="xl">{{ $opsRequest->title }}</flux:heading>
        <div class="mt-3 flex flex-wrap gap-3">
            <flux:badge size="sm" variant="pill" data-test="ops-request-status">{{ str_replace('_', ' ', $opsRequest->status) }}</flux:badge>
            <flux:badge size="sm" variant="pill" data-test="ops-request-category">{{ $opsRequest->category }}</flux:badge>
            @php
                $riskColors = ['critical' => 'red', 'high' => 'amber', 'medium' => 'blue', 'low' => 'zinc'];
            @endphp
            <flux:badge size="sm" variant="pill" :color="$riskColors[$opsRequest->risk_level] ?? 'zinc'" data-test="ops-request-risk-level">{{ $opsRequest->risk_level }}</flux:badge>
            <flux:badge size="sm" variant="pill" data-test="ops-request-execution-type">{{ $opsRequest->execution_type }}</flux:badge>
            @if ($opsRequest->environment)
                <flux:badge size="sm" variant="pill" data-test="ops-request-environment">{{ $opsRequest->environment }}</flux:badge>
            @endif
            @if ($opsRequest->assignedAgent)
                <flux:badge size="sm" variant="pill" data-test="ops-request-agent">{{ $opsRequest->assignedAgent->name }}</flux:badge>
            @endif
        </div>
    </div>

    {{-- Description --}}
    @if ($opsRequest->description)
        <div data-test="ops-request-description">
            <flux:heading size="lg">{{ __('Description') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap">{{ $opsRequest->description }}</flux:text>
        </div>
    @endif

    {{-- Pull Requests --}}
    @if (count($this->pullRequests) > 0)
        <div data-test="ops-request-pull-requests">
            <flux:heading size="lg">{{ __('Pull Requests') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($this->pullRequests as $pr)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="pr-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:text class="font-medium">{{ $pr['title'] }}</flux:text>
                            <flux:badge size="sm" variant="pill">{{ $pr['state'] }}</flux:badge>
                            <flux:badge size="sm" variant="pill">{{ $pr['_repo_name'] }}</flux:badge>
                            <flux:badge size="sm" variant="pill" class="font-mono">{{ $pr['head']['ref'] }}</flux:badge>
                            @if ($pr['html_url'])
                                <a href="{{ $pr['html_url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600 hover:underline dark:text-primary-400" data-test="pr-external-link">
                                    {{ __('Open PR') }} ↗
                                </a>
                            @endif
                        </div>
                        @if (count($pr['_reviews']) > 0)
                            <div class="mt-3 space-y-2">
                                @foreach ($pr['_reviews'] as $review)
                                    <div class="rounded bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50" data-test="review-item">
                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <flux:text class="font-medium">{{ $review['user']['login'] ?? '-' }}</flux:text>
                                            <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $review['state']) }}</flux:badge>
                                            @if ($review['submitted_at'] ?? null)
                                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ \Carbon\Carbon::parse($review['submitted_at'])->format('M j, Y H:i') }}</flux:text>
                                            @endif
                                        </div>
                                        @if ($review['body'] ?? null)
                                            <flux:text class="mt-1 block text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">{{ Str::limit($review['body'], 200) }}</flux:text>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Runbooks --}}
    @if ($opsRequest->runbooks->isNotEmpty())
        <div data-test="ops-request-runbooks">
            <flux:heading size="lg">{{ __('Runbooks') }}</flux:heading>
            <div class="mt-2 space-y-4">
                @foreach ($opsRequest->runbooks as $runbook)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="runbook-item">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('projects.ops-requests.runbooks.show', [$project, $opsRequest, $runbook]) }}" wire:navigate class="font-medium hover:underline" data-test="runbook-link">{{ $runbook->title }}</a>
                            <flux:badge size="sm" variant="pill">{{ $runbook->status }}</flux:badge>
                        </div>
                        @if ($runbook->description)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $runbook->description }}</flux:text>
                        @endif
                        @if ($runbook->steps->isNotEmpty())
                            <div class="mt-3 space-y-2" data-test="runbook-steps">
                                @foreach ($runbook->steps as $step)
                                    <div class="flex items-start gap-3 rounded border border-zinc-100 p-3 dark:border-zinc-700" data-test="runbook-step">
                                        <flux:badge size="sm" variant="pill" class="shrink-0">{{ $step->position }}</flux:badge>
                                        <div class="flex-1">
                                            <flux:text class="text-sm">{{ $step->instruction }}</flux:text>
                                            <div class="mt-1 flex items-center gap-2">
                                                @php
                                                    $stepStatusColors = ['completed' => 'green', 'executing' => 'blue', 'failed' => 'red', 'skipped' => 'zinc', 'pending' => 'zinc'];
                                                @endphp
                                                <flux:badge size="sm" variant="pill" :color="$stepStatusColors[$step->status] ?? 'zinc'" data-test="step-status">{{ $step->status }}</flux:badge>
                                                @if ($step->executed_by)
                                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('by :who', ['who' => $step->executed_by]) }}</flux:text>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- HITL Escalations --}}
    @if ($opsRequest->hitlEscalations->isNotEmpty())
        <div data-test="ops-request-escalations">
            <flux:heading size="lg">{{ __('HITL Escalations') }}</flux:heading>
            <div class="mt-2 space-y-3">
                @foreach ($opsRequest->hitlEscalations as $escalation)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="escalation-item">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" variant="pill">{{ $escalation->trigger_type }}</flux:badge>
                            @if ($escalation->isResolved())
                                <flux:badge size="sm" variant="pill" color="green">{{ __('Resolved') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="pill" color="red">{{ __('Unresolved') }}</flux:badge>
                            @endif
                            @if ($escalation->agent_confidence !== null)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Confidence: :pct%', ['pct' => round($escalation->agent_confidence * 100)]) }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-1 text-sm">{{ $escalation->reason }}</flux:text>
                        @if ($escalation->raisedByAgent)
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Raised by :agent', ['agent' => $escalation->raisedByAgent->name]) }}</flux:text>
                        @endif
                        @if ($escalation->isResolved())
                            <div class="mt-2 rounded bg-green-50 p-3 dark:bg-green-900/20" data-test="escalation-resolution">
                                <flux:text class="text-sm font-medium">{{ __('Resolution: :resolution', ['resolution' => $escalation->resolution]) }}</flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Resolved by :name on :date', ['name' => $escalation->resolved_by, 'date' => $escalation->resolved_at->format('M j, Y g:i A')]) }}</flux:text>
                            </div>
                        @else
                            {{-- Resolution Actions --}}
                            @if ($resolvingEscalationId === $escalation->id)
                                <form wire:submit="resolveEscalation({{ $escalation->id }})" class="mt-3 space-y-3" data-test="resolution-form">
                                    <flux:textarea wire:model="resolutionNotes" :label="__('Resolution Notes')" :placeholder="__('Describe how this escalation was resolved...')" required data-test="resolution-notes" />
                                    <div class="flex items-center gap-2">
                                        <flux:button type="submit" variant="primary" size="sm" data-test="resolve-button">{{ __('Resolve') }}</flux:button>
                                        <flux:button type="button" size="sm" wire:click="cancelResolving" data-test="cancel-resolve-button">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <div class="mt-3 flex items-center gap-2" data-test="escalation-actions">
                                    <flux:button size="sm" variant="primary" wire:click="startResolving({{ $escalation->id }})" data-test="start-resolve-button">{{ __('Resolve') }}</flux:button>
                                    <flux:button size="sm" wire:click="deferEscalation({{ $escalation->id }})" data-test="defer-button">{{ __('Defer') }}</flux:button>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
