<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketAnalysis extends Model
{
    use HasFactory;

    protected $table = 'market_analyses';

    protected $fillable = [
        'community_id',
        'title',
        'trend_strength',
        'market',
        'analysis_date',
        'market_overview',
        'trend_structure',
        'key_zones',
        'analyst_view',
        'strategy',
        'chart_signals',
        'rsi_level',
        'order_block',
        'entry_zones_description',
        'outlook_image',
        'discord_sent',
        'trading_plan',
        'Outlook_Code',
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'discord_sent' => 'boolean',
    ];

    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function discordMessages()
    {
        return $this->hasMany(MarketOutlookDiscord::class, 'outlook_id');
    }
}
