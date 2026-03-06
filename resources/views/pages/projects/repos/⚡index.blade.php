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
    public function gitHubRepos(): array
    {
        if (! $this->gitHubConnected) {
            return [];
        }

        $token = Auth::user()->github_token;
        $org = $this->organization;

        if ($org?->github_account_login) {
            $repos = $this->fetchAllPages("https://api.github.com/orgs/{$org->github_account_login}/repos", $token);
        } elseif ($org?->provider === 'github' && $org?->name) {
            $repos = $this->fetchAllPages("https://api.github.com/orgs/{$org->name}/repos", $token);
        } else {
            $repos = $this->fetchAllPages('https://api.github.com/user/repos', $token, ['affiliation' => 'owner']);
        }

        usort($repos, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        if ($this->search !== '') {
            $search = strtolower($this->search);
            $repos = array_values(array_filter($repos, fn ($repo) => str_contains(strtolower($repo['name']), $search)
                || str_contains(strtolower($repo['description'] ?? ''), $search)));
        }

        return $repos;
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

<div class="flex h-full w-full flex-1 flex-col gap-6">
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
    @else
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search repositories...') }}" icon="magnifying-glass" data-test="repo-search" />

        @if (empty($this->gitHubRepos))
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <flux:heading size="lg">{{ __('No Repositories Found') }}</flux:heading>
                    <flux:text class="mt-2">
                        @if ($search !== '')
                            {{ __('No repositories match your search.') }}
                        @else
                            {{ __('No repositories found for this organization.') }}
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
                        @foreach ($this->gitHubRepos as $ghRepo)
                            @php
                                $isLinked = in_array(strtolower($ghRepo['html_url']), $this->linkedRepoUrls);
                            @endphp
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 {{ $isLinked ? 'bg-green-50/50 dark:bg-green-900/10' : '' }}" data-test="repo-row" wire:key="gh-repo-{{ $ghRepo['id'] }}">
                                <td class="px-4 py-3">
                                    <div>
                                        <flux:text class="font-medium text-zinc-900 dark:text-zinc-100">{{ $ghRepo['name'] }}</flux:text>
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
                                        <flux:button size="sm" variant="danger" wire:click="unlinkRepo({{ $ghRepo['id'] }})" wire:confirm="{{ __('Remove this repository from the project?') }}" data-test="unlink-repo-button">
                                            {{ __('Unlink') }}
                                        </flux:button>
                                    @else
                                        <flux:button size="sm" variant="primary" wire:click="linkRepo({{ $ghRepo['id'] }})" data-test="link-repo-button">
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
