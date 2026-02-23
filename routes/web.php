<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Home;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\Landing;
use App\Http\Controllers\Member;
use App\Http\Controllers\Subscription;
use App\Http\Controllers\UpdateSystem;
$auth_or_guest =  env('APP_ENV')=='local' ? 'guest' : 'auth';
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/dashboard/user', [Dashboard::class,'index'])->middleware(['auth'])->name('dashboard-user');
Route::post('/dashboard/dashboard-change-data', [Dashboard::class,'dashboard_change_data'])->middleware(['auth'])->name('dashboard-change-data');

if(!file_exists(public_path("install.txt"))) {
    Route::get('/', function () {
        return redirect()->route('login');
    });
    Route::get('/dashboard', [Dashboard::class,'index'])->middleware(['auth'])->name('dashboard');
}
else {
    Route::get('/', [Landing::class,'install'])->name('home');
    Route::get('/dashboard', [Landing::class,'install'])->middleware(['auth'])->name('dashboard');
}

Route::get('usage-log',[Member::class,'usage_log'])->middleware(['auth'])->name('usage-log');
Route::post('notification/mark-seen',[Member::class,'notification_mark_seen'])->middleware(['auth'])->name('notification-mark-seen');

Route::group(['middleware' => ['auth']], function () {
    Route::get('account',[Member::class,'account'])->name('account');
    Route::post('account',[Member::class,'account_action'])->name('account-action');
    Route::get('transaction-log',[Member::class,'transaction_log'])->name('transaction-log');
    Route::get('settings/general',[Member::class,'general_settings'])->name('general-settings');
    Route::post('settings/general',[Member::class,'general_settings_action'])->name('general-settings-action');
});

Route::get('user/list',[Subscription::class,'list_user'])->middleware(['auth'])->name('list-user');
Route::post('user/list',[Subscription::class,'list_user_data'])->middleware(['auth'])->name('list-user-data');
Route::get('user/create',[Subscription::class,'create_user'])->middleware(['auth'])->name('create-user');
Route::post('user/create',[Subscription::class,'save_user'])->middleware(['auth','XssSanitizer'])->name('create-user-action');
Route::post('user/update-status',[Subscription::class,'update_user_status'])->middleware(['auth'])->name('update-user-status');
Route::get('user/update/{id}',[Subscription::class,'update_user'])->middleware(['auth'])->name('update-user');
Route::post('user/update',[Subscription::class,'save_user'])->middleware(['auth','XssSanitizer'])->name('update-user-action');
Route::post('user/delete',[Subscription::class,'delete_user'])->middleware(['auth'])->name('delete-user');
Route::post('user/send-email',[Subscription::class,'user_send_email'])->middleware(['auth'])->name('user-send-email');

Route::get('restricted', [Home::class,'restricted_access'])->name('restricted-access');
Route::get('credential/check', [Home::class,'credential_check'])->middleware(['auth'])->name('credential-check');
Route::post('credential/check', [Home::class,'credential_check_action'])->middleware(['auth'])->name('credential-check-action');
Route::get('check/update', [UpdateSystem::class,'update_list'])->middleware(['auth'])->name('update-list');
Route::post('initiate/update', [UpdateSystem::class,'initialize_update'])->middleware(['auth'])->name('update-initiate');

Route::get('/storage/{extra}', function ($extra) {
return redirect("/public/storage/$extra");
})->where('extra', '.*');

require __DIR__.'/api.php';
require __DIR__.'/auth.php';
// require __DIR__.'/bot.php';
require __DIR__.'/landing.php';
require __DIR__.'/cron.php';
// require __DIR__.'/docs.php';
// require __DIR__.'/agency.php';
require __DIR__.'/mtproto.php';
// if(check_build_version() == 'double'){
//     require __DIR__.'/webhook.php';
// }





