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
        Schema::create('crm_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('content')->nullable();
            $table->string('image')->nullable();
            $table->string('prompt')->nullable();
            $table->integer('source')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_feedback');
    }
};
