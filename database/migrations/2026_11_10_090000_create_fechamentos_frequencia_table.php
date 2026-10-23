<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fechamento do mês da frequência, por setor: a chefia confere e o RH fecha. O
 * boletim é guardado no fechamento, então o que foi para a folha não muda depois.
 * Mês fechado não aceita marcação, justificativa nem abono; o RH reabre com motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fechamentos_frequencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->restrictOnDelete();
            $table->date('mes'); // primeiro dia do mês
            $table->foreignId('conferido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conferido_em')->nullable();
            $table->string('observacao_chefia', 500)->nullable();
            $table->foreignId('fechado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fechado_em')->nullable();
            $table->json('boletim')->nullable(); // linhas do boletim no momento do fechamento
            $table->foreignId('reaberto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reaberto_em')->nullable();
            $table->string('motivo_reabertura', 255)->nullable();
            $table->timestamps();

            $table->unique(['unidade_id', 'mes']);
            $table->index(['orgao_id', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos_frequencia');
    }
};
