<?php
// includes/components/timeline.component.php
// Timeline estilo Twenty Activity Feed

function renderTimeline(array $activities): string {
    $html = '<div class="vend-timeline">';
    
    foreach ($activities as $a) {
        $icon = $a['icon'] ?? 'fas fa-circle';
        $color = $a['color'] ?? '#D85A30';
        $time = $a['time'] ?? '';
        $title = $a['title'] ?? '';
        $description = $a['description'] ?? '';
        
        $html .= '<div class="vend-timeline-item">';
        $html .= '<div class="vend-timeline-marker" style="background:' . $color . '"><i class="' . $icon . '"></i></div>';
        $html .= '<div class="vend-timeline-content">';
        $html .= '<div class="vend-timeline-header">';
        $html .= '<span class="vend-timeline-title">' . htmlspecialchars($title) . '</span>';
        $html .= '<span class="vend-timeline-time">' . htmlspecialchars($time) . '</span>';
        $html .= '</div>';
        if ($description) {
            $html .= '<div class="vend-timeline-desc">' . htmlspecialchars($description) . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}
?>