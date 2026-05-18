@extends('admin.admin_master')
@section('admin')

<title>Trading Appointments | HC Gaming Studio</title>

@php
    $appointmentRoleLabels = [
        1 => 'Administration',
        2 => 'Founder / Partner',
        760 => 'Leader',
    ];
@endphp

<style>
    .appointment-card {
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        background: #fff;
        padding: 16px;
        height: 100%;
    }
    .appointment-time {
        color: #0f172a;
        font-size: 18px;
        font-weight: 800;
    }
    .appointment-meta {
        color: #64748b;
        font-size: 12px;
        margin-top: 4px;
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Trading Appointments</h4>
                        <p class="text-muted mb-0">Book available appointment slots or submit a preferred time for review.</p>
                    </div>
                    @if(in_array((int) auth()->user()->role_id, [1, 2], true))
                        <a href="{{ route('admin.trading.appointments.index') }}" class="btn btn-outline-primary">Manage Slots</a>
                    @endif
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @include('trading_appointments._calendar', [
            'calendarTitle' => 'Slots Calendar',
            'calendarSubtitle' => 'Check available and booked appointment times before choosing a slot.',
        ])

        <div class="row">
            <div class="col-xl-8 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Available Slots</h5>
                        <div class="row">
                            @forelse($slots as $slot)
                                <div class="col-lg-6 mb-3">
                                    <div class="appointment-card">
                                        <div class="appointment-time">{{ $slot->start_at?->format('M d, Y H:i') }}</div>
                                        <div class="appointment-meta">
                                            {{ $slot->duration_minutes }} minutes with {{ $slot->host?->name ?: $slot->host?->username }}
                                            @if($slot->location)
                                                <span class="mx-1">.</span>{{ $slot->location }}
                                            @endif
                                        </div>
                                        @if($slot->notes)
                                            <p class="text-muted small mt-2 mb-0">{{ \Illuminate\Support\Str::limit($slot->notes, 120) }}</p>
                                        @endif

                                        <form method="POST" action="{{ route('trading.appointments.slots.book', $slot->id) }}" class="mt-3">
                                            @csrf
                                            <div class="mb-2">
                                                <label class="form-label small">Subject</label>
                                                <input type="text" name="subject" class="form-control form-control-sm" required placeholder="Trading consultation">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Notes</label>
                                                <textarea name="request_note" rows="2" class="form-control form-control-sm" placeholder="What would you like to discuss?"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Book this appointment slot?');">Book Slot</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12">
                                    <div class="text-center text-muted py-4">No available appointment slots right now.</div>
                                </div>
                            @endforelse
                        </div>

                        {{ $slots->links('vendor.pagination.bootstrap-4') }}
                    </div>
                </div>
            </div>

            <div class="col-xl-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Request Preferred Time</h5>
                        <form method="POST" action="{{ route('trading.appointments.preferred.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label" for="host_user_id">Host</label>
                                <select name="host_user_id" id="host_user_id" class="form-select" required>
                                    @forelse($preferredHosts as $host)
                                        <option value="{{ $host->id }}" {{ (int) old('host_user_id') === (int) $host->id ? 'selected' : '' }}>
                                            {{ $host->name ?: $host->username }}
                                            ({{ $appointmentRoleLabels[(int) $host->role_id] ?? 'Role ' . $host->role_id }})
                                        </option>
                                    @empty
                                        <option value="">No eligible hosts available</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="preferred_start_at">Preferred Date & Time</label>
                                <input type="datetime-local" name="preferred_start_at" id="preferred_start_at" class="form-control" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('preferred_start_at') }}" required>
                                <div class="text-muted small mt-1">Booked, pending, and unavailable times shown in the calendar cannot be requested again.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="duration_minutes">Duration</label>
                                <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" value="{{ old('duration_minutes', 30) }}" min="15" max="240" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="subject">Subject</label>
                                <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="request_note">Reason / Agenda</label>
                                <textarea name="request_note" id="request_note" rows="4" class="form-control" required>{{ old('request_note') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-info w-100" {{ $preferredHosts->isEmpty() ? 'disabled' : '' }}>Submit For Review</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">My Appointment Records</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>With</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Cancel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myAppointments as $appointment)
                                @php($otherParty = (int) $appointment->host_user_id === (int) auth()->id() ? $appointment->requester : $appointment->host)
                                <tr>
                                    <td>
                                        <strong>{{ $appointment->scheduled_start_at?->format('Y-m-d H:i') }}</strong>
                                        <div class="text-muted small">{{ $appointment->duration_minutes }} minutes / {{ $appointment->typeLabel() }}</div>
                                    </td>
                                    <td>{{ $otherParty?->name ?: $otherParty?->username }}</td>
                                    <td>
                                        {{ $appointment->subject }}
                                        @if($appointment->review_note)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($appointment->review_note, 100) }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $appointment->statusTone() }}">{{ $appointment->statusLabel() }}</span></td>
                                    <td>
                                        @if($appointment->canCancelByPolicy())
                                            <form method="POST" action="{{ route('trading.appointments.cancel', $appointment->id) }}">
                                                @csrf
                                                <textarea name="cancellation_reason" rows="2" class="form-control form-control-sm mb-2" required placeholder="Valid cancellation reason"></textarea>
                                                <div class="text-muted small mb-2">Available until {{ $appointment->cancellationDeadlineLabel() }}.</div>
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this appointment?');">Cancel</button>
                                            </form>
                                        @elseif($appointment->isCancelled())
                                            <span class="text-muted small">{{ \Illuminate\Support\Str::limit($appointment->cancellation_reason, 100) }}</span>
                                        @else
                                            <span class="text-muted small">{{ $appointment->cancellationClosedLabel() }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No appointment records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $myAppointments->links('vendor.pagination.bootstrap-4') }}
            </div>
        </div>
    </div>
</div>

@endsection
