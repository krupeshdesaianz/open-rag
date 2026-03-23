<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twin_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('twin_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('filepath');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status')->default('uploaded'); // uploaded, processing, ingested, failed
            $table->boolean('is_system_file')->default(false);
            $table->text('processing_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twin_files');
    }
};
