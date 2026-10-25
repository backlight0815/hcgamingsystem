<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinalExamSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'is_active',
        'passing_percentage',
        'allow_immediate_retake',
        'retake_cooldown_hours',
        'show_review_after_submission',
        'existing_users_passed_by_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'passing_percentage' => 'integer',
        'allow_immediate_retake' => 'boolean',
        'retake_cooldown_hours' => 'integer',
        'show_review_after_submission' => 'boolean',
        'existing_users_passed_by_default' => 'boolean',
    ];

    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'is_active' => true,
                'passing_percentage' => 60,
                'allow_immediate_retake' => true,
                'retake_cooldown_hours' => 24,
                'show_review_after_submission' => true,
                'existing_users_passed_by_default' => true,
            ]
        );
    }
}
