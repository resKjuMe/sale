<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('sold');
            $table->timestamp('paid_at')->nullable()->after('paid');
            $table->timestamp('shipped_at')->nullable()->after('shipped');
        });

        // Der genaue Zeitpunkt ist für Bestandsdaten unbekannt; die letzte Änderung kommt ihm am nächsten.
        foreach (['sold', 'paid', 'shipped'] as $flag) {
            DB::table('articles')->where($flag, true)->update(["{$flag}_at" => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['sold_at', 'paid_at', 'shipped_at']);
        });
    }
};
