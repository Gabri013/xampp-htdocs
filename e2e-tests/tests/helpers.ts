/**
 * Helper functions para testes E2E
 */

/**
 * Faz login no sistema com credenciais de teste (admin)
 */
export async function loginForTests(page: any): Promise<boolean> {
  try {
    await page.goto('/modules/auth/login.php');
    
    // Usa credenciais reais do banco de dados (senha resetada para admin123)
    await page.fill('input[name="email"]', 'admin@sistema.com');
    await page.fill('input[name="senha"]', 'admin123');
    await page.click('button[type="submit"]');
    
    // Aguarda redirecionamento
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    return currentUrl.includes('index.php') || currentUrl.includes('dashboard');
  } catch (error) {
    return false;
  }
}

/**
 * Faz login no sistema com credenciais do vendedor (nilton)
 */
export async function loginAsVendedor(page: any): Promise<boolean> {
  try {
    await page.goto('/modules/auth/login.php');
    
    // Usa credenciais do vendedor nilton
    await page.fill('input[name="email"]', 'nilton@cozinca.com.br');
    await page.fill('input[name="senha"]', 'nilton');
    await page.click('button[type="submit"]');
    
    // Aguarda redirecionamento
    await page.waitForTimeout(1000);
    
    const currentUrl = page.url();
    return currentUrl.includes('index.php') || currentUrl.includes('dashboard');
  } catch (error) {
    return false;
  }
}

/**
 * Obtém uma OS válida do banco de dados para testes
 * Retorna o ID da primeira OS encontrada ou 1 se não houver (testes devem tratar erro)
 */
export async function getValidOSId(page: any): Promise<number> {
  try {
    // Tenta fazer login primeiro
    const loggedIn = await loginForTests(page);
    
    // Tenta usar uma das OS existentes no banco (IDs 39-48)
    const existingOSIds = [39, 40, 41, 42, 43, 44, 45, 46, 47, 48];
    
    for (const osId of existingOSIds) {
      const response = await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
      
      if (response && response.ok()) {
        const pageContent = await page.textContent('body');
        
        // Se a página carregou sem erro de "não encontrada", OS existe
        if (!pageContent?.includes('Ordem de Serviço não encontrada') && 
            !pageContent?.includes('Erro') &&
            !pageContent?.includes('não encontrada') &&
            !pageContent?.includes('não existe') &&
            !pageContent?.includes('Acesso negado') &&
            !pageContent?.includes('Não autorizado')) {
          return osId;
        }
      }
    }
    
    // Se nenhuma OS existente funcionou, retorna 1 (testes devem tratar erro)
    return 1;
  } catch (error) {
    // Se houver erro, retorna 1 (testes devem tratar erro)
    return 1;
  }
}

/**
 * Verifica se uma OS específica existe no banco de dados
 */
export async function osExists(page: any, osId: number): Promise<boolean> {
  try {
    const response = await page.goto(`/modules/os/os_detalhes.php?os_id=${osId}`);
    
    if (response && response.ok()) {
      const pageContent = await page.textContent('body');
      
      return !pageContent?.includes('Ordem de Serviço não encontrada') && 
             !pageContent?.includes('Erro') &&
             !pageContent?.includes('não encontrada') &&
             !pageContent?.includes('não existe');
    }
    
    return false;
  } catch (error) {
    return false;
  }
}
