<?php
/**
 * Componentes de Botões Estilo Nomus
 *
 * Padrão Nomus com Tailwind CSS v4
 * Inspirado em: https://nomus.com.br/erpindustrial
 *
 * Uso:
 * echo btn_primary('Salvar', 'onClick="salvar()"');
 * echo btn_success('✓ Confirmar', 'type="submit"');
 * echo btn_danger('🗑️ Deletar', 'onclick="deletar()"');
 */

/**
 * Botão Primário (Ações principais)
 * Cor: Azul
 * Uso: Salvar, Criar, Enviar
 */
function btn_primary($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Sucesso (Confirmar, Aprovar)
 * Cor: Verde
 * Uso: Confirmar, Aprovar, Liberar
 */
function btn_success($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Perigo (Deletar, Recusar)
 * Cor: Vermelho
 * Uso: Deletar, Recusar, Cancelar processo
 */
function btn_danger($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Aviso (Atenção, Processando)
 * Cor: Âmbar/Laranja
 * Uso: Processando, Revisão, Atenção
 */
function btn_warning($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Info (Visualizar, Detalhes)
 * Cor: Ciano/Azul claro
 * Uso: Ver detalhes, Info, Visualizar
 */
function btn_info($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Secundário (Ações menos importantes)
 * Cor: Cinza
 * Uso: Cancelar, Voltar, Ações secundárias
 */
function btn_secondary($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-4 py-2 bg-gray-400 hover:bg-gray-500 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Outline (Bordo, sem fundo)
 * Uso: Ações alternativas
 */
function btn_outline($label, $color = 'blue', $attrs = '', $icon = '') {
    $colors = [
        'blue' => 'border-blue-600 text-blue-600 hover:bg-blue-50',
        'green' => 'border-green-600 text-green-600 hover:bg-green-50',
        'red' => 'border-red-600 text-red-600 hover:bg-red-50',
        'amber' => 'border-amber-500 text-amber-500 hover:bg-amber-50',
    ];
    $color_class = $colors[$color] ?? $colors['blue'];

    return "<button $attrs class='px-4 py-2 border-2 $color_class font-semibold rounded-lg transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão Ícone (Apenas ícone, sem texto)
 * Uso: Ações rápidas em tabelas
 */
function btn_icon($icon, $color = 'blue', $attrs = '', $tooltip = '') {
    $colors = [
        'blue' => 'bg-blue-100 text-blue-600 hover:bg-blue-200',
        'green' => 'bg-green-100 text-green-600 hover:bg-green-200',
        'red' => 'bg-red-100 text-red-600 hover:bg-red-200',
        'amber' => 'bg-amber-100 text-amber-500 hover:bg-amber-200',
        'cyan' => 'bg-cyan-100 text-cyan-600 hover:bg-cyan-200',
        'purple' => 'bg-purple-100 text-purple-600 hover:bg-purple-200',
    ];
    $color_class = $colors[$color] ?? $colors['blue'];
    $title = $tooltip ? "title='$tooltip'" : '';

    return "<button $attrs $title class='w-8 h-8 $color_class rounded-lg flex items-center justify-center transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon
    </button>";
}

/**
 * Grupo de Botões (Inline)
 * Uso: Agrupar botões relacionados
 */
function btn_group($buttons) {
    return "<div class='flex gap-2 flex-wrap'>
        " . implode("\n        ", $buttons) . "
    </div>";
}

/**
 * Botão Grande (CTA - Call To Action)
 * Uso: Ações principais, bem visíveis
 */
function btn_large($label, $attrs = '', $icon = '') {
    return "<button $attrs class='px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-lg rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'>
        $icon $label
    </button>";
}

/**
 * Botão com Badge (contador ou status)
 * Uso: Mostrar quantidade de itens
 */
function btn_with_badge($label, $badge_text, $color = 'blue', $attrs = '') {
    return "<button $attrs class='px-4 py-2 bg-$color-600 hover:bg-$color-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer relative disabled:opacity-50 disabled:cursor-not-allowed'>
        $label
        <span class='ml-2 inline-block px-2 py-0.5 bg-$color-900 rounded-full text-sm'>$badge_text</span>
    </button>";
}

/**
 * Status Badge (apenas exibição)
 * Uso: Mostrar status de um item
 */
function badge_status($status, $label) {
    $statuses = [
        'pendente' => 'bg-gray-100 text-gray-700 border border-gray-300',
        'processando' => 'bg-amber-100 text-amber-700 border border-amber-300',
        'aprovado' => 'bg-green-100 text-green-700 border border-green-300',
        'rejeitado' => 'bg-red-100 text-red-700 border border-red-300',
        'aguardando' => 'bg-blue-100 text-blue-700 border border-blue-300',
        'concluido' => 'bg-cyan-100 text-cyan-700 border border-cyan-300',
    ];
    $class = $statuses[$status] ?? $statuses['pendente'];

    return "<span class='inline-block px-3 py-1 rounded-full text-xs font-semibold $class'>$label</span>";
}

/**
 * Status Indicator (ponto colorido + texto)
 * Uso: Indicar status visual rápido
 */
function status_indicator($status, $label) {
    $statuses = [
        'pendente' => 'bg-gray-400',
        'processando' => 'bg-amber-500 animate-pulse',
        'ok' => 'bg-green-500',
        'erro' => 'bg-red-500',
        'aguardando' => 'bg-blue-500',
    ];
    $color = $statuses[$status] ?? $statuses['pendente'];

    return "<div class='flex items-center gap-2'>
        <div class='w-3 h-3 rounded-full $color'></div>
        <span class='text-sm'>$label</span>
    </div>";
}

/**
 * Action Menu (dropdown de ações)
 * Uso: Múltiplas ações para um item
 */
function action_menu($items) {
    $html = "<div class='relative inline-block group'>
        <button class='px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition-all'>
            ⋮ Ações
        </button>
        <div class='hidden group-hover:block absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-xl z-10'>
            <div class='py-2'>";

    foreach ($items as $item) {
        $label = $item['label'] ?? '';
        $onclick = $item['onclick'] ?? '';
        $icon = $item['icon'] ?? '';
        $color = $item['color'] ?? 'gray';

        $colors = [
            'green' => 'hover:bg-green-50 text-green-600',
            'red' => 'hover:bg-red-50 text-red-600',
            'blue' => 'hover:bg-blue-50 text-blue-600',
            'amber' => 'hover:bg-amber-50 text-amber-600',
            'gray' => 'hover:bg-gray-50 text-gray-600',
        ];
        $color_class = $colors[$color] ?? $colors['gray'];

        $html .= "<button onclick=\"$onclick\" class='w-full text-left px-4 py-2 $color_class font-semibold transition-all'>
            $icon $label
        </button>";
    }

    $html .= "            </div>
        </div>
    </div>";

    return $html;
}

/**
 * Loading Button (com animação)
 * Uso: Indicar que está processando
 */
function btn_loading($label, $attrs = '') {
    return "<button $attrs disabled class='px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md cursor-not-allowed opacity-75'>
        <span class='inline-block animate-spin mr-2'>⟳</span>
        $label
    </button>";
}

/**
 * Link estilizado como botão
 * Uso: Links que parecem botões
 */
function btn_link($label, $href, $color = 'blue', $icon = '') {
    return "<a href='$href' class='inline-block px-4 py-2 bg-$color-600 hover:bg-$color-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 no-underline'>
        $icon $label
    </a>";
}

?>
