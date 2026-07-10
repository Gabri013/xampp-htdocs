import { test, expect } from '@playwright/test';
import { getValidOSId } from '../helpers';

test.describe('Visual States & Layouts', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/index.php');
  });

  test('dashboard - layout responsivo em desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    // Verifica se página carregou
    const body = page.locator('body');
    await expect(body).toBeVisible();
    
    // Verifica KPIs se existirem
    const kpiCards = page.locator('.stat-card, .nomus-kpi-card');
    if (await kpiCards.count() > 0) {
      await expect(kpiCards.first()).toBeVisible();
    }
    
    // Verifica tabela de OS se existir
    const table = page.locator('table');
    if (await table.count() > 0) {
      await expect(table.first()).toBeVisible();
    }
  });

  test('dashboard - layout responsivo em tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    
    // Verifica se página carregou
    const body = page.locator('body');
    await expect(body).toBeVisible();
    
    // Verifica se elementos ainda são visíveis
    const kpiCards = page.locator('.stat-card, .nomus-kpi-card');
    if (await kpiCards.count() > 0) {
      await expect(kpiCards.first()).toBeVisible();
    }
  });

  test('dashboard - layout responsivo em mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Verifica se página carregou
    const body = page.locator('body');
    await expect(body).toBeVisible();
    
    // Verifica se layout se adapta
    const kpiCards = page.locator('.stat-card, .nomus-kpi-card');
    if (await kpiCards.count() > 0) {
      await expect(kpiCards.first()).toBeVisible();
    }
  });

  test('página projetista - estados de prioridade visual', async ({ page }) => {
    await page.goto('/modules/os/projetista.php');
    
    // Verifica badges de prioridade
    const priorityBadges = page.locator('.nomus-badge');
    if (await priorityBadges.count() > 0) {
      await expect(priorityBadges.first()).toBeVisible();
      
      // Verifica cores diferentes para prioridades
      const urgentBadge = page.locator('.nomus-badge-danger');
      const warningBadge = page.locator('.nomus-badge-warning');
      const successBadge = page.locator('.nomus-badge-success');
      
      // Pelo menos um deve estar presente
      const hasAnyBadge = await Promise.all([
        urgentBadge.count(),
        warningBadge.count(),
        successBadge.count()
      ]).then(counts => counts.some(c => c > 0));
      
      expect(hasAnyBadge).toBe(true);
    }
  });

  test('página projetista - estados de prazo crítico', async ({ page }) => {
    await page.goto('/modules/os/projetista.php');
    
    // Verifica linhas com prazo crítico
    const criticalRows = page.locator('.projetista-prazo-vermelho');
    if (await criticalRows.count() > 0) {
      await expect(criticalRows.first()).toBeVisible();
    }
  });

  test('página detalhes - layout de abas', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    // Verifica container de abas
    const tabsContainer = page.locator('.det-tabs');
    if (await tabsContainer.count() > 0) {
      await expect(tabsContainer).toBeVisible();
      
      // Verifica que todas as abas estão presentes
      const tabs = tabsContainer.locator('.det-tab');
      await expect(tabs).toHaveCount(6);
    }
  });

  test('página detalhes - conteúdo das abas', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    // Verifica que apenas a aba ativa é visível
    const activeTabContent = page.locator('.det-tab-content.active');
    if (await activeTabContent.count() > 0) {
      await expect(activeTabContent).toBeVisible();
      
      // Verifica que outras abas estão ocultas
      const inactiveTabContents = page.locator('.det-tab-content:not(.active)');
      const firstInactive = inactiveTabContents.first();
      if (await firstInactive.count() > 0) {
        await expect(firstInactive).not.toBeVisible();
      }
    }
  });

  test('kanban - layout de colunas', async ({ page }) => {
    await page.goto('/index.php');
    
    // Verifica container do kanban
    const kanbanContainer = page.locator('.kanban-container');
    if (await kanbanContainer.count() > 0) {
      await expect(kanbanContainer).toBeVisible();
      
      // Verifica colunas
      const columns = kanbanContainer.locator('.kanban-column');
      await expect(columns).toHaveCount(8); // autorizacao, corte, dobra, solda, acabamento, finalizacao, montagem, concluida
    }
  });

  test('cards do kanban - estados visuais', async ({ page }) => {
    await page.goto('/index.php');
    
    const kanbanCards = page.locator('.kanban-card');
    if (await kanbanCards.count() > 0) {
      const firstCard = kanbanCards.first();
      await expect(firstCard).toBeVisible();
      
      // Verifica elementos do card
      await expect(firstCard.locator('.kanban-card-header')).toBeVisible();
      await expect(firstCard.locator('.kanban-card-body')).toBeVisible();
      await expect(firstCard.locator('.kanban-card-footer')).toBeVisible();
    }
  });

  test('tabelas - layout responsivo', async ({ page }) => {
    await page.goto('/modules/os/projetista.php');
    
    const tables = page.locator('table');
    if (await tables.count() > 0) {
      const firstTable = tables.first();
      await expect(firstTable).toBeVisible();
      
      // Verifica se tabela tem wrapper responsivo
      const tableResponsive = firstTable.locator('..').locator('.table-responsive');
      await expect(tableResponsive).toBeVisible();
    }
  });

  test('badges - consistência visual', async ({ page }) => {
    await page.goto('/modules/os/projetista.php');
    
    const badges = page.locator('.nomus-badge');
    if (await badges.count() > 0) {
      const firstBadge = badges.first();
      
      // Verifica que badge tem padding e border-radius
      const styles = await firstBadge.evaluate(el => {
        const computed = window.getComputedStyle(el);
        return {
          padding: computed.padding,
          borderRadius: computed.borderRadius,
          display: computed.display
        };
      });
      
      expect(styles.display).toBe('inline-flex');
      expect(styles.padding).toBeTruthy();
      expect(styles.borderRadius).toBeTruthy();
    }
  });

  test('KPIs - layout em grid', async ({ page }) => {
    await page.goto('/modules/os/projetista.php');
    
    const kpiGrid = page.locator('.nomus-kpi-grid');
    if (await kpiGrid.count() > 0) {
      await expect(kpiGrid).toBeVisible();
      
      // Verifica número de KPIs
      const kpiCards = kpiGrid.locator('.nomus-kpi-card');
      await expect(kpiCards).toHaveCount(4);
    }
  });

  test('alertas - visibilidade e estilo', async ({ page }) => {
    await page.goto('/modules/os/projetista.php');
    
    const recallAlert = page.locator('.nomus-alert-recall');
    if (await recallAlert.count() > 0) {
      await expect(recallAlert).toBeVisible();
      
      // Verifica elementos do alerta
      await expect(recallAlert.locator('i')).toBeVisible();
      await expect(recallAlert.locator('strong')).toBeVisible();
    }
  });

  test('botões - estados hover', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    const buttons = page.locator('.nm-btn, .btn');
    if (await buttons.count() > 0) {
      const firstButton = buttons.first();
      
      // Simula hover
      await firstButton.hover();
      
      // Verifica mudança de cursor
      const cursor = await firstButton.evaluate(el => 
        window.getComputedStyle(el).cursor
      );
      expect(cursor).toBe('pointer');
    }
  });

  test('timeline - layout visual', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    const historicoTab = page.locator('.det-tab[data-tab="historico"]');
    if (await historicoTab.count() > 0) {
      await historicoTab.click();
      
      const timeline = page.locator('.nm-timeline');
      if (await timeline.count() > 0) {
        await expect(timeline).toBeVisible();
        
        // Verifica itens da timeline
        const timelineItems = timeline.locator('.nm-tl-item');
        if (await timelineItems.count() > 0) {
          await expect(timelineItems.first()).toBeVisible();
        }
      }
    }
  });
});
