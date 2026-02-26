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
    $is_admin = ($u->user_type == 'Admin');
    
    // Simulating Controller Queries
    $acc_q = MtprotoAccount::where('status', '1');
    if(!$is_admin) $acc_q->where('user_id', $u->id);
    
    $temp_q = MtprotoTemplate::query();
    if(!$is_admin) $temp_q->where('user_id', $u->id);
    
    $list_q = \App\Models\MtprotoContactList::query();
    if(!$is_admin) $list_q->where('user_id', $u->id);

    echo "  Accounts (Status=1): " . $acc_q->count() . "\n";
    echo "  Templates: " . $temp_q->count() . "\n";
    echo "  Lists: " . $list_q->count() . "\n";
}

echo "\n--- RAW ACCOUNT DATA (First) ---\n";
$first_acc = MtprotoAccount::first();
if ($first_acc) {
    print_r($first_acc->toArray());
    echo "Session File Exists: " . (file_exists($first_acc->session_path) ? "YES" : "NO") . "\n";
}

echo "\n--- LAST 10 ERRORS ---\n";
$errors = \App\Models\MtprotoMessage::where('status', 'failed')->latest()->limit(10)->get();
foreach($errors as $err) {
    echo "MsgID: {$err->id} | AccID: {$err->account_id} | Error: {$err->error}\n";
}
