<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // null = öffentliche Gesamtübersicht
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('visitor', 16);
            $table->date('viewed_on');
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
