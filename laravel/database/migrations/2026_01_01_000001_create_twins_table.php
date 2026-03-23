<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->default('open-rag');
            $table->text('description')->nullable();
            $table->string('status')->default('pending'); // pending, processing, ready, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twins');
    }
};
