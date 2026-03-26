<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_shopping_list_id')
                ->nullable()
                ->after('google_avatar')
                ->constrained('shopping_lists')
                ->nullOnDelete();

            $table->boolean('load_default_shopping_list_on_login')
                ->default(false)
                ->after('default_shopping_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_shopping_list_id');
            $table->dropColumn('load_default_shopping_list_on_login');
        });
    }
};
