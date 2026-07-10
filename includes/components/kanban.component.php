<?php
// includes/components/kanban.component.php
// Kanban board com drag-drop estilo Twenty

function renderKanban(array $columns, array $items, string $itemIdField = 'id', string $statusField = 'status'): string {
    $html = '<div class="vend-kanban" id="vendKanban">';
    $html .= '<div class="vend-kanban-board" id="vendKanbanBoard">';
    
    foreach ($columns as $columnKey => $column) {
        $html .= '<div class="vend-kanban-column" data-status="' . $columnKey . '" id="col-' . $columnKey . '">';
        $html .= '<div class="vend-kanban-header">';
        $html .= '<span class="vend-kanban-title">' . htmlspecialchars($column['label']) . '</span>';
        $html .= '<span class="vend-kanban-count">' . count(array_filter($items, fn($i) => ($i[$statusField] ?? null) === $columnKey)) . '</span>';
        $html .= '</div>';
        $html .= '<div class="vend-kanban-items">';
        
        foreach ($items as $item) {
            if (($item[$statusField] ?? null) === $columnKey) {
                $html .= '<div class="vend-kanban-card" draggable="true" data-id="' . $item[$itemIdField] . '">';
                $html .= '<div class="vend-kanban-card-title">' . htmlspecialchars($item['titulo'] ?? $item[$itemIdField]) . '</div>';
                if (!empty($item['subtitulo'])) {
                    $html .= '<div class="vend-kanban-card-subtitle">' . htmlspecialchars($item['subtitulo']) . '</div>';
                }
                if (!empty($item['valor'])) {
                    $html .= '<div class="vend-kanban-card-value">' . htmlspecialchars($item['valor']) . '</div>';
                }
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}
?>