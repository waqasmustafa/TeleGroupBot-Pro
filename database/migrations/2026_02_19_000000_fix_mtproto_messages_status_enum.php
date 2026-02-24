<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixMtprotoMessagesStatusEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add 'pending' and 'processing' to the status enum for messages
        // Also ensure 'error' exists (it was added in the previous migration but let's be safe)
        Schema::table('mtproto_messages', function (Blueprint $table) {
            $table->string('status')->default('success')->change(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mtproto_messages', function (Blueprint $table) {
            $table->enum('status', ['success', 'failed'])->default('success')->change();
        });
    }
}
