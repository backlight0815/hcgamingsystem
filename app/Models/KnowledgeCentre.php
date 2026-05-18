<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeCentre extends Model
{
    use HasFactory;

       protected $fillable = [
        'title',
        'description',
        'file_path',
        'community_id',
        'status',
        'uploaded_by',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_note',
    ];

    protected $casts = [
        'status' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // KnowledgeCentre.php
public function community()
{
    return $this->belongsTo(Community::class, 'community_id', 'id');
}

public function uploader()
{
    return $this->belongsTo(User::class, 'uploaded_by');
}

public function approver()
{
    return $this->belongsTo(User::class, 'approved_by');
}

}
