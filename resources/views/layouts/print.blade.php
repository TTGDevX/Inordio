<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>
        @vite(['resources/css/app.css'])
        <style>
            @media print {
                .no-print { display: none !important; }
                @page { margin: 1.5cm; }
            }
        </style>
    </head>
    <body class="bg-white text-gray-900 font-sans antialiased">
        <div class="max-w-3xl mx-auto p-6 sm:p-10">
            <div class="no-print mb-6 flex items-center justify-between">
                <button onclick="history.back()" type="button" class="text-sm text-gray-500 hover:text-gray-700">&larr; Back</button>
                <button onclick="window.print()" type="button"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    Print / Save as PDF
                </button>
            </div>

            @yield('content')
        </div>
    </body>
</html>
