/**
 * Script para funcionalidades mobile do sistema
 * Inclui menu hambúrguer, adaptações de interface e otimizações para toque
 */

document.addEventListener('DOMContentLoaded', function() {
    initMobileMenu();
    optimizeTouchInteractions();
});

/**
 * Inicializa o menu mobile (hambúrguer)
 */
function initMobileMenu() {
    const toggleBtn = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const closeBtn = document.getElementById('sidebarClose');
    
    if (!toggleBtn || !sidebar) return;
    
    // Abrir menu
    toggleBtn.addEventListener('click', function() {
        sidebar.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
    
    // Fechar menu
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    }
    
    // Fechar menu ao clicar em um link
    const menuLinks = sidebar.querySelectorAll('a');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.style.overflow = 'auto';
        });
    });
    
    // Fechar menu ao clicar fora dele
    document.addEventListener('click', function(e) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
}

/**
 * Otimiza interações de toque para dispositivos móveis
 */
function optimizeTouchInteractions() {
    // Aumentar área de clique dos botões em dispositivos móveis
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.style.minHeight = '44px';
        btn.style.minWidth = '44px';
    });
    
    // Adicionar feedback visual ao toque
    const clickableElements = document.querySelectorAll('button, a, .btn, [role="button"]');
    clickableElements.forEach(element => {
        element.addEventListener('touchstart', function() {
            this.style.opacity = '0.8';
        });
        
        element.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
    });
    
    // Melhorar tabelas em dispositivos móveis
    adaptTablesMobile();
}

/**
 * Adapta tabelas para exibição em cards em dispositivos móveis
 */
function adaptTablesMobile() {
    // Verificar se é dispositivo móvel
    if (window.innerWidth > 768) return;
    
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        // Não converter tabelas que já estão em cards
        if (table.closest('.table-card')) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
        
        // Adicionar atributos data aos td para exibição em cards
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index]);
                }
            });
        });
        
        // Adicionar classe para estilos CSS
        table.classList.add('table-mobile');
    });
}

/**
 * Detectar orientação do dispositivo
 */
window.addEventListener('orientationchange', function() {
    setTimeout(function() {
        adaptTablesMobile();
    }, 100);
});

/**
 * Melhorar performance de scroll em dispositivos móveis
 */
if ('ontouchstart' in window) {
    document.addEventListener('touchmove', function(e) {
        // Permitir scroll normal, mas otimizar performance
        if (e.target.closest('.table-responsive') || e.target.closest('.modal')) {
            e.target.closest('.table-responsive, .modal').style.WebkitOverflowScrolling = 'touch';
        }
    }, { passive: true });
}

/**
 * Função para abrir modais de forma responsiva
 */
function abrirModalResponsivo(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Função para fechar modais de forma responsiva
 */
function fecharModalResponsivo(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

/**
 * Detectar dispositivo específico (Xiaomi, iPhone, etc.)
 */
function detectDevice() {
    const ua = navigator.userAgent.toLowerCase();
    const device = {
        isXiaomi: /xiaomi|redmi|poco|mi 9|mi 10|mi 11|mi 12/.test(ua),
        isApple: /iphone|ipad|ipod/.test(ua),
        isAndroid: /android/.test(ua),
        isMobile: /mobile|android|iphone|ipad|phone/.test(ua),
        isTablet: /tablet|ipad|android/.test(ua) && !/mobile/.test(ua)
    };
    
    return device;
}

/**
 * Aplicar otimizações específicas por dispositivo
 */
const device = detectDevice();
if (device.isXiaomi || device.isAndroid) {
    document.body.classList.add('device-android');
} else if (device.isApple) {
    document.body.classList.add('device-ios');
}

/**
 * Melhorar entrada de dados em formulários móveis
 */
function optimizeFormsMobile() {
    const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea');
    inputs.forEach(input => {
        // Adicionar padding extra para melhor toque
        input.style.padding = '12px 15px';
        input.style.fontSize = '16px'; // Evitar zoom automático no iOS
        
        // Adicionar feedback visual
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
}

// Executar otimizações de formulário
optimizeFormsMobile();

/**
 * Suportar gestos de swipe para fechar modais
 */
function enableSwipeToClose() {
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, false);
    
    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);
    
    function handleSwipe() {
        const modal = document.querySelector('.modal.show');
        if (modal && touchEndX < touchStartX - 50) {
            // Swipe para a esquerda - fechar modal
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
}

enableSwipeToClose();
