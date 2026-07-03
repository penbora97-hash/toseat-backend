<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_notes_to_carts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
          
            $table->text('notes')->nullable()->after('quantity');
            
            
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->dropUnique(['user_id', 'product_id']);
        });
    }
};