<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chaves de acesso à API de consulta. Só o hash fica gravado: a chave aparece uma vez, ao criar.
        Schema::create('chaves_api', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos');
            $table->string('nome', 120);
            $table->string('prefixo', 12);
            $table->string('hash', 64)->unique();
            $table->json('escopos');
            $table->date('expira_em')->nullable();
            $table->timestamp('ultimo_uso_em')->nullable();
            $table->string('ultimo_ip', 45)->nullable();
            $table->unsignedBigInteger('usos')->default(0);
            $table->foreignId('criada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revogada_em')->nullable();
            $table->foreignId('revogada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chaves_api');
    }
};
