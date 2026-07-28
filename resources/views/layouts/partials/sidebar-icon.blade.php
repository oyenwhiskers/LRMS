@switch($icon)
    @case('dashboard')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <rect width="7" height="9" x="3" y="3" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
            <rect width="7" height="5" x="14" y="3" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
            <rect width="7" height="9" x="14" y="12" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
            <rect width="7" height="5" x="3" y="16" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break
    @case('approvals')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>
        </svg>
        @break
    @case('files')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 14 1.5-2.9A2 2 0 0 1 9.24 10H20a2 2 0 0 1 1.94 2.5l-1.54 6a2 2 0 0 1-1.95 1.5H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H18a2 2 0 0 1 2 2v2"/>
        </svg>
        @break
    @case('borrow')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2 9V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2 13h10"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 16 3-3-3-3"/>
        </svg>
        @break
    @case('return')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2 7.5V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-1.5"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2 13h10"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 10-3 3 3 3"/>
        </svg>
        @break
    @case('history')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v5h5"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l4 2"/>
        </svg>
        @break
    @case('staff')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 3.128a4 4 0 0 1 0 7.744"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <circle cx="9" cy="7" r="4"/>
        </svg>
        @break
    @case('settings')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
        @break
    @case('briefcase')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 4h4a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3V6a2 2 0 0 1 2-2Z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 12h4"/>
        </svg>
        @break
    @case('archive')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <rect x="3" y="4" width="18" height="4" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 12h4"/>
        </svg>
        @break
@endswitch
