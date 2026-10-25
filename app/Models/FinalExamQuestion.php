<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinalExamQuestion extends Model
{
    use HasFactory;

    public const CATEGORY_FOUNDATION = 'Foundation and Knowledge';

    public const CATEGORY_TECHNIQUE = 'Technique and Analysis Skills';

    public const CATEGORY_PSYCHOLOGY = 'Psychology';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_TEXT_MCQ = 'text_mcq';

    public const TYPE_CHART_MCQ = 'chart_mcq';

    public const TYPE_SCENARIO_MCQ = 'scenario_mcq';

    public const TYPE_CHART_SCENARIO_MCQ = 'chart_scenario_mcq';

    public const DIFFICULTY_BEGINNER = 'beginner';

    public const DIFFICULTY_INTERMEDIATE = 'intermediate';

    public const DIFFICULTY_ADVANCED = 'advanced';

    public const CATEGORIES = [
        self::CATEGORY_FOUNDATION,
        self::CATEGORY_TECHNIQUE,
        self::CATEGORY_PSYCHOLOGY,
    ];

    public const DIFFICULTIES = [
        self::DIFFICULTY_BEGINNER => 'Beginner',
        self::DIFFICULTY_INTERMEDIATE => 'Intermediate',
        self::DIFFICULTY_ADVANCED => 'Advanced',
    ];

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    public const QUESTION_TYPES = [
        self::TYPE_TEXT_MCQ => 'Text MCQ',
        self::TYPE_CHART_MCQ => 'Chart MCQ',
        self::TYPE_SCENARIO_MCQ => 'Scenario MCQ',
        self::TYPE_CHART_SCENARIO_MCQ => 'Chart Scenario MCQ',
    ];

    protected $fillable = [
        'category',
        'subcategory',
        'question_type',
        'question',
        'scenario_text',
        'chart_image_path',
        'chart_data_json',
        'chart_template_key',
        'chart_timeframe',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'explanation',
        'difficulty',
        'status',
    ];

    protected $casts = [
        'chart_data_json' => 'array',
    ];

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(FinalExamAttemptQuestion::class, 'question_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function optionText(?string $letter): ?string
    {
        $letter = strtoupper((string) $letter);

        return match ($letter) {
            'A' => $this->option_a,
            'B' => $this->option_b,
            'C' => $this->option_c,
            'D' => $this->option_d,
            default => null,
        };
    }

    public function correctOptionText(): ?string
    {
        return $this->optionText($this->correct_answer);
    }

    public function difficultyLabel(): string
    {
        return self::DIFFICULTIES[$this->difficulty] ?? ucfirst((string) $this->difficulty);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function questionTypeLabel(): string
    {
        return self::QUESTION_TYPES[$this->question_type] ?? ucfirst(str_replace('_', ' ', (string) $this->question_type));
    }

    public function isChartQuestion(): bool
    {
        return in_array($this->question_type, [self::TYPE_CHART_MCQ, self::TYPE_CHART_SCENARIO_MCQ], true);
    }

    public function isScenarioQuestion(): bool
    {
        return in_array($this->question_type, [self::TYPE_SCENARIO_MCQ, self::TYPE_CHART_SCENARIO_MCQ], true);
    }
}
