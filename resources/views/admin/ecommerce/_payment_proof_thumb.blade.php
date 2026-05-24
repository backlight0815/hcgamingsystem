@php
    $proofPath = $proofPath ?? null;
    $proofUrl = $proofPath ? asset($proofPath) : null;
    $proofExtension = strtolower(pathinfo(parse_url($proofPath ?? '', PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $isImageProof = in_array($proofExtension, $imageExtensions, true);
@endphp

@if($proofPath)
    <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="commerce-proof-link" title="Open payment proof">
        <img
            src="{{ $isImageProof ? $proofUrl : asset('upload/default.jpg') }}"
            class="commerce-thumb"
            alt="Payment proof"
        >
        @unless($isImageProof)
            <span class="commerce-proof-badge">{{ strtoupper($proofExtension ?: 'FILE') }}</span>
        @endunless
    </a>
@else
    <span class="commerce-muted">No proof</span>
@endif
