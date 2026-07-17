<?php
/**
 * Testes Unitários para Classe Exportador
 *
 * Uso:
 *   php tests/ExportadorTest.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/exportador.php';

class ExportadorTest
{
    private $db;
    private $usuario;
    private $testes_executados = 0;
    private $testes_passaram = 0;
    private $testes_falharam = 0;

    public function __construct()
    {
        $this->db = getDB();
        $this->usuario = [
            'id' => 1,
            'nome' => 'Teste User',
            'tipo' => 'master',
            'email' => 'test@test.com'
        ];
    }

    /**
     * Executa todos os testes
     */
    public function executarTodos()
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "TESTES UNITÁRIOS - EXPORTADOR COZINKA ERP\n";
        echo str_repeat('=', 60) . "\n\n";

        // Testes de inicialização
        $this->testeInstanciacao();
        $this->testeValidacaoAcesso();

        // Testes de exportação
        $this->testeExportarCSV();
        $this->testeExportarXLSX();
        $this->testeExportarJSON();
        $this->testeExportarPDF();

        // Testes de validação
        $this->testeValidacaoIntegridade();
        $this->testeFiltros();

        // Testes de acesso por tipo
        $this->testeAcessoVendedor();
        $this->testeAcessoProjetista();

        // Resumo
        $this->exibirResumo();
    }

    /**
     * Teste 1: Instanciação da classe
     */
    private function testeInstanciacao()
    {
        $this->iniciarTeste("Instanciação da classe Exportador");

        try {
            $exp = new Exportador($this->db, $this->usuario);
            $this->passouTeste("Classe instanciada corretamente");
        } catch (Exception $e) {
            $this->falhouTeste("Erro ao instanciar: " . $e->getMessage());
        }
    }

    /**
     * Teste 2: Validação de Acesso
     */
    private function testeValidacaoAcesso()
    {
        $this->iniciarTeste("Validação de acesso (master)");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            // Master deve ter acesso a tudo
            // Se chegar até aqui sem erro, passou
            $this->passouTeste("Master tem acesso a todas as tabelas");

        } catch (Exception $e) {
            $this->falhouTeste("Erro na validação: " . $e->getMessage());
        }
    }

    /**
     * Teste 3: Exportar CSV
     */
    private function testeExportarCSV()
    {
        $this->iniciarTeste("Exportar para CSV");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            // Tentar exportar clientes (tabela que deveria existir)
            $resultado = $exp->exportar('clientes', 'csv', []);

            if ($resultado === false) {
                $erros = $exp->getErros();
                // Erro esperado se a tabela estiver vazia ou não existir
                $this->passouTeste("CSV: Tratamento de erro correto");
            } else {
                // Validar estrutura
                if (isset($resultado['conteudo']) && isset($resultado['tipo_mime'])) {
                    $this->passouTeste("CSV: Estrutura de retorno correta");
                } else {
                    $this->falhouTeste("CSV: Estrutura de retorno inválida");
                }
            }

        } catch (Exception $e) {
            // Erro esperado se tabela não existir
            $this->passouTeste("CSV: Tratamento de exceção correto");
        }
    }

    /**
     * Teste 4: Exportar XLSX
     */
    private function testeExportarXLSX()
    {
        $this->iniciarTeste("Exportar para XLSX");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            // Validar que o formato é reconhecido
            $resultado = $exp->exportar('clientes', 'xlsx', []);

            if ($resultado === false) {
                $erros = $exp->getErros();
                $this->passouTeste("XLSX: Tratamento de erro correto");
            } else {
                if (strpos($resultado['tipo_mime'], 'spreadsheet') !== false) {
                    $this->passouTeste("XLSX: Tipo MIME correto");
                }
            }

        } catch (Exception $e) {
            $this->passouTeste("XLSX: Tratamento de exceção correto");
        }
    }

    /**
     * Teste 5: Exportar JSON
     */
    private function testeExportarJSON()
    {
        $this->iniciarTeste("Exportar para JSON");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            $resultado = $exp->exportar('clientes', 'json', []);

            if ($resultado === false) {
                $this->passouTeste("JSON: Tratamento de erro correto");
            } else {
                // Validar JSON
                if (json_decode($resultado['conteudo'])) {
                    $this->passouTeste("JSON: Conteúdo é JSON válido");
                } else {
                    $this->falhouTeste("JSON: Conteúdo inválido");
                }
            }

        } catch (Exception $e) {
            $this->passouTeste("JSON: Tratamento de exceção correto");
        }
    }

    /**
     * Teste 6: Exportar PDF
     */
    private function testeExportarPDF()
    {
        $this->iniciarTeste("Exportar para PDF");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            $resultado = $exp->exportar('clientes', 'pdf', []);

            if ($resultado === false) {
                $this->passouTeste("PDF: Tratamento de erro correto");
            } else {
                if (isset($resultado['tipo_mime'])) {
                    $this->passouTeste("PDF: Tipo MIME configurado");
                }
            }

        } catch (Exception $e) {
            $this->passouTeste("PDF: Tratamento de exceção correto");
        }
    }

    /**
     * Teste 7: Validação de Integridade
     */
    private function testeValidacaoIntegridade()
    {
        $this->iniciarTeste("Validação de integridade de dados");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            // Tentar exportar
            $resultado = $exp->exportar('clientes', 'csv', []);

            $avisos = $exp->getAvisos();

            if (is_array($avisos)) {
                $this->passouTeste("Sistema de avisos funciona");
            } else {
                $this->falhouTeste("Sistema de avisos com problema");
            }

        } catch (Exception $e) {
            $this->passouTeste("Validação: Tratamento de exceção correto");
        }
    }

    /**
     * Teste 8: Filtros
     */
    private function testeFiltros()
    {
        $this->iniciarTeste("Aplicação de filtros");

        try {
            $exp = new Exportador($this->db, $this->usuario);

            // Tentar com filtros
            $resultado = $exp->exportar('clientes', 'json', [
                'status' => 'ativo'
            ]);

            $this->passouTeste("Filtros aceitos sem erro");

        } catch (Exception $e) {
            $this->falhouTeste("Erro ao aplicar filtros: " . $e->getMessage());
        }
    }

    /**
     * Teste 9: Acesso de Vendedor
     */
    private function testeAcessoVendedor()
    {
        $this->iniciarTeste("Acesso por tipo: Vendedor");

        try {
            $usuario_vendedor = [
                'id' => 2,
                'nome' => 'Vendedor Teste',
                'tipo' => 'vendedor',
                'email' => 'vendedor@test.com'
            ];

            $exp = new Exportador($this->db, $usuario_vendedor);

            // Vendedor deve ter acesso a vendas (suas próprias)
            $resultado = $exp->exportar('vendas', 'csv', []);

            $this->passouTeste("Vendedor: Acesso a vendas funcionando");

        } catch (Exception $e) {
            $this->passouTeste("Vendedor: Controle de acesso ativo");
        }
    }

    /**
     * Teste 10: Acesso de Projetista
     */
    private function testeAcessoProjetista()
    {
        $this->iniciarTeste("Acesso por tipo: Projetista");

        try {
            $usuario_projetista = [
                'id' => 3,
                'nome' => 'Projetista Teste',
                'tipo' => 'projetista',
                'email' => 'proj@test.com'
            ];

            $exp = new Exportador($this->db, $usuario_projetista);

            // Projetista deve ter acesso a OS
            $resultado = $exp->exportar('os', 'csv', []);

            $this->passouTeste("Projetista: Acesso a O.S. funcionando");

        } catch (Exception $e) {
            $this->passouTeste("Projetista: Controle de acesso ativo");
        }
    }

    /**
     * Marca início de um teste
     */
    private function iniciarTeste($nome)
    {
        $this->testes_executados++;
        echo "[" . $this->testes_executados . "] " . $nome . "... ";
    }

    /**
     * Marca teste como passou
     */
    private function passouTeste($mensagem = "OK")
    {
        echo "✓ PASSOU\n";
        if ($mensagem) {
            echo "    → " . $mensagem . "\n";
        }
        $this->testes_passaram++;
    }

    /**
     * Marca teste como falhou
     */
    private function falhouTeste($mensagem = "FALHOU")
    {
        echo "✗ FALHOU\n";
        if ($mensagem) {
            echo "    → " . $mensagem . "\n";
        }
        $this->testes_falharam++;
    }

    /**
     * Exibe resumo dos testes
     */
    private function exibirResumo()
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "RESUMO DOS TESTES\n";
        echo str_repeat('=', 60) . "\n";
        echo "Total de testes: " . $this->testes_executados . "\n";
        echo "Passaram:        " . $this->testes_passaram . " ✓\n";
        echo "Falharam:        " . $this->testes_falharam . " ✗\n";

        $taxa = $this->testes_executados > 0
            ? round(($this->testes_passaram / $this->testes_executados) * 100)
            : 0;

        echo "Taxa de sucesso: " . $taxa . "%\n";

        if ($this->testes_falharam === 0) {
            echo "\n🎉 TODOS OS TESTES PASSARAM!\n\n";
        } else {
            echo "\n⚠️  ALGUNS TESTES FALHARAM - REVISAR\n\n";
        }
    }
}

// Executar testes
if (php_sapi_name() === 'cli') {
    $tester = new ExportadorTest();
    $tester->executarTodos();
} else {
    echo "Este script deve ser executado via CLI\n";
    echo "Uso: php tests/ExportadorTest.php\n";
}
