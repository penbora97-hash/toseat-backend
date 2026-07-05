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
        Schema::table('orders', function (Blueprint $table) {
         
            if (!Schema::hasColumn('orders', 'cancelled_by')) {
                $table->enum('cancelled_by', ['user', 'admin'])
                    ->nullable()
                    ->after('status')
                    ->comment('Who cancelled the order: user or admin');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }
        });
    }
};