<?php

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobLineItem;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    public ?int $customer_id = null;
    public string $title = '';
    public ?int $assigned_user_id = null;
    public string $scheduled_at = '';
    public string $notes = '';

    /** @var array<int, array{inventory_item_id: ?int, description: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    public function mount(?string $jobId = null): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        if ($jobId !== null) {
            $job = Job::with('lines')->findOrFail($jobId);
            $this->editingId = $job->id;
            $this->customer_id = $job->customer_id;
            $this->title = $job->title;
            $this->assigned_user_id = $job->assigned_user_id;
            $this->scheduled_at = optional($job->scheduled_at)->format('Y-m-d\TH:i') ?? '';
            $this->notes = (string) $job->notes;
            $this->lines = $job->lines->map(fn (JobLineItem $l) => [
                'inventory_item_id' => $l->inventory_item_id,
                'description' => $l->description,
                'quantity' => (string) $l->quantity,
                'unit_price' => (string) $l->unit_price,
            ])->all();
        }

        if ($this->lines === []) {
            $this->lines = [$this->emptyLine()];
        }
    }

    private function emptyLine(): array
    {
        return ['inventory_item_id' => null, 'description' => '', 'quantity' => '1', 'unit_price' => '0'];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function updated(string $name, $value): void
    {
        if (preg_match('/^lines\.(\d+)\.inventory_item_id$/', $name, $m) && $value) {
            $item = InventoryItem::find($value);
            if ($item) {
                $this->lines[(int) $m[1]]['description'] = $item->name;
                $this->lines[(int) $m[1]]['unit_price'] = (string) $item->price;
            }
        }
    }

    public function subtotal(): float
    {
        return collect($this->lines)->sum(fn ($l) => (float) ($l['quantity'] ?? 0) * (float) ($l['unit_price'] ?? 0));
    }

    protected function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save()
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-jobs'), 403);

        foreach ($this->lines as $i => $line) {
            if (($line['inventory_item_id'] ?? '') === '') {
                $this->lines[$i]['inventory_item_id'] = null;
            }
        }

        $data = $this->validate();

        $attributes = [
            'customer_id' => $data['customer_id'],
            'title' => $data['title'],
            'assigned_user_id' => $data['assigned_user_id'] ?: null,
            'scheduled_at' => $data['scheduled_at'] ?: null,
            'notes' => $data['notes'] ?? null,
        ];

        $job = $this->editingId
            ? tap(Job::findOrFail($this->editingId))->update($attributes)
            : Job::create($attributes);

        $job->lines()->delete();
        foreach (array_values($data['lines']) as $i => $line) {
            $job->lines()->create([
                'inventory_item_id' => $line['inventory_item_id'] ?: null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'position' => $i,
            ]);
        }

        session()->flash('status', $this->editingId ? 'Job updated.' : 'Job created.');

        return $this->redirect(route('jobs.show', $job->id), navigate: true);
    }

    public function with(): array
    {
        return [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(),
            'items' => InventoryItem::orderBy('name')->get(),
            'technicians' => User::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <a href="{{ route('jobs.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">&larr; Back to jobs</a>

        <form wire:submit="save" class="space-y-4">
            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
                <h1 class="text-xl font-semibold text-gray-900">{{ $editingId ? 'Edit job' : 'New job' }}</h1>

                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" wire:model="title" class="block mt-1 w-full" placeholder="e.g. Replace water heater" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="customer_id" value="Customer" />
                        <select id="customer_id" wire:model="customer_id"
                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="assigned_user_id" value="Technician" />
                        <select id="assigned_user_id" wire:model="assigned_user_id"
                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Unassigned —</option>
                            @foreach ($technicians as $tech)
                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('assigned_user_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="scheduled_at" value="Scheduled" />
                        <x-text-input id="scheduled_at" wire:model="scheduled_at" type="datetime-local" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium text-gray-800">Line items</h2>
                    <button type="button" wire:click="addLine" class="text-sm text-indigo-600 hover:text-indigo-800">+ Add line</button>
                </div>

                <x-input-error :messages="$errors->get('lines')" class="mt-1" />

                @foreach ($lines as $i => $line)
                    <div wire:key="jline-{{ $i }}" class="grid grid-cols-12 gap-2 items-start border-t border-gray-100 pt-3">
                        <div class="col-span-12 sm:col-span-4">
                            <select wire:model.live="lines.{{ $i }}.inventory_item_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Custom / no catalogue item</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->internal_sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-12 sm:col-span-3">
                            <x-text-input wire:model="lines.{{ $i }}.description" class="block w-full text-sm" placeholder="Description" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.description')" class="mt-1" />
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <x-text-input wire:model.live="lines.{{ $i }}.quantity" type="number" step="0.01" min="0" class="block w-full text-sm" placeholder="Qty" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.quantity')" class="mt-1" />
                        </div>
                        <div class="col-span-5 sm:col-span-2">
                            <x-text-input wire:model.live="lines.{{ $i }}.unit_price" type="number" step="0.01" min="0" class="block w-full text-sm" placeholder="Price" />
                            <x-input-error :messages="$errors->get('lines.'.$i.'.unit_price')" class="mt-1" />
                        </div>
                        <div class="col-span-3 sm:col-span-1 flex items-center justify-end pt-1">
                            @if (count($lines) > 1)
                                <button type="button" wire:click="removeLine({{ $i }})" class="text-gray-400 hover:text-red-600 text-sm">Remove</button>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end border-t border-gray-100 pt-3">
                    <div class="text-right">
                        <span class="text-sm text-gray-500">Subtotal (pre-tax)</span>
                        <p class="text-lg font-semibold text-gray-900 tabular-nums">${{ number_format($this->subtotal(), 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-4">
                <div>
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" wire:model="notes" rows="2"
                        class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center gap-3">
                    <x-primary-button>{{ $editingId ? 'Save changes' : 'Create job' }}</x-primary-button>
                    <a href="{{ route('jobs.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
