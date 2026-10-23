@extends('layouts.entry-auth')

@section('title', 'Log masuk')

@section('content')
<section class="entry-auth__card">
    <h2 class="entry-auth__title">Log masuk</h2>

    @if ($errors->any())
        <div class="entry-auth__alert" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="entry-auth__form" method="POST" action="{{ route('entry.login.store') }}">
        @csrf
        <label class="entry-auth__field">
            <span>Emel</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="nama@contoh.com">
        </label>
        <label class="entry-auth__field">
            <span>Kata laluan</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="entry-auth__submit" type="submit">Masuk</button>
    </form>

    <p class="entry-auth__switch">
        Belum ada akaun?
        <a href="{{ route('entry.register') }}">Daftar</a>
    </p>
</section>
@endsection
