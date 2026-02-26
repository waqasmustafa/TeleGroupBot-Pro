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

echo "\n--- ACCOUNTS DETAIL ---\n";
foreach (MtprotoAccount::all() as $a) {
    echo "ID: {$a->id} | Phone: {$a->phone} | Status: '{$a->status}' | UserID: {$a->user_id}\n";
}

echo "\n--- TEMPLATES DETAIL ---\n";
foreach (MtprotoTemplate::all() as $t) {
    echo "ID: {$t->id} | Name: {$t->template_name} | UserID: {$t->user_id}\n";
}
