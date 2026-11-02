@php
    $includeStatus = $includeStatus ?? true;
    $rackAttr = $rackAttr ?? 'data-print-station-filter-rack';
    $columnAttr = $columnAttr ?? 'data-print-station-filter-column';
    $shelfAttr = $shelfAttr ?? 'data-print-station-filter-shelf';
@endphp

@if ($includeStatus)
    <label class="entry-filters__field">
        <span>Status</span>
        <select name="status">
            <option value="pending" @selected($statusFilter === 'pending')>Belum dicetak</option>
            <option value="printed" @selected($statusFilter === 'printed')>Dicetak</option>
            <option value="all" @selected($statusFilter === 'all')>Semua</option>
        </select>
    </label>
@endif

<label class="entry-filters__field">
    <span>Pengesahan</span>
    <select name="review">
        <option value="all" @selected($reviewFilter === 'all')>Semua</option>
        <option value="verified" @selected($reviewFilter === 'verified')>Disahkan</option>
        <option value="unverified" @selected($reviewFilter === 'unverified')>Belum disahkan</option>
    </select>
</label>

<label class="entry-filters__field">
    <span>Operator</span>
    <select name="requested_by">
        <option value="">Semua operator</option>
        @foreach ($operators as $operator)
            <option value="{{ $operator->id }}" @selected((string) $filters['requested_by'] === (string) $operator->id)>
                {{ $operator->name }}
            </option>
        @endforeach
    </select>
</label>

<label class="entry-filters__field">
    <span>Rak</span>
    <select name="rack_id" {!! $rackAttr !!}>
        <option value="">Semua rak</option>
        @foreach ($racks as $rack)
            <option value="{{ $rack->id }}" @selected((string) $rackFilter === (string) $rack->id)>
                {{ $rack->code }}{{ $rack->name ? ' — '.$rack->name : '' }}
            </option>
        @endforeach
    </select>
</label>

<label class="entry-filters__field">
    <span>Kolum</span>
    <select name="column" {!! $columnAttr !!}>
        <option value="">Semua kolum</option>
        @foreach ($columnsByRack as $rackId => $columns)
            @foreach ($columns as $column)
                <option
                    value="{{ $column }}"
                    data-rack-id="{{ $rackId }}"
                    @selected((string) $rackFilter === (string) $rackId && $columnFilter === $column)
                >
                    {{ $column }}
                </option>
            @endforeach
        @endforeach
    </select>
</label>

@php
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
@endphp
<div class="entry-filters__field entry-shelf-combobox" data-shelf-combobox>
    <span>Shelf</span>
    <input type="hidden" name="shelf_id" value="{{ $shelfFilter }}" {!! $shelfAttr !!}>
    <input
        class="entry-shelf-combobox__search"
        type="search"
        value="{{ $selectedShelfLabel }}"
        placeholder="Cari kod shelf, cth. G/A1"
        autocomplete="off"
        spellcheck="false"
        data-shelf-combobox-search
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="print-station-shelf-options"
    >
    <div
        id="print-station-shelf-options"
        class="entry-shelf-combobox__list hidden"
        data-shelf-combobox-list
        role="listbox"
        hidden
    >
        <button
            class="entry-shelf-combobox__option"
            type="button"
            role="option"
            data-shelf-combobox-option
            data-value=""
            data-label="Semua shelf"
            aria-selected="{{ filled($shelfFilter) ? 'false' : 'true' }}"
        >
            Semua shelf
        </button>
        @foreach ($racks as $rack)
            @foreach ($rack->shelves as $shelf)
                @php
                    $label = $rack->code.'/'.$shelf->code.($shelf->name ? ' — '.$shelf->name : '');
                @endphp
                <button
                    class="entry-shelf-combobox__option"
                    type="button"
                    role="option"
                    data-shelf-combobox-option
                    data-value="{{ $shelf->id }}"
                    data-rack-id="{{ $shelf->rack_id }}"
                    data-column="{{ $shelf->columnLabel() }}"
                    data-label="{{ $label }}"
                    aria-selected="{{ (string) $shelfFilter === (string) $shelf->id ? 'true' : 'false' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        @endforeach
    </div>
    <p class="entry-shelf-combobox__hint hidden" data-shelf-combobox-empty>Tiada shelf sepadan.</p>
</div>
