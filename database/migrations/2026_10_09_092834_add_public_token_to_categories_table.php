<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable()->unique()->after('description');
        });

        DB::table('categories')->whereNull('public_token')->pluck('id')->each(
            fn (int $id) => DB::table('categories')->where('id', $id)->update(['public_token' => Str::random(32)])
        );
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
