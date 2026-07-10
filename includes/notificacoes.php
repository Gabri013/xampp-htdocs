<?php

/**
 * Núcleo de notificações (interno, email e whatsapp)
 */

function ensureNotificacoesSchema(PDO $db) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    if (!shouldRunSchemaSync('notificacoes', 86400)) {
        return;
    }

    $db->exec("\n        CREATE TABLE IF NOT EXISTS notificacoes (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            usuario_id INT NOT NULL,\n            tipo VARCHAR(50) NOT NULL,\n            titulo VARCHAR(120) NOT NULL,\n            mensagem TEXT NOT NULL,\n            lida TINYINT(1) NOT NULL DEFAULT 0,\n            chave_evento VARCHAR(120) NULL,\n            referencia_tipo VARCHAR(40) NULL,\n            referencia_id INT NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,\n            INDEX idx_notif_usuario_lida (usuario_id, lida),\n            UNIQUE KEY uq_notif_usuario_chave (usuario_id, chave_evento)\n        ) ENGINE=InnoDB\n    ");

    $db->exec("\n        CREATE TABLE IF NOT EXISTS notificacoes_envios (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            notificacao_id INT NOT NULL,\n            canal ENUM('interno','email','whatsapp') NOT NULL,\n            destino VARCHAR(160) NULL,\n            status ENUM('PENDENTE','ENVIADO','ERRO') NOT NULL DEFAULT 'PENDENTE',\n            resposta TEXT NULL,\n            tentativas INT NOT NULL DEFAULT 0,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            sent_at DATETIME NULL,\n            FOREIGN KEY (notificacao_id) REFERENCES notificacoes(id) ON DELETE CASCADE,\n            INDEX idx_envio_status (status, canal)\n        ) ENGINE=InnoDB\n    ");

    $notifCols = $db->query("SHOW COLUMNS FROM notificacoes")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('chave_evento', $notifCols, true)) {
        $db->exec("ALTER TABLE notificacoes ADD COLUMN chave_evento VARCHAR(120) NULL AFTER lida");
    }
    if (!in_array('referencia_tipo', $notifCols, true)) {
        $db->exec("ALTER TABLE notificacoes ADD COLUMN referencia_tipo VARCHAR(40) NULL AFTER chave_evento");
    }
    if (!in_array('referencia_id', $notifCols, true)) {
        $db->exec("ALTER TABLE notificacoes ADD COLUMN referencia_id INT NULL AFTER referencia_tipo");
    }
    $idxs = $db->query("SHOW INDEX FROM notificacoes")->fetchAll(PDO::FETCH_ASSOC);
    $temUniqueChave = false;
    foreach ($idxs as $idx) {
        if (($idx['Key_name'] ?? '') === 'uq_notif_usuario_chave') {
            $temUniqueChave = true;
            break;
        }
    }
    if (!$temUniqueChave) {
        $db->exec("ALTER TABLE notificacoes ADD UNIQUE KEY uq_notif_usuario_chave (usuario_id, chave_evento)");
    }

    $cols = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('receber_notificacao_email', $cols, true)) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN receber_notificacao_email TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
    }
    if (!in_array('receber_notificacao_whatsapp', $cols, true)) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN receber_notificacao_whatsapp TINYINT(1) NOT NULL DEFAULT 0 AFTER receber_notificacao_email");
    }
    if (!in_array('telefone_whatsapp', $cols, true)) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN telefone_whatsapp VARCHAR(30) NULL");
    }
}

function ensureNotificacoesSchemaCached(PDO $db, $ttl = 21600) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ensureNotificacoesSchema($db);
        return;
    }

    $lastCheck = (int) ($_SESSION['notificacoes_schema_checked_at'] ?? 0);
    if ($lastCheck > 0 && (time() - $lastCheck) < $ttl) {
        return;
    }

    ensureNotificacoesSchema($db);
    $_SESSION['notificacoes_schema_checked_at'] = time();
}

