<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PmTicketComment extends Model
{
    use SoftDeletes;

    protected $table = 'pm_ticket_comments';

    protected $guarded = ['id'];

    protected $casts = ['edited_at' => 'datetime'];

    protected static function booted(): void
    {
        static::created(fn (self $comment) => \App\Services\PmsTicketActivity::record($comment->issue_id, 'comments', null, null, null, ['comment_id' => $comment->id, 'action' => 'added']));
        static::updated(fn (self $comment) => $comment->wasChanged('body') ? \App\Services\PmsTicketActivity::record($comment->issue_id, 'comments', null, null, null, ['comment_id' => $comment->id, 'action' => 'edited']) : null);
        static::deleted(fn (self $comment) => \App\Services\PmsTicketActivity::record($comment->issue_id, 'comments', null, null, null, ['comment_id' => $comment->id, 'action' => 'removed']));
    }

    public function issue()
    {
    return $this->belongsTo(PmWorkItem::class, 'issue_id');
    }

    public function author()
    {
    return $this->belongsTo(User::class, 'author_id');
    }

    public function attachments()
    {
    return $this->hasMany(PmTicketAttachment::class, 'comment_id');
    }

    public function replies()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getSafeHtmlAttribute(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
