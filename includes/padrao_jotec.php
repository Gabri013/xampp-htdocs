<?php
/**
 * Padronização de Códigos JOTEC
 *
 * Centraliza toda a geração e validação de códigos
 * seguindo o padrão do arquivo JOTEC
 */

class PadraoJOTEC {

    // Padrões de código por tipo
    const PADRAO_MATERIAL = 'JOTEC-%06d';        // JOTEC-001000
    const PADRAO_PRODUTO = 'JOTEC-P%05d';       // JOTEC-P00001
    const PADRAO_FORNECEDOR = 'JOTEC-F%04d';    // JOTEC-F0001
    const PADRAO_CLIENTE = 'JOT-CLI-%06d';      // JOT-CLI-000001
    const PADRAO_OS = 'OS-JOTEC-%06d';          // OS-JOTEC-000001

    // Prefixos por ABA JOTEC
    const ABA_PRODUTOS_ACABADOS = 'PA';
    const ABA_MATERIAIS = 'MT';
    const ABA_GERAL = 'GR';
    const ABA_INSUMOS = 'IN';
    const ABA_COMPONENTES = 'CP';

    /**
     * Gera código de material JOTEC
     * Formato: JOTEC-001000, JOTEC-001001, etc
     *
     * @param int $sequencia Número sequencial
     * @return string Código formatado
     */
    public static function gerarCodigoMaterial($sequencia) {
        return sprintf(self::PADRAO_MATERIAL, $sequencia);
    }

    /**
     * Gera código de produto JOTEC
     * Formato: JOTEC-P00001, JOTEC-P00002, etc
     *
     * @param int $sequencia Número sequencial
     * @return string Código formatado
     */
    public static function gerarCodigoProduto($sequencia) {
        return sprintf(self::PADRAO_PRODUTO, $sequencia);
    }

    /**
     * Gera código de fornecedor JOTEC
     * Formato: JOTEC-F0001, JOTEC-F0002, etc
     *
     * @param int $sequencia Número sequencial
     * @return string Código formatado
     */
    public static function gerarCodigoFornecedor($sequencia) {
        return sprintf(self::PADRAO_FORNECEDOR, $sequencia);
    }

    /**
     * Gera código de cliente JOTEC
     * Formato: JOT-CLI-000001, JOT-CLI-000002, etc
     *
     * @param int $sequencia Número sequencial
     * @return string Código formatado
     */
    public static function gerarCodigoCliente($sequencia) {
        return sprintf(self::PADRAO_CLIENTE, $sequencia);
    }

    /**
     * Gera código de Ordem de Serviço JOTEC
     * Formato: OS-JOTEC-000001, OS-JOTEC-000002, etc
     *
     * @param int $sequencia Número sequencial
     * @return string Código formatado
     */
    public static function gerarCodigoOS($sequencia) {
        return sprintf(self::PADRAO_OS, $sequencia);
    }

