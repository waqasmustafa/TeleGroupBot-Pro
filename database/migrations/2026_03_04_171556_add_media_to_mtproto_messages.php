<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMediaToMtprotoMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mtproto_messages', function (Blueprint $table) {
            $table->string('media_path')->after('message')->nullable();
            $table->string('media_type')->after('media_path')->nullable();
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
            $table->dropColumn(['media_path', 'media_type']);
        });
    }
}
