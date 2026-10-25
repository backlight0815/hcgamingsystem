<?php

namespace App\Services;

use App\Models\PmTicketAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class PmsAttachmentThumbnail
{
    private const IMAGE_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif',
    ];

    public function path(PmTicketAttachment $attachment): ?string
    {
        if ((self::IMAGE_TYPES[strtolower($attachment->extension)] ?? null) !== $attachment->mime_type) return null;
        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->storage_path)) return null;
        $target = $this->target($attachment);
        if ($disk->exists($target)) return $target;

        $source = $disk->path($attachment->storage_path);
        $dimensions = @getimagesize($source);
        if (! $dimensions || (int) $dimensions[0] * (int) $dimensions[1] > 40000000) return null;
        try {
            $image = Image::make($source)->orientate()->resize(560, 360, function ($constraint): void {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $disk->put($target, (string) $image->encode('webp', 80));
            return $target;
        } catch (\Throwable $exception) {
            Log::warning('PMS image thumbnail could not be generated.', ['attachment_id' => $attachment->id]);
            return null;
        }
    }

    public function forget(PmTicketAttachment $attachment): void
    {
        Storage::disk('local')->delete($this->target($attachment));
    }

    private function target(PmTicketAttachment $attachment): string
    {
        return 'pms/thumbnails/'.$attachment->id.'-'.$attachment->sha256.'.webp';
    }
}
