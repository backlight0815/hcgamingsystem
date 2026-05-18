<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketOutlookDiscord extends Model
{
    use HasFactory;

    // Specify the table if it doesn't follow Laravel convention
    protected $table = 'market_outlook_discord';

    // Fields that can be mass assigned
    protected $fillable = [
        'outlook_id',   // foreign key to market_analyses
        'community_id', // foreign key to communities
        'message_id',   // Discord message ID
        'channel_id',   // Discord channel ID
    ];

    /**
     * Relationship to Market Analysis / Outlook
     */
    public function outlook()
    {
        return $this->belongsTo(MarketAnalysis::class, 'outlook_id');
    }

    /**
     * Relationship to Community
     */
    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
