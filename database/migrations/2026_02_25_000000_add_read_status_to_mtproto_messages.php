<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('mtproto_messages', function (Blueprint $table) {
            $table->bigInteger('telegram_message_id')->nullable()->after('id');
            $table->boolean('is_read')->default(false)->after('status');
        });
    }

    public function down()
    {
        Schema::table('mtproto_messages', function (Blueprint $table) {
            $table->dropColumn(['telegram_message_id', 'is_read']);
        });
    }
};
