<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Widen only: existing values and IDs remain unchanged. The mobile
        // journal stores FX prices and quantities beyond two decimal places.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE trading_journals '
                .'MODIFY entry_price DECIMAL(18,8) NOT NULL, '
                .'MODIFY exit_price DECIMAL(18,8) NULL, '
                .'MODIFY lot_size DECIMAL(18,8) NOT NULL, '
                .'MODIFY pips DECIMAL(18,5) NULL');
        }
    }

    public function down(): void
    {
        // Narrowing after eight-decimal data has been stored would lose data.
        // Restore the prior application code while retaining widened columns.
    }
};
