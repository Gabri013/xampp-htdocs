<?php

function ensureExpedienteSchema(PDO $db): void
{
    static $schemaGarantido = false;

    if ($schemaGarantido) {
        return;
    }

    if (!shouldRunSchemaSync('expediente', 86400)) {
        $schemaGarantido = true;
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios_expedientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            data_referencia DATE NOT NULL,
            status ENUM('em_trabalho', 'encerrado') NOT NULL DEFAULT 'em_trabalho',
            iniciado_em DATETIME NOT NULL,
            finalizado_em DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_usuario_data (usuario_id, data_referencia),
            CONSTRAINT fk_expediente_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    // Migração: o expediente aceita VÁRIOS períodos por dia (entrar, sair
    // para almoço, voltar) — remove a chave única antiga se ainda existir.
    try {
        $chk = $db->query("SHOW INDEX FROM usuarios_expedientes WHERE Key_name = 'uniq_usuario_data'");
        if ($chk && $chk->fetch()) {
            $db->exec("ALTER TABLE usuarios_expedientes DROP INDEX uniq_usuario_data, ADD INDEX idx_usuario_data (usuario_id, data_referencia)");
        }
    } catch (Throwable $e) { /* índice pode já ter sido migrado */ }

    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios_expediente_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expediente_id INT NOT NULL,
            usuario_id INT NOT NULL,
            tipo ENUM('inicio', 'fim') NOT NULL,
            registrado_em DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_expediente_log_expediente FOREIGN KEY (expediente_id) REFERENCES usuarios_expedientes(id) ON DELETE CASCADE,
            CONSTRAINT fk_expediente_log_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_usuario_registro (usuario_id, registrado_em)
        ) ENGINE=InnoDB
    ");

    $schemaGarantido = true;
}

function getExpedienteHoje(PDO $db, int $usuarioId): ?array
{
    ensureExpedienteSchema($db);

    // Pode haver vários períodos no dia (reabertura após encerrar) — o
    // estado atual é o do período mais recente.
    $stmt = $db->prepare("
        SELECT *
        FROM usuarios_expedientes
        WHERE usuario_id = ?
          AND data_referencia = CURDATE()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
    $expediente = $stmt->fetch(PDO::FETCH_ASSOC);

    return $expediente ?: null;
}

function getStatusExpedienteHoje(PDO $db, int $usuarioId): array
{
    $expediente = getExpedienteHoje($db, $usuarioId);

    if (!$expediente) {
        return [
            'status' => 'nao_iniciado',
            'expediente' => null,
        ];
    }

    return [
        'status' => $expediente['status'],
        'expediente' => $expediente,
    ];
}

function registrarLogExpediente(PDO $db, int $expedienteId, int $usuarioId, string $tipo): void
{
    $stmt = $db->prepare("
        INSERT INTO usuarios_expediente_logs (expediente_id, usuario_id, tipo, registrado_em)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$expedienteId, $usuarioId, $tipo]);
}

