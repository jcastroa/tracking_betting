<?php
require_once __DIR__ . '/../config/database.php';

$mensaje = '';
$tipo_mensaje = '';

// Fetch all tickets with analisis info
function getTickets(PDO $pdo, array $filtros = []): array {
    $where = ['1=1'];
    $params = [];

    if (!empty($filtros['confianza'])) {
        $where[] = 't.confianza = :confianza';
        $params['confianza'] = $filtros['confianza'];
    }
    if (!empty($filtros['riesgo'])) {
        $where[] = 't.riesgo = :riesgo';
        $params['riesgo'] = $filtros['riesgo'];
    }
    if (!empty($filtros['resultado'])) {
        $where[] = 't.resultado = :resultado';
        $params['resultado'] = $filtros['resultado'];
    }
    if (!empty($filtros['fecha'])) {
        $where[] = 'a.fecha_analisis = :fecha';
        $params['fecha'] = $filtros['fecha'];
    }

    $sql = "SELECT t.*, a.fecha_analisis, a.casa_apuestas
            FROM tickets t
            JOIN analisis a ON t.analisis_id = a.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.fecha_analisis DESC, t.value_pct DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

try {
    $pdo = Database::getConnection();
    $filtros = [
        'confianza' => $_GET['confianza'] ?? '',
        'riesgo'    => $_GET['riesgo'] ?? '',
        'resultado' => $_GET['resultado'] ?? '',
        'fecha'     => $_GET['fecha'] ?? '',
    ];
    $tickets = getTickets($pdo, $filtros);

    // Stats
    $stats = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN resultado='ganado' THEN 1 ELSE 0 END) as ganados,
            SUM(CASE WHEN resultado='perdido' THEN 1 ELSE 0 END) as perdidos,
            SUM(CASE WHEN resultado='pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN resultado='ganado' THEN ganancia_soles ELSE 0 END) -
            SUM(CASE WHEN resultado='perdido' THEN stake_soles ELSE 0 END) as ganancia_neta
        FROM tickets
    ")->fetch();

} catch (Exception $e) {
    $tickets = [];
    $stats = ['total'=>0,'ganados'=>0,'perdidos'=>0,'pendientes'=>0,'ganancia_neta'=>0];
    $mensaje = 'Error de conexión a la base de datos: ' . $e->getMessage();
    $tipo_mensaje = 'danger';
}

if (isset($_GET['msg'])) {
    $msg_map = [
        'importado'  => ['success', 'JSON importado exitosamente.'],
        'eliminado'  => ['warning', 'Ticket eliminado correctamente.'],
        'actualizado'=> ['success', 'Ticket actualizado correctamente.'],
        'error'      => ['danger',  'Ocurrió un error. Intente de nuevo.'],
    ];
    if (isset($msg_map[$_GET['msg']])) {
        [$tipo_mensaje, $mensaje] = $msg_map[$_GET['msg']];
    }
}

$confianza_sel = $filtros['confianza'];
$riesgo_sel    = $filtros['riesgo'];
$resultado_sel = $filtros['resultado'];
$fecha_sel     = $filtros['fecha'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betting Tracker - Gestión de Tickets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #0d6efd;
            --green-dark: #198754;
        }
        body { background: #f0f2f5; }
        .navbar-brand { font-weight: 700; letter-spacing: 1px; }
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,.08); border-radius: 12px; }
        .card-header { border-radius: 12px 12px 0 0 !important; }
        .stat-card { border-left: 4px solid var(--color); }
        .badge-confianza-ALTA  { background: #198754; }
        .badge-confianza-MEDIA { background: #fd7e14; }
        .badge-confianza-BAJA  { background: #dc3545; }
        .badge-riesgo-bajo   { background: #0dcaf0; color: #000; }
        .badge-riesgo-medio  { background: #ffc107; color: #000; }
        .badge-riesgo-alto   { background: #dc3545; }
        .badge-resultado-ganado    { background: #198754; }
        .badge-resultado-perdido   { background: #dc3545; }
        .badge-resultado-pendiente { background: #6c757d; }
        .badge-resultado-void      { background: #adb5bd; color:#000; }
        .value-pct { font-weight: 700; }
        .value-high  { color: #198754; }
        .value-mid   { color: #fd7e14; }
        .value-low   { color: #dc3545; }
        #jsonInput { font-family: 'Courier New', monospace; font-size: .82rem; }
        .table thead th { background: #212529; color: #fff; white-space: nowrap; }
        .table-hover tbody tr:hover { background: #e8f4fd; }
        .action-btn { padding: 2px 8px; }
        @media (max-width: 768px) {
            .table-responsive { font-size: .78rem; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-bar-chart-fill me-2"></i>Betting Tracker
        </span>
        <span class="text-light small">Sistema de gestión de tickets de apuestas</span>
    </div>
</nav>

<div class="container-fluid py-4">

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'warning' ? 'exclamation-triangle' : 'x-circle') ?> me-2"></i>
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100" style="--color:#0d6efd">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-primary"><?= $stats['total'] ?></div>
                    <div class="text-muted small">Total Tickets</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100" style="--color:#198754">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-success"><?= $stats['ganados'] ?></div>
                    <div class="text-muted small">Ganados</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100" style="--color:#dc3545">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-danger"><?= $stats['perdidos'] ?></div>
                    <div class="text-muted small">Perdidos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100" style="--color:<?= ($stats['ganancia_neta'] >= 0) ? '#198754' : '#dc3545' ?>">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold <?= ($stats['ganancia_neta'] >= 0) ? 'text-success' : 'text-danger' ?>">
                        S/ <?= number_format((float)$stats['ganancia_neta'], 2) ?>
                    </div>
                    <div class="text-muted small">Ganancia Neta</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Import JSON Panel -->
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-cloud-upload me-2"></i>Importar JSON
                </div>
                <div class="card-body">
                    <form id="jsonForm" action="process.php" method="POST">
                        <div class="mb-3">
                            <label for="jsonInput" class="form-label fw-semibold">
                                Pega el JSON de análisis:
                            </label>
                            <textarea
                                class="form-control"
                                id="jsonInput"
                                name="json_data"
                                rows="18"
                                placeholder='{ "fecha_analisis": "...", "tickets_recomendados": [...] }'
                                required
                            ></textarea>
                            <div class="invalid-feedback" id="jsonError"></div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="validateJson()">
                                <i class="bi bi-check2-circle me-1"></i>Validar JSON
                            </button>
                            <button type="submit" class="btn btn-primary" id="btnImport">
                                <i class="bi bi-database-add me-1"></i>Importar Tickets
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('jsonInput').value=''">
                                <i class="bi bi-trash me-1"></i>Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="bi bi-table me-2"></i>Tickets Recomendados</span>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-success" onclick="exportExcel()">
                            <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
                        </button>
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#filtros">
                            <i class="bi bi-funnel me-1"></i>Filtros
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="collapse <?= (!empty(array_filter($filtros))) ? 'show' : '' ?>" id="filtros">
                    <div class="card-body border-bottom bg-light">
                        <form method="GET" class="row g-2 align-items-end">
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold mb-1">Confianza</label>
                                <select name="confianza" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    <option value="ALTA"  <?= $confianza_sel === 'ALTA'  ? 'selected' : '' ?>>Alta</option>
                                    <option value="MEDIA" <?= $confianza_sel === 'MEDIA' ? 'selected' : '' ?>>Media</option>
                                    <option value="BAJA"  <?= $confianza_sel === 'BAJA'  ? 'selected' : '' ?>>Baja</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold mb-1">Riesgo</label>
                                <select name="riesgo" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="bajo"  <?= $riesgo_sel === 'bajo'  ? 'selected' : '' ?>>Bajo</option>
                                    <option value="medio" <?= $riesgo_sel === 'medio' ? 'selected' : '' ?>>Medio</option>
                                    <option value="alto"  <?= $riesgo_sel === 'alto'  ? 'selected' : '' ?>>Alto</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold mb-1">Resultado</label>
                                <select name="resultado" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <option value="pendiente" <?= $resultado_sel === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="ganado"    <?= $resultado_sel === 'ganado'    ? 'selected' : '' ?>>Ganado</option>
                                    <option value="perdido"   <?= $resultado_sel === 'perdido'   ? 'selected' : '' ?>>Perdido</option>
                                    <option value="void"      <?= $resultado_sel === 'void'      ? 'selected' : '' ?>>Void</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold mb-1">Fecha</label>
                                <input type="date" name="fecha" class="form-control form-control-sm" value="<?= htmlspecialchars($fecha_sel) ?>">
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x-circle me-1"></i>Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="ticketsTable" class="table table-hover table-bordered mb-0 small">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Casa</th>
                                    <th>Partido</th>
                                    <th>Mercado</th>
                                    <th>Selección</th>
                                    <th>Cuota</th>
                                    <th>Value%</th>
                                    <th>Confianza</th>
                                    <th>Stake (S/)</th>
                                    <th>Riesgo</th>
                                    <th>Resultado</th>
                                    <th>Ganancia</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $i => $t): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($t['fecha_analisis']) ?></td>
                                    <td><?= htmlspecialchars($t['casa_apuestas']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($t['partido']) ?></td>
                                    <td><?= htmlspecialchars($t['mercado']) ?></td>
                                    <td><?= htmlspecialchars($t['seleccion']) ?></td>
                                    <td class="text-center fw-bold text-primary"><?= number_format((float)$t['cuota_betano'], 2) ?></td>
                                    <td class="text-center">
                                        <span class="value-pct <?= $t['value_pct'] >= 20 ? 'value-high' : ($t['value_pct'] >= 10 ? 'value-mid' : 'value-low') ?>">
                                            <?= number_format((float)$t['value_pct'], 1) ?>%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-confianza-<?= $t['confianza'] ?> rounded-pill">
                                            <?= $t['confianza'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">S/ <?= number_format((float)$t['stake_soles'], 2) ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-riesgo-<?= $t['riesgo'] ?> rounded-pill">
                                            <?= ucfirst($t['riesgo']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-resultado-<?= $t['resultado'] ?> rounded-pill">
                                            <?= ucfirst($t['resultado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($t['ganancia_soles'] !== null): ?>
                                            <span class="<?= $t['ganancia_soles'] > 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                                                S/ <?= number_format((float)$t['ganancia_soles'], 2) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="white-space:nowrap">
                                        <button class="btn btn-sm btn-outline-primary action-btn"
                                                onclick="editTicket(<?= $t['id'] ?>)"
                                                title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger action-btn"
                                                onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['partido']), ENT_QUOTES) ?>')"
                                                title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Ticket -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Editar Ticket
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" action="edit.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Partido</label>
                            <input type="text" class="form-control" name="partido" id="edit_partido" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mercado</label>
                            <input type="text" class="form-control" name="mercado" id="edit_mercado" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Selección</label>
                            <input type="text" class="form-control" name="seleccion" id="edit_seleccion" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Cuota</label>
                            <input type="number" step="0.001" class="form-control" name="cuota_betano" id="edit_cuota" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Value %</label>
                            <input type="number" step="0.01" class="form-control" name="value_pct" id="edit_value" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Prob. Estimada</label>
                            <input type="number" step="0.0001" min="0" max="1" class="form-control" name="prob_estimada" id="edit_prob_est">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Prob. Implícita</label>
                            <input type="number" step="0.0001" min="0" max="1" class="form-control" name="prob_implicita_cuota" id="edit_prob_imp">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stake (S/)</label>
                            <input type="number" step="0.01" class="form-control" name="stake_soles" id="edit_stake">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Confianza</label>
                            <select class="form-select" name="confianza" id="edit_confianza">
                                <option value="ALTA">ALTA</option>
                                <option value="MEDIA">MEDIA</option>
                                <option value="BAJA">BAJA</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Riesgo</label>
                            <select class="form-select" name="riesgo" id="edit_riesgo">
                                <option value="bajo">Bajo</option>
                                <option value="medio">Medio</option>
                                <option value="alto">Alto</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Resultado</label>
                            <select class="form-select" name="resultado" id="edit_resultado">
                                <option value="pendiente">Pendiente</option>
                                <option value="ganado">Ganado</option>
                                <option value="perdido">Perdido</option>
                                <option value="void">Void</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="ganancia_field">
                            <label class="form-label fw-semibold">Ganancia (S/)</label>
                            <input type="number" step="0.01" class="form-control" name="ganancia_soles" id="edit_ganancia" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Razón</label>
                            <textarea class="form-control" name="razon" id="edit_razon" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirmar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Eliminar el ticket de <strong id="deletePartido"></strong>?</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <a id="deleteLink" href="#" class="btn btn-danger btn-sm">
                    <i class="bi bi-trash me-1"></i>Eliminar
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
// DataTable init
$(document).ready(function () {
    $('#ticketsTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json',
            emptyTable: '<span class="text-muted"><i class="bi bi-inbox me-2"></i>No hay tickets. Importa un JSON para comenzar.</span>'
        },
        pageLength: 25,
        order: [[7, 'desc']],
        columnDefs: [
            { orderable: false, targets: 13 }
        ]
    });
});

// JSON Validation
function validateJson() {
    const input = document.getElementById('jsonInput');
    const errorDiv = document.getElementById('jsonError');
    try {
        const data = JSON.parse(input.value);
        if (!data.tickets_recomendados) {
            throw new Error('Falta la clave "tickets_recomendados"');
        }
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        errorDiv.textContent = '';
        alert('JSON válido. ' + data.tickets_recomendados.length + ' tickets encontrados.');
    } catch (e) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        errorDiv.textContent = 'JSON inválido: ' + e.message;
    }
}

// Edit ticket - fetch data via AJAX
function editTicket(id) {
    fetch('get_ticket.php?id=' + id)
        .then(r => r.json())
        .then(t => {
            document.getElementById('edit_id').value       = t.id;
            document.getElementById('edit_partido').value  = t.partido;
            document.getElementById('edit_mercado').value  = t.mercado;
            document.getElementById('edit_seleccion').value= t.seleccion;
            document.getElementById('edit_cuota').value    = t.cuota_betano;
            document.getElementById('edit_value').value    = t.value_pct;
            document.getElementById('edit_prob_est').value = t.prob_estimada;
            document.getElementById('edit_prob_imp').value = t.prob_implicita_cuota;
            document.getElementById('edit_stake').value    = t.stake_soles;
            document.getElementById('edit_confianza').value= t.confianza;
            document.getElementById('edit_riesgo').value   = t.riesgo;
            document.getElementById('edit_resultado').value= t.resultado;
            document.getElementById('edit_ganancia').value = t.ganancia_soles ?? '';
            document.getElementById('edit_razon').value    = t.razon;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
}

// Confirm delete
function confirmDelete(id, partido) {
    document.getElementById('deletePartido').textContent = partido;
    document.getElementById('deleteLink').href = 'delete.php?id=' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Export Excel
function exportExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'export.php?' + params.toString();
}
</script>
</body>
</html>
