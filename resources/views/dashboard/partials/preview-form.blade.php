<form class="grid gap-3" method="GET" action="{{ $action }}" data-dashboard-pdf-preview>
    <input type="hidden" name="format" value="pdf">
    <input type="hidden" name="preview" value="1">
    <label>
        <span class="form-label">{{ $scopeLabel }}</span>
        <select class="form-input" name="scope">
            @foreach($scopes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @include('dashboard.partials.category-checklist', ['categories' => $categories ?? null])
    <button class="btn-primary w-full" type="submit">Open preview</button>
</form>
