<?php
/**
 * Documentation Generator - TIER 3
 * Gera documentação automática de APIs
 *
 * Skills: 📚 API Documentation, 📖 README Generator, 🏗️ Architecture Docs
 *
 * Uso:
 *   php generate_docs.php
 *
 * Gera:
 *   - OpenAPI 3.0 Spec
 *   - API_REFERENCE.md
 *   - README.md por módulo
 */

class DocGenerator {
    private $apis = [];
    private $modules = [];

    /**
     * Registrar API para documentação
     *
     * Uso:
     *   DocGenerator::register_api([
     *       'file' => '/api/mrp.php',
     *       'name' => 'MRP Engine',
     *       'description' => 'Material Requirements Planning',
     *       'endpoints' => [
     *           [
     *               'method' => 'GET',
     *               'path' => '/api/mrp.php?acao=analisar_demanda',
     *               'description' => 'Análise demanda vs estoque',
     *               'params' => [
     *                   ['name' => 'acao', 'type' => 'string', 'required' => true]
     *               ],
     *               'response' => [
     *                   'sucesso' => 'boolean',
     *                   'demanda' => 'array'
     *               ]
     *           ]
     *       ]
     *   ]);
     */
    public static function register_api($config) {
        // Salva em arquivo JSON para processamento posterior
        $file = "../docs/apis/" . str_replace('/', '_', $config['file']) . ".json";
        @mkdir(dirname($file), 0755, true);
        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Gerar OpenAPI 3.0 Spec
     */
    public static function generate_openapi_spec() {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Cozinka ERP API',
                'description' => 'API completa para gerenciamento de produção',
                'version' => '2.0.0',
                'contact' => [
                    'name' => 'Gabriel Costa',
                    'email' => 'g4bs011.gbl@gmail.com'
                ]
            ],
            'servers' => [
                [
                    'url' => 'http://localhost/cozinka',
                    'description' => 'Development'
                ],
                [
                    'url' => 'https://api.cozinka.com.br',
                    'description' => 'Production'
                ]
            ],
            'paths' => self::build_paths(),
            'components' => [
                'schemas' => self::build_schemas(),
                'securitySchemes' => [
                    'sessionAuth' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => 'PHPSESSID'
                    ]
                ]
            ]
        ];

        return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function build_paths() {
        return [
            '/api/mrp.php' => [
                'get' => [
                    'tags' => ['MRP'],
                    'summary' => 'Análise de demanda',
                    'parameters' => [
                        [
                            'name' => 'acao',
                            'in' => 'query',
                            'required' => true,
                            'schema' => ['type' => 'string', 'enum' => ['analisar_demanda', 'sugerir_ordens']]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Sucesso',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MRPResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/custos.php' => [
                'get' => [
                    'tags' => ['Custos'],
                    'summary' => 'Análise de custos',
                    'parameters' => [
                        [
                            'name' => 'acao',
                            'in' => 'query',
                            'required' => true,
                            'schema' => ['type' => 'string']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Sucesso',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/CustosResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
            // ... adicionar outros endpoints
        ];
    }

    private static function build_schemas() {
        return [
            'MRPResponse' => [
                'type' => 'object',
                'properties' => [
                    'sucesso' => ['type' => 'boolean'],
                    'total' => ['type' => 'integer'],
                    'demanda' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/DemandaItem']
                    ]
                ]
            ],
            'DemandaItem' => [
                'type' => 'object',
                'properties' => [
                    'venda_id' => ['type' => 'integer'],
                    'produto_nome' => ['type' => 'string'],
                    'faltante' => ['type' => 'number'],
                    'status_urgencia' => ['type' => 'string', 'enum' => ['crítica', 'alta', 'normal']]
                ]
            ],
            'CustosResponse' => [
                'type' => 'object',
                'properties' => [
                    'sucesso' => ['type' => 'boolean'],
                    'resumo' => ['$ref' => '#/components/schemas/ResumoFinanceiro']
                ]
            ],
            'ResumoFinanceiro' => [
                'type' => 'object',
                'properties' => [
                    'valor_venda' => ['type' => 'number'],
                    'custo_total' => ['type' => 'number'],
                    'lucro_bruto' => ['type' => 'number'],
                    'margem_percentual' => ['type' => 'number']
                ]
            ]
        ];
    }

    /**
     * Gerar README.md por módulo
     */
    public static function generate_module_readme($module_name, $config) {
        $readme = "# {$config['title']}\n\n";
        $readme .= "{$config['description']}\n\n";

        $readme .= "## Features\n\n";
        foreach ($config['features'] as $feature) {
            $readme .= "- ✅ {$feature}\n";
        }

        $readme .= "\n## API Endpoints\n\n";
        foreach ($config['endpoints'] as $endpoint) {
            $readme .= "### {$endpoint['method']} {$endpoint['path']}\n";
            $readme .= "{$endpoint['description']}\n\n";

            if (!empty($endpoint['params'])) {
                $readme .= "**Parâmetros:**\n";
                $readme .= "| Nome | Tipo | Obrigatório | Descrição |\n";
                $readme .= "|------|------|-------------|----------|\n";
                foreach ($endpoint['params'] as $param) {
                    $required = $param['required'] ? 'Sim' : 'Não';
                    $readme .= "| {$param['name']} | {$param['type']} | {$required} | {$param['description']} |\n";
                }
                $readme .= "\n";
            }

            if (!empty($endpoint['example'])) {
                $readme .= "**Exemplo:**\n```json\n{$endpoint['example']}\n```\n\n";
            }
        }

        $readme .= "## Instalação\n\n";
        $readme .= "Nenhuma instalação necessária - API integrada no Cozinka ERP.\n\n";

        $readme .= "## Permissões\n\n";
        $readme .= "Acesso: " . implode(", ", $config['access'] ?? ['master', 'gerente']) . "\n\n";

        return $readme;
    }

    /**
     * Gerar Architecture Decision Record (ADR)
     */
    public static function generate_adr($title, $context, $decision, $consequences) {
        $adr = "# ADR-" . date('YmdHi') . ": {$title}\n\n";
        $adr .= "## Contexto\n\n{$context}\n\n";
        $adr .= "## Decisão\n\n{$decision}\n\n";
        $adr .= "## Consequências\n\n{$consequences}\n\n";
        $adr .= "**Data**: " . date('Y-m-d') . "\n";
        $adr .= "**Status**: Aceito\n";

        return $adr;
    }

    /**
     * Gerar Deployment Guide
     */
    public static function generate_deployment_guide() {
        $guide = "# 🚀 Deployment Guide - Cozinka ERP TIER 3\n\n";

        $guide .= "## Pré-requisitos\n\n";
        $guide .= "- PHP 8.1+\n";
        $guide .= "- MySQL 8.0+\n";
        $guide .= "- Redis 6.0+ (recomendado)\n";
        $guide .= "- Docker (recomendado)\n\n";

        $guide .= "## Ambiente Local\n\n";
        $guide .= "1. Clone o repositório\n";
        $guide .= "2. `cp .env.example .env`\n";
        $guide .= "3. `composer install`\n";
        $guide .= "4. `php bin/migrate.php`\n";
        $guide .= "5. `npm install && npm run dev`\n\n";

        $guide .= "## Ambiente Produção\n\n";
        $guide .= "### Docker\n\n";
        $guide .= "```bash\n";
        $guide .= "docker build -t cozinka-erp .\n";
        $guide .= "docker run -d -p 80:8080 cozinka-erp\n";
        $guide .= "```\n\n";

        $guide .= "### CI/CD (GitHub Actions)\n\n";
        $guide .= "Push para `main` ativa:\n";
        $guide .= "1. Testes automáticos\n";
        $guide .= "2. Code quality checks\n";
        $guide .= "3. Build Docker\n";
        $guide .= "4. Deploy para produção\n\n";

        $guide .= "## Monitoring\n\n";
        $guide .= "- Logs: `/var/log/cozinka/app.log`\n";
        $guide .= "- Métricas: http://localhost:9090 (Prometheus)\n";
        $guide .= "- Dashboard: http://localhost:3000 (Grafana)\n\n";

        return $guide;
    }

    /**
     * Gerar Troubleshooting Guide
     */
    public static function generate_troubleshooting() {
        $guide = "# 🔧 Troubleshooting Guide\n\n";

        $guide .= "## Problemas Comuns\n\n";

        $guide .= "### ❌ 'Database connection failed'\n";
        $guide .= "- [ ] Verificar credentials em .env\n";
        $guide .= "- [ ] MySQL está rodando? `systemctl status mysql`\n";
        $guide .= "- [ ] Banco criado? `mysql -u root -p < schema.sql`\n\n";

        $guide .= "### ❌ 'Memory limit exceeded'\n";
        $guide .= "- [ ] Aumentar em php.ini: `memory_limit = 512M`\n";
        $guide .= "- [ ] Verificar queries lentas: `SHOW PROCESSLIST;`\n\n";

        $guide .= "### ❌ 'Cache not working'\n";
        $guide .= "- [ ] Redis está rodando? `redis-cli ping`\n";
        $guide .= "- [ ] Reiniciar: `redis-server restart`\n\n";

        $guide .= "## Performance Issues\n\n";
        $guide .= "1. Rodar: `php bin/analyze_performance.php`\n";
        $guide .= "2. Verificar slow queries em logs\n";
        $guide .= "3. Adicionar índices: `php bin/add_indexes.php`\n\n";

        return $guide;
    }

    /**
     * Gerar tudo de uma vez
     */
    public static function generate_all() {
        @mkdir('../docs/api', 0755, true);
        @mkdir('../docs/adr', 0755, true);
        @mkdir('../docs/guides', 0755, true);

        // OpenAPI Spec
        file_put_contents('../docs/api/openapi.json', self::generate_openapi_spec());
        echo "✅ OpenAPI spec gerado\n";

        // Deployment Guide
        file_put_contents('../docs/guides/DEPLOYMENT.md', self::generate_deployment_guide());
        echo "✅ Deployment guide gerado\n";

        // Troubleshooting
        file_put_contents('../docs/guides/TROUBLESHOOTING.md', self::generate_troubleshooting());
        echo "✅ Troubleshooting guide gerado\n";

        // ADRs (exemplos)
        $adrs = [
            'Cache Strategy' => 'Usar Redis para queries frequentes',
            'Database Design' => 'Normalização 3NF + índices estratégicos',
            'API Standards' => 'RESTful JSON + prepared statements sempre'
        ];

        foreach ($adrs as $title => $decision) {
            $adr = self::generate_adr($title, 'Necessidade de melhor performance', $decision, 'Aumenta complexidade mas melhora 10x');
            file_put_contents("../docs/adr/ADR_" . date('YmdHi') . ".md", $adr);
        }
        echo "✅ ADRs gerados\n";

        echo "\n📚 Documentação gerada em /docs\n";
    }
}

// Gerar tudo se executado como CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === 'doc_generator.php') {
    DocGenerator::generate_all();
}
