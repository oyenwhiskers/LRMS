<button
    class="btn-danger"
    type="button"
    data-reject-completion
    data-reject-url="{{ route('completions.reject', $file) }}"
    data-reject-ref="{{ $file->reference_number }}"
>Reject</button>
<form method="POST" action="{{ route('completions.approve', $file) }}">
    @csrf
    <button class="btn-success" type="submit">Approve</button>
</form>
