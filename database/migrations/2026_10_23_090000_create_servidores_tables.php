<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro de servidores com os campos da Ficha de Cadastro enviada à SEGAD/RR,
 * as declarações que acompanham a ficha (parentesco, acumulação e bens) e a
 * linha do tempo funcional.
 *
 * Documentos e dados bancários ficam criptografados (colunas text). O CPF tem
 * também um hash (HMAC) para busca e para impedir cadastro em dobro no órgão.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Busca por nome sem diferenciar acentos ("Joao" encontra "João").
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        Schema::create('servidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('vaga_id')->nullable()->unique()->constrained('vagas')->nullOnDelete();
            $table->string('situacao', 20)->default('ativo')->index();

            // Ato de nomeação e vínculo
            $table->string('decreto_nomeacao', 60)->nullable();
            $table->date('data_decreto')->nullable();
            $table->string('doe_numero', 20)->nullable();
            $table->date('data_doe')->nullable();
            $table->date('efeitos_a_partir_de')->nullable();
            $table->string('processo_sei', 40)->nullable();
            $table->string('matricula_comissionado', 30)->nullable();
            $table->string('carga_horaria', 40)->nullable();

            // Identificação
            $table->string('nome', 160);
            $table->text('cpf');
            $table->char('cpf_hash', 64);
            $table->char('sexo', 1);
            $table->unsignedSmallInteger('estado_civil');
            $table->unsignedSmallInteger('raca_cor');
            $table->boolean('pcd')->default(false);
            $table->unsignedSmallInteger('tipo_deficiencia')->nullable();
            $table->boolean('molestia_grave')->default(false);
            $table->string('nacionalidade', 60)->default('Brasileira');
            $table->string('naturalidade', 80);
            $table->date('data_nascimento');
            $table->string('tipo_sanguineo', 3)->nullable();
            $table->string('nome_mae', 160);
            $table->string('nome_pai', 160)->nullable();

            // Documentos
            $table->text('pis_pasep')->nullable();
            $table->text('rg_numero')->nullable();
            $table->string('rg_orgao_uf', 30)->nullable();
            $table->date('rg_data_expedicao')->nullable();
            $table->text('reservista_numero')->nullable();
            $table->string('reservista_orgao_uf', 30)->nullable();
            $table->date('reservista_data_expedicao')->nullable();
            $table->unsignedSmallInteger('reservista_categoria')->nullable();
            $table->unsignedSmallInteger('reservista_ano')->nullable();
            $table->text('titulo_numero')->nullable();
            $table->string('titulo_zona', 10)->nullable();
            $table->string('titulo_secao', 10)->nullable();
            $table->date('titulo_data_expedicao')->nullable();
            $table->string('titulo_cidade', 80)->nullable();
            $table->char('titulo_uf', 2)->nullable();
            $table->text('ctps_numero')->nullable();
            $table->string('ctps_serie', 20)->nullable();
            $table->char('ctps_uf', 2)->nullable();
            $table->date('ctps_data_expedicao')->nullable();
            $table->text('cnh_numero')->nullable();
            $table->char('cnh_uf', 2)->nullable();
            $table->string('cnh_categoria', 2)->nullable();
            $table->date('cnh_data_expedicao')->nullable();
            $table->date('cnh_data_validade')->nullable();
            $table->date('cnh_data_primeira')->nullable();
            $table->date('estrangeiro_data_chegada')->nullable();
            $table->unsignedSmallInteger('estrangeiro_ano_chegada')->nullable();
            $table->date('estrangeiro_data_limite')->nullable();
            $table->text('estrangeiro_rne')->nullable();

            // Formação e vínculos
            $table->unsignedSmallInteger('grau_instrucao');
            $table->string('curso', 120)->nullable();
            $table->string('instituicao_curso', 120)->nullable();
            $table->unsignedSmallInteger('ano_inicio_primeiro_emprego')->nullable();
            $table->unsignedSmallInteger('ano_fim_primeiro_emprego')->nullable();
            $table->boolean('vaga_pcd_ou_reabilitacao')->default(false);
            $table->boolean('servidor_efetivo')->default(false);
            $table->string('matricula_efetiva', 30)->nullable();
            $table->string('cargo_efetivo', 120)->nullable();
            $table->boolean('servidor_federal')->default(false);
            $table->string('matricula_siape', 30)->nullable();

            // Endereço, contato e banco
            $table->string('cep', 9);
            $table->string('logradouro', 160);
            $table->string('numero', 20);
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 80);
            $table->string('municipio', 80);
            $table->char('uf', 2);
            $table->string('celular', 20);
            $table->string('email', 160);
            $table->string('banco_codigo', 5);
            $table->string('banco_nome', 80)->nullable();
            $table->text('agencia');
            $table->text('conta_corrente');

            // Desligamento
            $table->date('data_desligamento')->nullable();
            $table->string('ato_desligamento', 60)->nullable();
            $table->string('motivo_desligamento', 255)->nullable();

            $table->timestamps();

            $table->unique(['orgao_id', 'cpf_hash']);
            $table->index(['orgao_id', 'nome']);
        });

        Schema::create('declaracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->date('data_declaracao');
            $table->unsignedSmallInteger('ano_referencia')->nullable();
            $table->text('conteudo'); // criptografado: bens, valores e nomes de parentes
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'tipo', 'data_declaracao']);
        });

        Schema::create('servidor_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->constrained('orgaos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->date('data');
            $table->string('tipo', 30);
            $table->string('descricao', 255);
            $table->string('detalhe', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['servidor_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servidor_eventos');
        Schema::dropIfExists('declaracoes');
        Schema::dropIfExists('servidores');
    }
};
