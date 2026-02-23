<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTicketUploadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticket_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_message_id');
            $table->string('path');
            $table->timestamps();

            if (Schema::hasTable('ticket_messages')) {
                $table->foreign('ticket_message_id')->references('id')->on('ticket_messages');
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
        Schema::dropIfExists('ticket_uploads');
    }
}
