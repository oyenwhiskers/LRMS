@can('categories.manage')
    <button
        class="btn-secondary"
        type="button"
        data-modal-open="edit-category-{{ $category->id }}"
    >Edit</button>
    <button
        class="btn-danger"
        type="button"
        data-modal-open="{{ $category->legal_files_count > 0 ? 'cannot-delete-category-'.$category->id : 'delete-category-'.$category->id }}"
    >Delete</button>
    <form class="col-span-2" method="POST" action="{{ route('categories.toggle', $category) }}">
        @csrf
        @method('PATCH')
        <button class="btn-secondary w-full" type="submit">
            {{ $category->is_active ? 'Deactivate' : 'Activate' }}
        </button>
    </form>
@endcan
