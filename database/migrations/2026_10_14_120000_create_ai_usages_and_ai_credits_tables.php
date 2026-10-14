<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ein Eintrag je abgerechnetem Modell-Durchlauf (bei Fallbacks mehrere je Anfrage).
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20);
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            // Mikro-Dollar; null = Modell ohne hinterlegten Preis
            $table->unsignedBigInteger('cost_micros')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Guthaben laut Console zum Zeitpunkt der Eingabe; der jüngste Eintrag zählt.
        Schema::create('ai_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_micros');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credits');
        Schema::dropIfExists('ai_usages');
    }
};
