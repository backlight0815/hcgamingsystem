<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Community extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'community_tag', 
        'discord_webhook',
        'status',
        'discord_webhook_signal',
        'discord_webhook_outlook',
        'discord_webhook_knowledge',
        'discord_webhook_news', // for News
        'category',
        'discord_webhook_weeklys_signal',
        'discord_everyone_enabled'
    ];

    /**
     * Relationship: A Community has many TP Settings
     */
    public function tpSettings()
    {
        return $this->hasMany(CommunityTPSetting::class, 'community_id', 'id');
    }

    /**
     * Relationship: A Community has many Market Analyses
     */
    public function marketAnalyses()
    {
        return $this->hasMany(MarketAnalysis::class, 'community_id', 'id');
    }

    /**
     * Relationship: A Community has many Knowledge Centre items
     */
    public function knowledgeCentres()
    {
        return $this->hasMany(KnowledgeCentre::class, 'community_id', 'id');
    }

    /**
     * Relationship: A Community has many News
     */
    public function news()
    {
        return $this->hasMany(News::class, 'community_id', 'id');
    }

    /**
     * Helper: Get Discord webhook for news
     */
    public function newsDiscordWebhook()
    {
        return $this->discord_webhook_news;
    }

    public function tradingSignalDiscords()
    {
        return $this->hasMany(TradingSignalDiscord::class, 'community_id');
    }

    public function documents()
    {
        return $this->hasMany(CommunityDocument::class, 'community_id', 'id');
    }
}
