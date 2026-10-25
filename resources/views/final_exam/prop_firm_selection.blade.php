@extends('admin.admin_master')
@section('admin')

<title>Prop Firm Model Selection | HC Gaming Studio</title>

<style>
    .prop-selection .model-card {
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        height: 100%;
    }
    .prop-selection .model-card.is-current {
        border-color: #22c55e;
        box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.12);
    }
</style>

<div class="page-content prop-selection">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">Prop Firm Model Selection</h4>
                <p class="text-muted mb-0">Choose your preferred model after passing the Final Examination.</p>
            </div>
            <a href="{{ route('final_exam.intro') }}" class="btn btn-light border">
                <i class="ri-arrow-left-line me-1"></i> Final Exam
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($preference)
            <div class="alert alert-success">
                Current selection: {{ $preference->propFirmModel?->name ?: $preference->prop_firm_model }}
                @if($preference->propFirmPhaseRule)
                    / {{ $preference->propFirmPhaseRule->display_name }}
                @endif
            </div>
        @endif

        <div class="row g-4">
            @forelse($models as $model)
                @php $isCurrent = (int) $user->prop_firm_model_id === (int) $model->id; @endphp
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card model-card {{ $isCurrent ? 'is-current' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h5 class="mb-0">{{ $model->name }}</h5>
                                @if($isCurrent)
                                    <span class="badge bg-success">Selected</span>
                                @endif
                            </div>
                            <p class="text-muted">{{ $model->description ?: 'Prop firm model configured by administration.' }}</p>

                            <div class="mb-3">
                                @forelse($model->phaseRules as $phaseRule)
                                    <div class="small border rounded p-2 mb-2">
                                        <strong>{{ $phaseRule->display_name }}</strong>
                                        <div class="text-muted">Target {{ $phaseRule->profit_target_percent }}% / Daily Loss {{ $phaseRule->max_daily_loss_percent }}%</div>
                                    </div>
                                @empty
                                    <div class="text-muted small">No active phase rules configured.</div>
                                @endforelse
                            </div>

                            <form method="POST" action="{{ route('final_exam.prop_firm_selection.save') }}" class="mt-auto">
                                @csrf
                                <input type="hidden" name="prop_firm_model_id" value="{{ $model->id }}">
                                <button type="submit" class="btn btn-{{ $isCurrent ? 'success' : 'primary' }} w-100">
                                    <i class="ri-check-line me-1"></i> {{ $isCurrent ? 'Keep Selected' : 'Select Model' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5 text-muted">No active prop firm models are available yet.</div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection
