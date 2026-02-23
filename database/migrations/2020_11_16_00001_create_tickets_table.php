<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('opener_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('subject');
            $table->string('priority')->default('low');
            $table->enum('state', [ 'OPEN', 'ANSWERED', 'CLOSED' ])->default('OPEN');
            $table->timestamps();

            // Removing foreign keys for safety if tables don't match, or hardcoding them?
            // If users table is 'users', it works.
            // If category table is 'ticket_categories', it works.
             if (Schema::hasTable('users') && Schema::hasTable('ticket_categories')) {
                $table->foreign('opener_id')->references('id')->on('users');
                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('category_id')->references('id')->on('ticket_categories');
             }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tickets');
    }
}
