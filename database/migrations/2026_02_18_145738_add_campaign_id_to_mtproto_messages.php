<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCampaignIdToMtprotoMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mtproto_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->nullable()->after('account_id');
            $table->enum('status', ['success', 'failed'])->default('success')->after('message');
            $table->string('error')->nullable()->after('status');

            $table->foreign('campaign_id')->references('id')->on('mtproto_campaigns')->onDelete('cascade');
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
            $table->dropForeign(['campaign_id']);
            $table->dropColumn(['campaign_id', 'status', 'error']);
        });
    }
}