function registrarInicioExpediente(PDO $db, array $usuario): array
{
    ensureExpedienteSchema($db);
    $usuarioId = (int) ($usuario['id'] ?? 0);
    $expediente = getExpedienteHoje($db, $usuarioId);

    if ($expediente && $expediente['status'] === 'em_trabalho') {
        return ['success' => false, 'message' => 'Seu expediente de hoje já foi iniciado.'];
    }

    // Se o período anterior foi encerrado, reabrir cria um NOVO período —
    // o intervalo parado (almoço, pausa) não conta como tempo trabalhado.
    $reabertura = ($expediente && $expediente['status'] === 'encerrado');

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            INSERT INTO usuarios_expedientes (usuario_id, data_referencia, status, iniciado_em)
            VALUES (?, CURDATE(), 'em_trabalho', NOW())
        ");
        $stmt->execute([$usuarioId]);
        $expedienteId = (int) $db->lastInsertId();

        registrarLogExpediente($db, $expedienteId, $usuarioId, 'inicio');

        $payload = [
            'tipo' => 'expediente_inicio',
            'titulo' => $reabertura ? 'Expediente reaberto' : 'Expediente iniciado',
            'mensagem' => ($usuario['nome'] ?? 'Usuário') . ($reabertura ? ' reabriu o expediente.' : ' iniciou o expediente.'),
            'chave_evento' => 'expediente_inicio_' . $usuarioId . '_' . date('YmdHis'),
            'referencia_tipo' => 'usuario',
            'referencia_id' => $usuarioId,
        ];
        notificarPerfis($db, ['master'], $payload, ['interno']);

        $db->commit();
        return ['success' => true, 'message' => $reabertura ? 'Expediente reaberto com sucesso.' : 'Expediente iniciado com sucesso.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function registrarFimExpediente(PDO $db, array $usuario): array
{
    ensureExpedienteSchema($db);
    $usuarioId = (int) ($usuario['id'] ?? 0);
    $expediente = getExpedienteHoje($db, $usuarioId);

    if (!$expediente) {
        return ['success' => false, 'message' => 'Nenhum expediente iniciado hoje.'];
    }

    if ($expediente['status'] === 'encerrado') {
        return ['success' => false, 'message' => 'Seu expediente de hoje já foi finalizado.'];
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            UPDATE usuarios_expedientes
            SET status = 'encerrado', finalizado_em = NOW()
            WHERE id = ?
        ");
        $stmt->execute([(int) $expediente['id']]);

        registrarLogExpediente($db, (int) $expediente['id'], $usuarioId, 'fim');

        $payload = [
            'tipo' => 'expediente_fim',
            'titulo' => 'Expediente finalizado',
            'mensagem' => ($usuario['nome'] ?? 'Usuário') . ' finalizou o expediente.',
            'chave_evento' => 'expediente_fim_' . $usuarioId . '_' . date('Ymd'),
            'referencia_tipo' => 'usuario',
            'referencia_id' => $usuarioId,
        ];
        notificarPerfis($db, ['master'], $payload, ['interno']);

        $db->commit();
        return ['success' => true, 'message' => 'Expediente finalizado com sucesso.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function formatarSegundosExpediente(int $segundos): string
{
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $segundosRestantes = $segundos % 60;

    return sprintf('%02d:%02d:%02d', $horas, $minutos, $segundosRestantes);
}

function getTempoExpedienteHoje(PDO $db, int $usuarioId): int
{
    // Soma TODOS os períodos do dia (reabertura cria períodos novos; a
    // pausa entre eles não conta como tempo trabalhado).
    ensureExpedienteSchema($db);
    $stmt = $db->prepare("
        SELECT iniciado_em, finalizado_em
        FROM usuarios_expedientes
        WHERE usuario_id = ? AND data_referencia = CURDATE()
    ");
    $stmt->execute([$usuarioId]);
    $segundos = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $periodo) {
        $inicio = new DateTimeImmutable($periodo['iniciado_em']);
        $fim = !empty($periodo['finalizado_em'])
            ? new DateTimeImmutable($periodo['finalizado_em'])
            : new DateTimeImmutable();
        $segundos += max(0, $fim->getTimestamp() - $inicio->getTimestamp());
    }
    return $segundos;
}

function calcularSegundosTrabalhadosNoPeriodo(PDO $db, int $usuarioId, string $inicio, string $fim): int
{
    ensureExpedienteSchema($db);

    $stmt = $db->prepare("
        SELECT iniciado_em, finalizado_em
        FROM usuarios_expedientes
        WHERE usuario_id = ?
          AND iniciado_em < ?
          AND COALESCE(finalizado_em, NOW()) > ?
        ORDER BY iniciado_em ASC
    ");
    $stmt->execute([$usuarioId, $fim, $inicio]);
    $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inicioBase = new DateTimeImmutable($inicio);
    $fimBase = new DateTimeImmutable($fim);
    $segundos = 0;

    foreach ($periodos as $periodo) {
        $inicioPeriodo = new DateTimeImmutable($periodo['iniciado_em']);
        $fimPeriodo = !empty($periodo['finalizado_em'])
            ? new DateTimeImmutable($periodo['finalizado_em'])
            : $fimBase;

        $inicioIntersecao = $inicioPeriodo > $inicioBase ? $inicioPeriodo : $inicioBase;
        $fimIntersecao = $fimPeriodo < $fimBase ? $fimPeriodo : $fimBase;

        if ($fimIntersecao > $inicioIntersecao) {
            $segundos += $fimIntersecao->getTimestamp() - $inicioIntersecao->getTimestamp();
        }
    }

    return $segundos;
}

function getTempoTrabalhadoEtapaEmAndamento(PDO $db, ?string $dataInicio, int $usuarioId): int
{
    if (empty($dataInicio) || $usuarioId <= 0) {
        return 0;
    }

    return calcularSegundosTrabalhadosNoPeriodo(
        $db,
        $usuarioId,
        $dataInicio,
        date('Y-m-d H:i:s')
    );
}

function resetarExpedienteHoje(PDO $db, int $usuarioId, array $executor = []): array
{
    ensureExpedienteSchema($db);
    $expediente = getExpedienteHoje($db, $usuarioId);

    if (!$expediente) {
        return ['success' => false, 'message' => 'O usuário não possui expediente iniciado hoje.'];
    }

    $db->beginTransaction();

    try {
        // Remove TODOS os períodos de hoje (pode haver mais de um)
        $stmtLogs = $db->prepare("DELETE l FROM usuarios_expediente_logs l
            INNER JOIN usuarios_expedientes e ON e.id = l.expediente_id
            WHERE e.usuario_id = ? AND e.data_referencia = CURDATE()");
        $stmtLogs->execute([$usuarioId]);

        $stmtExpediente = $db->prepare("DELETE FROM usuarios_expedientes WHERE usuario_id = ? AND data_referencia = CURDATE()");
        $stmtExpediente->execute([$usuarioId]);

        $db->commit();

        return ['success' => true, 'message' => 'Expediente resetado com sucesso.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
