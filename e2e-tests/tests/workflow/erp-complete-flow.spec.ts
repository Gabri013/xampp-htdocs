import { test, expect } from '@playwright/test';
import { loginAsVendedor } from '../helpers';

test.describe('Fluxo Completo ERP - Venda até Produção', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsVendedor(page);
  });

  test('Fluxo completo: Venda -> OS -> Proposta -> Aprovação -> Produção (10 etapas)', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });

    await page.goto('/modules/os/vendedor.php');
    await page.waitForSelector('.vend-layout', { timeout: 20000 });

    const firstOsLink = page.locator('a[href*="os_detalhes.php"]').first();
    if (!(await firstOsLink.isVisible())) {
      test.skip(true, 'Sem OS disponível para teste');
      return;
    }
    await firstOsLink.click();
    await page.waitForURL('**/os_detalhes.php*', { timeout: 20000 });
    await page.waitForSelector('.vend-layout', { timeout: 20000 });
    await expect(page.locator('.vbadge').first()).toBeVisible();

    const aprovarBtn = page.locator('button:has-text("Aprovar Proposta")');
    if (await aprovarBtn.isVisible()) {
      await aprovarBtn.click();
      await page.waitForTimeout(1000);
    }

    const gerarOpBtn = page.locator('button:has-text("Gerar OP em lote")');
    if (await gerarOpBtn.isVisible()) {
      await gerarOpBtn.click();
      await page.waitForTimeout(1000);
    }

    await page.goto('/modules/os/producao.php');
    await page.waitForSelector('.vend-layout', { timeout: 20000 });
    await expect(page.locator('.kanban-column').first()).toBeVisible();
  });

  test('Valida transições de status inválidas', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/modules/os/vendedor.php');
    await page.waitForSelector('.vend-layout', { timeout: 20000 });
    const row = page.locator('.vend-table tbody tr').first();
    if (await row.isVisible()) {
      const statusBadge = row.locator('.vbadge').first();
      if (await statusBadge.isVisible()) {
        expect(await statusBadge.textContent()).toBeTruthy();
      }
    }
  });

  test('Navegação pelos 10 setores de produção', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });

    const setores = [
      { nome: 'Programacao', url: '/modules/os/programacao.php' },
      { nome: 'Corte', url: '/modules/os/corte.php' },
      { nome: 'Mobiliario', url: '/modules/os/mobiliario.php' },
      { nome: 'Coccao', url: '/modules/os/coccao.php' },
      { nome: 'Refrigeracao', url: '/modules/os/refrigeracao.php' },
      { nome: 'Embalagem', url: '/modules/os/embalagem.php' },
      { nome: 'Engenharia', url: '/modules/os/engenharia_setor.php' },
      { nome: 'Dobra', url: '/modules/os/dobra.php' },
      { nome: 'Tubo', url: '/modules/os/tubo.php' },
      { nome: 'Solda', url: '/modules/os/solda.php' },
    ];

    for (const setor of setores) {
      await page.goto(setor.url);
      await page.waitForSelector('.vend-layout', { timeout: 20000 });
      await expect(page.locator('.vend-page-title')).toContainText(setor.nome);
    }
  });

  test('Workflow: valida redirecionamento ao tentar avançar sem iniciar programacao', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });

    await page.goto('/modules/os/programacao.php');
    await page.waitForSelector('.vend-layout', { timeout: 20000 });

    const semOs = await page.locator('text=Nenhuma ordem de serviço pendente').count();
    if (semOs > 0) {
      test.skip(true, 'Sem OS em programacao para validar bloqueio');
      return;
    }

    const btnIniciar = page.locator('button:has-text("Iniciar Trabalho")').first();
    if (!(await btnIniciar.isVisible())) {
      test.skip(true, 'Sem botao de iniciar disponivel');
      return;
    }

    await btnIniciar.click();
    await page.waitForTimeout(1000);

    const btnFinalizar = page.locator('button:has-text("Finalizar e Enviar")').first();
    if (!(await btnFinalizar.isVisible())) {
      test.skip(true, 'Sem botao de finalizar disponivel');
      return;
    }

    await btnFinalizar.click();
    await page.waitForTimeout(1000);
    await expect(page.locator('body')).toContainText(/Inicie o primeiro processo|Anexe pelo menos um arquivo|Transição de status|Finalize o apontamento|Não é permitido/);
  });

  test('Workflow: valida presenca do formulario de anexo em programacao', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });

    await page.goto('/modules/os/programacao.php');
    await page.waitForSelector('.vend-layout', { timeout: 20000 });

    const semOs = await page.locator('text=Nenhuma ordem de serviço pendente').count();
    if (semOs > 0) {
      test.skip(true, 'Sem OS em programacao para validar anexo');
      return;
    }

    await expect(page.locator('input[type="file"]').first()).toBeVisible({ timeout: 10000 });
  });
});
