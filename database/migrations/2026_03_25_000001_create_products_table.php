<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('picture')->nullable();
            $table->text('description')->nullable();
            $table->enum('quantity_type', ['kg', 'pcs']);
            $table->timestamps();

            $table->unique(['name', 'quantity_type'], 'products_name_qty_type_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};