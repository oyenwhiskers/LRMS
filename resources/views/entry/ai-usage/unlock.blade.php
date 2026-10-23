@extends('layouts.entry')

@section('title', 'Token AI')

@section('content')
<section class="entry-ai">
    <p class="entry-ai__eyebrow">Terhad</p>
    <h1 class="entry-ai__title">Token AI</h1>
    <p class="entry-ai__lede">Masukkan kata laluan modul. Akses kekal untuk akaun ini sehingga log keluar.</p>

    <form class="entry-ai__form" method="POST" action="{{ route('entry.ai-usage.unlock.store') }}">
        @csrf
        <label class="entry-ai__field">
            <span>Kata laluan</span>
            <input type="password" name="password" required autofocus autocomplete="current-password">
            @error('password')<p class="entry-ai__error">{{ $message }}</p>@enderror
        </label>
        <button class="entry-ai__submit" type="submit">Buka</button>
    </form>
</section>
@endsection
