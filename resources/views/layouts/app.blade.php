<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <title>{{ config('app.name', 'Laravel') }}</title>
    @yield('meta')
    <!-- Styles -->
    <link href="{{ mix('css/app.css', (config('app.env') == 'local') ? 'build' : '' ) }}" rel="stylesheet">

</head>
<body>
    <div id="app">
        <header>
            <h1 class="text-center">Розклад засідань</h1>
        </header>

        <main class="app-content py-3">
            <div class="container-fluid">
                @yield('content')
            </div>
        </main>

        <footer>
{{--            <div class="container-fluid">--}}
{{--                <div class="border-top pt-3">--}}
{{--                    <p style="text-align: center;">&copy; {{ date('Y') }} - {{ config('app.author') }}</p>--}}
{{--                </div>--}}
{{--            </div>--}}
        </footer>

    </div>

    <!-- Scripts -->
    <script  src="{{ mix('js/app.js', (config('app.env') == 'local') ? 'build' : '') }}" defer></script>
    @yield('scripts')
</body>
</html>
