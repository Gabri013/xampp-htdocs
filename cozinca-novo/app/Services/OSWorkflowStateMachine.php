<?php

namespace App\Services;

class OSWorkflowStateMachine
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_PROJETO = 'em_projeto';
    public const STATUS_PROPOSTA = 'proposta';
    public const STATUS_EM_REVISAO = 'em_revisao';
    public const STATUS_EM_PRODUCAO = 'em_producao';
    public const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_CANCELADA = 'cancelada';

    public const ETAPAS = [
        'programacao',
        'corte',
        'mobiliario',
        'coccao',
        'refrigeracao',
        'embalagem',
        'engenharia',
        'dobra',
        'tubo',
        'solda',
        'concluida',
    ];

    private const PERMISSOES_ETL = [
        'master' => self::ETAPAS,
        'gerente' => self::ETAPAS,
        'producao' => self::ETAPAS,
        'producao_geral' => self::ETAPAS,
        'corte' => self::ETAPAS,
        'dobra' => self::ETAPAS,
        'solda' => self::ETAPAS,
        'refrigeracao' => self::ETAPAS,
        'acabamento' => self::ETAPAS,
        'finalizacao' => self::ETAPAS,
        'montagem' => self::ETAPAS,
    ];

    private const TRANSICOES_STATUS = [
        self::STATUS_PENDENTE => [self::STATUS_EM_PROJETO, self::STATUS_EM_PRODUCAO, self::STATUS_PROPOSTA, self::STATUS_CANCELADA],
        self::STATUS_EM_PROJETO => [self::STATUS_PROPOSTA, self::STATUS_PENDENTE, self::STATUS_CANCELADA],
        self::STATUS_PROPOSTA => [self::STATUS_EM_PRODUCAO, self::STATUS_EM_PROJETO, self::STATUS_EM_REVISAO, self::STATUS_CANCELADA],
        self::STATUS_EM_REVISAO => [self::STATUS_PROPOSTA, self::STATUS_EM_PROJETO, self::STATUS_EM_PRODUCAO, self::STATUS_CANCELADA],
        self::STATUS_EM_PRODUCAO => [self::STATUS_CONCLUIDA, self::STATUS_EM_PROJETO, self::STATUS_CANCELADA],
        self::STATUS_CONCLUIDA => [],
        self::STATUS_CANCELADA => [],
    ];

    private array $errors = [];

    public function validarTransicaoStatus(string $from, string $to): bool
    {
        if (!in_array($from, self::ETAPAS) || !in_array($to, self::ETAPAS)) {
            $this->errors[] = 'Status inválido.';
            return false;
        }

        if ($from === $to) {
            return true;
        }

        if ($to === self::STATUS_CANCELADA) {
            return in_array($from, [self::STATUS_PENDENTE, self::STATUS_EM_PROJETO, self::STATUS_PROPOSTA, self::STATUS_EM_REVISAO, self::STATUS_EM_PRODUCAO], true);
        }

        if ($from === self::STATUS_CANCELADA) {
            $this->errors[] = 'O.S. cancelada não pode ter status alterado.';
            return false;
        }

        $allowed = self::TRANSICOES_STATUS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            $this->errors[] = "Transição de status '{$from}' para '{$to}' não permitida.";
            return false;
        }

        return true;
    }

    public function validarTransicaoEtapa(string $from, string $to): bool
    {
        if (!in_array($from, self::ETAPAS, true) || !in_array($to, self::ETAPAS, true)) {
            $this->errors[] = 'Etapa inválida.';
            return false;
        }

        if ($from === $to) {
            return true;
        }

        if ($from === self::STATUS_CONCLUIDA) {
            $this->errors[] = 'Etapa concluída não pode ser alterada.';
            return false;
        }

        if ($to === self::STATUS_CONCLUIDA) {
            $posFrom = array_search($from, self::ETAPAS, true);
            if ($posFrom === false || !isset(self::ETAPAS[$posFrom + 1])) {
                $this->errors[] = 'Só é permitido concluir a partir da etapa anterior à conclusão.';
                return false;
            }
            return true;
        }

        if ($to === 'engenharia') {
            return true;
        }

        $posFrom = array_search($from, self::ETAPAS, true);
        $posTo = array_search($to, self::ETAPAS, true);

        if ($posFrom === false || $posTo === false) {
            $this->errors[] = 'Etapas não encontradas no fluxo padrão.';
            return false;
        }

        if ($posTo <= $posFrom) {
            $this->errors[] = 'Só é permitido avançar para a próxima etapa.';
            return false;
        }

        if ($posTo - $posFrom > 1) {
            $this->errors[] = 'Não é permitido pular etapas.';
            return false;
        }

        return true;
    }

    public function podeOperarEtapa(string $etapa, string $userType): bool
    {
        $permitted = self::PERMISSOES_ETL[$userType] ?? [];
        return in_array($etapa, $permitted, true);
    }

    public function proximaEtapa(string $etapaAtual): ?string
    {
        $pos = array_search($etapaAtual, self::ETAPAS, true);
        if ($pos === false || !isset(self::ETAPAS[$pos + 1])) {
            return null;
        }
        return self::ETAPAS[$pos + 1];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function allStatuses(): array
    {
        return [
            self::STATUS_PENDENTE,
            self::STATUS_EM_PROJETO,
            self::STATUS_PROPOSTA,
            self::STATUS_EM_REVISAO,
            self::STATUS_EM_PRODUCAO,
            self::STATUS_CONCLUIDA,
            self::STATUS_CANCELADA,
        ];
    }

    public static function allEtapas(): array
    {
        return self::ETAPAS;
    }
}