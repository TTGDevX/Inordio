<?php

use App\Models\DocumentTemplate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    /** @var array<string, string> type => subject */
    public array $subject = [];
    /** @var array<string, string> type => body */
    public array $body = [];
    public string $statusMessage = '';

    public function mount(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);

        foreach (DocumentTemplate::TYPES as $type) {
            $t = DocumentTemplate::resolve($type);
            $this->subject[$type] = $t['subject'];
            $this->body[$type] = $t['body'];
        }
    }

    protected function rules(): array
    {
        return [
            'subject.*' => ['required', 'string', 'max:255'],
            'body.*' => ['required', 'string', 'max:5000'],
        ];
    }

    public function save(): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);
        $this->validate();

        foreach (DocumentTemplate::TYPES as $type) {
            DocumentTemplate::updateOrCreate(
                ['type' => $type],
                ['subject' => $this->subject[$type], 'body' => $this->body[$type]],
            );
        }

        $this->statusMessage = 'Templates saved.';
    }

    public function resetType(string $type): void
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('manage-settings'), 403);

        DocumentTemplate::where('type', $type)->delete();
        $d = DocumentTemplate::defaults($type);
        $this->subject[$type] = $d['subject'];
        $this->body[$type] = $d['body'];
        $this->statusMessage = 'Reset to default.';
    }

    public function with(): array
    {
        return [
            'types' => DocumentTemplate::TYPES,
            'labels' => ['invoice_email' => 'Invoice email', 'quote_email' => 'Quote email', 'payment_receipt' => 'Payment receipt'],
        ];
    }
}; ?>

<div class="py-6 sm:py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">Email templates</h1>
            <a href="{{ route('settings.company') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">Company settings</a>
        </div>

        @php($egToken = '{{ token }}')
        <p class="text-sm text-gray-500">Customise the subject and message of outgoing emails. Use <code class="bg-gray-100 px-1 rounded">{{ $egToken }}</code> placeholders — they're filled in per send. The branded header, totals, and footer are added automatically.</p>

        @if ($statusMessage)
            <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ $statusMessage }}</div>
        @endif

        <form wire:submit="save" class="space-y-6">
            @foreach ($types as $type)
                <div class="bg-white rounded-lg shadow-sm p-5 sm:p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="font-medium text-gray-800">{{ $labels[$type] }}</h2>
                        <button type="button" wire:click="resetType('{{ $type }}')" class="text-xs text-gray-500 hover:text-gray-700">Reset to default</button>
                    </div>

                    <div>
                        <x-input-label value="Subject" />
                        <x-text-input wire:model="subject.{{ $type }}" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('subject.'.$type)" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Message" />
                        <textarea wire:model="body.{{ $type }}" rows="6"
                            class="block mt-1 w-full rounded-md border-gray-300 shadow-sm font-mono text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('body.'.$type)" class="mt-1" />
                    </div>

                    <div class="text-xs text-gray-500">
                        Tokens:
                        @foreach (\App\Models\DocumentTemplate::tokens($type) as $token)
                            @php($ph = '{{ '.$token.' }}')
                            <code class="bg-gray-100 px-1 rounded mr-1">{{ $ph }}</code>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="pt-1">
                <x-primary-button>Save templates</x-primary-button>
            </div>
        </form>
    </div>
</div>
