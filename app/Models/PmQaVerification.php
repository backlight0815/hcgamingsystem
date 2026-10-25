<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmQaVerification extends Model
{
    protected $table = 'pm_qa_verifications';

    protected $guarded = ['id'];

    protected $casts = ['tested_at' => 'datetime'];

    public function issue()
    {
    return $this->belongsTo(PmWorkItem::class, 'issue_id');
    }

    public function tester()
    {
    return $this->belongsTo(User::class, 'tested_by');
    }
}
