<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_family_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained('shopping_lists')->cascadeOnDelete();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission', 20);
            $table->timestamps();

            $table->unique(['shopping_list_id', 'family_id', 'user_id'], 'slfu_list_family_user_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_family_user');
    }
};