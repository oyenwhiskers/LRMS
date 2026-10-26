@extends('layouts.entry')

@section('title', 'Reset kata laluan')

@section('content')
<section class="entry-ai">
    <p class="entry-ai__eyebrow">Urus Operator</p>
    <h1 class="entry-ai__title">Reset kata laluan</h1>
    <p class="entry-ai__lede">
        Tetapkan kata laluan baharu untuk <strong>{{ $user->name }}</strong> ({{ $user->email }}).
        Min. 12 aksara, huruf besar/kecil, nombor &amp; simbol.
    </p>

    <form class="entry-ai__form" method="POST" action="{{ route('entry.manage-operators.password.update', $user) }}">
        @csrf
        @method('PUT')
        <label class="entry-ai__field">
            <span>Kata laluan baharu</span>
            <input type="password" name="password" required autofocus autocomplete="new-password">
            @error('password')<p class="entry-ai__error">{{ $message }}</p>@enderror
        </label>
        <label class="entry-ai__field">
            <span>Sahkan kata laluan</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button class="entry-ai__submit" type="submit">Kemas kini</button>
        <p class="entry-ai__hero-hint"><a href="{{ route('entry.manage-operators.index') }}">Kembali</a></p>
    </form>
</section>
@endsection
