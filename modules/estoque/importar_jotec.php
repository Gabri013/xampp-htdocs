<?php
/**
 * IMPORTAR CADASTRO JOTEC - Produtos/Insumos
 *
 * Módulo para:
 * 1. Upload de arquivo Excel
 * 2. Validação de dados
 * 3. Importação para banco de dados
 * 4. Relatório de resultado
 */

require_once '../../config/config.php';
require_once '../../includes/workflow.php';

$db = getDB();
requirePermission(['master', 'estoque', 'gerente']);

$resultado = null;
$arquivoUpload = $_FILES['arquivo'] ?? null;
$acao = $_POST['acao'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Cadastro JOTEC - Cozinka ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                📊 Importar Cadastro JOTEC
            </h1>
            <p class="text-gray-600 mt-2">Importar matérias primas e insumos do arquivo Excel</p>
        </div>

        <!-- Abas -->
        <div class="bg-white rounded-lg shadow-sm mb-6">
            <div class="flex border-b">
                <button class="flex-1 py-4 px-6 text-center font-semibold text-blue-600 border-b-2 border-blue-600">
                    📁 Upload de Arquivo
                </button>
                <button class="flex-1 py-4 px-6 text-center font-semibold text-gray-600 hover:text-blue-600">
                    📋 Pré-visualização
                </button>
                <button class="flex-1 py-4 px-6 text-center font-semibold text-gray-600 hover:text-blue-600">
                    ✅ Resultado
                </button>
            </div>

            <!-- Aba 1: Upload -->
            <div class="p-8">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Instruções -->
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded">
                        <h3 class="font-semibold text-blue-900 mb-2">📌 Instruções:</h3>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>✅ Arquivo deve estar em formato Excel (.xls ou .xlsx)</li>
                            <li>✅ Primeira linha deve conter os headers</li>
                            <li>✅ Colunas esperadas: Código, Descrição, Fornecedor, Preço, Unidade</li>
                            <li>✅ Todas as abas do arquivo serão importadas</li>
                            <li>✅ Validação anti-duplicidade será aplicada</li>
                        </ul>
                    </div>

                    <!-- Upload -->
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-500 transition">
                        <input type="file" name="arquivo" id="arquivo" accept=".xls,.xlsx" class="hidden" required>
                        <label for="arquivo" class="cursor-pointer">
                            <div class="text-4xl mb-3">📤</div>
                            <p class="text-lg font-semibold text-gray-700">Clique ou arraste o arquivo</p>
                            <p class="text-sm text-gray-500">Formatos: .xls, .xlsx</p>
                        </label>
                    </div>

                    <!-- Arquivo selecionado -->
                    <div id="fileInfo" class="hidden bg-green-50 p-4 rounded border-l-4 border-green-500">
                        <p class="text-sm text-green-800">
                            📁 Arquivo: <span id="fileName" class="font-semibold"></span>
                        </p>
                    </div>

                    <!-- Opções -->
                    <div class="space-y-4">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="validar_duplicidade" checked class="w-5 h-5">
                            <span class="text-gray-700">✅ Validar duplicidade (não importar registros duplicados)</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="atualizar_existentes" checked class="w-5 h-5">
                            <span class="text-gray-700">🔄 Atualizar registros existentes (por código)</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" name="registrar_auditoria" checked class="w-5 h-5">
                            <span class="text-gray-700">📝 Registrar auditoria (quem, quando, o que)</span>
                        </label>
                    </div>

                    <!-- Botões -->
                    <div class="flex gap-4">
                        <button type="submit" name="acao" value="preview" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                            👁️ Pré-visualizar Dados
                        </button>
                        <button type="submit" name="acao" value="importar" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                            📥 Importar Agora
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info útil -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    📊 Estrutura esperada do arquivo
                </h3>
                <div class="text-sm bg-gray-50 p-3 rounded font-mono">
                    <p>Coluna A: <span class="text-blue-600">Código</span></p>
                    <p>Coluna B: <span class="text-blue-600">Descrição</span></p>
                    <p>Coluna C: <span class="text-blue-600">Fornecedor</span></p>
                    <p>Coluna D: <span class="text-blue-600">Preço</span></p>
                    <p>Coluna E: <span class="text-blue-600">Unidade</span></p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    ✅ Validações aplicadas
                </h3>
                <ul class="text-sm space-y-2">
                    <li>✓ Código único (não duplicar)</li>
                    <li>✓ Descrição obrigatória</li>
                    <li>✓ Fornecedor válido</li>
                    <li>✓ Preço > 0</li>
                    <li>✓ Unidade válida</li>
                    <li>✓ Anti-duplicidade (HASH)</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Drag and drop
        const fileInput = document.getElementById('arquivo');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        document.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.currentTarget.classList.add('bg-blue-50');
        });

        document.addEventListener('dragleave', (e) => {
            e.currentTarget.classList.remove('bg-blue-50');
        });

        document.addEventListener('drop', (e) => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                updateFileInfo();
            }
        });

        fileInput.addEventListener('change', updateFileInfo);

        function updateFileInfo() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                fileInfo.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
