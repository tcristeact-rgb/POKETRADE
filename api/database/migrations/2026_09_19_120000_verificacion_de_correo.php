<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Verificación de correo por código de 6 dígitos (spec 2026-09-19-verificacion-de-correo).
//
// email_verified_at es la columna estándar de Laravel (MustVerifyEmail). Las
// otras tres guardan el código VIGENTE: su hash (nunca el código), cuándo
// caduca y cuántas veces se ha fallado con él.
//
// Los usuarios que ya existan quedan verificados: son cuentas anteriores a la
// regla y no se les puede exigir un correo que nunca se envió.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('codigo_verificacion')->nullable()->after('email_verified_at');
            $table->timestamp('codigo_expira_en')->nullable()->after('codigo_verificacion');
            $table->unsignedTinyInteger('codigo_intentos')->default(0)->after('codigo_expira_en');
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'codigo_verificacion', 'codigo_expira_en', 'codigo_intentos']);
        });
    }
};
