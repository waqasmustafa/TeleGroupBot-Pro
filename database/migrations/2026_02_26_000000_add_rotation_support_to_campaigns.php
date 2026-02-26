<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mtproto_campaigns', function (Blueprint $table) {
            $table->text('account_ids')->nullable()->after('account_id');
            $table->text('template_ids')->nullable()->after('template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mtproto_campaigns', function (Blueprint $table) {
            $table->dropColumn(['account_ids', 'template_ids']);
        });
    }
};
