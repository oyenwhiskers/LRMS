@php($useOldInput = $useOldInput ?? true)

<div class="grid gap-6 sm:grid-cols-2">
    <label>
        <span class="form-label">Staff number</span>
        <input class="form-input" name="staff_number" value="{{ $useOldInput ? old('staff_number', $staffMember->staff_number) : $staffMember->staff_number }}" required autofocus>
    </label>
    <label>
        <span class="form-label">Full name</span>
        <input class="form-input" name="full_name" value="{{ $useOldInput ? old('full_name', $staffMember->full_name) : $staffMember->full_name }}" required>
    </label>
    <label>
        <span class="form-label">Email</span>
        <input class="form-input" type="email" name="email" value="{{ $useOldInput ? old('email', $staffMember->email) : $staffMember->email }}">
    </label>
    <label>
        <span class="form-label">Phone</span>
        <input class="form-input" name="phone" value="{{ $useOldInput ? old('phone', $staffMember->phone) : $staffMember->phone }}">
    </label>
    <label class="sm:col-span-2">
        <span class="form-label">Job position</span>
        <select class="form-input" name="position_id">
            <option value="">Unassigned</option>
            @foreach($positions as $position)
                <option value="{{ $position->id }}" @selected(($useOldInput ? old('position_id', $staffMember->position_id) : $staffMember->position_id) == $position->id)>{{ $position->name }}{{ $position->is_active ? '' : ' (Inactive)' }}</option>
            @endforeach
        </select>
    </label>
</div>
