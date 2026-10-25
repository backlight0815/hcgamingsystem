<?php

use App\Support\PmsTicketReference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->string('reference', 40)->nullable()->unique();
        });

        $reserved = [];
        DB::table('pm_work_items')->orderBy('id')->chunkById(500, function ($tickets) use (&$reserved): void {
            foreach ($tickets as $ticket) {
                $key = strtolower($ticket->issue_key);
                if (preg_match('/^sf(req|enh|prd|qat|tsk)([0-9]{4,})$/', $key, $match)) {
                    $reserved[$match[1]][(int) $match[2]] = true;
                }
            }
        });
        $numbers = [];
        DB::table('pm_work_items')->orderBy('id')->chunkById(500, function ($tickets) use (&$numbers, $reserved): void {
            foreach ($tickets as $ticket) {
                $category = PmsTicketReference::category($ticket->classification_key, $ticket->type);
                $prefix = strtolower($category);
                if (preg_match('/^sf(req|enh|prd|qat|tsk)([0-9]{4,})$/', strtolower($ticket->issue_key), $match)) {
                    $reference = strtolower($ticket->issue_key);
                } else {
                    $numbers[$category] = ($numbers[$category] ?? 0) + 1;
                    while (isset($reserved[$prefix][$numbers[$category]])) $numbers[$category]++;
                    $reference = PmsTicketReference::format($category, $numbers[$category]);
                }
                DB::table('pm_work_items')->where('id', $ticket->id)->update(['reference' => $reference]);
            }
        });
        foreach ($reserved as $prefix => $used) {
            $category = strtoupper($prefix);
            $numbers[$category] = max($numbers[$category] ?? 0, max(array_keys($used)));
        }
        foreach ($numbers as $category => $count) {
            DB::table('pm_ticket_sequences')->where('prefix', $category)
                ->where('next_number', '<=', $count)->update(['next_number' => $count + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('pm_work_items', fn (Blueprint $table) => $table->dropColumn('reference'));
    }
};
