<?php
/**
 * "Acessar como" — impersonação para teste/suporte.
 *   ?acao=entrar&id=<usuario>  → master passa a ver como o usuário (só master real)
 *   ?acao=sair                 → volta para a conta do master
 * A identidade original fica em $_SESSION['impersonator'] (ver includes/auth.php).
 */

require_once '../../config/config.php';
requireLogin();

$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';

if ($acao === 'sair') {
    encerrarImpersonacao();
    setSuccess('Você voltou para a sua conta.');
    header('Location: ' . SITE_URL . '/modules/cadastros/usuarios.php');
    exit;
}

if ($acao === 'entrar') {
    $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
    $r = iniciarImpersonacao($id);
    if ($r['ok']) {
        setSuccess('Você está acessando como ' . ($_SESSION['usuario_nome'] ?? 'usuário') . '. Use o aviso no topo para voltar.');
        header('Location: ' . SITE_URL . '/index.php');
    } else {
        setError($r['erro']);
        header('Location: ' . SITE_URL . '/modules/cadastros/usuarios.php');
    }
    exit;
}

header('Location: ' . SITE_URL . '/index.php');
exit;
