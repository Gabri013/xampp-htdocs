<?php
// includes/scripts.php - Scripts JS padrão do ERP Cozinca
// Este arquivo deve ser incluído no final do body antes do footer

// Painéis com auto-atualização (listas somente leitura; nunca formulários
// de criação/edição, para não perder dados digitados).
$czAutoRefreshPaths = [
    '/os/vendedor.php', '/os/gerente.php', '/os/producao.php',
    '/os/dashboard_producao.php', '/os/estatisticas.php', '/os/checkup.php',
    '/os/corte.php', '/os/dobra.php', '/os/solda.php', '/os/refrigeracao.php',
    '/os/acabamento.php', '/os/montagem.php', '/os/finalizacao.php',
    '/projetista/index.php', '/vendas/index.php',
    '/financeiro/index.php', '/financeiro/faturamento.php', '/financeiro/contas_pagar.php',
];
$czSelf = $_SERVER['PHP_SELF'] ?? '';
$czAutoRefresh = false;
foreach ($czAutoRefreshPaths as $czPath) {
    if (substr($czSelf, -strlen($czPath)) === $czPath) { $czAutoRefresh = true; break; }
}
?>
<script>
// ── Tempo real: badge de notificações + auto-atualização dos painéis ──
(function() {
  const REALTIME_URL = '<?php echo SITE_URL; ?>/api/realtime.php';
  const AUTO_REFRESH = <?php echo $czAutoRefresh ? 'true' : 'false'; ?>;
  const INTERVALO_MS = 15000;
  let fpInicial = null;

  function podeRecarregar() {
    // Não recarregar com modal aberto ou campo em edição
    const modalAberto = Array.from(document.querySelectorAll('.modal')).some(m =>
      m.classList.contains('show') || (m.style.display && m.style.display !== 'none')
    );
    if (modalAberto) return false;
    const el = document.activeElement;
    if (el && ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName)) return false;
    return true;
  }

  async function tick() {
    try {
      const r = await fetch(REALTIME_URL, { cache: 'no-store' });
      if (!r.ok) return;
      const d = await r.json();

      const badge = document.getElementById('czNotifBadge');
      if (badge) {
        if (d.notif > 0) { badge.textContent = d.notif; badge.style.display = ''; }
        else { badge.style.display = 'none'; }
      }

      if (AUTO_REFRESH && d.fp) {
        if (fpInicial === null) { fpInicial = d.fp; return; }
        if (d.fp !== fpInicial && podeRecarregar()) {
          location.reload();
        }
      }
    } catch (e) { /* offline/erro transitório: tenta no próximo tick */ }
  }

  setInterval(tick, INTERVALO_MS);
  tick();
})();
(function() {
  const sidebar = document.getElementById('czSidebar');
  const toggleBtn = document.getElementById('czSidebarToggle');
  const mobileBtn = document.getElementById('czMobileMenuBtn');
  const backdrop = document.getElementById('czSidebarBackdrop');

  const STORAGE_KEY = 'czSidebarCollapsed';
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    sidebar?.classList.add('is-collapsed');
  }

  toggleBtn?.addEventListener('click', () => {
    sidebar?.classList.toggle('is-collapsed');
    localStorage.setItem(STORAGE_KEY, sidebar?.classList.contains('is-collapsed') ? '1' : '0');
  });

  function openMobileSidebar() {
    sidebar?.classList.add('is-open');
    backdrop?.classList.add('is-visible');
    document.body.style.overflow = 'hidden';
  }
  function closeMobileSidebar() {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-visible');
    document.body.style.overflow = '';
  }

  mobileBtn?.addEventListener('click', openMobileSidebar);
  backdrop?.addEventListener('click', closeMobileSidebar);
})();

document.querySelectorAll('.cz-tr-clickable').forEach(tr => {
  tr.addEventListener('click', e => {
    if (e.target.closest('a, button, .cz-td-actions, input, select')) return;
    const href = tr.dataset.href;
    if (!href) return;
    if (tr.dataset.target === '_blank' || e.ctrlKey || e.metaKey) window.open(href, '_blank');
    else window.location.href = href;
  });
});

document.querySelectorAll('[data-target="_blank"]').forEach(el => {
  el.addEventListener('click', e => {
    if (e.target.closest('a, button, .cz-td-actions')) return;
    const href = el.dataset.href;
    if (href) window.open(href, '_blank');
  });
});

async function czFetch(url, data = {}) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data),
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

function showToast(msg, type = 'success') {
  const toastEl = document.getElementById('czToastContainer');
  if (!toastEl) return;

  const icons = { success:'&#10003;', danger:'&#10007;', warning:'&#9888;', info:'&#8505;' };
  const toast = document.createElement('div');
  toast.className = `cz-toast cz-toast--${type}`;
  toast.innerHTML = `<span>${icons[type]}</span> ${msg}`;
  toastEl.appendChild(toast);

  setTimeout(() => toast.classList.add('is-visible'), 10);
  setTimeout(() => {
    toast.classList.remove('is-visible');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function czConfirm(mensagem, onConfirm) {
  const modal = document.getElementById('czConfirmModal');
  const msgEl = document.getElementById('czConfirmMsg');
  const btnOk = document.getElementById('czConfirmOk');

  if (!modal || !msgEl || !btnOk) return;

  msgEl.textContent = mensagem;
  const bsModal = new bootstrap.Modal(modal);
  bsModal.show();

  const newBtn = btnOk.cloneNode(true);
  btnOk.parentNode.replaceChild(newBtn, btnOk);
  newBtn.addEventListener('click', () => { bsModal.hide(); onConfirm(); });
}
// Debounce helper
function debounce(fn, delay) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(context, args), delay);
    };
}

// Kanban drag-drop
(function() {
    const kanbanBoard = document.getElementById('vendKanbanBoard');
    if (!kanbanBoard) return;
    
    let draggedCard = null;
    
    kanbanBoard.addEventListener('dragstart', e => {
        if (e.target.classList.contains('vend-kanban-card')) {
            draggedCard = e.target;
            e.target.classList.add('dragging');
        }
    });
    
    kanbanBoard.addEventListener('dragend', e => {
        if (e.target.classList.contains('vend-kanban-card')) {
            e.target.classList.remove('dragging');
            draggedCard = null;
        }
    });
    
    kanbanBoard.querySelectorAll('.vend-kanban-items').forEach(zone => {
        zone.addEventListener('dragover', e => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });
        
        zone.addEventListener('dragleave', e => {
            zone.classList.remove('drag-over');
        });
        
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (draggedCard) {
                zone.appendChild(draggedCard);
                const itemId = draggedCard.dataset.id;
                const newStatus = zone.closest('.vend-kanban-column').dataset.status;
                // Envia update via AJAX
                fetch('/api/os_update_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: itemId, status: newStatus})
                }).then(r => r.json()).then(data => {
                    if (data.success) showToast('Status atualizado', 'success');
                });
            }
        });
    });
})();
</script>

<div id="czToastContainer" class="cz-toast-container" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px;"></div>

<div class="modal fade" id="czConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-body py-4 text-center">
        <p id="czConfirmMsg" class="mb-3"></p>
        <button class="vbtn-sm" id="czConfirmOk">Confirmar</button>
        <button class="vbtn-sm" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>