function notificarUsuario(PDO $db, array $usuario, array $payload, array $canais = ['interno']) {
    $chave = $payload['chave_evento'] ?? null;

    $stmt = $db->prepare("\n        INSERT INTO notificacoes\n        (usuario_id, tipo, titulo, mensagem, chave_evento, referencia_tipo, referencia_id)\n        VALUES (?, ?, ?, ?, ?, ?, ?)\n        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)\n    ");
    $stmt->execute([
        $usuario['id'],
        $payload['tipo'],
        $payload['titulo'],
        $payload['mensagem'],
        $chave,
        $payload['referencia_tipo'] ?? null,
        $payload['referencia_id'] ?? null
    ]);

    $notificacaoId = (int) $db->lastInsertId();
    if ($notificacaoId <= 0) {
        return;
    }

    foreach ($canais as $canal) {
        $stmtExiste = $db->prepare("SELECT id FROM notificacoes_envios WHERE notificacao_id = ? AND canal = ? LIMIT 1");
        $stmtExiste->execute([$notificacaoId, $canal]);
        if ($stmtExiste->fetch()) {
            continue;
        }

        if ($canal === 'interno') {
            $stmtEnvio = $db->prepare("INSERT INTO notificacoes_envios (notificacao_id, canal, destino, status, sent_at, resposta) VALUES (?, 'interno', NULL, 'ENVIADO', NOW(), 'Disponível no painel interno')");
            $stmtEnvio->execute([$notificacaoId]);
            continue;
        }

        if ($canal === 'email') {
            if ((int) ($usuario['receber_notificacao_email'] ?? 1) === 1 && !empty($usuario['email'])) {
                $stmtEnvio = $db->prepare("INSERT INTO notificacoes_envios (notificacao_id, canal, destino) VALUES (?, 'email', ?)");
                $stmtEnvio->execute([$notificacaoId, $usuario['email']]);
            }
            continue;
        }

        if ($canal === 'whatsapp') {
            if ((int) ($usuario['receber_notificacao_whatsapp'] ?? 0) === 1 && !empty($usuario['telefone_whatsapp'])) {
                $stmtEnvio = $db->prepare("INSERT INTO notificacoes_envios (notificacao_id, canal, destino) VALUES (?, 'whatsapp', ?)");
                $stmtEnvio->execute([$notificacaoId, $usuario['telefone_whatsapp']]);
            }
        }
    }
}

function notificarPerfis(PDO $db, array $tipos, array $payload, array $canais = ['interno']) {
    if (empty($tipos)) {
        return;
    }

    $in = implode(',', array_fill(0, count($tipos), '?'));
    $stmt = $db->prepare("SELECT id, nome, email, receber_notificacao_email, receber_notificacao_whatsapp, telefone_whatsapp FROM usuarios WHERE status='ativo' AND tipo IN ($in)");
    $stmt->execute($tipos);
    $usuarios = $stmt->fetchAll();

    foreach ($usuarios as $u) {
        notificarUsuario($db, $u, $payload, $canais);
    }
}

