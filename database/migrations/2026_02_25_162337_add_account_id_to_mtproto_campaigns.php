<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountIdToMtprotoCampaigns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mtproto_campaigns', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('user_id');
            // Adding a foreign key for data integrity (optional based on existing table structure, but recommended)
            // $table->foreign('account_id')->references('id')->on('mtproto_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mtproto_campaigns', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('account_id');
        });
    }
}
