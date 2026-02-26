<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\MtprotoAccount;
use App\Models\MtprotoTemplate;
use App\Models\MtprotoContactList;

echo "--- USERS ---\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id} | Email: {$u->email} | Type: {$u->user_type}\n";
    echo "  Accounts: " . MtprotoAccount::where('user_id', $u->id)->count() . "\n";
    echo "  Templates: " . MtprotoTemplate::where('user_id', $u->id)->count() . "\n";
    echo "  Lists: " . MtprotoContactList::where('user_id', $u->id)->count() . "\n";
}

echo "\n--- RAW ACCOUNT DATA (First) ---\n";
$first_acc = MtprotoAccount::first();
if ($first_acc) print_r($first_acc->toArray());

echo "\n--- RAW TEMPLATE DATA (First) ---\n";
$first_temp = MtprotoTemplate::first();
if ($first_temp) print_r($first_temp->toArray());
