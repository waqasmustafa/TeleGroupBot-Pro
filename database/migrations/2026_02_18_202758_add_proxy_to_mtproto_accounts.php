<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProxyToMtprotoAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mtproto_accounts', function (Blueprint $table) {
            $table->string('proxy_host')->nullable()->after('session_path');
            $table->integer('proxy_port')->nullable()->after('proxy_host');
            $table->string('proxy_user')->nullable()->after('proxy_port');
            $table->string('proxy_pass')->nullable()->after('proxy_user');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mtproto_accounts', function (Blueprint $table) {
            $table->dropColumn(['proxy_host', 'proxy_port', 'proxy_user', 'proxy_pass']);
        });
    }
}
