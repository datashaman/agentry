<?php

use App\Models\Repo;
use App\Models\Skill;
use App\Services\SkillImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Import Skills')] #[Layout('layouts.app')] class extends Component {
    public ?int $selectedRepoId = null;

    /** @var array<int, array{path: string, parsed: array, sha: string, resource_paths: list<string>}> */
    public array $discovered = [];

    /** @var list<int> */
    public array $selectedIndexes = [];

    public bool $scanned = false;

    public string $scanError = '';

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return Auth::user()->currentOrganization();
    }

    #[Computed]
    public function repos(): \Illuminate\Database\Eloquent\Collection
    {
        $org = $this->organization;

        if (! $org) {
            return collect();
        }

        return Repo::query()
            ->whereIn('project_id', $org->projects()->pluck('id'))
            ->orderBy('name')
            ->get();
    }

    public function scan(): void
    {
        $this->validate([
            'selectedRepoId' => ['required', 'exists:repos,id'],
        ]);

        $repo = Repo::findOrFail($this->selectedRepoId);

        $org = $this->organization;
        if (! $org) {
            return;
        }

        $projectIds = $org->projects()->pluck('id');
        if (! $projectIds->contains($repo->project_id)) {
            $this->scanError = __('Repo does not belong to your organization.');

            return;
        }

        $service = app(SkillImportService::class);
        $this->discovered = $service->discoverSkillsInRepo($repo)->all();
        $this->selectedIndexes = [];
        $this->scanned = true;
        $this->scanError = '';

        if (empty($this->discovered)) {
            $this->scanError = __('No skills found in .agents/skills/ directory.');
        }
    }

    public function getDiscoveryStatus(array $item): string
    {
        $org = $this->organization;

        if (! $org || ! $item['parsed']['valid']) {
            return 'invalid';
        }

        $slug = $item['parsed']['name'];
        $existing = Skill::query()
            ->where('organization_id', $org->id)
            ->where('slug', $slug)
            ->first();

        if (! $existing) {
            return 'new';
        }

        if ($existing->source_sha !== $item['sha']) {
            return 'updated';
        }

        return 'current';
    }

    public function importSelected(): void
    {
        $org = $this->organization;

        if (! $org) {
            return;
        }

        $repo = Repo::findOrFail($this->selectedRepoId);
        $service = app(SkillImportService::class);

        foreach ($this->selectedIndexes as $index) {
            if (isset($this->discovered[$index]) && $this->discovered[$index]['parsed']['valid']) {
                $service->importSkill($org, $this->discovered[$index], $repo);
            }
        }

        $this->redirect(route('skills.index'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if ($this->organization)
        <x-breadcrumbs :organization="$this->organization" />

        <div>
            <flux:heading size="xl">{{ __('Import Skills from Repo') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Scan a linked GitHub repository for skills in .agents/skills/ directories.') }}</flux:text>
        </div>

        <form wire:submit="scan" class="flex max-w-xl items-end gap-2" data-test="scan-form">
            <flux:field class="flex-1">
                <flux:label>{{ __('Repository') }}</flux:label>
                <flux:select wire:model="selectedRepoId" :placeholder="__('Select a repo...')" data-test="repo-select">
                    @foreach ($this->repos as $repo)
                        <flux:select.option :value="(string) $repo->id">{{ $repo->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedRepoId" />
            </flux:field>
            <flux:button type="submit" variant="primary" data-test="scan-button" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="scan">{{ __('Scan') }}</span>
                <span wire:loading wire:target="scan">{{ __('Scanning...') }}</span>
            </flux:button>
        </form>

        @if ($scanError)
            <flux:text class="text-red-600" data-test="scan-error">{{ $scanError }}</flux:text>
        @endif

        @if ($scanned && count($discovered) > 0)
            <form wire:submit="importSelected" data-test="import-form">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400"></th>
                                <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</th>
                                <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</th>
                                <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($discovered as $index => $item)
                                @php $status = $this->getDiscoveryStatus($item); @endphp
                                <tr class="border-b border-zinc-200 dark:border-zinc-700" wire:key="discovered-{{ $index }}" data-test="discovered-row">
                                    <td class="px-4 py-3">
                                        @if ($item['parsed']['valid'])
                                            <flux:checkbox wire:model="selectedIndexes" :value="$index" data-test="skill-checkbox-{{ $index }}" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">{{ $item['parsed']['name'] ?? __('(invalid)') }}</td>
                                    <td class="px-4 py-3">
                                        <flux:text class="truncate">{{ Str::limit($item['parsed']['description'] ?? '', 60) }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($status === 'new')
                                            <flux:badge color="green" size="sm">{{ __('New') }}</flux:badge>
                                        @elseif ($status === 'updated')
                                            <flux:badge color="yellow" size="sm">{{ __('Updated') }}</flux:badge>
                                        @elseif ($status === 'current')
                                            <flux:badge size="sm">{{ __('Current') }}</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm">{{ __('Invalid') }}</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <flux:button type="submit" variant="primary" data-test="import-button" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="importSelected">{{ __('Import Selected') }}</span>
                        <span wire:loading wire:target="importSelected">{{ __('Importing...') }}</span>
                    </flux:button>
                    <a href="{{ route('skills.index') }}" wire:navigate>
                        <flux:button>{{ __('Cancel') }}</flux:button>
                    </a>
                </div>
            </form>
        @endif
    @else
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('No Organization') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Select an organization to import skills.') }}</flux:text>
            </div>
        </div>
    @endif
</div>
