<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->string('access_key');
            $table->string('refresh_key');
            $table->string('product_field_article');
            $table->string('product_field_brand');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('integrations');
    }
};
