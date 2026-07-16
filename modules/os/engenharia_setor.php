<?php
/**
 * O setor de engenharia foi unificado no Projetista.
 * Esta página existe só para redirecionar links/favoritos antigos.
 */
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission(['master', 'gerente', 'projetista', 'producao', 'engenharia']);

header('Location: ' . SITE_URL . '/modules/projetista/index.php');
exit;
