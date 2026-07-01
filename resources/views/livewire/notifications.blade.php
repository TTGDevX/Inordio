<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $statusMessage = '';

    public function markRead(string $id): void
    {
        $n = auth()->user()->notifications()->whereKey($id)->first();
        $n?->markAsRead();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->statusMessage = 'All caught up.';
    }

    public function with(): array
    {
        return ['notifications' => auth()->user()->notifications()->latest()->limit(50)->get()];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">Notifications</h1>
            <button type="button" wire:click="markAllRead" class="text-sm text-indigo-600 hover:text-indigo-800">Mark all read</button>
        </div>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        @if ($notifications->isEmpty())
            <div class="bg-white rounded-lg shadow-sm p-8 text-center text-gray-500">No notifications.</div>
        @else
            <div class="bg-white rounded-lg shadow-sm divide-y divide-gray-100">
                @foreach ($notifications as $note)
                    <div wire:key="n-{{ $note->id }}" class="flex items-start justify-between gap-3 px-5 py-3 {{ $note->read_at ? '' : 'bg-indigo-50/40' }}">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900">
                                @if (! $note->read_at)<span class="inline-block h-2 w-2 rounded-full bg-indigo-500 mr-1 align-middle"></span>@endif
                                @if (! empty($note->data['job_id']))
                                    <a href="{{ route('jobs.show', $note->data['job_id']) }}" wire:navigate class="hover:underline">{{ $note->data['message'] ?? 'Notification' }}</a>
                                @else
                                    {{ $note->data['message'] ?? 'Notification' }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">{{ $note->created_at->diffForHumans() }}</p>
                        </div>
                        @unless ($note->read_at)
                            <button type="button" wire:click="markRead('{{ $note->id }}')" class="shrink-0 text-xs text-gray-500 hover:text-gray-700">Mark read</button>
                        @endunless
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
