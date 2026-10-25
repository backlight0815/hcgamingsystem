<?php
// Run only against the isolated SQLite database created by journal_qa.py.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local') || config('database.default') !== 'sqlite'
    || !str_contains(config('database.connections.sqlite.database'), 'journal-repair-20261013')) {
    throw new RuntimeException('Refusing to seed outside the isolated journal QA database.');
}
$user = App\Models\User::factory()->create([
    'name' => 'Journal QA', 'username' => 'journalqa', 'email' => 'journal-qa@example.test',
    'password' => Illuminate\Support\Facades\Hash::make(getenv('JOURNAL_QA_PASSWORD')),
]);
$other = App\Models\User::factory()->create(['email' => 'other-qa@example.test']);
App\Models\Capital::create(['user_id' => $user->id, 'type' => 1, 'amount' => 10000, 'deposit_date' => '2026-10-01', 'notes' => 'QA starting capital']);
$record = App\Models\TradingJournal::create([
    'user_id' => $user->id, 'type' => 'trade', 'status' => 'closed',
    'open_date' => '2026-10-01 08:00:00', 'close_date' => '2026-10-01 09:00:00',
    'pair' => 'XAUUSD', 'direction' => 1, 'entry_price' => 2350.50,
    'exit_price' => 2365, 'lot_size' => 1, 'pips' => 14.5,
    'profit_loss' => 145, 'result' => 1, 'notes' => 'Website QA seed',
]);
file_put_contents(getenv('JOURNAL_QA_FIXTURE'), json_encode([
    'email' => $user->email, 'password' => getenv('JOURNAL_QA_PASSWORD'),
    'website_user_id' => $user->id, 'website_record_id' => $record->id,
    'token' => $user->createToken('isolated-journal-qa')->plainTextToken,
    'other_token' => $other->createToken('isolated-journal-qa')->plainTextToken,
]));
echo "Isolated QA users, capital and website journal persisted.\n";
