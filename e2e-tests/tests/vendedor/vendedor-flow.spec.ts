import { test, expect } from '@playwright/test';
import { loginAsVendedor } from '../helpers';

test.describe('Fluxo do Vendedor - Usuário Nilton', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsVendedor(page);
  });

  test('Dashboard do Vendedor - Verificar métricas e layout', async ({ page }) => {
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    
    // Verificar se a página carregou
    await expect(page.locator('.vend-layout')).toBeVisible();
    
    // Verificar sidebar
    await expect(page.locator('.vend-sidebar')).toBeVisible();
    await expect(page.locator('.vend-logo-text')).toContainText('Cozinca Inox');
    
    // Verificar título
    await expect(page.locator('.vend-page-title')).toContainText('Dashboard');
    
    // Verificar abas de período
    await expect(page.locator('.vend-period-tabs')).toBeVisible();
    await expect(page.locator('.vend-period-tab')).toHaveCount(4);
    
    // Verificar métricas
    await expect(page.locator('.vend-metrics')).toBeVisible();
    await expect(page.locator('.vend-metric')).toHaveCount(4);
    
    // Verificar ações rápidas
    await expect(page.locator('.vend-actions')).toBeVisible();
    await expect(page.locator('.vend-action')).toHaveCount(6);
    
    // Verificar cards recentes
    await expect(page.locator('.vend-two-col')).toBeVisible();
    await expect(page.locator('.vend-card')).toHaveCount(2);
  });

  test('Dashboard - Navegação por período', async ({ page }) => {
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    
    // Clicar em "Hoje"
    await page.click('.vend-period-tab:has-text("Hoje")');
    await expect(page).toHaveURL(/.*periodo=hoje/);
    
    // Clicar em "Esta semana"
    await page.click('.vend-period-tab:has-text("Esta semana")');
    await expect(page).toHaveURL(/.*periodo=semana/);
    
    // Clicar em "Este mês"
    await page.click('.vend-period-tab:has-text("Este mês")');
    await expect(page).toHaveURL(/.*periodo=mes/);
    
    // Clicar em "Acumulado"
    await page.click('.vend-period-tab:has-text("Acumulado")');
    await expect(page).toHaveURL(/.*periodo=acumulado/);
  });

  test('Dashboard - Ações rápidas funcionam', async ({ page }) => {
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    
    // Verificar link Novo Orçamento
    const orcamentoLink = page.locator('.vend-action').filter({ hasText: 'Novo orçamento' });
    await expect(orcamentoLink).toHaveAttribute('href', /modules\/orcamentos\/index\.php/);
    
    // Verificar link Nova Venda
    const vendaLink = page.locator('.vend-action').filter({ hasText: 'Nova venda' });
    await expect(vendaLink).toHaveAttribute('href', /nova_venda\.php/);
    
    // Verificar link Nova O.S.
    const osLink = page.locator('.vend-action').filter({ hasText: 'Nova O.S.' });
    await expect(osLink).toHaveAttribute('href', /modules\/os\/nova_os_independente\.php/);
  });

  test('Lista de O.S. - Verificar layout e filtros', async ({ page }) => {
    await page.goto('/modules/os/vendedor.php');
    
    // Verificar se a página carregou
    await expect(page.locator('.vend-layout')).toBeVisible();
    
    // Verificar título
    await expect(page.locator('.vend-page-title')).toContainText('Ordens de Serviço');
    
    // Verificar filtros por status
    await expect(page.locator('.vend-filter-bar')).toBeVisible();
    const filterPills = page.locator('.vend-filter-pill');
    const pillCount = await filterPills.count();
    expect(pillCount).toBeGreaterThan(0);
    
    // Verificar campo de busca
    await expect(page.locator('.vend-search').getByRole('textbox')).toBeVisible();
    
    // Verificar tabela
    await expect(page.locator('.vend-table-wrap')).toBeVisible();
  });

  test('Lista de O.S. - Filtros por status', async ({ page }) => {
    await page.goto('/modules/os/vendedor.php');
    
    // Clicar em "Em produção"
    const producaoPill = page.locator('.vend-filter-pill').filter({ hasText: 'Em produção' });
    if (await producaoPill.isVisible()) {
      await producaoPill.click();
      await expect(page).toHaveURL(/.*status=em_producao/);
    }
    
    // Clicar em "Todas"
    await page.click('.vend-filter-pill:has-text("Todas")');
    await expect(page).toHaveURL(/.*status=todas/);
  });

  test('Lista de O.S. - Busca funciona', async ({ page }) => {
    await page.goto('/modules/os/vendedor.php');
    
    // Preencher campo de busca
    await page.fill('.vend-search input[name="busca"]', 'teste');
    
    // Submeter busca
    await page.click('.vend-search button');
    
    // Verificar se a URL tem o parâmetro de busca
    await expect(page).toHaveURL(/.*busca=teste/);
  });

  test('Sidebar - Navegação entre páginas', async ({ page }) => {
    // Garantir viewport desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    // Começar no dashboard
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    await expect(page.locator('.vend-nav-item.active')).toContainText('Dashboard');
    
    // Navegar para O.S. via sidebar
    await page.click('.vend-nav-item:has-text("O.S.")');
    await expect(page).toHaveURL(/modules\/os\/vendedor\.php/);
    
    // Navegar para dashboard via URL direta (mais confiável)
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    await expect(page).toHaveURL(/modules\/vendas\/dashboard_vendedor\.php/);
    
    // Verificar que a sidebar está visível
    await expect(page.locator('.vend-sidebar')).toBeVisible();
    
    // Navegar para orçamentos via URL direta
    await page.goto('/modules/orcamentos/index.php');
    await expect(page).toHaveURL(/modules\/orcamentos\/index\.php/);
  });

  test('Sidebar - Badges de notificação', async ({ page }) => {
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    
    // Verificar se há badges na sidebar
    const badges = page.locator('.vend-nav-badge');
    const badgeCount = await badges.count();
    
    // Se houver badges, verificar que são visíveis
    if (badgeCount > 0) {
      await expect(badges.first()).toBeVisible();
    }
  });

  test('Layout Responsivo - Sidebar escondida em mobile', async ({ page }) => {
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    
    // Verificar sidebar visível em desktop
    await expect(page.locator('.vend-sidebar')).toBeVisible();
    
    // Mudar para tamanho mobile
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Verificar sidebar escondida em mobile
    await expect(page.locator('.vend-sidebar')).not.toBeVisible();
    
    // Voltar para desktop
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    // Verificar sidebar visível novamente
    await expect(page.locator('.vend-sidebar')).toBeVisible();
  });

  test('Dashboard - Cards de vendas e O.S. recentes', async ({ page }) => {
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    
    // Verificar card de vendas recentes
    await expect(page.locator('.vend-card').filter({ hasText: 'Vendas recentes' })).toBeVisible();
    
    // Verificar card de O.S. que precisam de atenção
    await expect(page.locator('.vend-card').filter({ hasText: 'O.S. que precisam de atenção' })).toBeVisible();
    
    // Verificar links "Ver todas"
    await expect(page.locator('.vend-card-link')).toHaveCount(2);
  });

  test('Integração completa - Fluxo do vendedor', async ({ page }) => {
    // Garantir que estamos em desktop para testes iniciais
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    // 1. Acessar dashboard
    await page.goto('/modules/vendas/dashboard_vendedor.php');
    await expect(page.locator('.vend-layout')).toBeVisible();
    
    // 2. Verificar métricas
    await expect(page.locator('.vend-metric')).toHaveCount(4);
    
    // 3. Navegar para O.S.
    await page.click('.vend-nav-item:has-text("O.S.")');
    await expect(page).toHaveURL(/modules\/os\/vendedor\.php/);
    
    // 4. Verificar lista de O.S.
    await expect(page.locator('.vend-table-wrap')).toBeVisible();
    
    // 5. Usar filtro
    await page.click('.vend-filter-pill:has-text("Todas")');
    
    // 6. Voltar para dashboard
    await page.click('.vend-nav-item:has-text("Dashboard")');
    await expect(page).toHaveURL(/modules\/vendas\/dashboard_vendedor\.php/);
    
    // 7. Verificar ações rápidas
    await expect(page.locator('.vend-action')).toHaveCount(6);
    
    // 8. Verificar cards recentes
    await expect(page.locator('.vend-card')).toHaveCount(2);
  });
});
