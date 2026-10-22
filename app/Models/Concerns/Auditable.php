<?php

namespace App\Models\Concerns;

use App\Support\Auditoria;

/**
 * Registra na trilha de auditoria toda criação, alteração e exclusão do modelo,
 * guardando os valores antes e depois. Campos sensíveis nunca são gravados.
 */
trait Auditable
{
    /** @var list<string> */
    protected static array $camposNuncaAuditados = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'ultimo_acesso_em', 'updated_at', 'created_at',
    ];

    /** Desliga o registro automático (ex.: importação em lote, que grava um único registro de resumo). */
    protected static bool $auditoriaPausada = false;

    public static function semAuditoria(callable $callback): mixed
    {
        $anterior = static::$auditoriaPausada;
        static::$auditoriaPausada = true;

        try {
            return $callback();
        } finally {
            static::$auditoriaPausada = $anterior;
        }
    }

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (static::$auditoriaPausada) {
                return;
            }
            Auditoria::registrar('criado', $model, $model->rotuloAuditoria().' criad'.$model->generoAuditoria(), null, $model->valoresAuditaveis($model->getAttributes()));
        });

        static::updated(function ($model) {
            if (static::$auditoriaPausada) {
                return;
            }

            $novos = $model->valoresAuditaveis($model->getChanges());

            if ($novos === []) {
                return;
            }

            $antigos = array_intersect_key($model->valoresAuditaveis($model->getOriginal()), $novos);
            $alterados = array_keys($novos);

            Auditoria::registrar('alterado', $model, $model->rotuloAuditoria().' alterad'.$model->generoAuditoria().' ('.implode(', ', $alterados).')', $antigos, $novos);
        });

        static::deleted(function ($model) {
            if (static::$auditoriaPausada) {
                return;
            }
            Auditoria::registrar('excluido', $model, $model->rotuloAuditoria().' excluíd'.$model->generoAuditoria(), $model->valoresAuditaveis($model->getAttributes()), null);
        });
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    protected function valoresAuditaveis(array $valores): array
    {
        $valores = array_diff_key($valores, array_flip([...static::$camposNuncaAuditados, ...$this->camposSensiveisAuditoria()]));

        // Campos protegidos: a trilha registra que mudaram, mas nunca o valor.
        foreach (array_intersect_key($valores, array_flip($this->camposProtegidosAuditoria())) as $campo => $valor) {
            $valores[$campo] = $valor === null ? null : '[protegido]';
        }

        foreach ($valores as $campo => $valor) {
            if ($valor instanceof \DateTimeInterface) {
                $valores[$campo] = $valor->format('Y-m-d H:i:s');
            }
        }

        return $valores;
    }

    /**
     * Campos do modelo que nunca vão para a trilha (além de senhas e tokens).
     *
     * @return list<string>
     */
    protected function camposSensiveisAuditoria(): array
    {
        return [];
    }

    /**
     * Campos que aparecem na trilha só como "[protegido]": dá para saber que foram
     * alterados, sem expor o conteúdo (documentos, dados bancários, dados sensíveis da LGPD).
     *
     * @return list<string>
     */
    protected function camposProtegidosAuditoria(): array
    {
        return [];
    }

    /** Terminação de gênero usada na descrição: "o" (criado) ou "a" (criada). */
    protected function generoAuditoria(): string
    {
        return 'o';
    }

    protected function rotuloAuditoria(): string
    {
        return class_basename($this).' #'.$this->getKey();
    }
}
