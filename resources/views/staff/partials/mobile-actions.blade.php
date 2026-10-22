@can('staff.qr_print')
    <a class="btn-secondary" href="{{ route('staff.qr', $member) }}">QR</a>
@endcan
@can('staff.update')
    <a class="btn-secondary" href="{{ route('staff.edit', $member) }}">Edit</a>
@endcan
@can('staff.deactivate')
    <form class="col-span-2" method="POST" action="{{ route('staff.toggle', $member) }}">
        @csrf
        @method('PATCH')
        <button class="btn-secondary w-full">{{ $member->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>
@endcan
