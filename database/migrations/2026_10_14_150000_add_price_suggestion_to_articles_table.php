<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Von der KI geschätzter Vinted-Preis; nur Anzeige, nie aus dem Formular.
            $table->decimal('price_suggestion', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('price_suggestion');
        });
    }
};
