<?php

namespace App\Imports;

use App\Models\TradingPair;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class TradingPairsImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    protected $allowedHeadings = ['symbol', 'description'];

    public function model(array $row)
    {
        return new TradingPair([
            'symbol' => $row['symbol'],
            'description' => $row['description'],
        ]);
    }

    public function rules(): array
    {
        return [
            '*.symbol' => 'required|string|max:50',
            '*.description' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.symbol.required' => 'Each row must have a symbol.',
            '*.description.required' => 'Each row must have a description.',
        ];
    }

    /**
     * Extra validation: ensure only allowed columns exist (case-sensitive).
     */
    public function prepareForValidation($data, $index)
    {
        // Only check the first row (headings)
        if ($index === 0) {
            $columns = array_keys($data);

            // Ensure no extra columns exist
            foreach ($columns as $column) {
                if (!in_array($column, $this->allowedHeadings, true)) {
                    throw ValidationException::withMessages([
                        'file' => "Invalid column detected: {$column}. Allowed columns: " . implode(', ', $this->allowedHeadings),
                    ]);
                }
            }
        }

        return $data;
    }
}
