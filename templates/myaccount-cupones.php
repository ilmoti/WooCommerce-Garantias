<?php
if (!defined('ABSPATH')) exit;

$customer_id = get_current_user_id();
$filtro_estado = isset($_GET['filtro_estado']) ? sanitize_text_field($_GET['filtro_estado']) : 'todos';

// Obtener cupones del cliente
$cupones = WC_Garantias_Cupones::get_cupones_cliente($customer_id, $filtro_estado);

// Calcular estadísticas rápidas
$stats = [
    'total' => 0,
    'pendientes' => 0,
    'canjeados' => 0,
    'monto_total' => 0,
    'monto_pendiente' => 0,
];

foreach ($cupones as $cupon) {
    $stats['total']++;
    $stats['monto_total'] += $cupon['monto'];

    if ($cupon['estado'] === 'pendiente') {
        $stats['pendientes']++;
        $stats['monto_pendiente'] += $cupon['monto'];
    } elseif ($cupon['estado'] === 'canjeado') {
        $stats['canjeados']++;
    }
}
?>

<style>
.cupones-container {
    max-width: 1200px;
    margin: 0 auto;
}

.cupones-header {
    margin-bottom: 30px;
}

.cupones-header h2 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-card.pendientes {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card.canjeados {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.monto {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 500;
    opacity: 0.9;
}

.stat-card .stat-value {
    font-size: 32px;
    font-weight: bold;
    margin: 0;
}

.filtros-cupones {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.filtros-cupones form {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filtros-cupones label {
    font-weight: 500;
    color: #555;
}

.filtros-cupones select {
    padding: 8px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background: white;
}

.filtros-cupones button {
    padding: 8px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s;
}

.filtros-cupones button:hover {
    background: #5568d3;
}

.cupones-table {
    width: 100%;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.cupones-table table {
    width: 100%;
    border-collapse: collapse;
}

.cupones-table thead {
    background: #f8f9fa;
}

.cupones-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #555;
    border-bottom: 2px solid #e0e0e0;
}

.cupones-table td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
}

.cupones-table tbody tr:hover {
    background: #f8f9fa;
}

.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge.pendiente {
    background: #fff3cd;
    color: #856404;
}

.badge.canjeado {
    background: #d4edda;
    color: #155724;
}

.badge.expirado {
    background: #f8d7da;
    color: #721c24;
}

.cupon-codigo {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 14px;
    color: #667eea;
}

.cupon-monto {
    font-size: 18px;
    font-weight: 600;
    color: #43e97b;
}

.cupon-items {
    margin: 5px 0;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 5px;
    font-size: 12px;
}

.cupon-items ul {
    margin: 0;
    padding-left: 20px;
    list-style: disc;
}

.cupon-items li {
    margin: 3px 0;
    color: #666;
}

.link-garantia {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}

.link-garantia:hover {
    text-decoration: underline;
}

.link-order {
    color: #4facfe;
    text-decoration: none;
    font-weight: 500;
}

.link-order:hover {
    text-decoration: underline;
}

.no-cupones {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 10px;
    margin: 20px 0;
}

.no-cupones svg {
    width: 80px;
    height: 80px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-cupones h3 {
    font-size: 20px;
    margin-bottom: 10px;
    color: #666;
}

.no-cupones p {
    color: #999;
}

@media (max-width: 768px) {
    .cupones-table {
        overflow-x: auto;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="cupones-container">
    <div class="cupones-header">
        <h2 style="text-align: center; margin-bottom: 20px;">MIS GARANTÍAS</h2>

        <!-- Menú de navegación de tabs -->
        <div class="garantias-nav-tabs" style="
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
        ">
            <a href="<?php echo esc_url(wc_get_endpoint_url('garantias', '', wc_get_page_permalink('myaccount'))); ?>"
               class="nav-tab"
               style="
                   padding: 12px 30px;
                   text-decoration: none;
                   color: #666;
                   font-weight: 500;
                   border-bottom: 3px solid transparent;
                   transition: all 0.3s;
               "
               onmouseover="this.style.color='#667eea'; this.style.borderBottomColor='#667eea';"
               onmouseout="this.style.color='#666'; this.style.borderBottomColor='transparent';">
                Mis Garantías
            </a>
            <a href="<?php echo esc_url(wc_get_endpoint_url('cupones-garantia', '', wc_get_page_permalink('myaccount'))); ?>"
               class="nav-tab active"
               style="
                   padding: 12px 30px;
                   text-decoration: none;
                   color: #667eea;
                   font-weight: 600;
                   border-bottom: 3px solid #667eea;
                   transition: all 0.3s;
               ">
                Mis Cupones
            </a>
        </div>

        <p style="color: #666; text-align: center; margin-bottom: 20px;">Aquí puedes ver todos tus cupones generados por garantías aprobadas.</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Cupones</h3>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
        </div>

        <div class="stat-card pendientes">
            <h3>Pendientes</h3>
            <div class="stat-value"><?php echo $stats['pendientes']; ?></div>
        </div>

        <div class="stat-card canjeados">
            <h3>Canjeados</h3>
            <div class="stat-value"><?php echo $stats['canjeados']; ?></div>
        </div>

        <div class="stat-card monto">
            <h3>Total Pendiente</h3>
            <div class="stat-value">$<?php echo number_format($stats['monto_pendiente'], 2); ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filtros-cupones">
        <form method="get">
            <label for="filtro_estado">Filtrar por estado:</label>
            <select name="filtro_estado" id="filtro_estado">
                <option value="todos" <?php selected($filtro_estado, 'todos'); ?>>Todos</option>
                <option value="pendiente" <?php selected($filtro_estado, 'pendiente'); ?>>Pendientes</option>
                <option value="canjeado" <?php selected($filtro_estado, 'canjeado'); ?>>Canjeados</option>
                <option value="expirado" <?php selected($filtro_estado, 'expirado'); ?>>Expirados</option>
            </select>
            <button type="submit">Aplicar Filtro</button>
        </form>
    </div>

    <!-- Tabla de Cupones -->
    <?php if (empty($cupones)): ?>
        <div class="no-cupones">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3>No hay cupones disponibles</h3>
            <p>Cuando se aprueben tus garantías, los cupones aparecerán aquí.</p>
        </div>
    <?php else: ?>
        <div class="cupones-table">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Garantía</th>
                        <th>Productos</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                        <th>Información de Canje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cupones as $cupon): ?>
                        <tr>
                            <td>
                                <div class="cupon-codigo"><?php echo esc_html($cupon['codigo']); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($cupon['garantia_codigo'])): ?>
                                    <a href="<?php echo esc_url(wc_get_endpoint_url('garantias', '', wc_get_page_permalink('myaccount'))); ?>"
                                       class="link-garantia">
                                        <?php echo esc_html($cupon['garantia_codigo']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($cupon['cupon_items'])): ?>
                                    <div class="cupon-items">
                                        <ul>
                                            <?php foreach ($cupon['cupon_items'] as $item): ?>
                                                <li>
                                                    <strong><?php echo esc_html($item['nombre']); ?></strong>
                                                    (x<?php echo $item['cantidad']; ?>)
                                                    - $<?php echo number_format($item['subtotal'], 2); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;">Sin detalles</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cupon-monto">$<?php echo number_format($cupon['monto'], 2); ?></div>
                            </td>
                            <td>
                                <span class="badge <?php echo esc_attr($cupon['estado']); ?>">
                                    <?php echo esc_html(ucfirst($cupon['estado'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($cupon['fecha_creacion'])); ?>
                            </td>
                            <td>
                                <?php if ($cupon['estado'] === 'canjeado' && !empty($cupon['order_id'])): ?>
                                    <div>
                                        <strong>Canjeado:</strong><br>
                                        <?php echo date('d/m/Y H:i', strtotime($cupon['fecha_canje'])); ?><br>
                                        <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $cupon['order_id'], wc_get_page_permalink('myaccount'))); ?>"
                                           class="link-order">
                                            Ver Orden #<?php echo $cupon['order_id']; ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
