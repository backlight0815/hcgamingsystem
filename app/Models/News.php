<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use HasFactory;

    // Table name (optional if table name is 'news')
    protected $table = 'news';

    // Fields that can be mass assigned
    protected $fillable = [
        'content',
        'impact',
        'news_date',
        'image',
        'community_id', // added foreign key
    ];

    // Cast fields
    protected $casts = [
        'impact' => 'integer',
        'news_date' => 'date',
    ];

    /**
     * Relation to NewsDiscord
     */
    public function discordMessages()
    {
        return $this->hasMany(NewsDiscord::class, 'news_id');
    }

    /**
     * Relation to Community
     */
    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id');
    }
}
