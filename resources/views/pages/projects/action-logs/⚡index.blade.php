<?php

use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\OpsRequest;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Action Logs')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public Project $project;

    #[Url]
    public string $agent = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $workItemType = '';

    public function mount(): void
    {
        //
    }

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    protected function baseQuery()
    {
        $projectId = $this->project->id;

        return ActionLog::query()
            ->with(['agent', 'workItem'])
            ->where('work_item_type', OpsRequest::class)
            ->whereIn('work_item_id', OpsRequest::query()
                ->where('project_id', $projectId)
                ->select('id'));
    }

    #[Computed]
    public function actionLogs(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($this->agent !== '') {
            $query->where('agent_id', $this->agent);
        }

        if ($this->action !== '') {
            $query->where('action', $this->action);
        }

        if ($this->workItemType !== '') {
            $query->where('work_item_type', $this->workItemType);
        }

        return $query->latest('timestamp')->paginate(15);
    }

    #[Computed]
    public function agents(): \Illuminate\Database\Eloquent\Collection
    {
        $agentIds = $this->baseQuery()->distinct()->pluck('agent_id')->filter()->all();

        if (empty($agentIds)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return Agent::query()->whereIn('id', $agentIds)->orderBy('name')->get();
    }

    #[Computed]
    public function actions(): array
    {
        return $this->baseQuery()->distinct()->pluck('action')->filter()->sort()->values()->all();
    }

    public function workItemUrl(ActionLog $log): ?string
    {
        $item = $log->workItem;
        if (! $item) {
            return null;
        }

        return match (get_class($item)) {
            OpsRequest::class => route('projects.ops-requests.show', [$this->project, $item]),
            default => null,
        };
    }

    public function workItemLabel(ActionLog $log): string
    {
        $item = $log->workItem;
        if (! $item) {
            return '-';
        }
        $type = class_basename(get_class($item));
        $title = $item->title ?? (method_exists($item, 'title') ? $item->title : $item->id ?? '-');

        return "{$type}: {$title}";
    }

    public function clearFilters(): void
    {
        $this->agent = '';
        $this->action = '';
        $this->workItemType = '';
        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Action Logs') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Audit agent activity for :project', ['project' => $project->name]) }}</flux:text>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4" data-test="action-logs-filters">
        <flux:field>
            <flux:label>{{ __('Agent') }}</flux:label>
            <flux:select wire:model.live="agent" data-test="filter-agent">
                <flux:select.option value="">{{ __('All agents') }}</flux:select.option>
                @foreach ($this->agents as $a)
                    <flux:select.option :value="$a->id">{{ $a->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Action') }}</flux:label>
            <flux:select wire:model.live="action" data-test="filter-action">
                <flux:select.option value="">{{ __('All actions') }}</flux:select.option>
                @foreach ($this->actions as $act)
                    <flux:select.option :value="$act">{{ str_replace('_', ' ', ucfirst($act)) }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
        <flux:field>
            <flux:label>{{ __('Work Item Type') }}</flux:label>
            <flux:select wire:model.live="workItemType" data-test="filter-work-item-type">
                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                <flux:select.option value="App\Models\OpsRequest">{{ __('Ops Request') }}</flux:select.option>
            </flux:select>
        </flux:field>
        @if ($this->agent !== '' || $this->action !== '' || $this->workItemType !== '')
            <flux:button variant="ghost" size="sm" wire:click="clearFilters" data-test="clear-filters">{{ __('Clear filters') }}</flux:button>
        @endif
    </div>

    @if ($this->actionLogs->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Action Logs') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No action logs found for this project.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" data-test="action-logs-table">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Timestamp') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Agent') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Action') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Work Item') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Reasoning') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->actionLogs as $log)
                        <tr class="border-b border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50" wire:key="log-{{ $log->id }}" data-test="action-log-row">
                            <td class="px-4 py-3">
                                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $log->timestamp?->format('M j, Y H:i') ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $log->agent?->name ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $log->action) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                @php($url = $this->workItemUrl($log))
                                @if ($url)
                                    <a href="{{ $url }}" wire:navigate class="font-medium hover:underline" data-test="work-item-link">{{ $this->workItemLabel($log) }}</a>
                                @else
                                    <flux:text>{{ $this->workItemLabel($log) }}</flux:text>
                                @endif
                            </td>
                            <td class="max-w-xs px-4 py-3">
                                @if ($log->reasoning)
                                    <div x-data="{ expanded: false }" @click="expanded = !expanded" class="cursor-pointer" data-test="reasoning-cell">
                                        <flux:text x-show="!expanded" class="block truncate" data-test="reasoning-excerpt">{{ Str::limit($log->reasoning, 80) }}</flux:text>
                                        <flux:text x-show="expanded" x-cloak class="block whitespace-pre-wrap" data-test="reasoning-full">{{ $log->reasoning }}</flux:text>
                                    </div>
                                @else
                                    <flux:text class="text-zinc-500 dark:text-zinc-400">-</flux:text>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4" data-test="action-logs-pagination">
            {{ $this->actionLogs->links() }}
        </div>
    @endif
</div>
