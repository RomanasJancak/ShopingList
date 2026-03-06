<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('level')->default(1);
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['family_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_roles');
    }
};