<?php

use App\Models\HitlEscalation;
use App\Models\OpsRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Escalations')] #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $triggerType = '';

    #[Url]
    public string $workItemType = '';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function projectIds(): array
    {
        if (! $this->organization) {
            return [];
        }

        return $this->organization->projects()->pluck('id')->all();
    }

    #[Computed]
    public function escalations(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (empty($this->projectIds)) {
            return HitlEscalation::query()->whereRaw('1 = 0')->paginate(20);
        }

        $query = HitlEscalation::query()
            ->whereNull('resolved_at')
            ->with(['raisedByAgent'])
            ->where('work_item_type', OpsRequest::class)
            ->whereIn('work_item_id', OpsRequest::query()
                ->whereIn('project_id', $this->projectIds)
                ->select('id'));

        if ($this->triggerType !== '') {
            $query->where('trigger_type', $this->triggerType);
        }

        if ($this->workItemType !== '') {
            $typeClass = match ($this->workItemType) {
                'ops' => OpsRequest::class,
                default => null,
            };
            if ($typeClass) {
                $query->where('work_item_type', $typeClass);
            }
        }

        $query->latest('created_at');

        return $query->paginate(20);
    }

    #[Computed]
    public function triggerTypes(): array
    {
        return ['confidence', 'risk', 'policy', 'ambiguity'];
    }

    public function updatedTriggerType(): void
    {
        $this->resetPage();
    }

    public function updatedWorkItemType(): void
    {
        $this->resetPage();
    }

    public function getWorkItemRoute($escalation): ?string
    {
        $workItem = $escalation->workItem;
        if (! $workItem) {
            return null;
        }

        return match ($escalation->work_item_type) {
            OpsRequest::class => route('projects.ops-requests.show', [$workItem->project, $workItem]),
            default => null,
        };
    }

    public function getWorkItemLabel($escalation): string
    {
        return match ($escalation->work_item_type) {
            OpsRequest::class => 'Ops Request',
            default => 'Unknown',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />
    @endif

    <div>
        <flux:heading size="xl">{{ __('Escalations') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Unresolved HITL escalations across your organization.') }}</flux:text>
    </div>

    <div class="flex flex-wrap gap-3" data-test="filters">
        <select wire:model.live="triggerType" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="trigger-type-filter">
            <option value="">{{ __('All Trigger Types') }}</option>
            @foreach ($this->triggerTypes as $tt)
                <option value="{{ $tt }}">{{ ucfirst($tt) }}</option>
            @endforeach
        </select>

        <select wire:model.live="workItemType" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-600 dark:bg-zinc-800" data-test="work-item-type-filter">
            <option value="">{{ __('All Work Items') }}</option>
            <option value="ops">{{ __('Ops Requests') }}</option>
        </select>
    </div>

    @if ($this->escalations->isEmpty())
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Escalations') }}</flux:heading>
                <flux:text class="mt-2">{{ __('No unresolved escalations match your filters.') }}</flux:text>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Trigger Type') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Trigger Class') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Work Item') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Raised By') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Reason') }}</th>
                        <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->escalations as $escalation)
                        <tr class="border-b border-zinc-200 dark:border-zinc-700" data-test="escalation-row" wire:key="escalation-{{ $escalation->id }}">
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill" :color="match($escalation->trigger_type) { 'risk' => 'red', 'confidence' => 'amber', 'policy' => 'blue', default => 'zinc' }">{{ ucfirst($escalation->trigger_type) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $escalation->trigger_class ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $route = $this->getWorkItemRoute($escalation);
                                    $label = $this->getWorkItemLabel($escalation);
                                @endphp
                                @if ($route)
                                    <a href="{{ $route }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="work-item-link">
                                        <flux:badge size="sm" variant="pill" class="mr-1">{{ $label }}</flux:badge>
                                        {{ $escalation->workItem->title }}
                                    </a>
                                @else
                                    <flux:text>-</flux:text>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $escalation->raisedByAgent?->name ?? '-' }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text class="max-w-xs truncate">{{ $escalation->reason }}</flux:text>
                            </td>
                            <td class="px-4 py-3">
                                <flux:text>{{ $escalation->created_at->format('M j, Y') }}</flux:text>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->escalations->hasPages())
            <div class="mt-4">
                {{ $this->escalations->links() }}
            </div>
        @endif
    @endif
</div>
