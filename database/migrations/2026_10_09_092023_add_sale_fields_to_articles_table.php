<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('sold')->default(false)->after('price');
            $table->string('buyer_name')->nullable()->after('sold');
            $table->text('buyer_address')->nullable()->after('buyer_name');
            $table->boolean('paid')->default(false)->after('buyer_address');
            $table->decimal('sale_price', 10, 2)->nullable()->after('paid');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['sold', 'buyer_name', 'buyer_address', 'paid', 'sale_price']);
        });
    }
};
