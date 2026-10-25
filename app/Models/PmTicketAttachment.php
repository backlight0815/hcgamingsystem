<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmTicketAttachment extends Model
{
    use SoftDeletes;

    protected $table = 'pm_ticket_attachments';

    protected $guarded = ['id'];
    protected $casts = ['removed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(fn (self $file) => \App\Services\PmsTicketActivity::record($file->issue_id, 'attachments', null, null, null, ['attachment_id' => $file->id, 'filename' => $file->original_name, 'size_bytes' => $file->size_bytes, 'mime_type' => $file->mime_type, 'action' => 'added']));
        static::deleted(fn (self $file) => \App\Services\PmsTicketActivity::record($file->issue_id, 'attachments', null, null, null, ['attachment_id' => $file->id, 'filename' => $file->original_name, 'action' => 'removed']));
    }

    public function issue()
    {
    return $this->belongsTo(PmWorkItem::class, 'issue_id');
    }

    public function comment()
    {
    return $this->belongsTo(PmTicketComment::class, 'comment_id');
    }

    public function uploader()
    {
    return $this->belongsTo(User::class, 'uploaded_by');
    }
}
