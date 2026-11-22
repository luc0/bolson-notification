<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table) {
            $table->id();
            $table->string('source')->unique();
            $table->text('content');
            $table->text('description')->nullable();
            $table->string('price')->nullable();
            $table->decimal('price_amount', 14, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('location')->nullable();
            $table->string('city', 30)->nullable()->index();
            $table->string('neighborhood')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->decimal('covered_area', 10, 2)->nullable();
            $table->decimal('total_area', 14, 2)->nullable();
            $table->text('image_url')->nullable();
            $table->string('site_path')->index();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('houses');
    }
};
