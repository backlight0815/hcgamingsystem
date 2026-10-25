<?php

use App\Models\FinalExamQuestion;
use App\Services\FinalExamQuestionBankFactory;
use App\Services\FinalExamService;

test('final exam question bank factory creates exactly 500 questions with requested distribution', function () {
    $questions = collect((new FinalExamQuestionBankFactory())->questions());

    expect($questions)->toHaveCount(500)
        ->and($questions->where('category', FinalExamQuestion::CATEGORY_FOUNDATION))->toHaveCount(150)
        ->and($questions->where('category', FinalExamQuestion::CATEGORY_TECHNIQUE))->toHaveCount(250)
        ->and($questions->where('category', FinalExamQuestion::CATEGORY_PSYCHOLOGY))->toHaveCount(100);
});

test('final exam question bank creates required chart question mix', function () {
    $questions = collect((new FinalExamQuestionBankFactory())->questions());

    expect($questions->where('category', FinalExamQuestion::CATEGORY_TECHNIQUE)->where('question_type', FinalExamQuestion::TYPE_TEXT_MCQ))->toHaveCount(150)
        ->and($questions->where('category', FinalExamQuestion::CATEGORY_TECHNIQUE)->where('question_type', FinalExamQuestion::TYPE_CHART_MCQ))->toHaveCount(100)
        ->and($questions->where('category', FinalExamQuestion::CATEGORY_PSYCHOLOGY)->where('question_type', FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ))->toHaveCount(100);

    $chartQuestions = $questions->whereIn('question_type', [
        FinalExamQuestion::TYPE_CHART_MCQ,
        FinalExamQuestion::TYPE_CHART_SCENARIO_MCQ,
    ]);

    expect($chartQuestions)->toHaveCount(200);

    foreach ($chartQuestions as $question) {
        expect($question['chart_data_json'])->toBeArray()
            ->and($question['chart_timeframe'])->not->toBeEmpty()
            ->and($question['chart_template_key'])->not->toBeEmpty();
    }
});

test('final exam question bank has no duplicate question text and valid answers', function () {
    $questions = collect((new FinalExamQuestionBankFactory())->questions());

    expect($questions->pluck('question')->duplicates())->toBeEmpty();

    foreach ($questions as $question) {
        expect($question['correct_answer'])->toBeIn(['A', 'B', 'C', 'D'])
            ->and($question['option_a'])->not->toBe('')
            ->and($question['option_b'])->not->toBe('')
            ->and($question['option_c'])->not->toBe('')
            ->and($question['option_d'])->not->toBe('')
            ->and($question['difficulty'])->toBeIn(array_keys(FinalExamQuestion::DIFFICULTIES))
            ->and($question['status'])->toBe(FinalExamQuestion::STATUS_ACTIVE);
    }
});

test('final exam structure is fifty questions with thirty required at sixty percent', function () {
    expect(array_sum(FinalExamService::CATEGORY_COUNTS))->toBe(50)
        ->and(FinalExamService::CATEGORY_COUNTS[FinalExamQuestion::CATEGORY_FOUNDATION])->toBe(10)
        ->and(FinalExamService::CATEGORY_COUNTS[FinalExamQuestion::CATEGORY_TECHNIQUE])->toBe(25)
        ->and(FinalExamService::CATEGORY_COUNTS[FinalExamQuestion::CATEGORY_PSYCHOLOGY])->toBe(15)
        ->and(FinalExamService::MIN_TECHNIQUE_CHART_QUESTIONS)->toBe(12)
        ->and(FinalExamService::MIN_PSYCHOLOGY_CHART_SCENARIO_QUESTIONS)->toBe(10)
        ->and((int) ceil(50 * 0.60))->toBe(30);
});
