<?php

namespace App\Importacao;

use App\Frequencia\Fechamento;
use App\Models\RegistroPonto;
use App\Models\Servidor;
use App\Support\Cpf;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Lê o arquivo AFD (Arquivo-Fonte de Dados) do relógio de ponto. Só interessam as
 * marcações (registro tipo 3), nos dois leiautes:
 *
 * - Portaria 671/2021: NSR (9) + "3" + data e hora AAAA-MM-DDThh:mm:00-0300 (24) + CPF (12) + CRC (4);
 * - Portaria 1.510/2009: NSR (9) + "3" + data DDMMAAAA (8) + hora hhmm (4) + PIS (12).
 *
 * O servidor é achado pelo CPF ou pelo PIS/PASEP da ficha. Marcações que já estão
 * no sistema (mesmo servidor, mesmo minuto) são ignoradas, então reenviar o mesmo
 * arquivo não duplica nada.
 */
class AnalisadorPonto
{
    /** Identificações sem servidor listadas uma a uma (o resto vai numa linha só). */
    private const LIMITE_DIVERGENCIAS = 30;

    /** @var list<array{nivel: string, cargo: ?string, mensagem: string}> */
    private array $divergencias = [];

    /** @return array{formato: string, ponto: list<array<string, mixed>>, resumo: array<string, mixed>, divergencias: list<array<string, mixed>>} */
    public function analisar(string $caminho, int $orgaoId): array
    {
        $this->divergencias = [];
        $conteudo = @file_get_contents($caminho);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler o arquivo.');
        }
        if (! mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'ISO-8859-1');
        }

        $servidores = Servidor::with('unidade')->where('orgao_id', $orgaoId)->get();
        $porCpf = $servidores->keyBy('cpf_hash');
        $porPis = $servidores->filter(fn (Servidor $s) => $s->pis_pasep)->keyBy(fn (Servidor $s) => ltrim(preg_replace('/\D/', '', $s->pis_pasep), '0'));

        $linhas = preg_split('/\r\n|\r|\n/', $conteudo);
        $marcacoes = [];
        $semServidor = [];
        $ilegiveis = 0;
        $formato = null;
        $total = 0;

        foreach ($linhas as $i => $linha) {
            $linha = rtrim($linha);
            if (strlen($linha) < 10 || $linha[9] !== '3' || ! ctype_digit(substr($linha, 0, 9))) {
                continue;
            }
            $total++;

            [$momento, $identificador, $tipo, $leiaute] = $this->lerMarcacao($linha);
            if ($momento === null) {
                $ilegiveis++;
                if ($ilegiveis <= 5) {
                    $this->divergir('erro', 'Linha '.($i + 1), 'Não será importada: marcação com data, hora ou identificação ilegível.');
                }

                continue;
            }
            $formato ??= $leiaute;

            $servidor = $tipo === 'cpf' ? $porCpf->get(Cpf::hash($identificador)) : $porPis->get(ltrim($identificador, '0'));
            if ($servidor === null) {
                $chave = $tipo.':'.$identificador;
                $semServidor[$chave] = ($semServidor[$chave] ?? 0) + 1;

                continue;
            }

            $marcacoes[$servidor->id.'|'.$momento->format('Y-m-d H:i')] ??= [
                'servidor_id' => $servidor->id,
                'servidor_nome' => $servidor->nome,
                'unidade_sigla' => $servidor->unidade?->sigla,
                'momento' => $momento->format('Y-m-d H:i:00'),
                'nsr' => (int) substr($linha, 0, 9),
            ];
        }

        if ($total === 0) {
            $this->divergir('bloqueia', null, 'Nenhuma marcação de ponto encontrada. Envie o arquivo AFD do relógio (texto, com linhas de registro tipo 3), nos leiautes da Portaria 671/2021 ou 1.510/2009.');

            return $this->resultado('desconhecido', [], 0, 0, 0);
        }
        if ($ilegiveis > 5) {
            $this->divergir('erro', null, 'Mais '.($ilegiveis - 5).' marcação(ões) ilegível(is) não serão importadas.');
        }

        foreach (array_slice($semServidor, 0, self::LIMITE_DIVERGENCIAS, true) as $chave => $n) {
            [$tipo, $id] = explode(':', $chave, 2);
            $this->divergir('erro', null, sprintf(
                'Não serão importadas: %d marcação(ões) do %s %s, que não é de nenhum servidor do órgão. Confira o cadastro no relógio ou na ficha.',
                $n, $tipo === 'cpf' ? 'CPF' : 'PIS', $tipo === 'cpf' ? Cpf::mascarar($id) : substr($id, 0, 3).'.*****.'.substr($id, -3),
            ));
        }
        if (count($semServidor) > self::LIMITE_DIVERGENCIAS) {
            $this->divergir('erro', null, 'Há mais '.(count($semServidor) - self::LIMITE_DIVERGENCIAS).' identificação(ões) sem servidor no órgão.');
        }

        // Já registradas no sistema: ignoradas sem erro.
        $marcacoes = array_values($marcacoes);
        $jaRegistradas = 0;
        if ($marcacoes !== []) {
            $momentos = array_column($marcacoes, 'momento');
            $existentes = RegistroPonto::whereIn('servidor_id', array_unique(array_column($marcacoes, 'servidor_id')))
                ->whereBetween('momento', [min($momentos), max($momentos)])->get(['servidor_id', 'momento'])
                ->map(fn ($r) => $r->servidor_id.'|'.$r->momento->format('Y-m-d H:i:00'))->flip();
            $novas = array_values(array_filter($marcacoes, fn ($m) => ! $existentes->has($m['servidor_id'].'|'.$m['momento'])));
            $jaRegistradas = count($marcacoes) - count($novas);
            $marcacoes = $novas;
        }
        if ($jaRegistradas > 0) {
            $this->divergir('aviso', null, $jaRegistradas.' marcação(ões) já estão no sistema e serão ignoradas.');
        }
        // Meses já fechados pelo RH não recebem marcação nova.
        $emFechado = [];
        if ($marcacoes !== []) {
            $fechados = Fechamento::mesesFechados($servidores->whereIn('id', array_column($marcacoes, 'servidor_id'))->pluck('unidade_id', 'id')->all());
            $emFechado = array_filter($marcacoes, fn ($m) => isset($fechados[$m['servidor_id'].'|'.substr($m['momento'], 0, 7)]));
            if ($emFechado !== []) {
                $meses = collect($emFechado)->map(fn ($m) => substr($m['momento'], 5, 2).'/'.substr($m['momento'], 0, 4))->unique()->sort()->implode(', ');
                $this->divergir('erro', null, 'Não serão importadas: '.count($emFechado).' marcação(ões) de mês já fechado pelo RH ('.$meses.'). Para incluir, o RH reabre o mês do setor.');
                $marcacoes = array_values(array_diff_key($marcacoes, $emFechado));
            }
        }
        $futuras = array_filter($marcacoes, fn ($m) => Carbon::parse($m['momento'])->isFuture());
        if ($futuras !== []) {
            $this->divergir('bloqueia', null, count($futuras).' marcação(ões) com data no futuro. Confira a data e a hora do relógio de ponto antes de importar.');
        }

        return $this->resultado($formato ?? 'afd_671', $marcacoes, $total, $jaRegistradas, $ilegiveis + array_sum($semServidor) + count($emFechado));
    }

    /** @return array{0: ?Carbon, 1: ?string, 2: ?string, 3: ?string} momento, identificador, tipo (cpf ou pis), leiaute */
    private function lerMarcacao(string $linha): array
    {
        // Portaria 671: AAAA-MM-DDThh:mm:00-0300 a partir da posição 11.
        if (strlen($linha) >= 46 && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?([+-]\d{4})?$/', $data = substr($linha, 10, 24))) {
            $cpf = substr(preg_replace('/\D/', '', substr($linha, 34, 12)), -11);
            $momento = $this->data(substr($data, 0, 16), 'Y-m-d\TH:i');

            return Cpf::valido($cpf) && $momento ? [$momento, $cpf, 'cpf', 'afd_671'] : [null, null, null, null];
        }

        // Portaria 1.510: DDMMAAAA hhmm PIS.
        if (strlen($linha) >= 34 && ctype_digit(substr($linha, 10, 24))) {
            $momento = $this->data(substr($linha, 10, 12), 'dmYHi');
            $pis = substr($linha, 22, 12);

            return $momento && (int) $pis > 0 ? [$momento, $pis, 'pis', 'afd_1510'] : [null, null, null, null];
        }

        return [null, null, null, null];
    }

    private function data(string $texto, string $formato): ?Carbon
    {
        $data = Carbon::createFromFormat('!'.$formato, $texto, config('app.timezone'));

        return $data && $data->format($formato) === $texto && $data->year >= 2000 ? $data : null;
    }

    private function divergir(string $nivel, ?string $rotulo, string $mensagem): void
    {
        $this->divergencias[] = ['nivel' => $nivel, 'cargo' => $rotulo, 'mensagem' => $mensagem];
    }

    /** @param  list<array<string, mixed>>  $marcacoes */
    private function resultado(string $formato, array $marcacoes, int $total, int $jaRegistradas, int $comErro): array
    {
        $momentos = array_column($marcacoes, 'momento');

        return [
            'formato' => $formato,
            'ponto' => $marcacoes,
            'resumo' => ['calculado' => [
                'linhas' => $total,
                'validas' => count($marcacoes),
                'com_erro' => $comErro,
                'ja_registradas' => $jaRegistradas,
                'servidores' => count(array_unique(array_column($marcacoes, 'servidor_id'))),
                'de' => $momentos ? substr(min($momentos), 0, 10) : null,
                'ate' => $momentos ? substr(max($momentos), 0, 10) : null,
            ]],
            'divergencias' => $this->divergencias,
        ];
    }
}
