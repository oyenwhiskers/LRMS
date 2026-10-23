<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12352f">
    <title>@yield('title') · Entry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,600;9..40,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="entry-auth">
    <div class="entry-auth__frame">
        <header class="entry-auth__brand">
            <p class="entry-auth__mark">Tsang &amp; Co.</p>
            <h1 class="entry-auth__product">Entry</h1>
        </header>

        <main class="entry-auth__main">
            @yield('content')
        </main>
    </div>
</body>
</html>
