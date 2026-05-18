<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\SignalProviderCertificate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class SignalProviderCertificateController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        abort_unless($authUser, 403);

        $query = SignalProviderCertificate::with(['user', 'issuer'])->latest('updated_at');

        if (! $this->isAdmin($authUser)) {
            $query->where('user_id', $authUser->id);
        } else {
            $query
                ->when($request->filled('user_id'), fn ($builder) => $builder->where('user_id', $request->user_id))
                ->when($request->filled('level'), fn ($builder) => $builder->where('level', $request->level))
                ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->status));
        }

        $certificates = $query->get();
        $users = $this->eligibleUsers();
        $levels = SignalProviderCertificate::levels();
        $types = SignalProviderCertificate::certificateTypes();
        $statuses = SignalProviderCertificate::statuses();

        return view('admin.signal_performance.certificate_all', compact(
            'users',
            'certificates',
            'levels',
            'types',
            'statuses'
        ));
    }

    public function providerindex()
    {
        $authUser = auth()->user();
        abort_unless($authUser && in_array((int) $authUser->role_id, SignalProviderCertificate::eligibleRoleIds(), true), 403);

        $certificates = SignalProviderCertificate::with(['user', 'issuer'])
            ->where('user_id', $authUser->id)
            ->where('status', SignalProviderCertificate::STATUS_PUBLISHED)
            ->latest('published_at')
            ->get();

        return view('admin.signal_performance.provider_certificate_all', compact('certificates'));
    }

    public function create()
    {
        $this->ensureAdmin();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Certificates', 'url' => route('certificate.index')],
            ['label' => 'Generate Certificate', 'url' => route('certificate.create')],
        ];

        $users = $this->eligibleUsers();
        $certificates = SignalProviderCertificate::with(['user', 'issuer'])
            ->latest('updated_at')
            ->take(20)
            ->get();
        $levels = SignalProviderCertificate::levels();
        $types = SignalProviderCertificate::certificateTypes();
        $statuses = [
            SignalProviderCertificate::STATUS_APPROVED => 'Approve only',
            SignalProviderCertificate::STATUS_PUBLISHED => 'Approve and publish',
            SignalProviderCertificate::STATUS_DRAFT => 'Save as draft',
        ];

        return view('admin.signal_performance.certificate_add', compact(
            'breadcrumbData',
            'users',
            'certificates',
            'levels',
            'types',
            'statuses'
        ));
    }

    public function upload(Request $request)
    {
        $this->ensureAdmin();
        $this->validateCurrentPassword($request);

        $data = $this->validatedCertificateData($request);
        $status = $data['status'];
        $now = now();

        $certificate = SignalProviderCertificate::create([
            'user_id' => $data['user_id'],
            'recipient_name' => $data['recipient_name'],
            'level' => $data['level'],
            'certificate_title' => 'HC Traders Club Certificate of Trading Completion',
            'certificate_type' => $data['certificate_type'],
            'status' => $status,
            'discipline_summary' => $data['discipline_summary'] ?? null,
            'strategy_summary' => $data['strategy_summary'] ?? null,
            'founder_name' => $data['founder_name'],
            'founder_title' => 'HC Founder',
            'issued_by' => auth()->id(),
            'eligible_at' => $now,
            'approved_at' => in_array($status, [
                SignalProviderCertificate::STATUS_APPROVED,
                SignalProviderCertificate::STATUS_PUBLISHED,
            ], true) ? $now : null,
            'published_at' => $status === SignalProviderCertificate::STATUS_PUBLISHED ? $now : null,
            'verification_code' => $this->uniqueVerificationCode(),
        ]);

        $certificate->update([
            'certificate_path' => $this->generateCertificateImage($certificate),
        ]);

        return redirect()
            ->route('certificate.index')
            ->with('success', 'Trading certificate generated successfully.');
    }

    public function addCertificate(Request $request)
    {
        return $this->upload($request);
    }

    public function approve(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureAdmin();
        $this->validateCurrentPassword($request);

        $certificate->update([
            'status' => SignalProviderCertificate::STATUS_APPROVED,
            'approved_at' => $certificate->approved_at ?: now(),
        ]);

        return back()->with('success', 'Certificate approved successfully.');
    }

    public function publish(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureAdmin();
        $this->validateCurrentPassword($request);

        $publishedAt = now();
        $certificate->fill([
            'status' => SignalProviderCertificate::STATUS_PUBLISHED,
            'approved_at' => $certificate->approved_at ?: $publishedAt,
            'published_at' => $publishedAt,
            'issued_by' => auth()->id(),
        ]);
        $certificate->save();

        $certificate->update([
            'certificate_path' => $this->generateCertificateImage($certificate),
        ]);

        return back()->with('success', 'Certificate published and regenerated with the publish date.');
    }

    public function regenerate(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureAdmin();
        $this->validateCurrentPassword($request);

        $certificate->update([
            'certificate_path' => $this->generateCertificateImage($certificate),
        ]);

        return back()->with('success', 'Certificate image refreshed with the latest professional layout.');
    }

    public function revoke(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureAdmin();
        $this->validateCurrentPassword($request);

        $certificate->update([
            'status' => SignalProviderCertificate::STATUS_REVOKED,
        ]);

        return back()->with('success', 'Certificate revoked successfully.');
    }

    public function view(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureCanAccessCertificate($certificate);
        $this->validateCurrentPassword($request);
        $this->ensureCertificateFileExists($certificate);

        $certificate->increment('view_count');

        $response = response()->file($this->certificateFullPath($certificate), [
            'Content-Type' => 'image/png',
        ]);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $this->certificateFileName($certificate)
        );

        return $response;
    }

    public function download(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureCanAccessCertificate($certificate);
        $this->validateCurrentPassword($request);
        $this->ensureCertificateFileExists($certificate);

        $certificate->increment('download_count');

        return response()->download(
            $this->certificateFullPath($certificate),
            $this->certificateFileName($certificate)
        );
    }

    public function destroy(Request $request, SignalProviderCertificate $certificate)
    {
        $this->ensureAdmin();
        $this->validateCurrentPassword($request);

        if ($certificate->certificate_path && File::exists($this->certificateFullPath($certificate))) {
            File::delete($this->certificateFullPath($certificate));
        }

        $certificate->delete();

        return back()->with('success', 'Certificate deleted successfully.');
    }

    private function validatedCertificateData(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'level' => ['required', 'string', Rule::in(array_keys(SignalProviderCertificate::levels()))],
            'certificate_type' => ['required', 'string', Rule::in(array_keys(SignalProviderCertificate::certificateTypes()))],
            'discipline_summary' => ['nullable', 'string', 'max:1200'],
            'strategy_summary' => ['nullable', 'string', 'max:1200'],
            'founder_name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', Rule::in([
                SignalProviderCertificate::STATUS_DRAFT,
                SignalProviderCertificate::STATUS_APPROVED,
                SignalProviderCertificate::STATUS_PUBLISHED,
            ])],
        ]);
    }

    private function generateCertificateImage(SignalProviderCertificate $certificate): string
    {
        $width = 1800;
        $height = 1275;
        $image = imagecreatetruecolor($width, $height);

        $black = $this->color($image, '#0B0C0C');
        $panel = $this->color($image, '#161615');
        $gold = $this->color($image, '#E8C164');
        $mutedGold = $this->color($image, '#A9843F');
        $white = $this->color($image, '#F7F1E2');
        $muted = $this->color($image, '#D8CAA7');

        imagefill($image, 0, 0, $black);
        imagefilledrectangle($image, 70, 70, $width - 70, $height - 70, $panel);
        imagesetthickness($image, 4);
        imagerectangle($image, 92, 92, $width - 92, $height - 92, $gold);
        imagesetthickness($image, 1);
        imagerectangle($image, 122, 122, $width - 122, $height - 122, $mutedGold);

        $this->drawCornerLines($image, $gold, $width, $height);
        $this->placeLogo($image);

        $regular = $this->font('regular');
        $bold = $this->font('bold');
        $signature = $this->font('signature');
        $recipientName = $this->certificateDisplayName($certificate);

        $this->centerText($image, 'HC TRADERS CLUB', 34, 285, $gold, $bold, 1000);
        $this->centerText($image, 'CERTIFICATE OF TRADING COMPLETION', 52, 380, $white, $bold, 1340);
        imageline($image, 620, 420, 1180, 420, $mutedGold);

        $this->centerText($image, 'This certificate is proudly presented to', 28, 492, $muted, $regular, 1000);
        $this->centerText($image, $recipientName, 78, 604, $gold, $bold, 1260);
        imageline($image, 480, 635, 1320, 635, $mutedGold);

        $body = 'in recognition of completing the HC Traders Club trading classes, demonstrating disciplined execution, passing the evaluation, and presenting a strategy aligned with professional trading standards.';
        $this->wrappedText($image, $body, 29, 365, 715, 1070, 44, $white, $regular);

        $publishedAt = $certificate->published_at ?: now();
        $dateText = 'Published on ' . $publishedAt->format('F d, Y');
        $this->centerText($image, $dateText, 26, 890, $muted, $regular, 900);
        $this->centerText($image, 'HC Traders Club professional trading credential', 21, 942, $mutedGold, $regular, 900);

        imageline($image, 310, 1040, 690, 1040, $mutedGold);
        imageline($image, 1110, 1040, 1490, 1040, $mutedGold);
        $this->centerTextAt($image, $recipientName, 34, 500, 1005, $gold, $bold, 380);
        $this->centerTextAt($image, 'Certified Member', 24, 500, 1087, $white, $bold, 380);
        $this->centerTextAt($image, 'HC Traders Club', 20, 500, 1132, $muted, $regular, 380);

        $this->centerTextAt($image, $certificate->founder_name, 46, 1300, 1005, $gold, $signature, 380);
        $this->centerTextAt($image, $certificate->founder_name, 24, 1300, 1087, $white, $bold, 380);
        $this->centerTextAt($image, $certificate->founder_title, 20, 1300, 1132, $muted, $regular, 380);

        $directory = storage_path('app/trading_certificates/generated');
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = 'certificate_' . $certificate->id . '_' . Str::slug($recipientName) . '.png';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $fileName;
        imagepng($image, $fullPath, 9);
        imagedestroy($image);

        return 'trading_certificates/generated/' . $fileName;
    }

    private function placeLogo($image): void
    {
        $logoPath = public_path('upload/certificates/assets/hc-logo.png');

        if (! File::exists($logoPath)) {
            return;
        }

        $logo = imagecreatefrompng($logoPath);
        imagesavealpha($logo, true);

        $targetWidth = 150;
        $targetHeight = 130;
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($logo), imagesy($logo));
        imagecopy($image, $resized, 825, 115, 0, 0, $targetWidth, $targetHeight);

        imagedestroy($resized);
        imagedestroy($logo);
    }

    private function certificateDisplayName(SignalProviderCertificate $certificate): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $certificate->recipient_display_name ?: 'Certified Member'));

        if ($name === '') {
            return 'Certified Member';
        }

        if ($name === strtolower($name) || $name === strtoupper($name)) {
            return Str::of(strtolower($name))->title()->toString();
        }

        return $name;
    }

    private function drawCornerLines($image, int $gold, int $width, int $height): void
    {
        imagesetthickness($image, 3);

        foreach ([[150, 150], [$width - 150, 150], [150, $height - 150], [$width - 150, $height - 150]] as [$x, $y]) {
            $xDirection = $x < $width / 2 ? 1 : -1;
            $yDirection = $y < $height / 2 ? 1 : -1;
            imageline($image, $x, $y, $x + ($xDirection * 170), $y, $gold);
            imageline($image, $x, $y, $x, $y + ($yDirection * 170), $gold);
        }

        imagesetthickness($image, 1);
    }

    private function wrappedText($image, string $text, int $size, int $x, int $y, int $maxWidth, int $lineHeight, int $color, string $font): void
    {
        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);
            $box = imagettfbbox($size, 0, $font, $candidate);
            $candidateWidth = abs($box[2] - $box[0]);

            if ($candidateWidth > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        foreach ($lines as $index => $lineText) {
            $this->centerTextAt($image, $lineText, $size, $x + ($maxWidth / 2), $y + ($index * $lineHeight), $color, $font, $maxWidth);
        }
    }

    private function centerText($image, string $text, int $size, int $y, int $color, string $font, int $maxWidth): void
    {
        $this->centerTextAt($image, $text, $size, 900, $y, $color, $font, $maxWidth);
    }

    private function centerTextAt($image, string $text, int $size, float $centerX, int $y, int $color, string $font, int $maxWidth): void
    {
        $fitSize = $size;

        do {
            $box = imagettfbbox($fitSize, 0, $font, $text);
            $textWidth = abs($box[2] - $box[0]);
            $fitSize -= 2;
        } while ($textWidth > $maxWidth && $fitSize > 14);

        $box = imagettfbbox($fitSize, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        $x = (int) round($centerX - ($textWidth / 2));

        imagettftext($image, $fitSize, 0, $x, $y, $color, $font, $text);
    }

    private function color($image, string $hex): int
    {
        $hex = ltrim($hex, '#');

        return imagecolorallocate(
            $image,
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    private function font(string $style): string
    {
        $fonts = [
            'regular' => ['C:\Windows\Fonts\arial.ttf', 'C:\Windows\Fonts\bahnschrift.ttf'],
            'bold' => ['C:\Windows\Fonts\arialbd.ttf', 'C:\Windows\Fonts\bahnschrift.ttf'],
            'signature' => ['C:\Windows\Fonts\segoepr.ttf', 'C:\Windows\Fonts\FRSCRIPT.TTF', 'C:\Windows\Fonts\BRUSHSCI.TTF'],
        ];

        foreach ($fonts[$style] ?? $fonts['regular'] as $font) {
            if (File::exists($font)) {
                return $font;
            }
        }

        return 'C:\Windows\Fonts\arial.ttf';
    }

    private function validateCurrentPassword(Request $request): void
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }
    }

    private function ensureCanAccessCertificate(SignalProviderCertificate $certificate): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if (! $this->isAdmin($user)) {
            abort_unless((int) $certificate->user_id === (int) $user->id, 403);
            abort_unless($certificate->isDownloadable(), 404);
        }
    }

    private function ensureCertificateFileExists(SignalProviderCertificate $certificate): void
    {
        abort_unless($certificate->certificate_path && File::exists($this->certificateFullPath($certificate)), 404);
    }

    private function certificateFullPath(SignalProviderCertificate $certificate): string
    {
        $storagePath = storage_path('app/' . $certificate->certificate_path);

        if (File::exists($storagePath)) {
            return $storagePath;
        }

        return public_path($certificate->certificate_path);
    }

    private function certificateFileName(SignalProviderCertificate $certificate): string
    {
        return Str::slug($certificate->recipient_display_name ?: 'hc-traders-club') . '-certificate.png';
    }

    private function uniqueVerificationCode(): string
    {
        do {
            $code = 'HC-' . now()->format('Y') . '-' . Str::upper(Str::random(8));
        } while (SignalProviderCertificate::where('verification_code', $code)->exists());

        return $code;
    }

    private function eligibleUsers()
    {
        return User::whereIn('role_id', SignalProviderCertificate::eligibleRoleIds())
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && $this->isAdmin(auth()->user()), 403);
    }

    private function isAdmin(User $user): bool
    {
        return in_array((int) $user->role_id, [1, 2], true);
    }
}
