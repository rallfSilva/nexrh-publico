<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Prazo para fechar o mês: dia do mês seguinte (a SELC informou o dia 15).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regras_frequencia', function (Blueprint $table) {
            $table->unsignedTinyInteger('dia_fechamento')->default(15);
        });
    }

    public function down(): void
    {
        Schema::table('regras_frequencia', function (Blueprint $table) {
            $table->dropColumn('dia_fechamento');
        });
    }
};
