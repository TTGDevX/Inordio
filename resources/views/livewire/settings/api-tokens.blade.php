<?php

use App\Models\ApiToken;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $newName = '';
    public ?string $plaintext = null; // shown once, right after creation
    public string $statusMessage = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);
    }

    public function create(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);
        $this->validate(['newName' => ['required', 'string', 'max:255']]);

        $result = ApiToken::issue(auth()->user(), $this->newName);
        $this->plaintext = $result['plaintext'];
        $this->reset('newName');
        $this->statusMessage = 'Token created. Copy it now — it will not be shown again.';
    }

    public function revoke(int $id): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);

        $token = ApiToken::find($id);
        if ($token) {
            $token->delete();
            $this->statusMessage = 'Token revoked.';
        }
    }

    public function with(): array
    {
        return ['tokens' => ApiToken::with('user')->latest()->get()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">API tokens</h1>
            <a href="{{ route('settings.company') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Company settings</a>
        </div>

        <p class="text-sm text-gray-500">Use a token as a <code class="bg-gray-100 px-1 rounded">Bearer</code> credential against the JSON API at <code class="bg-gray-100 px-1 rounded">/api/v1</code> (e.g. <code class="bg-gray-100 px-1 rounded">/api/v1/invoices</code>). Tokens are scoped to this company.</p>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        @if ($plaintext)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm text-amber-800">Your new token (shown once):</p>
                <code class="mt-1 block break-all font-mono text-sm text-gray-900">{{ $plaintext }}</code>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6">
            <h2 class="font-medium text-gray-800">Create a token</h2>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="newName" value="Label" />
                    <x-text-input id="newName" wire:model="newName" class="block mt-1 w-64" placeholder="e.g. Zapier integration" />
                    <x-input-error :messages="$errors->get('newName')" class="mt-1" />
                </div>
                <x-primary-button wire:click="create" type="button">Generate token</x-primary-button>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
            @forelse ($tokens as $token)
                <div wire:key="tok-{{ $token->id }}" class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="font-medium text-gray-900">{{ $token->name }}</p>
                        <p class="text-xs text-gray-500">
                            by {{ $token->user?->name ?? '—' }} ·
                            {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}
                        </p>
                    </div>
                    <button type="button" wire:click="revoke({{ $token->id }})"
                        wire:confirm="Revoke this token? Any integration using it will stop working."
                        class="text-sm text-red-600 hover:text-red-800">Revoke</button>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-500">No API tokens yet.</p>
            @endforelse
        </div>
    </div>
</div>
