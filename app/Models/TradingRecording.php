<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'video_url',
        'download_url',
        'source_name',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_note',
        'uploaded_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(TradingRecordingMaterial::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getEffectiveDownloadUrlAttribute(): string
    {
        return $this->download_url ?: $this->video_url;
    }

    public function getEmbedUrlAttribute(): string
    {
        return $this->buildEmbedUrl($this->video_url);
    }

    public function getIsDirectVideoAttribute(): bool
    {
        $path = parse_url($this->video_url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'webm', 'ogg', 'mov', 'm4v'], true);
    }

    private function buildEmbedUrl(string $url): string
    {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);

        if (str_contains($host, 'youtube.com')) {
            $videoId = $query['v'] ?? null;

            if (! $videoId && preg_match('#/(embed|shorts|live)/([^/?]+)#', $path, $matches)) {
                $videoId = $matches[2];
            }

            if ($videoId) {
                return 'https://www.youtube.com/embed/' . $videoId;
            }
        }

        if (str_contains($host, 'youtu.be')) {
            $videoId = trim($path, '/');

            if ($videoId !== '') {
                return 'https://www.youtube.com/embed/' . $videoId;
            }
        }

        if (str_contains($host, 'vimeo.com') && preg_match('#/(\d+)#', $path, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        if (str_contains($host, 'drive.google.com')) {
            if (preg_match('#/file/d/([^/]+)#', $path, $matches)) {
                return 'https://drive.google.com/file/d/' . $matches[1] . '/preview';
            }

            if (! empty($query['id'])) {
                return 'https://drive.google.com/file/d/' . $query['id'] . '/preview';
            }
        }

        return $url;
    }
}
