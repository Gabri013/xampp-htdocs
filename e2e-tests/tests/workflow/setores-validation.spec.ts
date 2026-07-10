import { test, expect } from '@playwright/test';
import { loginAsVendedor } from '../helpers';

test.describe('Validação Setores Produção', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsVendedor(page);
  });

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
    test(`Setor ${setor.nome} - layout e fluxo`, async ({ page }) => {
      await page.setViewportSize({ width: 1920, height: 1080 });
      const response = await page.goto(setor.url);
      expect(response.status()).toBe(200);

      await page.waitForSelector('.vend-layout', { timeout: 20000 });
      await expect(page.locator('.vend-page-title')).toContainText(setor.nome);

      const sidebar = page.locator('.vend-sidebar');
      await expect(sidebar).toBeVisible();

      await expect(page.locator('.card').first()).toBeVisible();
      await expect(page.locator('table')).toBeVisible();

      const hasModalRetorno = await page.evaluate(() => !!document.getElementById('modalRetorno'));
      expect(hasModalRetorno).toBe(true);

      const hasModalOS = await page.evaluate(() => !!document.getElementById('modalOS'));
      expect(hasModalOS).toBe(true);
    });
  }
});
