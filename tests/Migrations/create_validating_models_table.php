<?php

namespace StevenFox\LaravelModelValidation\Tests\Fixtures;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validating_models', static function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('unique_column')->unique();
            $table->string('required_string');
            $table->string('stringable')->nullable();
            $table->dateTime('datetime')->nullable();
            $table->json('json')->nullable();
            $table->json('array')->nullable();
            $table->json('collection')->nullable();
            $table->text('encrypted_object')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validating_models');
    }
};
