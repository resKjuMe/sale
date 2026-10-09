<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('pickup')->default(false)->after('shipping_cost');
            $table->boolean('picked_up')->default(false)->after('pickup');
            $table->timestamp('picked_up_at')->nullable()->after('picked_up');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['pickup', 'picked_up', 'picked_up_at']);
        });
    }
};
