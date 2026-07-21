@extends('layouts.app')

@section('title', $position->exists ? 'Edit position' : 'Create position')

@section('content')
@php($selectedPermissions = old('permissions', $position->exists ? $position->permissions->pluck('id')->all() : []))
<div class="mx-auto max-w-5xl">
    <div class="mb-8">
        <a class="text-sm font-semibold text-amber-800 hover:text-amber-700" href="{{ route('admin.positions.index') }}">&larr; Back to positions</a>
        <p class="eyebrow mt-6 text-amber-700">Access control</p>
        <h1 class="mt-2 text-3xl font-semibold">{{ $position->exists ? 'Edit position' : 'Create position' }}</h1>
    </div>

    <form method="POST" action="{{ $position->exists ? route('admin.positions.update', $position) : route('admin.positions.store') }}" class="space-y-6">
        @csrf
        @if($position->exists) @method('PUT') @endif

        <section class="border border-stone-200 bg-white p-6 sm:p-8">
            <h2 class="text-lg font-semibold">Position details</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="name">Name</label>
                    <input class="form-input" id="name" name="name" value="{{ old('name', $position->name) }}" required>
                </div>
                <div>
                    <label class="form-label" for="slug">Slug</label>
                    <input class="form-input" id="slug" name="slug" value="{{ old('slug', $position->slug) }}" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-input min-h-24" id="description" name="description">{{ old('description', $position->description) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-center gap-3 text-sm font-medium">
                        <input class="size-4 rounded border-stone-300 text-amber-700 focus:ring-amber-600" type="checkbox" name="is_active" value="1" @checked(old('is_active', $position->exists ? $position->is_active : true))>
                        Active and available for registration
                    </label>
                </div>
            </div>
        </section>

        <section class="border border-stone-200 bg-white p-6 sm:p-8">
            <div>
                <h2 class="text-lg font-semibold">Action permissions</h2>
                <p class="mt-1 text-sm text-stone-600">All staff assigned to this position inherit the same permissions.</p>
            </div>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                @foreach($permissions as $module => $modulePermissions)
                    <fieldset class="border border-stone-200 p-4">
                        <legend class="px-2 text-xs font-bold uppercase tracking-[0.14em] text-amber-800">{{ $module }}</legend>
                        <div class="space-y-3">
                            @foreach($modulePermissions as $permission)
                                <label class="flex items-start gap-3 text-sm">
                                    <input class="mt-0.5 size-4 rounded border-stone-300 text-amber-700 focus:ring-amber-600" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $selectedPermissions))>
                                    <span><strong class="block font-medium">{{ $permission->label }}</strong><small class="text-stone-500">{{ $permission->name }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('admin.positions.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">{{ $position->exists ? 'Save changes' : 'Create position' }}</button>
        </div>
    </form>
</div>
@endsection
