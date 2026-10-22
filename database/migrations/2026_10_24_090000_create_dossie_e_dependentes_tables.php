<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossiê digital (documentos do servidor por categoria) e dependentes com os
 * códigos de parentesco da ficha e as finalidades (IR, previdência, salário-família).
 *
 * O arquivo do documento fica criptografado no disco privado; aqui só o caminho,
 * o hash e os metadados. Nada é apagado: documento é arquivado com motivo e
 * dependente é encerrado com data e motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('categoria', 20);
            $table->string('tipo', 40);
            $table->string('descricao', 160)->nullable();
            $table->date('data_documento')->nullable();
            $table->date('validade')->nullable();
            $table->string('situacao', 20)->default('pendente')->index();
            $table->string('motivo', 255)->nullable();

            $table->string('arquivo_caminho', 255);
            $table->string('arquivo_nome', 255);
            $table->string('arquivo_mime', 60);
            $table->unsignedInteger('arquivo_tamanho');
            $table->char('arquivo_hash', 64);

            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('enviado_pelo_servidor')->default(false);
            $table->foreignId('analisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('analisado_em')->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'categoria']);
            $table->index(['orgao_id', 'situacao']);
        });

        Schema::create('dependentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('nome', 160);
            $table->text('cpf'); // criptografado
            $table->char('cpf_hash', 64);
            $table->date('data_nascimento');
            $table->char('sexo', 1);
            $table->string('parentesco', 2);
            $table->boolean('declarar_ir')->default(false);
            $table->boolean('declarar_previdencia')->default(false);
            $table->boolean('salario_familia')->default(false);
            $table->boolean('pcd')->default(false);
            $table->unsignedSmallInteger('tipo_deficiencia')->nullable();
            $table->boolean('universitario')->default(false);
            $table->date('encerrado_em')->nullable();
            $table->string('motivo_encerramento', 80)->nullable();
            $table->timestamps();

            $table->unique(['servidor_id', 'cpf_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependentes');
        Schema::dropIfExists('documentos');
    }
};
