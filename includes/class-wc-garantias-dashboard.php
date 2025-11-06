<?php
if (!defined('ABSPATH')) exit;

/**
 * Dashboard mejorado con estadísticas y métricas
 */
class WC_Garantias_Dashboard {
    
    /**
     * Inicializar dashboard
     */
    public static function init() {
        // Hooks para admin
        add_action('admin_menu', [__CLASS__, 'add_dashboard_page']);
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
        
        // Shortcodes para frontend
        add_shortcode('garantias_dashboard', [__CLASS__, 'render_customer_dashboard']);
        
        // AJAX endpoints
        add_action('wp_ajax_wcgarantias_get_dashboard_stats', [__CLASS__, 'ajax_get_stats']);
        add_action('wp_ajax_wcgarantias_export_report', [__CLASS__, 'ajax_export_report']);
    }
    
    /**
     * Agregar página de dashboard en admin
     */
    public static function add_dashboard_page() {
        add_submenu_page(
            'wc-garantias',
            'Dashboard de Garantías',
            'Dashboard',
            'manage_woocommerce',
            'wc-garantias-dashboard',
            [__CLASS__, 'render_admin_dashboard']
        );
    }
    
    /**
     * Widget para el dashboard principal de WordPress
     */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'garantias_dashboard_widget',
            'Garantías - Resumen Rápido',
            [__CLASS__, 'render_dashboard_widget']
        );
    }
    
    /**
     * Renderizar dashboard de admin
     */
    public static function render_admin_dashboard() {
        $stats = self::get_admin_stats();
        ?>
        <div class="wrap garantias-dashboard-admin">
            <h1>Dashboard de Garantías</h1>
            
            <!-- Filtros de fecha -->
            <div class="date-filters" style="margin: 20px 0;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wc-garantias-dashboard">
                    <label>Desde: <input type="date" name="fecha_desde" value="<?php echo esc_attr($_GET['fecha_desde'] ?? date('Y-m-01')); ?>"></label>
                    <label>Hasta: <input type="date" name="fecha_hasta" value="<?php echo esc_attr($_GET['fecha_hasta'] ?? date('Y-m-d')); ?>"></label>
                    <button type="submit" class="button">Filtrar</button>
                </form>
            </div>
            
            <!-- Cards de estadísticas -->
            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-top: 0;">Total Garantías</h3>
                    <div style="font-size: 36px; font-weight: bold;"><?php echo number_format($stats['total']); ?></div>
                    <p style="margin-bottom: 0; opacity: 0.9;">
                        <span style="color: #4ade80;">↑ <?php echo $stats['variacion_total']; ?>%</span> vs mes anterior
                    </p>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-top: 0;">Pendientes</h3>
                    <div style="font-size: 36px; font-weight: bold;"><?php echo number_format($stats['pendientes']); ?></div>
                    <p style="margin-bottom: 0; opacity: 0.9;">
                        <?php echo $stats['porcentaje_pendientes']; ?>% del total
                    </p>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-top: 0;">Tiempo Promedio</h3>
                    <div style="font-size: 36px; font-weight: bold;"><?php echo $stats['tiempo_promedio']; ?></div>
                    <p style="margin-bottom: 0; opacity: 0.9;">días de resolución</p>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 20px; border-radius: 10px;">
                    <h3 style="margin-top: 0;">Tasa de Aprobación</h3>
                    <div style="font-size: 36px; font-weight: bold;"><?php echo $stats['tasa_aprobacion']; ?>%</div>
                    <p style="margin-bottom: 0; opacity: 0.9;">
                        de garantías aprobadas
                    </p>
                </div>
                
            </div>
            
            <!-- Gráficos -->
            <div class="charts-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                
                <!-- Gráfico de línea - Tendencia -->
                <div class="chart-container" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Tendencia de Garantías (Últimos 30 días)</h3>
                    <canvas id="trendChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Gráfico de dona - Estados -->
                <div class="chart-container" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Distribución por Estado</h3>
                    <canvas id="statusChart" width="400" height="200"></canvas>
                </div>
                
            </div>
            
            <!-- Top productos con más reclamos -->
            <div class="top-products" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
                <h3>Top 10 Productos con Más Reclamos</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>Reclamos</th>
                            <th>Tasa</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['top_productos'] as $producto): ?>
                        <tr>
                            <td><?php echo esc_html($producto['nombre']); ?></td>
                            <td><?php echo esc_html($producto['sku']); ?></td>
                            <td><?php echo number_format($producto['reclamos']); ?></td>
                            <td>
                                <span style="color: <?php echo $producto['tasa'] > 5 ? '#dc3545' : '#28a745'; ?>;">
                                    <?php echo $producto['tasa']; ?>%
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($producto['id']); ?>" class="button button-small">
                                    Ver Producto
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Clientes con más reclamos -->
            <div class="top-customers" style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3>Clientes con Más Reclamos</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Total Compras</th>
                            <th>Total Reclamos</th>
                            <th>Tasa</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['top_clientes'] as $cliente): ?>
                        <tr style="<?php echo $cliente['tasa'] > 10 ? 'background: #fff3cd;' : ''; ?>">
                            <td><?php echo esc_html($cliente['nombre']); ?></td>
                            <td><?php echo esc_html($cliente['email']); ?></td>
                            <td>$<?php echo number_format($cliente['total_compras'], 2); ?></td>
                            <td><?php echo number_format($cliente['reclamos']); ?></td>
                            <td>
                                <span style="color: <?php echo $cliente['tasa'] > 10 ? '#dc3545' : '#28a745'; ?>; font-weight: bold;">
                                    <?php echo $cliente['tasa']; ?>%
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $cliente['id']); ?>" class="button button-small">
                                    Ver Perfil
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Botones de exportación -->
            <div class="export-section" style="margin-top: 30px;">
                <button class="button button-primary" onclick="exportarReporte('pdf')">
                    📄 Exportar PDF
                </button>
                <button class="button" onclick="exportarReporte('excel')">
                    📊 Exportar Excel
                </button>
            </div>
            
        </div>
        
        <!-- Scripts para gráficos -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        // Datos para los gráficos
        const trendData = <?php echo json_encode($stats['trend_data']); ?>;
        const statusData = <?php echo json_encode($stats['status_data']); ?>;
        
        // Gráfico de tendencia
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [{
                    label: 'Garantías',
                    data: trendData.values,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gráfico de estados
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.values,
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#fa709a',
                        '#a8e6cf'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        
        // Función de exportación
        function exportarReporte(formato) {
            window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=wcgarantias_export_report&formato=' + formato + '&nonce=<?php echo wp_create_nonce('export_report'); ?>';
        }
        </script>
        <?php
    }
    
    /**
     * Widget del dashboard principal
     */
    public static function render_dashboard_widget() {
        $nuevas = self::get_count_by_status('nueva');
        $pendientes = self::get_count_by_status(['nueva', 'en_revision', 'pendiente_envio', 'recibido']);
        
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
        
        echo '<div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px;">';
        echo '<div style="font-size: 32px; font-weight: bold; color: #dc3545;">' . $nuevas . '</div>';
        echo '<div style="color: #666;">Nuevas</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 5px;">';
        echo '<div style="font-size: 32px; font-weight: bold; color: #ffc107;">' . $pendientes . '</div>';
        echo '<div style="color: #666;">Pendientes</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<p style="text-align: center; margin-top: 15px;">';
        echo '<a href="' . admin_url('admin.php?page=wc-garantias') . '" class="button button-primary">Ver Todas</a>';
        echo '</p>';
    }
    
    /**
     * Obtener estadísticas del admin
     */
    private static function get_admin_stats() {
        $fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        
        // Total de garantías
        $total = wp_count_posts('garantia')->publish;
        
        // Estados
        $pendientes = self::get_count_by_status(['nueva', 'en_revision', 'pendiente_envio', 'recibido']);
        $aprobadas = self::get_count_by_status(['aprobado_cupon', 'finalizado_cupon', 'finalizado']);
        $rechazadas = self::get_count_by_status('rechazado');
        
        // Calcular porcentajes y métricas
        $porcentaje_pendientes = $total > 0 ? round(($pendientes / $total) * 100, 1) : 0;
        $tasa_aprobacion = ($aprobadas + $rechazadas) > 0 ? round(($aprobadas / ($aprobadas + $rechazadas)) * 100, 1) : 0;
        
        // Tiempo promedio de resolución
        $tiempo_promedio = self::get_average_resolution_time();
        
        // Variación vs mes anterior
        $variacion_total = self::get_month_variation();
        
        // Top productos con reclamos
        $top_productos = self::get_top_products_with_claims(10);
        
        // Top clientes con reclamos
        $top_clientes = self::get_top_customers_with_claims(10);
        
        // Datos para gráficos
        $trend_data = self::get_trend_data(30);
        $status_data = self::get_status_distribution();
        
        return [
            'total' => $total,
            'pendientes' => $pendientes,
            'aprobadas' => $aprobadas,
            'rechazadas' => $rechazadas,
            'porcentaje_pendientes' => $porcentaje_pendientes,
            'tasa_aprobacion' => $tasa_aprobacion,
            'tiempo_promedio' => $tiempo_promedio,
            'variacion_total' => $variacion_total,
            'top_productos' => $top_productos,
            'top_clientes' => $top_clientes,
            'trend_data' => $trend_data,
            'status_data' => $status_data,
        ];
    }
    
    /**
     * Contar garantías por estado
     */
    private static function get_count_by_status($status) {
        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_estado',
                    'value' => $status,
                    'compare' => is_array($status) ? 'IN' : '='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Obtener tiempo promedio de resolución
     */
    private static function get_average_resolution_time() {
        global $wpdb;
        
        $sql = "
            SELECT AVG(DATEDIFF(pm2.meta_value, pm1.meta_value)) as avg_days
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_fecha'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_fecha_resolucion'
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_estado'
            WHERE p.post_type = 'garantia'
            AND p.post_status = 'publish'
            AND pm3.meta_value IN ('aprobado_cupon', 'rechazado', 'finalizado', 'finalizado_cupon')
        ";
        
        $result = $wpdb->get_var($sql);
        return $result ? round($result, 1) : 0;
    }
    
    /**
     * Obtener variación vs mes anterior
     */
    private static function get_month_variation() {
        $current_month = date('Y-m');
        $last_month = date('Y-m', strtotime('-1 month'));
        
        $current_count = self::get_count_by_date_range($current_month . '-01', $current_month . '-31');
        $last_count = self::get_count_by_date_range($last_month . '-01', $last_month . '-31');
        
        if ($last_count == 0) return 0;
        
        return round((($current_count - $last_count) / $last_count) * 100, 1);
    }
    
    /**
     * Contar garantías por rango de fecha
     */
    private static function get_count_by_date_range($start, $end) {
        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => [
                [
                    'after' => $start,
                    'before' => $end,
                    'inclusive' => true,
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Obtener top productos con reclamos
     */
    private static function get_top_products_with_claims($limit = 10) {
        global $wpdb;
        
        $sql = "
            SELECT 
                pm.meta_value as product_id,
                COUNT(*) as claim_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'garantia'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_producto'
            GROUP BY pm.meta_value
            ORDER BY claim_count DESC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $limit));
        $productos = [];
        
        foreach ($results as $result) {
            $product = wc_get_product($result->product_id);
            if (!$product) continue;
            
            // Calcular tasa de reclamo
            $total_vendido = self::get_product_sales_count($result->product_id);
            $tasa = $total_vendido > 0 ? round(($result->claim_count / $total_vendido) * 100, 2) : 0;
            
            $productos[] = [
                'id' => $result->product_id,
                'nombre' => $product->get_name(),
                'sku' => $product->get_sku(),
                'reclamos' => $result->claim_count,
                'vendidos' => $total_vendido,
                'tasa' => $tasa,
            ];
        }
        
        return $productos;
    }
    
    /**
     * Obtener cantidad vendida de un producto
     */
    private static function get_product_sales_count($product_id) {
        global $wpdb;
        
        $sql = "
            SELECT SUM(oim.meta_value) as total
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id
            INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            WHERE oim.meta_key = '_qty'
            AND oim2.meta_key = '_product_id'
            AND oim2.meta_value = %d
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ";
        
        return (int) $wpdb->get_var($wpdb->prepare($sql, $product_id));
    }
    
    /**
     * Obtener top clientes con reclamos
     */
    private static function get_top_customers_with_claims($limit = 10) {
        global $wpdb;
        
        $sql = "
            SELECT 
                pm.meta_value as customer_id,
                COUNT(*) as claim_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'garantia'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_cliente'
            GROUP BY pm.meta_value
            ORDER BY claim_count DESC
            LIMIT %d
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $limit));
        $clientes = [];
        
        foreach ($results as $result) {
            $user = get_userdata($result->customer_id);
            if (!$user) continue;
            
            // Obtener total de compras
            $customer = new WC_Customer($result->customer_id);
            $total_compras = $customer->get_total_spent();
            $total_pedidos = $customer->get_order_count();
            
            // Calcular tasa
            $tasa = $total_pedidos > 0 ? round(($result->claim_count / $total_pedidos) * 100, 2) : 0;
            
            $clientes[] = [
                'id' => $result->customer_id,
                'nombre' => $user->display_name,
                'email' => $user->user_email,
                'reclamos' => $result->claim_count,
                'total_compras' => $total_compras,
                'total_pedidos' => $total_pedidos,
                'tasa' => $tasa,
            ];
        }
        
        return $clientes;
    }
    
    /**
     * Obtener datos de tendencia
     */
    private static function get_trend_data($days = 30) {
        $data = [
            'labels' => [],
            'values' => [],
        ];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $count = self::get_count_by_date_range($date, $date);
            
            $data['labels'][] = date('d/m', strtotime($date));
            $data['values'][] = $count;
        }
        
        return $data;
    }
    
    /**
     * Obtener distribución por estado
     */
    private static function get_status_distribution() {
        $estados = [
            'nueva' => 'Nuevas',
            'en_revision' => 'En Revisión',
            'recibido' => 'Recibidas',
            'aprobado_cupon' => 'Aprobadas',
            'rechazado' => 'Rechazadas',
        ];
        
        $data = [
            'labels' => [],
            'values' => [],
        ];
        
        foreach ($estados as $key => $label) {
            $count = self::get_count_by_status($key);
            if ($count > 0) {
                $data['labels'][] = $label;
                $data['values'][] = $count;
            }
        }
        
        return $data;
    }
    
    /**
     * Exportar reporte
     */
    public static function ajax_export_report() {
        check_ajax_referer('export_report', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Sin permisos');
        }
        
        $formato = $_GET['formato'] ?? 'excel';
        $stats = self::get_admin_stats();
        
        if ($formato === 'excel') {
            self::export_excel($stats);
        } else {
            self::export_pdf($stats);
        }
        
        wp_die();
    }
    
    /**
     * Exportar a Excel
     */
    private static function export_excel($stats) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="garantias_report_' . date('Y-m-d') . '.xls"');
        
        echo '<table border="1">';
        echo '<tr><th colspan="2">Reporte de Garantías - ' . date('d/m/Y') . '</th></tr>';
        echo '<tr><td>Total Garantías</td><td>' . $stats['total'] . '</td></tr>';
        echo '<tr><td>Pendientes</td><td>' . $stats['pendientes'] . '</td></tr>';
        echo '<tr><td>Aprobadas</td><td>' . $stats['aprobadas'] . '</td></tr>';
        echo '<tr><td>Rechazadas</td><td>' . $stats['rechazadas'] . '</td></tr>';
        echo '<tr><td>Tasa de Aprobación</td><td>' . $stats['tasa_aprobacion'] . '%</td></tr>';
        echo '<tr><td>Tiempo Promedio Resolución</td><td>' . $stats['tiempo_promedio'] . ' días</td></tr>';
        echo '</table>';
    }
}

// Inicializar
WC_Garantias_Dashboard::init();