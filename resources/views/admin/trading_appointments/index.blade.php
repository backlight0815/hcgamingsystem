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
    .appointment-badge {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 5px 10px;
    }
    .slot-edit-grid {
        display: grid;
        gap: 8px;
        grid-template-columns: minmax(180px, 1.3fr) 90px minmax(150px, 1fr) 110px minmax(160px, 1fr) auto;
        align-items: end;
    }
    .review-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        min-width: 320px;
    }
    @media (max-width: 1200px) {
        .slot-edit-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
        .slot-edit-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Trading Appointments</h4>
                        <p class="text-muted mb-0">Manage appointment availability, preferred-time reviews, and upcoming meetings.</p>
                    </div>
                    <a href="{{ route('trading.appointments.index') }}" class="btn btn-outline-primary">
                        My Appointments
                    </a>
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

        <div class="row mb-4">
            <div class="col-md-3 mb-3"><div class="card bg-success text-white text-center p-3"><h6 class="mb-1">Available</h6><h3 class="mb-0">{{ $metrics['available'] }}</h3></div></div>
            <div class="col-md-3 mb-3"><div class="card bg-primary text-white text-center p-3"><h6 class="mb-1">Booked</h6><h3 class="mb-0">{{ $metrics['booked'] }}</h3></div></div>
            <div class="col-md-3 mb-3"><div class="card bg-warning text-dark text-center p-3"><h6 class="mb-1">Pending Review</h6><h3 class="mb-0">{{ $metrics['pending'] }}</h3></div></div>
            <div class="col-md-3 mb-3"><div class="card bg-secondary text-white text-center p-3"><h6 class="mb-1">Cancelled</h6><h3 class="mb-0">{{ $metrics['cancelled'] }}</h3></div></div>
        </div>

        @include('trading_appointments._calendar', [
            'calendarTitle' => 'Slots Calendar',
            'calendarSubtitle' => 'Review all host availability, confirmed bookings, pending requests, and inactive slots by month.',
        ])

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Create Availability Slot</h5>
                <form action="{{ route('admin.trading.appointments.slots.store') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label" for="host_user_id">Host</label>
                        <select name="host_user_id" id="host_user_id" class="form-select" required>
                            @foreach($hosts as $host)
                                <option value="{{ $host->id }}" {{ old('host_user_id') == $host->id ? 'selected' : '' }}>
                                    {{ $host->name ?: $host->username }}
                                    ({{ $appointmentRoleLabels[(int) $host->role_id] ?? 'Role ' . $host->role_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="start_at">Date & Time</label>
                        <input type="datetime-local" name="start_at" id="start_at" class="form-control" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('start_at') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="duration_minutes">Duration</label>
                        <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" value="{{ old('duration_minutes', 30) }}" min="15" max="240" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="status">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="location">Location</label>
                        <input type="text" name="location" id="location" class="form-control" value="{{ old('location') }}" placeholder="Zoom / Discord">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Internal Notes</label>
                        <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Create Slot</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Preferred-Time Review Queue</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Requester</th>
                                <th>Host</th>
                                <th>Requested Time</th>
                                <th>Subject</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingRequests as $appointment)
                                <tr>
                                    <td>{{ $appointment->requester?->name ?: $appointment->requester?->username }}</td>
                                    <td>{{ $appointment->host?->name ?: $appointment->host?->username }}</td>
                                    <td>
                                        <strong>{{ $appointment->scheduled_start_at?->format('Y-m-d H:i') }}</strong>
                                        <div class="text-muted small">{{ $appointment->duration_minutes }} minutes</div>
                                    </td>
                                    <td>
                                        <strong>{{ $appointment->subject }}</strong>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($appointment->request_note, 120) }}</div>
                                    </td>
                                    <td>
                                        <div class="review-box">
                                            <form method="POST" action="{{ route('admin.trading.appointments.approve', $appointment->id) }}" class="mb-2">
                                                @csrf
                                                <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2" placeholder="Optional approval note"></textarea>
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this appointment request?');">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.trading.appointments.reject', $appointment->id) }}">
                                                @csrf
                                                <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2" required placeholder="Reason if not approved"></textarea>
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject this appointment request?');">Not Approve</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No preferred appointment requests are waiting for review.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Availability Slots</h5>
                @forelse($slots as $slot)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <strong>{{ $slot->host?->name ?: $slot->host?->username }}</strong>
                                <span class="badge bg-{{ $slot->statusTone() }} ms-2">{{ $slot->statusLabel() }}</span>
                                <div class="text-muted small">{{ $slot->start_at?->format('Y-m-d H:i') }} to {{ $slot->end_at?->format('H:i') }}</div>
                            </div>
                            @if($slot->appointment)
                                <span class="text-muted small">Booked by {{ $slot->appointment?->requester?->name ?: $slot->appointment?->requester?->username }}</span>
                            @endif
                        </div>

                        @if($slot->status !== \App\Models\TradingAppointmentSlot::STATUS_BOOKED)
                            <form method="POST" action="{{ route('admin.trading.appointments.slots.update', $slot->id) }}" class="slot-edit-grid">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="form-label small">Date & Time</label>
                                    <input type="datetime-local" name="start_at" class="form-control form-control-sm" value="{{ $slot->start_at?->format('Y-m-d\TH:i') }}" required>
                                </div>
                                <div>
                                    <label class="form-label small">Duration</label>
                                    <input type="number" name="duration_minutes" class="form-control form-control-sm" value="{{ $slot->duration_minutes }}" min="15" max="240" required>
                                </div>
                                <div>
                                    <label class="form-label small">Host</label>
                                    <select name="host_user_id" class="form-select form-select-sm" required>
                                        @foreach($hosts as $host)
                                            <option value="{{ $host->id }}" {{ (int) $slot->host_user_id === (int) $host->id ? 'selected' : '' }}>
                                                {{ $host->name ?: $host->username }}
                                                ({{ $appointmentRoleLabels[(int) $host->role_id] ?? 'Role ' . $host->role_id }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="active" {{ $slot->status === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ $slot->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small">Location</label>
                                    <input type="text" name="location" class="form-control form-control-sm" value="{{ $slot->location }}">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-info btn-sm">Update</button>
                                </div>
                                <input type="hidden" name="notes" value="{{ $slot->notes }}">
                            </form>
                            <form method="POST" action="{{ route('admin.trading.appointments.slots.destroy', $slot->id) }}" class="mt-2" onsubmit="return confirm('Remove this available slot?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm">Remove Slot</button>
                            </form>
                        @else
                            <div class="text-muted small">Booked slots cannot be edited. Cancel the appointment first if the slot must be reopened.</div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No appointment slots found.</div>
                @endforelse

                {{ $slots->links('vendor.pagination.bootstrap-4') }}
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Appointment Records</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Requester</th>
                                <th>Host</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Cancel</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($appointments as $appointment)
                                <tr>
                                    <td>
                                        <strong>{{ $appointment->scheduled_start_at?->format('Y-m-d H:i') }}</strong>
                                        <div class="text-muted small">{{ $appointment->typeLabel() }} / {{ $appointment->duration_minutes }} minutes</div>
                                    </td>
                                    <td>{{ $appointment->requester?->name ?: $appointment->requester?->username }}</td>
                                    <td>{{ $appointment->host?->name ?: $appointment->host?->username }}</td>
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
                                <tr><td colspan="6" class="text-center text-muted py-4">No appointment records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $appointments->links('vendor.pagination.bootstrap-4') }}
            </div>
        </div>
    </div>
</div>

@endsection
