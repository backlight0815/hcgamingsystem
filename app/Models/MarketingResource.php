<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketingResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'title',
        'description',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'status',
        'download_count',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'status' => 'boolean',
        'download_count' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeLabelAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    public function getFileExtensionAttribute(): string
    {
        return Str::upper(pathinfo($this->original_filename, PATHINFO_EXTENSION) ?: 'FILE');
    }
}
