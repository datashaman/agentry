@props(['changeSets'])

@if ($changeSets->isNotEmpty())
    <div data-test="changeset-detail">
        <flux:heading size="lg">{{ __('Change Sets') }}</flux:heading>
        <div class="mt-2 space-y-4">
            @foreach ($changeSets as $changeSet)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" data-test="changeset-item">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:text class="font-medium">{{ $changeSet->summary ?? __('Change set #:id', ['id' => $changeSet->id]) }}</flux:text>
                        <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $changeSet->status) }}</flux:badge>
                    </div>
                    @if ($changeSet->pullRequests->isNotEmpty())
                        <div class="mt-4 space-y-3 pl-4 border-l-2 border-zinc-200 dark:border-zinc-700">
                            @foreach ($changeSet->pullRequests as $pr)
                                <div class="rounded border border-zinc-100 p-3 dark:border-zinc-700" data-test="pr-item">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:text class="font-medium">{{ $pr->title }}</flux:text>
                                        <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $pr->status) }}</flux:badge>
                                        <flux:badge size="sm" variant="pill">{{ $pr->repo?->name ?? '-' }}</flux:badge>
                                        <flux:badge size="sm" variant="pill" class="font-mono">{{ $pr->branch?->name ?? '-' }}</flux:badge>
                                        @if ($pr->agent)
                                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Author: :agent', ['agent' => $pr->agent->name]) }}</flux:text>
                                        @endif
                                        @if ($pr->external_url)
                                            <a href="{{ $pr->external_url }}" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600 hover:underline dark:text-primary-400" data-test="pr-external-link">
                                                {{ __('Open PR') }} ↗
                                            </a>
                                        @endif
                                    </div>
                                    @if ($pr->reviews->isNotEmpty())
                                        <div class="mt-3 space-y-2">
                                            @foreach ($pr->reviews as $review)
                                                <div class="rounded bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50" data-test="review-item">
                                                    <div class="flex flex-wrap items-center gap-2 text-sm">
                                                        @if ($review->agent)
                                                            <flux:text class="font-medium">{{ $review->agent->name }}</flux:text>
                                                        @endif
                                                        <flux:badge size="sm" variant="pill">{{ str_replace('_', ' ', $review->status) }}</flux:badge>
                                                        @if ($review->submitted_at)
                                                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $review->submitted_at->format('M j, Y H:i') }}</flux:text>
                                                        @endif
                                                    </div>
                                                    @if ($review->body)
                                                        <flux:text class="mt-1 block text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2">{{ Str::limit($review->body, 200) }}</flux:text>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
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
