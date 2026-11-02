@php
    $includeStatus = $includeStatus ?? true;
    $rackAttr = $rackAttr ?? 'data-print-station-filter-rack';
    $columnAttr = $columnAttr ?? 'data-print-station-filter-column';
    $shelfAttr = $shelfAttr ?? 'data-print-station-filter-shelf';

    $selectedShelfLabel = 'Semua shelf';
    if (filled($shelfFilter)) {
        foreach ($racks as $rack) {
            $match = $rack->shelves->firstWhere('id', (int) $shelfFilter);
            if ($match) {
                $selectedShelfLabel = $rack->code.'/'.$match->code.($match->name ? ' — '.$match->name : '');
                break;
            }
        }
    }

    $selectedRackLabel = 'Semua rak';
    if (filled($rackFilter)) {
        $rackModel = $racks->firstWhere('id', (int) $rackFilter);
        if ($rackModel) {
            $selectedRackLabel = $rackModel->code.($rackModel->name ? ' — '.$rackModel->name : '');
        }
    }

    $selectedColumnLabel = filled($columnFilter) ? $columnFilter : 'Semua kolum';

    $selectedOperatorLabel = 'Semua operator';
    if (filled($filters['requested_by'] ?? null)) {
        $op = $operators->firstWhere('id', (int) $filters['requested_by']);
        if ($op) {
            $selectedOperatorLabel = $op->name;
        }
    }

    $statusLabels = [
        'pending' => 'Belum dicetak',
        'printed' => 'Dicetak',
        'all' => 'Semua',
    ];
    $reviewLabels = [
        'all' => 'Semua',
        'verified' => 'Disahkan',
        'unverified' => 'Belum disahkan',
    ];
@endphp

@if ($includeStatus)
    <div class="entry-filters__field entry-combobox" data-entry-combobox>
        <span>Status</span>
        <input type="hidden" name="status" value="{{ $statusFilter }}">
        <input
            class="entry-combobox__search"
            type="search"
            value="{{ $statusLabels[$statusFilter] ?? 'Belum dicetak' }}"
            placeholder="Cari status"
            autocomplete="off"
            spellcheck="false"
            data-entry-combobox-search
            aria-autocomplete="list"
            aria-expanded="false"
        >
        <div class="entry-combobox__popover hidden" data-entry-combobox-popover role="listbox" hidden>
            @foreach ($statusLabels as $value => $label)
                <button
                    class="entry-combobox__option"
                    type="button"
                    role="option"
                    data-entry-combobox-option
                    data-value="{{ $value }}"
                    data-label="{{ $label }}"
                    aria-selected="{{ $statusFilter === $value ? 'true' : 'false' }}"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>
@endif

<div class="entry-filters__field entry-combobox" data-entry-combobox>
    <span>Pengesahan</span>
    <input type="hidden" name="review" value="{{ $reviewFilter }}">
    <input
        class="entry-combobox__search"
        type="search"
        value="{{ $reviewLabels[$reviewFilter] ?? 'Semua' }}"
        placeholder="Cari pengesahan"
        autocomplete="off"
        spellcheck="false"
        data-entry-combobox-search
        aria-autocomplete="list"
        aria-expanded="false"
    >
    <div class="entry-combobox__popover hidden" data-entry-combobox-popover role="listbox" hidden>
        @foreach ($reviewLabels as $value => $label)
            <button
                class="entry-combobox__option"
                type="button"
                role="option"
                data-entry-combobox-option
                data-value="{{ $value }}"
                data-label="{{ $label }}"
                aria-selected="{{ $reviewFilter === $value ? 'true' : 'false' }}"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>

<div class="entry-filters__field entry-combobox" data-entry-combobox>
    <span>Operator</span>
    <input type="hidden" name="requested_by" value="{{ $filters['requested_by'] }}">
    <input
        class="entry-combobox__search"
        type="search"
        value="{{ $selectedOperatorLabel }}"
        placeholder="Cari operator"
        autocomplete="off"
        spellcheck="false"
        data-entry-combobox-search
        aria-autocomplete="list"
        aria-expanded="false"
    >
    <div class="entry-combobox__popover hidden" data-entry-combobox-popover role="listbox" hidden>
        <button
            class="entry-combobox__option"
            type="button"
            role="option"
            data-entry-combobox-option
            data-value=""
            data-label="Semua operator"
            aria-selected="{{ filled($filters['requested_by'] ?? null) ? 'false' : 'true' }}"
        >Semua operator</button>
        @foreach ($operators as $operator)
            <button
                class="entry-combobox__option"
                type="button"
                role="option"
                data-entry-combobox-option
                data-value="{{ $operator->id }}"
                data-label="{{ $operator->name }}"
                aria-selected="{{ (string) ($filters['requested_by'] ?? '') === (string) $operator->id ? 'true' : 'false' }}"
            >{{ $operator->name }}</button>
        @endforeach
    </div>
