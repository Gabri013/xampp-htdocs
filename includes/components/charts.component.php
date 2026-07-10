<?php
// includes/components/charts.component.php
// Charts estilo Twenty Dashboard

function renderChart(string $type, string $id, string $label, array $datasets, array $options = []): string {
    $defaultOptions = [
        'responsive' => true,
        'maintainAspectRatio' => false,
        'plugins' => [
            'legend' => ['display' => false],
            'tooltip' => ['backgroundColor' => '#1a1a1a', 'titleColor' => '#fff', 'bodyColor' => '#ddd'],
        ],
        'scales' => [
            'x' => ['grid' => ['display' => false], 'ticks' => ['color' => '#888']],
            'y' => ['grid' => ['color' => 'rgba(0,0,0,.05)'], 'ticks' => ['color' => '#888']],
        ],
    ];
    
    $options = array_merge_recursive($defaultOptions, $options);
    
    $html = '<div class="vend-chart-card">';
    $html .= '<div class="vend-chart-head"><span class="vend-chart-title">' . htmlspecialchars($label) . '</span></div>';
    $html .= '<div class="vend-chart-body"><canvas id="' . $id . '"></canvas></div>';
    $html .= '</div>';
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '  const ctx = document.getElementById("' . $id . '").getContext("2d");';
    $html .= '  new Chart(ctx, {';
    $html .= '    type: "' . $type . '",';
    $html .= '    data: {';
    $html .= '      labels: ' . json_encode(array_keys($datasets)) . ',';
    $html .= '      datasets: [{';
    $html .= '        data: ' . json_encode(array_values($datasets)) . ',';
    $html .= '        backgroundColor: ["#D85A30", "#007bff", "#28a745", "#ffc107", "#17a2b8", "#6c757d"],';
    $html .= '        borderColor: "#fff",';
    $html .= '      }]' . ($type === 'line' ? ',{borderColor:"#D85A30",backgroundColor:"rgba(216,90,48,.1)",fill:true,data:' . json_encode(array_values($datasets)) . '}' : '');
    $html .= '    },';
    $html .= '    options: ' . json_encode($options) . '';
    $html .= '  });';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}
?>