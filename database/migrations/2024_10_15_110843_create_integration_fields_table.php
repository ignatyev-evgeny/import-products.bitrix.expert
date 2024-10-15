<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('integration_fields', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('article')->nullable();
            $table->string('brand')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('integration_fields');
    }
};
