<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmTestCaseReview extends Model
{
    protected $table = 'pm_test_case_reviews';
    protected $guarded = ['id'];
    protected $casts = ['reviewed_at' => 'datetime'];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
