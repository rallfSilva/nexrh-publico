<?php

namespace App\Frequencia;

use App\Models\RegistroPonto;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Registro de entrada e saída pelo próprio servidor. A hora é a do servidor do
 * sistema, nunca a do computador de quem registra, e o registro só vale de dentro
 * da rede do órgão (quando a regra exige).
 */
class Marcador
{
    /** Evita duas marcações por um clique duplo. */
    public const INTERVALO_MINIMO_MINUTOS = 2;

    public const MAXIMO_POR_DIA = 8;

    /** O que impede o servidor de registrar agora, ou null se pode. */
    public function impedimento(Servidor $servidor, ?string $ip, ?Espelho $espelho = null): ?string
    {
        if (! $servidor->ativo()) {
            return 'Só servidores ativos registram o ponto.';
        }

        $espelho ??= (new Espelho)->carregar(collect([$servidor]), today());
        $jornada = $espelho->jornadaEm($servidor, today());

        if (! $jornada->registraPonto()) {
            return $jornada->modalidade === 'teletrabalho'
                ? 'Sua jornada é em teletrabalho: não há registro de ponto.'
                : 'Você está dispensado do registro de ponto.';
        }

        if (! $espelho->regra()->redePermite($ip)) {
            return 'O ponto só pode ser registrado de dentro da rede do órgão. Você está acessando de fora ('.$ip.').';
        }

        return null;
    }

    public function registrar(Servidor $servidor, User $usuario, ?string $ip): RegistroPonto
    {
        if ($erro = $this->impedimento($servidor, $ip)) {
            throw ValidationException::withMessages(['ponto' => $erro]);
        }

        $agora = Carbon::now()->startOfMinute();
        $hoje = RegistroPonto::validos()->where('servidor_id', $servidor->id)
            ->whereBetween('momento', [today(), today()->endOfDay()])->orderByDesc('momento')->get();

        if ($hoje->count() >= self::MAXIMO_POR_DIA) {
            throw ValidationException::withMessages(['ponto' => 'Você já tem '.self::MAXIMO_POR_DIA.' marcações hoje. Se precisar corrigir alguma, fale com o RH.']);
        }
        if ($hoje->isNotEmpty() && $hoje->first()->momento->diffInMinutes($agora) < self::INTERVALO_MINIMO_MINUTOS) {
            throw ValidationException::withMessages(['ponto' => 'Você acabou de registrar às '.$hoje->first()->momento->format('H:i').'. Espere alguns minutos para a próxima marcação.']);
        }

        return RegistroPonto::create([
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'momento' => $agora,
            'origem' => 'sistema',
            'ip' => $ip,
            'registrado_por' => $usuario->id,
        ]);
    }

    /** "Registrar entrada" ou "Registrar saída", pela quantidade de marcações válidas de hoje. */
    public static function proximaAcao(int $marcacoesHoje): string
    {
        return $marcacoesHoje % 2 === 0 ? 'entrada' : 'saída';
    }
}