    /**
     * Gera código com prefixo de ABA
     * Formato: PA-001000 (Produtos Acabados), MT-001000 (Materiais), etc
     *
     * @param string $aba Prefixo da ABA
     * @param int $sequencia Número sequencial
     * @return string Código formatado
     */
    public static function gerarCodigoComABA($aba, $sequencia) {
        $aba = strtoupper(substr($aba, 0, 2));
        return $aba . '-' . str_pad($sequencia, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Valida se é um código válido
     *
     * Aceita:
     * - Números puros (1000000+)
     * - Prefixos JOTEC (JOTEC-, JOT-CLI-, etc) [compatibilidade]
     *
     * @param string $codigo Código a validar
     * @return bool True se válido
     */
    public static function validarCodigo($codigo) {
        // Padrão 1: Números puros (principal)
        if (preg_match('/^\d+$/', $codigo)) {
            $num = (int)$codigo;
            return $num >= 1000000; // Começam do 1000000
        }

        // Padrão 2: Prefixos JOTEC (compatibilidade com código legado)
        $prefixosValidos = ['JOTEC-', 'JOT-CLI-', 'OS-JOTEC-', 'PA-', 'MT-', 'GR-', 'IN-', 'CP-'];
        foreach ($prefixosValidos as $prefixo) {
            if (strpos($codigo, $prefixo) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrai a sequência de um código JOTEC
     *
     * @param string $codigo Código JOTEC
     * @return int|false Sequência ou false se inválido
     */
    public static function extrairSequencia($codigo) {
        // Remove o prefixo e retorna apenas os números
        $partes = preg_match('/(\d+)$/', $codigo, $matches);
        return $partes ? (int)$matches[1] : false;
    }

    /**
     * Obtém a próxima sequência disponível no banco
     *
     * @param PDO $db Conexão com banco
     * @param string $tabela Tabela a verificar
     * @param string $coluna Coluna com o código
     * @return int Próxima sequência
     */
    public static function obterProximaSequencia($db, $tabela, $coluna = 'codigo') {
        try {
            // Obter a maior sequência numérica da coluna
            $sql = "
                SELECT MAX(
                    CAST(
                        REGEXP_SUBSTR($coluna, '[0-9]+$') AS UNSIGNED
                    )
                ) as max_seq
                FROM $tabela
                WHERE $coluna IS NOT NULL AND $coluna != ''
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado && $resultado['max_seq']) {
                $max = (int)$resultado['max_seq'];
                return $max + 1; // Próximo número após o máximo
            }
        } catch (Exception $e) {
            // Se falhar com REGEXP_SUBSTR, tentar alternativa
            try {
                $sql = "
                    SELECT MAX(
                        CAST(
                            RIGHT($coluna, 6) AS UNSIGNED
                        )
                    ) as max_seq
                    FROM $tabela
                    WHERE $coluna IS NOT NULL AND $coluna != ''
                ";

                $stmt = $db->prepare($sql);
                $stmt->execute();
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($resultado && $resultado['max_seq']) {
                    $max = (int)$resultado['max_seq'];
                    return $max + 1;
                }
            } catch (Exception $e2) {
                // Ignorar, usar padrão
            }
        }

        return 1000; // Começar do 1000 se tabela estiver vazia
    }

    /**
     * Cria um código único e sequencial
     * Garante que não há duplicata no banco
     *
     * Retorna número puro (ex: 4003499)
     * Sempre incrementa, mesmo se o código não foi inserido ainda
     *
     * @param PDO $db Conexão com banco
     * @param string $tipo Tipo de código (material, produto, fornecedor, cliente, os)
     * @param int $tentativas Número de tentativas antes de falhar
     * @return string Código único gerado (número puro)
     */
    public static function criarCodigoUnico($db, $tipo = 'material', $tentativas = 100) {
        $tipo = strtolower($tipo);

        // Determinar tabela e coluna baseado no tipo
        $config = [
            'material' => ['materias_primas', 'codigo'],
            'produto' => ['produtos', 'codigo'],
            'fornecedor' => ['fornecedores', 'razao_social'],
            'cliente' => ['clientes', 'codigo'],
            'os' => ['ordens_servico', 'numero'],
        ];

        if (!isset($config[$tipo])) {
            throw new Exception("Tipo de código inválido: $tipo");
        }

        list($tabela, $coluna) = $config[$tipo];

        // Obter sequência inicial
        $sequencia = self::obterProximaSequencia($db, $tabela, $coluna);

        // Usar variável estática para garantir incremento mesmo sem inserção
        static $ultimo_codigo_por_tipo = [];

        // Se há um código anterior deste tipo, começar a partir dele
        if (isset($ultimo_codigo_por_tipo[$tipo])) {
            $seq_test = (int)$ultimo_codigo_por_tipo[$tipo] + 1;
            if ($seq_test > $sequencia) {
                $sequencia = $seq_test;
            }
        }

        // Tentar criar código único com múltiplas tentativas
        for ($i = 0; $i < $tentativas; $i++) {
            // Gerar número puro sequencial
            $codigo = (string)($sequencia + $i);

            // Verificar se já existe (com tratamento de erro)
            try {
                $verificacao = $db->prepare("SELECT COUNT(*) as cnt FROM $tabela WHERE $coluna = ?");
                $verificacao->execute([$codigo]);
                $resultado = $verificacao->fetch(PDO::FETCH_ASSOC);

                // Se não existe, retornar código
                if ($resultado['cnt'] == 0) {
                    // Guardar código gerado para próxima chamada
                    $ultimo_codigo_por_tipo[$tipo] = $codigo;
                    return $codigo;
                }
            } catch (Exception $e) {
                // Se erro na query, tentar próximo
                continue;
            }
        }

        // Se chegou aqui, nenhum código único foi encontrado
        throw new Exception(
            "Não foi possível gerar código único para tipo '$tipo' após $tentativas tentativas. " .
            "Sequência inicial: $sequencia, última tentada: " . ($sequencia + $tentativas - 1)
        );
    }

    /**
     * Formata um código para exibição
     *
     * @param string $codigo Código JOTEC
     * @return string Código formatado para exibição
     */
    public static function formatarParaExibicao($codigo) {
        // Adiciona espaços e formatação visual
        if (preg_match('/^JOTEC-(\d{6})$/', $codigo, $matches)) {
            return "JOTEC-" . substr($matches[1], 0, 3) . " " . substr($matches[1], 3);
        }
        return $codigo;
    }

    /**
     * Retorna as constantes de padrão por tipo
     *
     * @param string $tipo Tipo de padrão
     * @return string Padrão de formato
     */
    public static function obterPadrao($tipo) {
        $tipo = strtoupper('PADRAO_' . str_replace('-', '_', $tipo));

        if (defined("self::$tipo")) {
            return constant("self::$tipo");
        }

        return self::PADRAO_MATERIAL; // Padrão padrão
    }
}

?>
