<form class="grid gap-3" method="GET" action="{{ $action }}">
    <label>
        <span class="form-label">{{ $scopeLabel }}</span>
        <select class="form-input" name="scope">
            @foreach($scopes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="form-label">Format</span>
        <select class="form-input" name="format">
            <option value="csv">CSV</option>
            <option value="pdf">PDF</option>
        </select>
    </label>
    <button class="btn-primary w-full" type="submit">Download</button>
</form>
