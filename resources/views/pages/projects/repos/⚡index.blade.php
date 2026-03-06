<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Repositories')] #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Session]
    public string $search = '';

    public array $gitHubRepos = [];

    public bool $loadingRepos = true;

    #[Computed]
    public function organization(): ?\App\Models\Organization
    {
        return $this->project->organization;
    }

    #[Computed]
    public function linkedRepoUrls(): array
    {
        return $this->project->repos()->pluck('url')->map(fn ($url) => strtolower($url))->all();
    }

    #[Computed]
    public function gitHubConnected(): bool
    {
        return Auth::user()->hasGitHub();
    }

    #[Computed]
    public function filteredGitHubRepos(): array
    {
        $repos = $this->gitHubRepos;

        if ($this->search !== '') {
            $search = strtolower($this->search);
            $repos = array_values(array_filter($repos, fn ($repo) => str_contains(strtolower($repo['name']), $search)
                || str_contains(strtolower($repo['full_name'] ?? ''), $search)
                || str_contains(strtolower($repo['description'] ?? ''), $search)));
        }

        return $repos;
    }

    public function mount(): void
    {
        $this->loadingRepos = $this->gitHubConnected;
    }

    public function loadGitHubRepos(): void
    {
        if (! $this->gitHubConnected) {
            $this->loadingRepos = false;

            return;
        }

        $token = Auth::user()->github_token;
        $repos = $this->fetchAllPages('https://api.github.com/user/repos', $token, [
            'affiliation' => 'owner,collaborator,organization_member',
            'visibility' => 'all',
            'sort' => 'full_name',
        ]);

        usort($repos, fn ($a, $b) => strcasecmp($a['full_name'] ?? $a['name'], $b['full_name'] ?? $b['name']));

        $this->gitHubRepos = $repos;
        $this->loadingRepos = false;
    }

    public function linkRepo(int $gitHubId): void
    {
        $repo = collect($this->gitHubRepos)->firstWhere('id', $gitHubId);

        if (! $repo) {
            return;
        }

        if ($this->project->repos()->where('url', $repo['html_url'])->exists()) {
            return;
        }

        $this->project->repos()->create([
            'name' => $repo['name'],
            'url' => $repo['html_url'],
            'primary_language' => $repo['language'],
            'default_branch' => $repo['default_branch'] ?? 'main',
        ]);

        unset($this->linkedRepoUrls);
    }

    public function unlinkRepo(int $gitHubId): void
    {
        $repo = collect($this->gitHubRepos)->firstWhere('id', $gitHubId);

        if (! $repo) {
            return;
        }

        $this->project->repos()->where('url', $repo['html_url'])->delete();

        unset($this->linkedRepoUrls);
    }

    protected function fetchAllPages(string $url, string $token, array $query = []): array
    {
        $allRepos = [];
        $page = 1;

        do {
            $response = Http::withToken($token)
                ->accept('application/vnd.github+json')
                ->get($url, array_merge($query, ['per_page' => 100, 'page' => $page]));

            if (! $response->successful()) {
                break;
            }

            $repos = $response->json();

            if (empty($repos)) {
                break;
            }

            $allRepos = array_merge($allRepos, $repos);
            $page++;
        } while (count($repos) === 100);

        return $allRepos;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6"
    @if ($loadingRepos) wire:init="loadGitHubRepos" @endif
>
    <x-breadcrumbs :organization="$this->organization" :project="$project" />

    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Repositories') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Link GitHub repositories to :project.', ['project' => $project->name]) }}</flux:text>
        </div>
        <a href="{{ route('projects.repos.create', $project) }}" wire:navigate data-test="create-repo-button">
            <flux:button>{{ __('Add Manually') }}</flux:button>
        </a>
    </div>

    @if (! $this->gitHubConnected)
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:heading size="lg">{{ __('GitHub Not Connected') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Connect your GitHub account to browse and link repositories.') }}</flux:text>
                <div class="mt-4">
                    <flux:button variant="primary" :href="route('github.redirect')">{{ __('Connect GitHub') }}</flux:button>
                </div>
            </div>
        </div>
    @elseif ($loadingRepos)
        <div class="flex flex-1 items-center justify-center">
            <div class="text-center">
                <flux:icon name="arrow-path" class="mx-auto size-8 animate-spin text-zinc-400" />
                <flux:text class="mt-3">{{ __('Loading repositories from GitHub...') }}</flux:text>
            </div>
        </div>
    @else
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search repositories...') }}" icon="magnifying-glass" data-test="repo-search" />

        @if (empty($this->filteredGitHubRepos))
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <flux:heading size="lg">{{ __('No Repositories Found') }}</flux:heading>
                    <flux:text class="mt-2">
                        @if ($search !== '')
                            {{ __('No repositories match your search.') }}
                        @else
                            {{ __('No repositories found on GitHub.') }}
                        @endif
                    </flux:text>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Repository') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Language') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Default Branch') }}</th>
                            <th class="px-4 py-3 font-medium text-zinc-500 dark:text-zinc-400">{{ __('Visibility') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->filteredGitHubRepos as $ghRepo)
                            @php
                                $isLinked = in_array(strtolower($ghRepo['html_url']), $this->linkedRepoUrls);
                                $localRepo = $isLinked ? $project->repos->first(fn ($r) => strtolower($r->url) === strtolower($ghRepo['html_url'])) : null;
                            @endphp
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 {{ $isLinked ? 'bg-green-50/50 dark:bg-green-900/10' : '' }}" data-test="repo-row" wire:key="gh-repo-{{ $ghRepo['id'] }}">
                                <td class="px-4 py-3">
                                    <div>
                                        @if ($localRepo)
                                            <a href="{{ route('projects.repos.show', [$project, $localRepo]) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-zinc-100" data-test="repo-show-link">
                                                {{ $ghRepo['full_name'] ?? $ghRepo['name'] }}
                                            </a>
                                        @else
                                            <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $ghRepo['full_name'] ?? $ghRepo['name'] }}</flux:text>
                                        @endif
                                        @if ($ghRepo['description'] ?? null)
                                            <flux:text class="mt-0.5 text-xs text-zinc-500">{{ Str::limit($ghRepo['description'], 80) }}</flux:text>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $ghRepo['language'] ?? '-' }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:text>{{ $ghRepo['default_branch'] ?? 'main' }}</flux:text>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" :variant="$ghRepo['private'] ? 'warning' : 'success'">
                                        {{ $ghRepo['private'] ? __('Private') : __('Public') }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($isLinked)
                                        <flux:button size="sm" variant="danger" wire:click="unlinkRepo({{ $ghRepo['id'] }})" wire:target="unlinkRepo({{ $ghRepo['id'] }})" wire:confirm="{{ __('Remove this repository from the project?') }}" data-test="unlink-repo-button">
                                            {{ __('Unlink') }}
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="primary" wire:click="linkRepo({{ $ghRepo['id'] }})" wire:target="linkRepo({{ $ghRepo['id'] }})" data-test="link-repo-button">
                                            {{ __('Link') }}
                                        </flux:button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
