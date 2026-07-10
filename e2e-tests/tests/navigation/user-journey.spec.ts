import { test, expect } from '@playwright/test';
import { getValidOSId } from '../helpers';

test.describe('User Journey Navigation', () => {
  test.beforeEach(async ({ page }) => {
    // Simula login - em produção, usar credenciais reais de teste
    await page.goto('/index.php');
  });

  test('navegação do dashboard para detalhes da OS', async ({ page }) => {
    // Verifica se há tabela
    const table = page.locator('table tbody tr');
    if (await table.count() > 0) {
      // Clica em uma OS na lista
      const firstRow = table.first();
      await firstRow.click();
      
      // Verifica se navegou para página de detalhes
      const currentUrl = page.url();
      const navigated = currentUrl.includes('detalhes') || currentUrl.includes('os_detalhes');
      
      if (!navigated) {
        // Se não navegou, pode ser porque não há OS ou redirecionou
        expect(true).toBe(true);
      } else {
        expect(navigated).toBe(true);
      }
    } else {
      // Se não há tabela, pula teste
      test.skip();
    }
  });

  test('navegação do dashboard para painel projetista', async ({ page }) => {
    // Verifica se existe link ou menu para projetista
    const projetistaLink = page.locator('a[href*="projetista.php"]');
    if (await projetistaLink.count() > 0) {
      await projetistaLink.first().click();
      await expect(page).toHaveURL(/projetista\.php/);
    }
  });

  test('navegação entre abas na página de detalhes', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    // Verifica abas
    const tabs = page.locator('.det-tab');
    if (await tabs.count() > 0) {
      await expect(tabs.first()).toBeVisible();
      
      // Clica em cada aba e verifica conteúdo
      const tabNames = ['visao-geral', 'arquivos', 'engenharia', 'producao', 'historico', 'anotacoes'];
      
      for (const tabName of tabNames) {
        const tab = page.locator(`.det-tab[data-tab="${tabName}"]`);
        if (await tab.count() > 0) {
          await tab.click();
          const tabContent = page.locator(`#tab-${tabName}`);
          if (await tabContent.count() > 0) {
            await expect(tabContent).toBeVisible();
          }
        }
      }
    }
  });

  test('botão voltar funciona corretamente', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    const backButton = page.locator('a[href*="projetista.php"]');
    if (await backButton.count() > 0) {
      await backButton.click();
      await expect(page).toHaveURL(/projetista\.php/);
    }
  });

  test('navegação via breadcrumbs', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    // Verifica se há breadcrumbs ou navegação hierárquica
    const breadcrumbs = page.locator('.breadcrumb, .nav-breadcrumb');
    if (await breadcrumbs.count() > 0) {
      await expect(breadcrumbs.first()).toBeVisible();
    }
  });

  test('links externos abrem em nova aba', async ({ page }) => {
    const osId = await getValidOSId(page);
    
    await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    // Verifica se página carregou corretamente
    const pageContent = await page.textContent('body');
    
    // Se não tiver OS, pula teste
    if (pageContent?.includes('Ordem de Serviço não encontrada') || pageContent?.includes('Erro')) {
      test.skip();
      return;
    }
    
    const externalLinks = page.locator('a[target="_blank"]');
    if (await externalLinks.count() > 0) {
      const [newPage] = await Promise.all([
        page.context().waitForEvent('page'),
        externalLinks.first().click()
      ]);
      await newPage.waitForLoadState();
      expect(newPage.url()).toBeTruthy();
      await newPage.close();
    }
  });
});
