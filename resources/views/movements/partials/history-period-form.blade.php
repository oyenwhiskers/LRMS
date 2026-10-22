<form class="grid gap-3" method="GET" data-history-filter-form>
    <input type="hidden" name="search" value="{{ request('search') }}">
    <input type="hidden" name="movement" value="{{ $movementType }}">
    <label>
        <span class="form-label">View</span>
        <select class="form-input" name="period" data-history-period>
            <option value="today" @selected($period === 'today')>Today</option>
            <option value="this_week" @selected($period === 'this_week')>This week</option>
            <option value="this_month" @selected($period === 'this_month')>This month</option>
            <option value="this_year" @selected($period === 'this_year')>This year</option>
            <option value="specific_date" @selected($period === 'specific_date')>Specific date</option>
            <option value="custom_range" @selected($period === 'custom_range')>Custom range</option>
            <option value="all" @selected($period === 'all')>All history</option>
        </select>
    </label>

    <label @class(['hidden' => $period !== 'specific_date']) data-history-date>
        <span class="form-label">Date</span>
        <input class="form-input" type="date" name="date" value="{{ $selectedDate }}">
    </label>

    <label @class(['hidden' => $period !== 'custom_range']) data-history-from>
        <span class="form-label">From</span>
        <input class="form-input" type="date" name="date_from" value="{{ $dateFrom }}">
    </label>

    <label @class(['hidden' => $period !== 'custom_range']) data-history-to>
        <span class="form-label">To</span>
        <input class="form-input" type="date" name="date_to" value="{{ $dateTo }}">
    </label>

    <div class="grid grid-cols-2 gap-3 pt-1">
        <a class="btn-secondary w-full" href="{{ route('movements.history') }}">Reset</a>
        <button class="btn-primary w-full" type="submit">Apply</button>
    </div>
</form>
