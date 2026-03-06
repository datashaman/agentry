<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Agent Permissions')] #[Layout('layouts.app')] class extends Component {
    /** @var array<string, bool> */
    public array $permissions = [];

    public function mount(): void
    {
        $organization = Auth::user()->currentOrganization();

        if (! $organization) {
            return;
        }

        $stored = $organization->agent_permissions ?? [];

        foreach (Organization::allAgentPermissionKeys() as $key) {
            $this->permissions[$key] = (bool) ($stored[$key] ?? false);
        }
    }

    public function save(): void
    {
        $organization = Auth::user()->currentOrganization();

        if (! $organization) {
            return;
        }

        $organization->update(['agent_permissions' => $this->permissions]);

        session()->flash('status', 'permissions-saved');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Agent Permissions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Control what actions agents can perform within your organization. All permissions are off by default.') }}</flux:text>
    </div>

    @if (session('status') === 'permissions-saved')
        <flux:callout variant="success" data-test="save-success">
            {{ __('Agent permissions saved successfully.') }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-8" data-test="permissions-form">
        @foreach (\App\Models\Organization::AGENT_PERMISSIONS as $category => $keys)
            <div>
                <flux:heading size="lg" class="mb-3">{{ __($category) }}</flux:heading>
                <div class="space-y-3">
                    @foreach ($keys as $key)
                        <flux:switch
                            wire:model="permissions.{{ $key }}"
                            :label="__(str_replace('_', ' ', ucfirst($key)))"
                            data-test="permission-{{ $key }}"
                        />
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center gap-2">
            <flux:button type="submit" variant="primary" data-test="save-permissions-button">{{ __('Save Permissions') }}</flux:button>
        </div>
    </form>
</div>
