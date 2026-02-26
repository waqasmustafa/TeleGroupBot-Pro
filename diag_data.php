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

echo "\n--- ORPHAN DATA (Wrong or Missing UID) ---\n";
$all_uids = User::pluck('id')->toArray();
echo "Accounts not in users: " . MtprotoAccount::whereNotIn('user_id', $all_uids)->count() . "\n";
echo "Templates not in users: " . MtprotoTemplate::whereNotIn('user_id', $all_uids)->count() . "\n";
