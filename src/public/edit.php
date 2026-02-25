<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php?msg=error');
    exit;
}

$confianza = strtoupper($_POST['confianza'] ?? 'MEDIA');
if (!in_array($confianza, ['ALTA', 'MEDIA', 'BAJA'])) $confianza = 'MEDIA';

$riesgo = strtolower($_POST['riesgo'] ?? 'medio');
if (!in_array($riesgo, ['bajo', 'medio', 'alto'])) $riesgo = 'medio';

$resultado = strtolower($_POST['resultado'] ?? 'pendiente');
if (!in_array($resultado, ['pendiente', 'ganado', 'perdido', 'void'])) $resultado = 'pendiente';

$ganancia = null;
if ($resultado !== 'pendiente' && $_POST['ganancia_soles'] !== '') {
    $ganancia = filter_input(INPUT_POST, 'ganancia_soles', FILTER_VALIDATE_FLOAT);
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("
        UPDATE tickets SET
            partido              = :partido,
            mercado              = :mercado,
            seleccion            = :seleccion,
            cuota_betano         = :cuota,
            value_pct            = :value_pct,
            prob_estimada        = :prob_est,
            prob_implicita_cuota = :prob_imp,
            stake_soles          = :stake,
            confianza            = :confianza,
            riesgo               = :riesgo,
            resultado            = :resultado,
            ganancia_soles       = :ganancia,
            razon                = :razon
        WHERE id = :id
    ");

    $stmt->execute([
        'partido'   => trim($_POST['partido']   ?? ''),
        'mercado'   => trim($_POST['mercado']   ?? ''),
        'seleccion' => trim($_POST['seleccion'] ?? ''),
        'cuota'     => filter_input(INPUT_POST, 'cuota_betano', FILTER_VALIDATE_FLOAT),
        'value_pct' => filter_input(INPUT_POST, 'value_pct',   FILTER_VALIDATE_FLOAT),
        'prob_est'  => filter_input(INPUT_POST, 'prob_estimada', FILTER_VALIDATE_FLOAT),
        'prob_imp'  => filter_input(INPUT_POST, 'prob_implicita_cuota', FILTER_VALIDATE_FLOAT),
        'stake'     => filter_input(INPUT_POST, 'stake_soles', FILTER_VALIDATE_FLOAT),
        'confianza' => $confianza,
        'riesgo'    => $riesgo,
        'resultado' => $resultado,
        'ganancia'  => $ganancia,
        'razon'     => trim($_POST['razon'] ?? ''),
        'id'        => $id,
    ]);

    header('Location: index.php?msg=actualizado');
    exit;

} catch (Exception $e) {
    error_log('Edit error: ' . $e->getMessage());
    header('Location: index.php?msg=error');
    exit;
}
