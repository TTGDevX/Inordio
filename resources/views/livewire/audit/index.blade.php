<?php

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = '';

    #[Url]
    public string $action = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('view-audit'), 403);
    }

    public function updating($name): void
    {
        if (in_array($name, ['type', 'action'], true)) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $query = AuditLog::with('user')->latest();

        if ($this->type !== '') {
            $query->where('auditable_type', $this->type);
        }
        if ($this->action !== '') {
            $query->where('action', $this->action);
        }

        // Distinct model types present, for the filter dropdown.
        $types = AuditLog::query()
            ->select('auditable_type')->distinct()->pluck('auditable_type')
            ->mapWithKeys(fn ($t) => [$t => class_basename($t)])
            ->sort();

        return [
            'logs' => $query->paginate(50),
            'types' => $types,
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">Audit trail</h1>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500">Record type</label>
                <select wire:model.live="type"
                    class="mt-1 block w-52 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All types</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500">Action</label>
                <select wire:model.live="action"
                    class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All actions</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Who</th>
                        <th class="px-4 py-2">Action</th>
                        <th class="px-4 py-2">Record</th>
                        <th class="px-4 py-2">Changed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        @php
                            $badge = match ($log->action) {
                                'created' => 'bg-green-100 text-green-800',
                                'updated' => 'bg-amber-100 text-amber-800',
                                'deleted' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                            $fields = collect(array_keys($log->changes ?? []))
                                ->reject(fn ($k) => in_array($k, ['updated_at', 'created_at'], true));
                        @endphp
                        <tr wire:key="al-{{ $log->id }}">
                            <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-2 text-gray-900">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ ucfirst($log->action) }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                {{ class_basename($log->auditable_type) }}
                                <span class="text-gray-400 font-mono">#{{ $log->auditable_id }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-500">
                                {{ $fields->isNotEmpty() ? $fields->take(6)->implode(', ').($fields->count() > 6 ? '…' : '') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-gray-500" colspan="5">No audit entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $logs->links() }}</div>
    </div>
</div>
