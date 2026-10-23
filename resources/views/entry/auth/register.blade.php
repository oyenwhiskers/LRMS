@extends('layouts.entry-auth')

@section('title', 'Daftar')

@section('content')
<section class="entry-auth__card">
    <h2 class="entry-auth__title">Daftar akaun</h2>
    <p class="entry-auth__lede">Untuk pasukan kemasukan data sahaja.</p>

    @if ($errors->any())
        <div class="entry-auth__alert" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="entry-auth__form" method="POST" action="{{ route('entry.register.store') }}">
        @csrf
        <label class="entry-auth__field">
            <span>Nama</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </label>
        <label class="entry-auth__field">
            <span>Emel</span>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="nama@contoh.com">
        </label>
        <label class="entry-auth__field">
            <span>Kata laluan</span>
            <input type="password" name="password" required autocomplete="new-password">
            <small>Min. 12 aksara, huruf besar/kecil, nombor &amp; simbol.</small>
        </label>
        <label class="entry-auth__field">
            <span>Sahkan kata laluan</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button class="entry-auth__submit" type="submit">Daftar</button>
    </form>

    <p class="entry-auth__switch">
        Sudah ada akaun?
        <a href="{{ route('entry.login') }}">Log masuk</a>
    </p>
</section>
@endsection
