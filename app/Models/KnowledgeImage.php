<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_centre_id',
        'community_id',
        'image_path',
        'message_id',
        'channel_id',
    ];

    // Relationship to KnowledgeCentre
    public function knowledgeCentre()
    {
        return $this->belongsTo(KnowledgeCentre::class);
    }

    // Relationship to Community
    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
