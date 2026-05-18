@php
    $calendarTitle = $calendarTitle ?? 'Appointment Calendar';
    $calendarSubtitle = $calendarSubtitle ?? 'Review available and booked appointment times by month.';
    $calendarCounts = $appointmentCalendar['counts'] ?? [];
    $calendarWeeks = $appointmentCalendar['weeks'] ?? [];
    $calendarQuery = request()->except(['calendar_month', 'slots_page', 'appointments_page']);
    $calendarUrl = function (string $month) use ($calendarQuery) {
        $query = array_merge($calendarQuery, ['calendar_month' => $month]);
        $queryString = http_build_query($query);

        return url()->current() . ($queryString ? '?' . $queryString : '');
    };
    $calendarLegend = [
        'available' => 'Available',
        'booked' => 'Booked',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
        'inactive' => 'Inactive',
        'expired' => 'Expired',
    ];
    $hasCalendarEvents = array_sum($calendarCounts) > 0;
@endphp

<style>
    .appointment-calendar-card {
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
    }
    .appointment-calendar-toolbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }
    .appointment-calendar-toolbar h5 {
        color: #0f172a;
        font-weight: 800;
        margin: 0;
    }
    .appointment-calendar-toolbar p {
        color: #64748b;
        margin: 6px 0 0;
    }
    .appointment-calendar-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }
    .appointment-calendar-month {
        align-items: center;
        background: #0f172a;
        border-radius: 8px;
        color: #fff;
        display: inline-flex;
        font-weight: 800;
        min-height: 38px;
        padding: 0 14px;
    }
    .appointment-calendar-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    .appointment-calendar-legend-item {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        color: #475569;
        display: inline-flex;
        font-size: 12px;
        font-weight: 700;
        gap: 7px;
        padding: 6px 10px;
    }
    .appointment-calendar-dot {
        border-radius: 999px;
        display: inline-block;
        height: 9px;
        width: 9px;
    }
    .appointment-calendar-dot--available { background: #16a34a; }
    .appointment-calendar-dot--booked { background: #2563eb; }
    .appointment-calendar-dot--pending { background: #d97706; }
    .appointment-calendar-dot--cancelled { background: #64748b; }
    .appointment-calendar-dot--inactive { background: #111827; }
    .appointment-calendar-dot--expired { background: #94a3b8; }
    .appointment-calendar-scroll {
        overflow-x: auto;
        padding-bottom: 2px;
    }
    .appointment-calendar-grid {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        display: grid;
        grid-template-columns: repeat(7, minmax(136px, 1fr));
        min-width: 980px;
        overflow: hidden;
    }
    .appointment-calendar-weekday {
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        padding: 10px 12px;
        text-transform: uppercase;
    }
    .appointment-calendar-day {
        background: #fff;
        border-bottom: 1px solid #eef2f7;
        border-right: 1px solid #eef2f7;
        min-height: 148px;
        padding: 10px;
    }
    .appointment-calendar-day:nth-child(7n + 7) {
        border-right: 0;
    }
    .appointment-calendar-day.is-muted {
        background: #fbfdff;
    }
    .appointment-calendar-date-row {
        align-items: center;
        display: flex;
        justify-content: flex-start;
        margin-bottom: 8px;
    }
    .appointment-calendar-date-label {
        align-items: center;
        border-radius: 999px;
        color: #0f172a;
        display: inline-flex;
        gap: 6px;
        min-height: 28px;
        padding: 0;
    }
    .appointment-calendar-weekday-name {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .appointment-calendar-date-number {
        color: #0f172a;
        font-weight: 800;
    }
    .appointment-calendar-day.is-muted .appointment-calendar-weekday-name,
    .appointment-calendar-day.is-muted .appointment-calendar-date-number {
        color: #94a3b8;
    }
    .appointment-calendar-day.is-today .appointment-calendar-date-label {
        background: #0ea5e9;
        color: #fff;
        padding: 4px 9px;
    }
    .appointment-calendar-day.is-today .appointment-calendar-weekday-name,
    .appointment-calendar-day.is-today .appointment-calendar-date-number {
        color: #fff;
    }
    .appointment-calendar-events {
        display: grid;
        gap: 6px;
    }
    .appointment-calendar-event {
        border: 1px solid #e5e7eb;
        border-left: 4px solid #94a3b8;
        border-radius: 7px;
        padding: 7px 8px;
    }
    .appointment-calendar-event--available {
        background: #ecfdf5;
        border-color: #bbf7d0;
        border-left-color: #16a34a;
    }
    .appointment-calendar-event--booked {
        background: #eff6ff;
        border-color: #bfdbfe;
        border-left-color: #2563eb;
    }
    .appointment-calendar-event--pending {
        background: #fffbeb;
        border-color: #fde68a;
        border-left-color: #d97706;
    }
    .appointment-calendar-event--cancelled {
        background: #f8fafc;
        border-color: #cbd5e1;
        border-left-color: #64748b;
    }
    .appointment-calendar-event--inactive {
        background: #f3f4f6;
        border-color: #d1d5db;
        border-left-color: #111827;
    }
    .appointment-calendar-event--expired {
        background: #f8fafc;
        border-color: #e2e8f0;
        border-left-color: #94a3b8;
        opacity: .78;
    }
    .appointment-calendar-event-top {
        align-items: center;
        display: flex;
        gap: 6px;
        justify-content: space-between;
    }
    .appointment-calendar-time {
        color: #0f172a;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }
    .appointment-calendar-badge {
        border-radius: 999px;
        color: #475569;
        font-size: 10px;
        font-weight: 800;
        line-height: 1;
        text-transform: uppercase;
    }
    .appointment-calendar-event-title {
        color: #0f172a;
        font-size: 12px;
        font-weight: 800;
        margin-top: 4px;
        word-break: break-word;
    }
    .appointment-calendar-event-subtitle {
        color: #64748b;
        font-size: 11px;
        line-height: 1.35;
        margin-top: 2px;
        word-break: break-word;
    }
    .appointment-calendar-empty {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        color: #64748b;
        margin-top: 14px;
        padding: 14px;
        text-align: center;
    }
    @media (max-width: 767px) {
        .appointment-calendar-toolbar {
            align-items: stretch;
            flex-direction: column;
        }
        .appointment-calendar-nav {
            justify-content: flex-start;
        }
    }
</style>

<div class="card appointment-calendar-card mb-4">
    <div class="card-body">
        <div class="appointment-calendar-toolbar">
            <div>
                <h5>{{ $calendarTitle }}</h5>
                <p>{{ $calendarSubtitle }}</p>
            </div>
            <div class="appointment-calendar-nav">
                <a href="{{ $calendarUrl($calendarMonth->copy()->subMonth()->format('Y-m')) }}" class="btn btn-outline-secondary btn-sm">
                    Previous
                </a>
                <span class="appointment-calendar-month">{{ $calendarMonth->format('F Y') }}</span>
                <a href="{{ $calendarUrl(now()->format('Y-m')) }}" class="btn btn-outline-secondary btn-sm">
                    Today
                </a>
                <a href="{{ $calendarUrl($calendarMonth->copy()->addMonth()->format('Y-m')) }}" class="btn btn-outline-secondary btn-sm">
                    Next
                </a>
            </div>
        </div>

        <div class="appointment-calendar-legend">
            @foreach($calendarLegend as $statusKey => $statusLabel)
                @if(($calendarCounts[$statusKey] ?? 0) > 0 || in_array($statusKey, ['available', 'booked', 'pending'], true))
                    <span class="appointment-calendar-legend-item">
                        <span class="appointment-calendar-dot appointment-calendar-dot--{{ $statusKey }}"></span>
                        {{ $statusLabel }} {{ $calendarCounts[$statusKey] ?? 0 }}
                    </span>
                @endif
            @endforeach
        </div>

        <div class="appointment-calendar-scroll">
            <div class="appointment-calendar-grid">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div class="appointment-calendar-weekday">{{ $weekday }}</div>
                @endforeach

                @foreach($calendarWeeks as $week)
                    @foreach($week as $day)
                        <div class="appointment-calendar-day {{ $day['is_current_month'] ? '' : 'is-muted' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                            <div class="appointment-calendar-date-row">
                                <span class="appointment-calendar-date-label">
                                    <span class="appointment-calendar-weekday-name">{{ $day['date']->format('D') }}</span>
                                    <span class="appointment-calendar-date-number">{{ $day['date']->format('j') }}</span>
                                </span>
                            </div>
                            <div class="appointment-calendar-events">
                                @foreach($day['events'] as $event)
                                    <div class="appointment-calendar-event appointment-calendar-event--{{ $event['status'] }}">
                                        <div class="appointment-calendar-event-top">
                                            <span class="appointment-calendar-time">{{ $event['time'] }}</span>
                                            <span class="appointment-calendar-badge">{{ $event['badge'] }}</span>
                                        </div>
                                        <div class="appointment-calendar-event-title">{{ $event['title'] }}</div>
                                        @if($event['subtitle'])
                                            <div class="appointment-calendar-event-subtitle">{{ $event['subtitle'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        @unless($hasCalendarEvents)
            <div class="appointment-calendar-empty">
                No appointment slots or bookings have been scheduled for {{ $calendarMonth->format('F Y') }}.
            </div>
        @endunless
    </div>
</div>
