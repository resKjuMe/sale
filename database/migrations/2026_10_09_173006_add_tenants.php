<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['categories', 'articles', 'bundles'];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        // Kategorienamen sind nur noch je Mandant eindeutig.
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['tenant_id', 'name']);
        });

        // Bestand gehört dem bisherigen (einzigen) Mandanten.
        if (DB::table('users')->exists() || DB::table('categories')->exists()) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => DB::table('users')->orderBy('id')->value('name') ?? 'Standard',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (['users', ...self::TABLES] as $name) {
                DB::table($name)->update(['tenant_id' => $tenantId]);
            }
        }
    }

    public function down(): void
    {
        // Erst die Fremdschlüssel, sonst hält MySQL den zusammengesetzten Index fest.
        foreach (['users', ...self::TABLES] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropForeign(['tenant_id']));
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->unique('name');
        });

        foreach (['users', ...self::TABLES] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('tenant_id'));
        }

        Schema::dropIfExists('tenants');
    }
};