function gerarEventosNotificacoes(PDO $db) {
    ensureNotificacoesSchema($db);

    // 1) O.S atrasada
    $stmt = $db->query("\n        SELECT id, numero, data_termino\n        FROM ordens_servico\n        WHERE status NOT IN ('concluida','cancelada')\n          AND data_termino IS NOT NULL\n          AND data_termino < CURDATE()\n    ");
    foreach ($stmt->fetchAll() as $os) {
        $dias = (int) floor((time() - strtotime($os['data_termino'])) / 86400);
        $chave = 'os_atrasada_' . $os['id'] . '_' . date('Ymd');
        $payload = [
            'tipo' => 'os_atrasada',
            'titulo' => 'O.S atrasada',
            'mensagem' => 'A O.S ' . $os['numero'] . ' está atrasada há ' . $dias . ' dia(s).',
            'chave_evento' => $chave,
            'referencia_tipo' => 'os',
            'referencia_id' => $os['id']
        ];
        notificarPerfis($db, ['master', 'gerente', 'producao'], $payload, ['interno', 'email', 'whatsapp']);
    }

    // 2) Projeto aguardando (OS sem envio do projetista)
    $stmt = $db->query("\n        SELECT id, numero, created_at\n        FROM ordens_servico\n        WHERE status IN ('pendente','em_projeto')\n          AND created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)\n    ");
    foreach ($stmt->fetchAll() as $os) {
        $chave = 'projeto_aguardando_' . $os['id'] . '_' . date('Ymd');
        $payload = [
            'tipo' => 'projeto_aguardando',
            'titulo' => 'Projeto aguardando',
            'mensagem' => 'A O.S ' . $os['numero'] . ' está aguardando projeto do setor técnico.',
            'chave_evento' => $chave,
            'referencia_tipo' => 'os',
            'referencia_id' => $os['id']
        ];
        notificarPerfis($db, ['master', 'projetista', 'gerente'], $payload, ['interno', 'email', 'whatsapp']);
    }

    // 3) Venda aguardando pagamento
    $stmt = $db->query("\n        SELECT cr.id, cr.venda_id, v.numero as venda_numero\n        FROM contas_receber cr\n        INNER JOIN vendas v ON v.id = cr.venda_id\n        WHERE cr.status IN ('PENDENTE','ATRASADO')\n          AND cr.data_vencimento <= CURDATE()\n    ");
    foreach ($stmt->fetchAll() as $cr) {
        $chave = 'venda_pgto_' . $cr['id'] . '_' . date('Ymd');
        $payload = [
            'tipo' => 'venda_aguardando_pagamento',
            'titulo' => 'Venda aguardando pagamento',
            'mensagem' => 'Há título pendente da venda ' . $cr['venda_numero'] . ' aguardando pagamento.',
            'chave_evento' => $chave,
            'referencia_tipo' => 'conta_receber',
            'referencia_id' => $cr['id']
        ];
        notificarPerfis($db, ['master', 'vendedor'], $payload, ['interno', 'email', 'whatsapp']);
    }
}

function enviarFilaNotificacoes(PDO $db, $limite = 100) {
    $stmt = $db->prepare("\n        SELECT ne.id, ne.canal, ne.destino, n.titulo, n.mensagem\n        FROM notificacoes_envios ne\n        INNER JOIN notificacoes n ON n.id = ne.notificacao_id\n        WHERE ne.status = 'PENDENTE'\n        ORDER BY ne.id ASC\n        LIMIT ?\n    ");
    $stmt->bindValue(1, (int) $limite, PDO::PARAM_INT);
    $stmt->execute();
    $envios = $stmt->fetchAll();

    foreach ($envios as $e) {
        $ok = false;
        $resp = '';

        if ($e['canal'] === 'email') {
            $subject = '[ERP] ' . $e['titulo'];
            $body = $e['mensagem'];
            $headers = "From: noreply@localhost\\r\\nContent-Type: text/plain; charset=UTF-8";
            $ok = @mail($e['destino'], $subject, $body, $headers);
            $resp = $ok ? 'Email enviado' : 'Falha no envio de email';
        }

        if ($e['canal'] === 'whatsapp') {
            $webhook = getenv('WHATSAPP_WEBHOOK_URL') ?: '';
            if ($webhook === '') {
                $ok = false;
                $resp = 'WHATSAPP_WEBHOOK_URL não configurada';
            } else {
                $payload = json_encode([
                    'to' => $e['destino'],
                    'message' => $e['titulo'] . "\n" . $e['mensagem']
                ]);

                $ch = curl_init($webhook);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $result = curl_exec($ch);
                $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);

                $ok = ($http >= 200 && $http < 300 && !$err);
                $resp = $ok ? 'WhatsApp enviado' : ('Falha WhatsApp: ' . ($err ?: $result));
            }
        }

        $status = $ok ? 'ENVIADO' : 'ERRO';
        $stmtUpd = $db->prepare("UPDATE notificacoes_envios SET status=?, resposta=?, tentativas=tentativas+1, sent_at=NOW() WHERE id=?");
        $stmtUpd->execute([$status, $resp, $e['id']]);
    }
}

function processarMotorNotificacoes(PDO $db) {
    gerarEventosNotificacoes($db);
    enviarFilaNotificacoes($db, 100);
}
