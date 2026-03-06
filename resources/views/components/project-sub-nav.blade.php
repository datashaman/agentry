@props(['project'])

@if ($project)
    <nav aria-label="Project sections" data-test="project-sub-nav" class="mb-4 flex flex-wrap gap-1 border-b border-zinc-200 pb-3 dark:border-zinc-700">
        <a
            href="{{ route('projects.show', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.show'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.show'),
            ])
            data-test="sub-nav-overview"
        >
            {{ __('Overview') }}
        </a>
        <a
            href="{{ route('projects.work-items.index', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.work-items.*'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.work-items.*'),
            ])
            data-test="sub-nav-work-items"
        >
            {{ __('Work Items') }}
        </a>
        <a
            href="{{ route('projects.ops-requests.index', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.ops-requests.*'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.ops-requests.*'),
            ])
            data-test="sub-nav-ops-requests"
        >
            {{ __('Ops Requests') }}
        </a>
        <a
            href="{{ route('projects.repos.index', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.repos.*'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.repos.*'),
            ])
            data-test="sub-nav-repos"
        >
            {{ __('Repos') }}
        </a>
        <a
            href="{{ route('projects.milestones.index', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.milestones.*'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.milestones.*'),
            ])
            data-test="sub-nav-milestones"
        >
            {{ __('Milestones') }}
        </a>
        <a
            href="{{ route('projects.labels.index', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.labels.*'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.labels.*'),
            ])
            data-test="sub-nav-labels"
        >
            {{ __('Labels') }}
        </a>
        <a
            href="{{ route('projects.action-logs.index', $project) }}"
            wire:navigate
            @class([
                'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'bg-zinc-100 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100' => request()->routeIs('projects.action-logs.*'),
                'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100' => ! request()->routeIs('projects.action-logs.*'),
            ])
            data-test="sub-nav-action-logs"
        >
            {{ __('Action Logs') }}
        </a>
    </nav>
@endif
