<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNameKhToCategoriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('categories', 'name_kh')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('name_kh')->nullable()->after('name');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('categories', 'name_kh')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('name_kh');
            });
        }
    }
}