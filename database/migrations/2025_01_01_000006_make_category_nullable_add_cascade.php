<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                  ->references('id')->on('categories')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')
                  ->references('id')->on('categories');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id')->nullable(false)->change();
        });
    }
};