</div>

<div class="entry-filters__field entry-combobox" data-entry-combobox data-entry-combobox-key="rack">
    <span>Rak</span>
    <input type="hidden" name="rack_id" value="{{ $rackFilter }}" {!! $rackAttr !!}>
    <input
        class="entry-combobox__search"
        type="search"
        value="{{ $selectedRackLabel }}"
        placeholder="Cari rak"
        autocomplete="off"
        spellcheck="false"
        data-entry-combobox-search
        aria-autocomplete="list"
        aria-expanded="false"
    >
    <div class="entry-combobox__popover hidden" data-entry-combobox-popover role="listbox" hidden>
        <button
            class="entry-combobox__option"
            type="button"
            role="option"
            data-entry-combobox-option
            data-value=""
            data-label="Semua rak"
            aria-selected="{{ filled($rackFilter) ? 'false' : 'true' }}"
        >Semua rak</button>
        @foreach ($racks as $rack)
            @php $label = $rack->code.($rack->name ? ' — '.$rack->name : ''); @endphp
            <button
                class="entry-combobox__option"
                type="button"
                role="option"
                data-entry-combobox-option
                data-value="{{ $rack->id }}"
                data-label="{{ $label }}"
                aria-selected="{{ (string) $rackFilter === (string) $rack->id ? 'true' : 'false' }}"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>

<div class="entry-filters__field entry-combobox" data-entry-combobox data-entry-combobox-key="column">
    <span>Kolum</span>
    <input type="hidden" name="column" value="{{ $columnFilter }}" {!! $columnAttr !!}>
    <input
        class="entry-combobox__search"
        type="search"
        value="{{ $selectedColumnLabel }}"
        placeholder="Cari kolum"
        autocomplete="off"
        spellcheck="false"
        data-entry-combobox-search
        aria-autocomplete="list"
        aria-expanded="false"
    >
    <div class="entry-combobox__popover hidden" data-entry-combobox-popover role="listbox" hidden>
        <button
            class="entry-combobox__option"
            type="button"
            role="option"
            data-entry-combobox-option
            data-value=""
            data-label="Semua kolum"
            aria-selected="{{ filled($columnFilter) ? 'false' : 'true' }}"
        >Semua kolum</button>
        @foreach ($columnsByRack as $rackId => $columns)
            @foreach ($columns as $column)
                <button
                    class="entry-combobox__option"
                    type="button"
                    role="option"
                    data-entry-combobox-option
                    data-value="{{ $column }}"
                    data-rack-id="{{ $rackId }}"
                    data-label="{{ $column }}"
                    aria-selected="{{ (string) $rackFilter === (string) $rackId && $columnFilter === $column ? 'true' : 'false' }}"
                >{{ $column }}</button>
            @endforeach
        @endforeach
    </div>
</div>

<div class="entry-filters__field entry-combobox entry-combobox--wide" data-entry-combobox data-entry-combobox-key="shelf">
    <span>Shelf</span>
    <input type="hidden" name="shelf_id" value="{{ $shelfFilter }}" {!! $shelfAttr !!}>
    <input
        class="entry-combobox__search"
        type="search"
        value="{{ $selectedShelfLabel }}"
        placeholder="Cari kod shelf, cth. G/A1"
        autocomplete="off"
        spellcheck="false"
        data-entry-combobox-search
        aria-autocomplete="list"
        aria-expanded="false"
    >
    <div class="entry-combobox__popover hidden" data-entry-combobox-popover role="listbox" hidden>
        <button
            class="entry-combobox__option"
            type="button"
            role="option"
            data-entry-combobox-option
            data-value=""
            data-label="Semua shelf"
            aria-selected="{{ filled($shelfFilter) ? 'false' : 'true' }}"
        >Semua shelf</button>
        @foreach ($racks as $rack)
            @foreach ($rack->shelves as $shelf)
                @php $label = $rack->code.'/'.$shelf->code.($shelf->name ? ' — '.$shelf->name : ''); @endphp
                <button
                    class="entry-combobox__option"
                    type="button"
                    role="option"
                    data-entry-combobox-option
                    data-value="{{ $shelf->id }}"
                    data-rack-id="{{ $shelf->rack_id }}"
                    data-column="{{ $shelf->columnLabel() }}"
                    data-label="{{ $label }}"
                    aria-selected="{{ (string) $shelfFilter === (string) $shelf->id ? 'true' : 'false' }}"
                >{{ $label }}</button>
            @endforeach
        @endforeach
    </div>
</div>
