@extends('layouts.entry')

@section('title', 'Urus Operator')

@section('content')
<div class="entry-ops">
    <div class="entry-ops__intro">
        <div>
            <h1 class="entry-page-title">Urus Operator</h1>
            <p class="entry-page-lede">Reset atau tukar kata laluan akaun operator.</p>
        </div>
    </div>

    <div class="entry-ops__list">
        <div class="entry-ops__list-head">
            <p class="entry-ops__list-count">{{ $users->total() }} operator</p>
        </div>

        @if ($users->isEmpty())
            <p class="entry-empty__text">Tiada akaun operator lagi.</p>
        @else
            <div class="entry-ops__table-wrap">
                <table class="entry-ops__table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Emel</th>
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
                                <td>
                                    <a href="{{ route('entry.manage-operators.password.edit', $user) }}">Reset kata laluan</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
