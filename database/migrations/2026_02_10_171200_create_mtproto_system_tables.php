<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMtprotoSystemTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. MTProto Accounts
        Schema::create('mtproto_accounts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('phone')->unique();
            $table->integer('api_id');
            $table->string('api_hash');
            $table->string('session_path')->nullable();
            $table->enum('status', ['0', '1'])->default('0'); // 0: Inactive, 1: Active
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 2. Contact Lists
        Schema::create('mtproto_contact_lists', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 3. Contacts
        Schema::create('mtproto_contacts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->unsignedBigInteger('list_id');
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('list_id')->references('id')->on('mtproto_contact_lists')->onDelete('cascade');
        });

        // 4. Templates
        Schema::create('mtproto_templates', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title');
            $table->text('message');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 5. Campaigns
        Schema::create('mtproto_campaigns', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->unsignedBigInteger('list_id');
            $table->unsignedBigInteger('template_id');
            $table->string('campaign_name');
            $table->integer('interval_min')->default(5);
            $table->enum('status', ['pending', 'processing', 'completed', 'paused', 'failed'])->default('pending');
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('list_id')->references('id')->on('mtproto_contact_lists')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('mtproto_templates')->onDelete('cascade');
        });

        // 6. Messages (Inbox/Outbox)
        Schema::create('mtproto_messages', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->unsignedBigInteger('account_id');
            $table->string('contact_identifier'); // Phone or Username
            $table->enum('direction', ['in', 'out']);
            $table->text('message');
            $table->timestamp('message_time');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('mtproto_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mtproto_messages');
        Schema::dropIfExists('mtproto_campaigns');
        Schema::dropIfExists('mtproto_templates');
        Schema::dropIfExists('mtproto_contacts');
        Schema::dropIfExists('mtproto_contact_lists');
        Schema::dropIfExists('mtproto_accounts');
    }
}
