<?php
if (!defined('ABSPATH')) exit;

/**
 * Administración de Cupones de Garantías
 */
class WC_Garantias_Admin_Cupones {

    /**
     * Inicializar
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page'], 60);
    }

    /**
     * Agregar página al menú de admin
     */
    public static function add_menu_page() {
        add_submenu_page(
            'wc-garantias-dashboard',
            'Cupones de Garantías',
            'Cupones',
            'manage_woocommerce',
            'wc-garantias-cupones',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Renderizar página principal
     */
    public static function render_page() {
        // Obtener filtros
        $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        $filtro_estado = $_GET['filtro_estado'] ?? 'todos';
        $filtro_cliente = $_GET['filtro_cliente'] ?? '';
        $search = $_GET['s'] ?? '';

        // Obtener estadísticas
        $stats = WC_Garantias_Cupones::get_cupones_stats([
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ]);

        // Obtener todos los cupones
        $cupones = self::get_all_cupones_filtered($filtro_estado, $filtro_cliente, $search, $fecha_desde, $fecha_hasta);

        // Paginación
        $per_page = 20;
        $total_cupones = count($cupones);
        $total_pages = ceil($total_cupones / $per_page);
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        $cupones_paginados = array_slice($cupones, $offset, $per_page);

        ?>
        <div class="wrap garantias-cupones-admin">
            <h1>Gestión de Cupones de Garantías</h1>

            <style>
                .stats-grid-cupones {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 20px;
                    margin: 30px 0;
                }

                .stat-card-cupon {
                    background: white;
                    padding: 20px;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    border-left: 4px solid;
                }

                .stat-card-cupon.total { border-left-color: #667eea; }
                .stat-card-cupon.pendientes { border-left-color: #f093fb; }
                .stat-card-cupon.canjeados { border-left-color: #4facfe; }
                .stat-card-cupon.expirados { border-left-color: #fa709a; }
                .stat-card-cupon.monto-total { border-left-color: #43e97b; }
                .stat-card-cupon.monto-canjeado { border-left-color: #38f9d7; }
                .stat-card-cupon.monto-pendiente { border-left-color: #fee140; }

                .stat-card-cupon h3 {
                    margin: 0 0 10px 0;
                    font-size: 13px;
                    font-weight: 600;
                    color: #666;
                    text-transform: uppercase;
                }

                .stat-card-cupon .stat-value {
                    font-size: 28px;
                    font-weight: bold;
                    color: #333;
                }

                .filtros-cupones-admin {
                    background: white;
                    padding: 20px;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    margin-bottom: 20px;
                }

                .filtros-cupones-admin form {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    align-items: end;
                }

                .filtros-cupones-admin .form-group {
                    display: flex;
                    flex-direction: column;
                }

                .filtros-cupones-admin label {
                    font-weight: 500;
                    margin-bottom: 5px;
                    color: #555;
                    font-size: 13px;
                }

                .filtros-cupones-admin input,
                .filtros-cupones-admin select {
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }

                .filtros-cupones-admin button {
                    padding: 8px 20px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-weight: 500;
                    transition: background 0.3s;
                }

                .filtros-cupones-admin button:hover {
                    background: #5568d3;
                }

                .tabla-cupones-admin {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    overflow: hidden;
                }

                .tabla-cupones-admin table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .tabla-cupones-admin thead {
                    background: #f8f9fa;
                }

                .tabla-cupones-admin th {
                    padding: 12px 15px;
                    text-align: left;
                    font-weight: 600;
                    color: #555;
                    border-bottom: 2px solid #e0e0e0;
                    font-size: 13px;
                }

                .tabla-cupones-admin td {
                    padding: 12px 15px;
                    border-bottom: 1px solid #f0f0f0;
                    font-size: 13px;
                }

                .tabla-cupones-admin tbody tr:hover {
                    background: #f8f9fa;
                }

                .badge-cupon {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }

                .badge-cupon.pendiente {
                    background: #fff3cd;
                    color: #856404;
                }

                .badge-cupon.canjeado {
                    background: #d4edda;
                    color: #155724;
                }

                .badge-cupon.expirado {
                    background: #f8d7da;
                    color: #721c24;
                }

                .cupon-codigo-admin {
                    font-family: 'Courier New', monospace;
                    font-weight: bold;
                    color: #667eea;
                }

                .cupon-monto-admin {
                    font-weight: 600;
                    color: #43e97b;
                }

                .pagination-cupones {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 10px;
                    margin-top: 20px;
                    padding: 20px;
                }

                .pagination-cupones a,
                .pagination-cupones span {
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    text-decoration: none;
                    color: #333;
                }

                .pagination-cupones a:hover {
                    background: #667eea;
                    color: white;
                    border-color: #667eea;
                }

                .pagination-cupones .current {
                    background: #667eea;
                    color: white;
                    border-color: #667eea;
                }

                .export-buttons {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                }

                .export-buttons a {
                    padding: 10px 20px;
                    background: #43e97b;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: 500;
                }

                .export-buttons a:hover {
                    background: #38d66a;
                }
            </style>

            <!-- Stats Cards -->
            <div class="stats-grid-cupones">
                <div class="stat-card-cupon total">
                    <h3>Total Cupones</h3>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                </div>

                <div class="stat-card-cupon pendientes">
                    <h3>Pendientes</h3>
                    <div class="stat-value"><?php echo number_format($stats['pendientes']); ?></div>
                </div>

                <div class="stat-card-cupon canjeados">
                    <h3>Canjeados</h3>
                    <div class="stat-value"><?php echo number_format($stats['canjeados']); ?></div>
                </div>

                <div class="stat-card-cupon expirados">
                    <h3>Expirados</h3>
                    <div class="stat-value"><?php echo number_format($stats['expirados']); ?></div>
                </div>

                <div class="stat-card-cupon monto-total">
                    <h3>Monto Total</h3>
                    <div class="stat-value">$<?php echo number_format($stats['monto_total'], 2); ?></div>
                </div>

                <div class="stat-card-cupon monto-canjeado">
                    <h3>Monto Canjeado</h3>
                    <div class="stat-value">$<?php echo number_format($stats['monto_canjeado'], 2); ?></div>
                </div>

                <div class="stat-card-cupon monto-pendiente">
                    <h3>Monto Pendiente</h3>
                    <div class="stat-value">$<?php echo number_format($stats['monto_pendiente'], 2); ?></div>
                </div>
            </div>

            <!-- Exportar -->
            <div class="export-buttons">
                <a href="<?php echo admin_url('admin-ajax.php?action=wcgarantias_export_cupones&formato=csv&nonce=' . wp_create_nonce('export_cupones')); ?>">
                    Exportar CSV
                </a>
                <a href="<?php echo admin_url('admin-ajax.php?action=wcgarantias_export_cupones&formato=excel&nonce=' . wp_create_nonce('export_cupones')); ?>">
                    Exportar Excel
                </a>
            </div>

            <!-- Filtros -->
            <div class="filtros-cupones-admin">
                <form method="get">
                    <input type="hidden" name="page" value="wc-garantias-cupones">

                    <div class="form-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo esc_attr($fecha_desde); ?>">
                    </div>

                    <div class="form-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo esc_attr($fecha_hasta); ?>">
                    </div>

                    <div class="form-group">
                        <label>Estado</label>
                        <select name="filtro_estado">
                            <option value="todos" <?php selected($filtro_estado, 'todos'); ?>>Todos</option>
                            <option value="pendiente" <?php selected($filtro_estado, 'pendiente'); ?>>Pendientes</option>
                            <option value="canjeado" <?php selected($filtro_estado, 'canjeado'); ?>>Canjeados</option>
                            <option value="expirado" <?php selected($filtro_estado, 'expirado'); ?>>Expirados</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Buscar (Código/Cliente/Email/Garantía)</label>
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Buscar...">
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit">Aplicar Filtros</button>
                    </div>
                </form>
            </div>

            <!-- Tabla -->
            <div class="tabla-cupones-admin">
                <table>
                    <thead>
                        <tr>
                            <th>Código Cupón</th>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Garantía</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Fecha Canje</th>
                            <th>Orden Canje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cupones_paginados)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                    No se encontraron cupones con los filtros aplicados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cupones_paginados as $cupon): ?>
                                <tr>
                                    <td>
                                        <span class="cupon-codigo-admin"><?php echo esc_html($cupon['codigo']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($cupon['cliente_id'])) {
                                            $user = get_userdata($cupon['cliente_id']);
                                            echo $user ? esc_html($user->display_name) : 'N/A';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($cupon['cliente_id'])) {
                                            $user = get_userdata($cupon['cliente_id']);
                                            echo $user ? esc_html($user->user_email) : 'N/A';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($cupon['garantia_id'])): ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $cupon['garantia_id'] . '&action=edit'); ?>">
                                                <?php echo esc_html($cupon['garantia_codigo']); ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="cupon-monto-admin">$<?php echo number_format($cupon['monto'], 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-cupon <?php echo esc_attr($cupon['estado']); ?>">
                                            <?php echo esc_html(ucfirst($cupon['estado'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($cupon['fecha_creacion'])); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($cupon['estado'] === 'canjeado' && !empty($cupon['fecha_canje'])) {
                                            echo date('d/m/Y H:i', strtotime($cupon['fecha_canje']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($cupon['estado'] === 'canjeado' && !empty($cupon['order_id'])) {
                                            ?>
                                            <a href="<?php echo admin_url('post.php?post=' . $cupon['order_id'] . '&action=edit'); ?>">
                                                #<?php echo $cupon['order_id']; ?>
                                            </a>
                                            <?php
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-cupones">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo self::get_pagination_url($current_page - 1); ?>">← Anterior</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo self::get_pagination_url($i); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo self::get_pagination_url($current_page + 1); ?>">Siguiente →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Obtener todos los cupones filtrados
     */
    private static function get_all_cupones_filtered($filtro_estado, $filtro_cliente, $search, $fecha_desde, $fecha_hasta) {
        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'date_query' => [
                [
                    'after' => $fecha_desde,
                    'before' => $fecha_hasta,
                    'inclusive' => true
                ]
            ]
        ];

        $garantias = get_posts($args);
        $cupones = [];

        foreach ($garantias as $garantia) {
            // Cupón principal
            $cupon_codigo = get_post_meta($garantia->ID, '_cupon_generado', true);
            if ($cupon_codigo) {
                $info = WC_Garantias_Cupones::get_cupon_info($cupon_codigo);
                if ($info) {
                    // Aplicar filtros
                    if (!self::apply_filters($info, $filtro_estado, $search)) {
                        continue;
                    }
                    $cupones[] = $info;
                }
            }

            // Cupones adicionales
            $cupones_adicionales = get_post_meta($garantia->ID, '_cupones_adicionales', true) ?: [];
            foreach ($cupones_adicionales as $cupon_adicional) {
                $info = WC_Garantias_Cupones::get_cupon_info($cupon_adicional['codigo']);
                if ($info) {
                    if (!self::apply_filters($info, $filtro_estado, $search)) {
                        continue;
                    }
                    $cupones[] = $info;
                }
            }
        }

        // Ordenar por fecha de creación descendente
        usort($cupones, function($a, $b) {
            return strtotime($b['fecha_creacion']) - strtotime($a['fecha_creacion']);
        });

        return $cupones;
    }

    /**
     * Aplicar filtros a un cupón
     */
    private static function apply_filters($cupon, $filtro_estado, $search) {
        // Filtro por estado
        if ($filtro_estado !== 'todos' && $cupon['estado'] !== $filtro_estado) {
            return false;
        }

        // Filtro de búsqueda
        if (!empty($search)) {
            $search_lower = strtolower($search);

            // Buscar en código de cupón
            if (stripos($cupon['codigo'], $search) !== false) {
                return true;
            }

            // Buscar en código de garantía
            if (!empty($cupon['garantia_codigo']) && stripos($cupon['garantia_codigo'], $search) !== false) {
                return true;
            }

            // Buscar en nombre/email del cliente
            if (!empty($cupon['cliente_id'])) {
                $user = get_userdata($cupon['cliente_id']);
                if ($user) {
                    if (stripos($user->display_name, $search) !== false || stripos($user->user_email, $search) !== false) {
                        return true;
                    }
                }
            }

            // Si no coincide con nada, filtrar
            return false;
        }

        return true;
    }

    /**
     * Generar URL de paginación
     */
    private static function get_pagination_url($page) {
        $args = $_GET;
        $args['paged'] = $page;
        return add_query_arg($args, admin_url('admin.php'));
    }
}
