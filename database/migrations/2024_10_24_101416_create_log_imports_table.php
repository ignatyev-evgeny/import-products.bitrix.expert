<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('log_imports', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('domain')->nullable();
            $table->string('status')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_size')->nullable();
            $table->integer('file_count_rows')->nullable();
            $table->integer('product_count_rows')->nullable();
            $table->json('events_history')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('log_imports');
    }
};
