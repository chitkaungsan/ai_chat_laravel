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
         Schema::create('messages', function (Blueprint $table) {
            $table->id(); // bigint auto increment
            $table->string('session_id', 255);
            $table->string('role', 255);
            $table->text('content');
            $table->timestamps(); // created_at, updated_at (nullable by default in PG)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
