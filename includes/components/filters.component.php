<?php
// includes/components/filters.component.php
// Componente de filtros dinâmicos estilo Twenty List view

function renderFilters(array $filters, string $formId = 'filtersForm', bool $autoSubmit = true): string {
    $html = '<div class="vend-filters">';
    $html .= '<form id="' . $formId . '" method="GET" class="vend-filters-form">';
    $html .= '<div class="vend-filters-grid">';
    
    foreach ($filters as $key => $config) {
        $type = $config['type'] ?? 'text';
        $label = $config['label'] ?? ucfirst($key);
        $value = $config['value'] ?? ($_GET[$key] ?? '');
        $options = $config['options'] ?? [];
        $placeholder = $config['placeholder'] ?? '';
        
        $html .= '<div class="vend-filter-item">';
        
        if ($type === 'select') {
            $html .= '<label class="vend-filter-label">' . htmlspecialchars($label) . '</label>';
            $html .= '<select name="' . $key . '" class="vend-filter-input"';
            if ($autoSubmit) $html .= ' onchange="this.form.submit()"';
            $html .= '>';
            $html .= '<option value="">' . htmlspecialchars($placeholder ?: 'Todos') . '</option>';
            foreach ($options as $optValue => $optLabel) {
                $selected = $value == $optValue ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars($optValue) . '"' . $selected . '>' . htmlspecialchars($optLabel) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'date') {
            $html .= '<label class="vend-filter-label">' . htmlspecialchars($label) . '</label>';
            $html .= '<input type="date" name="' . $key . '" value="' . htmlspecialchars($value) . '" class="vend-filter-input"';
            if ($autoSubmit) $html .= ' onchange="this.form.submit()"';
            $html .= '>';
        } elseif ($type === 'search') {
            $html .= '<div class="vend-search-wrap">';
            $html .= '<i class="fas fa-search vend-search-icon"></i>';
            $html .= '<input type="text" name="' . $key . '" value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder ?: 'Buscar...') . '" class="vend-search-input"';
            if ($autoSubmit) $html .= ' onkeyup="debounce(() => this.form.submit(), 500)"';
            $html .= '>';
            $html .= '</div>';
        } else {
            $html .= '<label class="vend-filter-label">' . htmlspecialchars($label) . '</label>';
            $html .= '<input type="' . $type . '" name="' . $key . '" value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder) . '" class="vend-filter-input"';
            if ($autoSubmit) $html .= ' onchange="this.form.submit()"';
            $html .= '>';
        }
        
        $html .= '</div>';
    }
    
    // Campo de busca rápida
    $html .= '<div class="vend-filter-item vend-filter-search">';
    $html .= '<div class="vend-search-wrap">';
    $html .= '<i class="fas fa-search vend-search-icon"></i>';
    $searchValue = $_GET['search'] ?? '';
    $html .= '<input type="text" name="search" value="' . htmlspecialchars($searchValue) . '" placeholder="Buscar..." class="vend-search-input" onkeyup="debounce(() => this.form.submit(), 500)">';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</form>';
    $html .= '</div>';
    
    return $html;
}
?>