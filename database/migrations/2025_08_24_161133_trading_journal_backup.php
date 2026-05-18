<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::create('trading_journals_backup', function (Blueprint $table) {
        $table->id();
        $table->decimal('capital', 10, 2)->nullable(); // to store deposit/balance updates

        $table->dateTime('trade_date');
        $table->string('pair');
        $table->unsignedTinyInteger('direction'); // 1 = Buy, 2 = Sell
        $table->decimal('entry_price', 10, 2);
        $table->decimal('exit_price', 10, 2);
        $table->decimal('lot_size', 10, 2);
        $table->decimal('pips', 10, 2);
        $table->decimal('profit_loss', 10, 2);
        $table->unsignedTinyInteger('result')->nullable(); // 1 = Win, 2 = Loss, 3 = BE
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_journals_backup');
    }
};
