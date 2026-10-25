<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PmsTicketReference
{
    public static function category(?string $classification, ?string $type = null): string
    {
        return match (strtoupper((string) $classification)) {
            'REQ', 'FTR' => 'REQ',
            'ENH', 'EHN' => 'ENH',
            'PRD', 'PDI' => 'PRD',
            'QAT' => 'QAT',
            'TSK' => 'TSK',
            default => match ($type) {
                'Enhancement' => 'ENH', 'Bug', 'Production Issue' => 'PRD',
                'Quality Assurance Ticket' => 'QAT', 'Task' => 'TSK',
                default => 'REQ',
            },
        };
    }

    public static function format(string $category, int $number): string
    {
        return 'sf'.strtolower($category).str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    // Called within the caller's transaction so the sequence lock covers creation.
    public static function next(string $category): string
    {
        $row = DB::table('pm_ticket_sequences')->where('prefix', $category)->lockForUpdate()->first();
        if (! $row) {
            throw new \RuntimeException("Ticket sequence {$category} is missing.");
        }
        DB::table('pm_ticket_sequences')->where('prefix', $category)->update(['next_number' => $row->next_number + 1]);

        return self::format($category, (int) $row->next_number);
    }
}
