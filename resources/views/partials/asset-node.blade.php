{{-- Recursive node for the serialized-asset tree. Expects $node and $asset (focused). --}}
<li class="py-1" wire:key="node-{{ $node->id }}">
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-mono text-sm {{ $node->id === $asset->id ? 'font-semibold text-gray-900' : 'text-gray-800' }}">{{ $node->serial_number }}</span>
        @if ($node->item)
            <span class="text-xs text-gray-500">{{ $node->item->name }}</span>
        @endif
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $node->status->badgeClasses() }}">{{ $node->status->label() }}</span>

        @if ($node->id !== $asset->id)
            <a href="{{ route('assets.show', $node->id) }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-800">open</a>
        @endif

        @can('move-stock')
            @if ($node->parent_id)
                <button type="button" wire:click="detach({{ $node->id }})"
                    wire:confirm="Detach {{ $node->serial_number }} from its parent?"
                    class="text-xs text-amber-700 hover:text-amber-900">detach</button>
            @endif
        @endcan
    </div>

    @if ($node->children->isNotEmpty())
        <ul class="ml-5 border-l border-gray-100 pl-3">
            @foreach ($node->children as $child)
                @include('partials.asset-node', ['node' => $child, 'asset' => $asset])
            @endforeach
        </ul>
    @endif
</li>
