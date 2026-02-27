<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MTProtoController;

Route::group(['middleware' => ['auth']], function () {
    // Accounts
    Route::get('mtproto/accounts', [MTProtoController::class, 'index'])->name('mtproto.accounts.index');
    Route::get('mtproto/accounts/create', [MTProtoController::class, 'addAccount'])->name('mtproto.accounts.create');
    Route::post('mtproto/accounts/store', [MTProtoController::class, 'storeAccount'])->name('mtproto.accounts.store');
    Route::delete('mtproto/accounts/{id}', [MTProtoController::class, 'deleteAccount'])->name('mtproto.accounts.delete');
    Route::get('mtproto/accounts/verify-otp', [MTProtoController::class, 'showVerifyOtp'])->name('mtproto.verify.otp');
    Route::post('mtproto/accounts/verify-otp', [MTProtoController::class, 'verifyOtp'])->name('mtproto.verify.otp.submit');

    // Contacts
    Route::get('mtproto/contacts', [MTProtoController::class, 'contactsIndex'])->name('mtproto.contacts.index');
    Route::get('mtproto/contacts/{id}', [MTProtoController::class, 'viewContactList'])->name('mtproto.contacts.view');
    Route::delete('mtproto/contacts/{id}', [MTProtoController::class, 'deleteContactList'])->name('mtproto.contacts.delete');
    Route::post('mtproto/contacts/import', [MTProtoController::class, 'importContacts'])->name('mtproto.contacts.import');

    // Templates
    Route::get('mtproto/templates', [MTProtoController::class, 'templatesIndex'])->name('mtproto.templates.index');
    Route::post('mtproto/templates/store', [MTProtoController::class, 'storeTemplate'])->name('mtproto.templates.store');
    Route::delete('mtproto/templates/{id}', [MTProtoController::class, 'deleteTemplate'])->name('mtproto.templates.delete');

    // Campaigns
    Route::get('mtproto/campaigns', [MTProtoController::class, 'campaignsIndex'])->name('mtproto.campaigns.index');
    Route::get('mtproto/campaigns/{id}/logs', [MTProtoController::class, 'campaignLogs'])->name('mtproto.campaigns.logs');
    Route::post('mtproto/campaigns/store', [MTProtoController::class, 'storeCampaign'])->name('mtproto.campaigns.store');
    Route::delete('mtproto/campaigns/{id}', [MTProtoController::class, 'deleteCampaign'])->name('mtproto.campaigns.delete');

    // Inbox
    Route::get('mtproto/inbox', [MTProtoController::class, 'inbox'])->name('mtproto.inbox');
    Route::get('mtproto/inbox/messages/{contact_id}', [MTProtoController::class, 'getChatMessages'])->name('mtproto.inbox.messages');
    Route::post('mtproto/inbox/send', [MTProtoController::class, 'sendReply'])->name('mtproto.inbox.send');
    Route::post('mtproto/inbox/delete', [MTProtoController::class, 'deleteMessage'])->name('mtproto.inbox.delete');
    Route::post('mtproto/inbox/read', [MTProtoController::class, 'markAsRead'])->name('mtproto.inbox.read');
});
