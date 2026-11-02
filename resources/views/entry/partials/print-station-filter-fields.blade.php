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

<label class="entry-filters__field">
    <span>Shelf</span>
    <select name="shelf_id" {!! $shelfAttr !!}>
        <option value="">Semua shelf</option>
        @foreach ($racks as $rack)
            @foreach ($rack->shelves as $shelf)
                <option
                    value="{{ $shelf->id }}"
                    data-rack-id="{{ $shelf->rack_id }}"
                    data-column="{{ $shelf->columnLabel() }}"
                    @selected((string) $shelfFilter === (string) $shelf->id)
                >
                    {{ $rack->code }}/{{ $shelf->code }}{{ $shelf->name ? ' — '.$shelf->name : '' }}
                </option>
            @endforeach
        @endforeach
    </select>
</label>
