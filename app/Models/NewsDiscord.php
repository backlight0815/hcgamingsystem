<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsDiscord extends Model
{
    use HasFactory;

    protected $table = 'news_discord';

    protected $fillable = [
        'community_id',
        'news_id',
        'message_id',
        'channel_id',
    ];

    /**
     * Relation to News
     */
    public function news()
    {
        return $this->belongsTo(News::class, 'news_id');
    }

    /**
     * Relation to Community
     */
    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id');
    }
}
