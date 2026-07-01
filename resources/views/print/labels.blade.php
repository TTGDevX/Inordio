@extends('layouts.print')

@section('title', $heading)

@section('content')
    <div class="no-print mb-4">
        <h1 class="text-lg font-semibold text-gray-800">{{ $heading }}</h1>
        <p class="text-sm text-gray-500">Scan codes with the Inordio app. Use your browser's print dialog to print or save these labels.</p>
    </div>

    @if (empty($labels))
        <p class="text-sm text-gray-500">Nothing to label.</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            @foreach ($labels as $label)
                <div class="border border-gray-300 rounded-lg p-3 text-center" style="break-inside: avoid;">
                    <div class="qr flex justify-center" data-payload="{{ $label['payload'] }}"></div>
                    <p class="mt-2 text-sm font-medium text-gray-900 truncate">{{ $label['title'] }}</p>
                    @if (! empty($label['subtitle']))
                        <p class="text-xs text-gray-500 font-mono truncate">{{ $label['subtitle'] }}</p>
                    @endif
                    <p class="mt-0.5 text-[10px] text-gray-400 font-mono">{{ $label['payload'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- QR rendered client-side (no server dependency). --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            (function render() {
                if (typeof QRCode === 'undefined') { return setTimeout(render, 100); }
                document.querySelectorAll('.qr[data-payload]').forEach(function (el) {
                    if (el.dataset.rendered) { return; }
                    el.dataset.rendered = '1';
                    new QRCode(el, {
                        text: el.dataset.payload,
                        width: 128,
                        height: 128,
                        correctLevel: QRCode.CorrectLevel.M,
                    });
                });
            })();
        </script>
    @endif
@endsection
