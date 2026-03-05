@props(['organization' => null, 'project' => null])

@if ($organization)
    <nav aria-label="Breadcrumb" data-test="breadcrumbs">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>
                {{ $organization->name }}
            </flux:breadcrumbs.item>

            @if ($project)
                <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate>
                    {{ __('Projects') }}
                </flux:breadcrumbs.item>
                <flux:breadcrumbs.item>
                    {{ $project->name }}
                </flux:breadcrumbs.item>
            @endif
        </flux:breadcrumbs>
    </nav>

    <x-project-sub-nav :project="$project" />
@endif
