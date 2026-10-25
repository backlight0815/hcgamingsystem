<?php

namespace Database\Seeders;

use App\Models\FinalExamAttemptQuestion;
use App\Models\FinalExamQuestion;
use App\Services\FinalExamQuestionBankFactory;
use Illuminate\Database\Seeder;

class FinalExamQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = app(FinalExamQuestionBankFactory::class)->questions();
        $questionTexts = collect($questions)->pluck('question')->all();

        if (! FinalExamAttemptQuestion::exists()) {
            FinalExamQuestion::query()->delete();
        } else {
            FinalExamQuestion::whereNotIn('question', $questionTexts)->update([
                'status' => FinalExamQuestion::STATUS_INACTIVE,
            ]);
        }

        foreach ($questions as $question) {
            FinalExamQuestion::updateOrCreate(
                ['question' => $question['question']],
                $question
            );
        }
    }
}
