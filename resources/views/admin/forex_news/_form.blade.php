@php
    $isEdit = $mode === 'edit';
    $selectedImpact = (int) old('impact', $news->impact ?: 2);
    $selectedCommunity = old('community_id', $news->community_id);
    $selectedDate = old('news_date', $news->news_date ? $news->news_date->format('Y-m-d') : now()->toDateString());
    $imageUrl = $news->image ? asset($news->image) : null;
@endphp

<div class="news-form-grid">
    <div class="news-panel">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="newsForm">
            @csrf

            <div class="news-field">
                <label for="community_id" class="form-label">Community</label>
                <select name="community_id" id="community_id" class="form-select" required>
                    <option value="">Select community</option>
                    @foreach($communities as $community)
                        <option value="{{ $community->id }}" {{ (string) $selectedCommunity === (string) $community->id ? 'selected' : '' }}>
                            {{ $community->name }}
                        </option>
                    @endforeach
                </select>
                @error('community_id')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="news-field">
                        <label for="news_date" class="form-label">News Date</label>
                        <input type="date" name="news_date" id="news_date" class="form-control" value="{{ $selectedDate }}" required>
                        @error('news_date')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="news-field">
                        <label for="impact" class="form-label">Impact Level</label>
                        <select name="impact" id="impact" class="form-select" required>
                            <option value="1" {{ $selectedImpact === 1 ? 'selected' : '' }}>Low Impact</option>
                            <option value="2" {{ $selectedImpact === 2 ? 'selected' : '' }}>Medium Impact</option>
                            <option value="3" {{ $selectedImpact === 3 ? 'selected' : '' }}>High Impact</option>
                        </select>
                        @error('impact')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="news-field">
                <label for="image" class="form-label">News Image</label>
                <input type="file" name="image" id="image" class="form-control" accept="image/*">
                @error('image')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                @if($isEdit && $news->image)
                    <div class="news-muted mt-2">Current image will remain unless a new image is uploaded.</div>
                @endif
            </div>

            <div class="news-field">
                <label for="contentPreviewInput" class="form-label">Generated Briefing Preview</label>
                <textarea id="contentPreviewInput" class="form-control" rows="8" readonly></textarea>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="{{ $cancelRoute }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary" id="newsSubmitButton">
                    <i class="{{ $isEdit ? 'ri-save-3-line' : 'ri-send-plane-line' }}"></i>
                    {{ $isEdit ? 'Update Briefing' : 'Create Briefing' }}
                </button>
            </div>
        </form>
    </div>

    <aside class="news-panel">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <div class="news-muted text-uppercase fw-bold">Live Preview</div>
                <h5 class="mb-0">Trader-Facing Briefing</h5>
            </div>
            <span class="impact-pill" id="previewImpactPill">Medium Impact</span>
        </div>

        <div class="news-preview-image mb-3" id="imagePreviewBox">
            @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="Trading news image preview" id="imagePreview">
            @else
                <div id="imagePreviewPlaceholder">
                    <i class="ri-image-line d-block mb-2" style="font-size: 30px;"></i>
                    Image preview
                </div>
            @endif
        </div>

        <div class="news-preview-copy" id="briefingPreview"></div>
    </aside>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const impactInput = document.getElementById('impact');
        const dateInput = document.getElementById('news_date');
        const imageInput = document.getElementById('image');
        const preview = document.getElementById('briefingPreview');
        const previewTextarea = document.getElementById('contentPreviewInput');
        const impactPill = document.getElementById('previewImpactPill');
        const imagePreviewBox = document.getElementById('imagePreviewBox');
        const form = document.getElementById('newsForm');
        const submitButton = document.getElementById('newsSubmitButton');

        const profiles = {
            1: {
                label: 'Low Impact',
                className: 'impact-low',
                risk: 'Market movement is usually more contained, but risk controls still apply.',
                execution: 'Trade only if the setup matches the plan and the reward-to-risk remains acceptable.'
            },
            2: {
                label: 'Medium Impact',
                className: 'impact-medium',
                risk: 'Expect moderate volatility and possible short-term liquidity changes.',
                execution: 'Use planned levels, keep stops logical, and avoid increasing size without confirmation.'
            },
            3: {
                label: 'High Impact',
                className: 'impact-high',
                risk: 'Expect fast repricing, wider spreads, and possible slippage around the release window.',
                execution: 'Reduce exposure, avoid impulsive entries, and wait for post-news structure before committing risk.'
            }
        };

        function formatDate(value) {
            if (! value) {
                return 'Select a date';
            }

            const date = new Date(value + 'T00:00:00');

            return date.toLocaleDateString('en-GB', {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function updatePreview() {
            const impact = profiles[impactInput.value] || profiles[2];
            const dateLabel = formatDate(dateInput.value);
            const content = [
                'Market News Briefing',
                'Date: ' + dateLabel,
                'Impact Level: ' + impact.label,
                'Primary Focus: USD-related scheduled news',
                'Risk Guidance: ' + impact.risk,
                'Execution Plan: ' + impact.execution,
                'Reminder: Confirm your trading plan, position size, stop placement, and news risk before taking any trade.'
            ].join('\n');

            preview.textContent = content;
            previewTextarea.value = content;
            impactPill.textContent = impact.label;
            impactPill.className = 'impact-pill ' + impact.className;
        }

        impactInput.addEventListener('change', updatePreview);
        dateInput.addEventListener('change', updatePreview);

        imageInput.addEventListener('change', function (event) {
            const file = event.target.files[0];

            if (! file) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (readerEvent) {
                imagePreviewBox.innerHTML = '<img src="' + readerEvent.target.result + '" alt="Trading news image preview" id="imagePreview">';
            };

            reader.readAsDataURL(file);
        });

        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="ri-loader-4-line"></i> Saving';
        });

        updatePreview();
    });
</script>
