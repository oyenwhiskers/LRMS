@extends('layouts.app')

@section('title', 'Entry operators')
@section('back', route('dashboard'))

@section('content')
<div>
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow text-amber-700">Data entry</p>
            <h1 class="mt-1 text-3xl font-semibold">Entry operators</h1>
            <p class="mt-2 text-sm text-stone-600">Internal accounts for the /entry scan-register mini-app. Not linked to staff.</p>
        </div>
    </div>

    <form class="mt-8 space-y-4 border border-stone-200 bg-white p-6" method="POST" action="{{ route('admin.entry-users.store') }}">
        @csrf
        <h2 class="text-xl font-semibold">Create operator</h2>
        <div class="grid gap-4 lg:grid-cols-3">
            <label class="block space-y-1">
                <span class="form-label">Name</span>
                <input class="form-input" name="name" value="{{ old('name') }}" required>
                @error('name')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            </label>
            <label class="block space-y-1">
                <span class="form-label">Email</span>
                <input class="form-input" type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            </label>
            <label class="block space-y-1">
                <span class="form-label">Password</span>
                <input class="form-input" type="password" name="password" required>
                @error('password')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
            </label>
            <label class="block space-y-1 lg:col-span-3">
                <span class="form-label">Confirm password</span>
                <input class="form-input" type="password" name="password_confirmation" required>
            </label>
        </div>
        <button class="btn-primary">Create account</button>
    </form>

    <div class="mt-8 overflow-x-auto border border-stone-200 bg-white">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->status }}</td>
                        <td class="text-right">
                            @if ($user->isApproved())
                                <form method="POST" action="{{ route('admin.entry-users.deactivate', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn-secondary text-sm" type="submit">Deactivate</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.entry-users.reactivate', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn-secondary text-sm" type="submit">Reactivate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($users->isEmpty())
        <div class="mt-4">
            @include('layouts.partials.table-empty', [
                'title' => 'No entry operators',
                'message' => 'Create an operator account above to start data entry.',
            ])
        </div>
    @endif

    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection
