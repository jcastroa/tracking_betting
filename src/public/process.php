<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['json_data'])) {
    header('Location: index.php?msg=error');
    exit;
}

try {
    $data = json_decode($_POST['json_data'], true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    header('Location: index.php?msg=error');
    exit;
}

if (empty($data['tickets_recomendados'])) {
    header('Location: index.php?msg=error');
    exit;
}

try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // Insert analisis record
    $stmtAnalisis = $pdo->prepare("
        INSERT INTO analisis
            (fecha_analisis, casa_apuestas, total_partidos, resumen,
             exposicion_soles, exposicion_pct_bankroll, exposicion_num_tickets)
        VALUES
            (:fecha, :casa, :total, :resumen, :soles, :pct, :num)
    ");
    $stmtAnalisis->execute([
        'fecha'  => $data['fecha_analisis']  ?? date('Y-m-d'),
        'casa'   => $data['casa_apuestas']   ?? 'Sin nombre',
        'total'  => $data['total_partidos']  ?? 0,
        'resumen'=> $data['resumen']         ?? null,
        'soles'  => $data['exposicion_total']['soles']        ?? 0,
        'pct'    => $data['exposicion_total']['pct_bankroll'] ?? 0,
        'num'    => $data['exposicion_total']['num_tickets']  ?? 0,
    ]);
    $analisisId = $pdo->lastInsertId();

    // Insert tickets
    $stmtTicket = $pdo->prepare("
        INSERT INTO tickets
            (analisis_id, partido, mercado, seleccion, cuota_betano,
             prob_estimada, prob_implicita_cuota, value_pct, confianza,
             stake_unidades, stake_soles, razon, riesgo)
        VALUES
            (:analisis_id, :partido, :mercado, :seleccion, :cuota,
             :prob_est, :prob_imp, :value_pct, :confianza,
             :stake_u, :stake_s, :razon, :riesgo)
    ");

    foreach ($data['tickets_recomendados'] as $t) {
        $confianza = strtoupper($t['confianza'] ?? 'MEDIA');
        if (!in_array($confianza, ['ALTA', 'MEDIA', 'BAJA'])) $confianza = 'MEDIA';

        $riesgo = strtolower($t['riesgo'] ?? 'medio');
        if (!in_array($riesgo, ['bajo', 'medio', 'alto'])) $riesgo = 'medio';

        $stmtTicket->execute([
            'analisis_id' => $analisisId,
            'partido'     => $t['partido']                ?? '',
            'mercado'     => $t['mercado']                ?? '',
            'seleccion'   => $t['seleccion']              ?? '',
            'cuota'       => $t['cuota_betano']           ?? 0,
            'prob_est'    => $t['prob_estimada']          ?? 0,
            'prob_imp'    => $t['prob_implicita_cuota']   ?? 0,
            'value_pct'   => $t['value_pct']              ?? 0,
            'confianza'   => $confianza,
            'stake_u'     => $t['stake_unidades']         ?? 1,
            'stake_s'     => $t['stake_soles']            ?? 1,
            'razon'       => $t['razon']                  ?? null,
            'riesgo'      => $riesgo,
        ]);
    }

    // Insert partidos_sin_value if present
    if (!empty($data['partidos_sin_value'])) {
        $stmtSinValue = $pdo->prepare("
            INSERT INTO partidos_sin_value (analisis_id, partido, razon, cuota_mas_cercana)
            VALUES (:analisis_id, :partido, :razon, :cuota)
        ");
        foreach ($data['partidos_sin_value'] as $p) {
            $stmtSinValue->execute([
                'analisis_id' => $analisisId,
                'partido'     => $p['partido']           ?? '',
                'razon'       => $p['razon']             ?? null,
                'cuota'       => $p['cuota_mas_cercana'] ?? null,
            ]);
        }
    }

    $pdo->commit();
    header('Location: index.php?msg=importado');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Import error: ' . $e->getMessage());
    header('Location: index.php?msg=error');
    exit;
}
