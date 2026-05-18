<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KnowledgeCentreDiscord extends Model
{
    use HasFactory;

    protected $table = 'knowledge_centre_discord';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'community_id',
        'knowledge_centre_id',
        'message_id',
        'channel_id',
    ];

    /**
     * Relationship: Discord message belongs to a Community
     */


    /**
     * Relationship: Discord message belongs to Knowledge Centre
     */
    public function knowledgeCentre()
    {
        return $this->belongsTo(KnowledgeCentre::class, 'knowledge_centre_id', 'id');
    }
}
