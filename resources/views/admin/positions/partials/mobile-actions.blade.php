@can('update', $position)
    <a class="btn-secondary" href="{{ route('admin.positions.edit', $position) }}">Edit</a>
@endcan
@can('delete', $position)
    @if($position->is_active)
        <form method="POST" action="{{ route('admin.positions.destroy', $position) }}" onsubmit="return confirm('Deactivate this position? Assigned users will lose its permissions.')">
            @csrf
            @method('DELETE')
            <button class="btn-danger w-full" type="submit">Deactivate</button>
        </form>
    @endif
@endcan
