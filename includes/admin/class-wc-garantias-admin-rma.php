<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo de Panel RMA (Devoluciones) - Versión Mejorada
 */
class WC_Garantias_Admin_RMA {
    
    /**
     * Obtener información completa del RMA
     */
    private static function get_rma_info($cupon_post) {
        $coupon = new WC_Coupon($cupon_post->ID);
        $codigo_cupon = $coupon->get_code();
        
        $info = [
            'cupon_id' => $cupon_post->ID,
            'codigo_cupon' => $codigo_cupon,
            'producto_id' => null,
            'sku' => 'N/A',
            'nombre_producto' => 'Producto no encontrado',
            'garantia_id' => null,
            'metodo_recuperacion' => 'ninguno'
        ];
        
        // Método 1: Buscar por meta _producto_rma_id
        $producto_id = get_post_meta($cupon_post->ID, '_producto_rma_id', true);
        if ($producto_id) {
            $producto = wc_get_product($producto_id);
            if ($producto) {
                $info['producto_id'] = $producto_id;
                $info['nombre_producto'] = $producto->get_name();
                $info['sku'] = get_post_meta($producto_id, '_alg_ean', true) ?: 'N/A';
                $info['metodo_recuperacion'] = 'meta_producto_rma';
            }
        }
        
        // Método 2: Buscar en garantías
        $garantia_info = self::buscar_en_garantias($codigo_cupon);
        error_log('Garantía info para ' . $codigo_cupon . ': ' . print_r($garantia_info, true));
        if ($garantia_info) {
            $info['garantia_id'] = $garantia_info['garantia_id'];
            
            // Si no tenemos SKU, usar el de la garantía
            if ($info['sku'] === 'N/A' && !empty($garantia_info['sku'])) {
                $info['sku'] = $garantia_info['sku'];
                $info['metodo_recuperacion'] = 'garantia';
            }
            
            // Si no tenemos producto, intentar encontrarlo por SKU
            if (!$info['producto_id'] && !empty($garantia_info['sku'])) {
                $producto_por_sku = self::buscar_producto_por_sku($garantia_info['sku']);
                if ($producto_por_sku) {
                    $info['producto_id'] = $producto_por_sku->get_id();
                    $info['nombre_producto'] = $producto_por_sku->get_name();
                    $info['metodo_recuperacion'] = 'sku_garantia';
                }
            }
        }
        
        // Método 3: Buscar por patrón en código de cupón
        if ($info['producto_id'] === null) {
            $producto_por_patron = self::buscar_producto_por_patron_cupon($codigo_cupon);
            if ($producto_por_patron) {
                $info['producto_id'] = $producto_por_patron->get_id();
                $info['nombre_producto'] = $producto_por_patron->get_name();
                $info['sku'] = get_post_meta($info['producto_id'], '_alg_ean', true) ?: 'N/A';
                $info['metodo_recuperacion'] = 'patron_cupon';
            }
        }
        
        // Método 4: Buscar en historial de rdenes del cliente
        if ($info['producto_id'] === null) {
            $customer_emails = $coupon->get_email_restrictions();
            if (!empty($customer_emails)) {
                $producto_historial = self::buscar_en_historial_cliente($customer_emails[0], $codigo_cupon);
                if ($producto_historial) {
                    $info = array_merge($info, $producto_historial);
                    $info['metodo_recuperacion'] = 'historial_cliente';
                }
            }
        }
        
        // Log para debug
        if ($info['metodo_recuperacion'] === 'ninguno') {
            error_log('RMA Debug - No se pudo recuperar info para cupón: ' . $codigo_cupon);
        } else {
            error_log('RMA Debug - Cupón ' . $codigo_cupon . ' recuperado por método: ' . $info['metodo_recuperacion']);
        }
        
        return $info;
    }
    
    /**
     * Buscar información en garantías
     */
    private static function buscar_en_garantias($codigo_cupon) {
        global $wpdb;
        
        // IMPORTANTE: No usar prepare aquí porque necesitamos LIKE con %
        $garantias_ids = $wpdb->get_col("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_items_reclamados' 
            AND meta_value LIKE '%{$codigo_cupon}%'
            LIMIT 1
        ");
        
        if (empty($garantias_ids)) {
            return null;
        }
        
        $garantia_id = $garantias_ids[0];
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            return null;
        }
        
        foreach ($items as $item) {
            if (isset($item['cupon_rma']) && $item['cupon_rma'] === $codigo_cupon) {
                return [
                    'garantia_id' => $garantia_id,
                    'sku' => $item['codigo_item'] ?? null,
                    'producto_id' => $item['producto_id'] ?? null
                ];
            }
        }
        
        return ['garantia_id' => $garantia_id]; // Devolver al menos el ID aunque no encuentre el item
    }
    
    /**
     * Buscar producto por SKU
     */
    private static function buscar_producto_por_sku($sku) {
        if (empty($sku) || $sku === 'N/A') {
            return null;
        }
        
        global $wpdb;
        
        // Buscar por meta _alg_ean
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_alg_ean' 
            AND meta_value = %s 
            LIMIT 1
        ", $sku));
        
        if ($product_id) {
            return wc_get_product($product_id);
        }
        
        // Buscar por SKU estándar de WooCommerce
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sku' 
            AND meta_value = %s 
            LIMIT 1
        ", $sku));
        
        return $product_id ? wc_get_product($product_id) : null;
    }
    
    /**
     * Buscar producto por patrón en código de cupón
     */
    private static function buscar_producto_por_patron_cupon($codigo_cupon) {
        // Extraer posibles SKUs del código del cupón
        // Por ejemplo: "rma-devolucion-grt-item-tbykfpei-x1" podría contener "tbykfpei"
        preg_match_all('/[a-zA-Z0-9]{6,}/', $codigo_cupon, $matches);
        
        if (empty($matches[0])) {
            return null;
        }
        
        foreach ($matches[0] as $posible_sku) {
            // Saltar palabras comunes
            if (in_array(strtolower($posible_sku), ['devolucion', 'item'])) {
                continue;
            }
            
            $producto = self::buscar_producto_por_sku($posible_sku);
            if ($producto) {
                return $producto;
            }
        }
        
        return null;
    }
    
    /**
     * Buscar en historial de órdenes del cliente
     */
    private static function buscar_en_historial_cliente($email, $codigo_cupon) {
        $user = get_user_by('email', $email);
        if (!$user) {
            return null;
        }
        
        // Obtener órdenes recientes del cliente
        $orders = wc_get_orders([
            'customer' => $user->ID,
            'limit' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['completed', 'processing', 'on-hold']
        ]);
        
        // Buscar productos que coincidan con patrones del cupón
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $producto = $item->get_product();
                if (!$producto) continue;
                
                // Verificar si el SKU está en el código del cupón
                $sku = get_post_meta($producto->get_id(), '_alg_ean', true) ?: $producto->get_sku();
                if ($sku && stripos($codigo_cupon, $sku) !== false) {
                    return [
                        'producto_id' => $producto->get_id(),
                        'nombre_producto' => $producto->get_name(),
                        'sku' => $sku
                    ];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Renderizar página principal
     */
    public static function render_page() {
        // Buscar cupones RMA activos
        $args = array(
            'post_type' => 'shop_coupon',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_es_cupon_rma',
                    'value' => 'yes'
                )
            )
        );
        
        $cupones_rma = get_posts($args);
        ?>
        <div class="wrap" style="max-width: 1400px; margin: 0 auto;">
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
                
                .rma-dashboard {
                    font-family: 'Inter', sans-serif;
                    background: #f0f2f5;
                    margin: -20px -20px 0 -20px;
                    padding: 0;
                    min-height: 100vh;
                }
                
                .rma-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 40px;
                    color: white;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                }
                
                .rma-header h1 {
                    margin: 0;
                    font-size: 36px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                
                .rma-header p {
                    margin: 10px 0 0 0;
                    font-size: 18px;
                    opacity: 0.9;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 25px;
                    padding: 25px 40px;
                    margin-top: -50px;
                }
                
                .stat-card {
                    background: white;
                    border-radius: 16px;
                    padding: 30px;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }
                
                .stat-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                }
                
                .stat-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                }
                
                .stat-icon {
                    width: 60px;
                    height: 60px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    margin-bottom: 15px;
                }
                
                .stat-value {
                    font-size: 36px;
                    font-weight: 700;
                    margin: 10px 0;
                    color: #1a1a1a;
                }
                
                .stat-label {
                    font-size: 14px;
                    color: #6b7280;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .rma-table-container {
                    background: white;
                    border-radius: 16px;
                    margin: 0 40px 40px;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                    overflow: hidden;
                }
                
                .rma-table-header {
                    background: #f8f9fa;
                    padding: 20px 30px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .rma-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .rma-table th {
                    background: #f8f9fa;
                    padding: 15px 20px;
                    text-align: left;
                    font-weight: 600;
                    color: #374151;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    border-bottom: 2px solid #e5e7eb;
                }
                
                .rma-table td {
                    padding: 20px;
                    border-bottom: 1px solid #f3f4f6;
                    font-size: 14px;
                }
                
                .rma-table tr:hover {
                    background: #f9fafb;
                }
                
                .coupon-code {
                    font-family: 'Monaco', monospace;
                    background: #e0e7ff;
                    color: #4c1d95;
                    padding: 6px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                }
                
                .sku-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    background: #fef3c7;
                    color: #92400e;
                    padding: 6px 10px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 13px;
                    font-weight: 500;
                }
                
                .status-active {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .status-used {
                    background: #ddd6fe;
                    color: #5b21b6;
                }
                
                .status-expired {
                    background: #fee2e2;
                    color: #991b1b;
                }
                
                .btn-group {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                
                .btn-group .btn {
                    min-width: 70px;
                    width: auto;
                }
                
                .btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    padding: 8px 12px;
                    border-radius: 8px;
                    font-size: 13px;
                    font-weight: 500;
                    text-decoration: none;
                    transition: all 0.2s ease;
                    border: none;
                    cursor: pointer;
                    min-width: 60px;
                    text-align: center;
                }
                
                .btn-group {
                    display: flex;
                    gap: 8px;
                    flex-wrap: wrap;
                    align-items: stretch;
                }
                
                .btn-group .btn {
                    flex: 0 0 auto;
                    min-width: 60px;
                    width: 60px;
                    max-width: 60px;
                }
                .btn-primary {
                    background: #667eea;
                    color: white;
                }
                
                .btn-primary:hover {
                    background: #5a67d8;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                
                .btn-success {
                    background: #10b981;
                    color: white;
                }
                
                .btn-success:hover {
                    background: #059669;
                }
                
                .btn-warning {
                    background: #f59e0b;
                    color: white;
                }
                
                .btn-warning:hover {
                    background: #d97706;
                }
                
                .btn-info {
                    background: #3b82f6;
                    color: white;
                }
                
                .btn-info:hover {
                    background: #2563eb;
                }
                
                .btn-outline {
                    background: white;
                    color: #6b7280;
                    border: 1px solid #e5e7eb;
                }
                
                .btn-outline:hover {
                    background: #f9fafb;
                    border-color: #d1d5db;
                }
                
                .product-info {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                
                .product-name {
                    font-weight: 500;
                    color: #1f2937;
                }
                
                .product-deleted {
                    font-size: 11px;
                    color: #ef4444;
                    font-weight: 500;
                }
                
                .empty-state {
                    text-align: center;
                    padding: 80px 20px;
                }
                
                .empty-icon {
                    font-size: 64px;
                    color: #e5e7eb;
                    margin-bottom: 20px;
                }
                
                .empty-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #374151;
                    margin-bottom: 10px;
                }
                
                .empty-text {
                    font-size: 16px;
                    color: #6b7280;
                }
                
                .loading-shimmer {
                    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
                    background-size: 200% 100%;
                    animation: shimmer 1.5s infinite;
                }
                
                @keyframes shimmer {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }
                
                .tooltip {
                    position: relative;
                    cursor: help;
                }
                
                .tooltip:hover::after {
                    content: attr(data-tooltip);
                    position: absolute;
                    bottom: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1f2937;
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 12px;
                    white-space: nowrap;
                    z-index: 1000;
                    margin-bottom: 5px;
                }
                
                .rma-filters {
                    display: flex;
                    gap: 15px;
                    align-items: center;
                }
                
                .filter-select {
                    padding: 8px 16px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    background: white;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .filter-select:hover {
                    border-color: #d1d5db;
                }
                
                .search-box {
                    position: relative;
                    flex: 1;
                    max-width: 300px;
                }
                
                .search-input {
                    width: 100%;
                    padding: 8px 16px 8px 40px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }
                
                .search-input:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .search-icon {
                    position: absolute;
                    left: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #9ca3af;
                }
            </style>
            
            <div class="rma-dashboard">
                <div class="rma-header">
                    <h1><i class="fas fa-exchange-alt"></i> Centro de Devoluciones RMA</h1>
                    <p>Gestión inteligente de cupones y devoluciones</p>
                </div>
                
                <?php 
                // Mostrar resumen de recuperación
                self::mostrar_resumen_recuperacion($cupones_rma);
                
                // Calcular estadísticas
                $total_cupones = count($cupones_rma);
                $cupones_activos = 0;
                $cupones_usados = 0;
                $cupones_expirados = 0;
                $valor_total = 0;
                
                foreach ($cupones_rma as $cupon_post) {
                    $usage_count = get_post_meta($cupon_post->ID, 'usage_count', true);
                    if ($usage_count > 0) {
                        $cupones_usados++;
                    } else {
                        $expiry_date = get_post_meta($cupon_post->ID, 'date_expires', true);
                        if ($expiry_date && $expiry_date < time()) {
                            $cupones_expirados++;
                        } else {
                            $cupones_activos++;
                        }
                    }
                    
                    $coupon = new WC_Coupon($cupon_post->ID);
                    $valor_total += $coupon->get_amount();
                }
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #e0e7ff; color: #4c1d95;">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_cupones; ?></div>
                        <div class="stat-label">Total Cupones RMA</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?php echo $cupones_activos; ?></div>
                        <div class="stat-label">Cupones Activos</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #ddd6fe; color: #5b21b6;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-value"><?php echo $cupones_usados; ?></div>
                        <div class="stat-label">Cupones Canjeados</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #991b1b;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?php echo $cupones_expirados; ?></div>
                        <div class="stat-label">Cupones Expirados</div>
                    </div>
                </div>
                
                <div class="rma-table-container">
                    <div class="rma-table-header">
                        <h2 style="margin: 0; font-size: 20px; font-weight: 600;">
                            <i class="fas fa-list"></i> Listado de Devoluciones
                        </h2>
                        
                        <div class="rma-filters">
                            <div class="search-box">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" 
                                       class="search-input" 
                                       id="rma-search" 
                                       placeholder="Buscar cupón, SKU o cliente...">
                            </div>
                            
                            <select class="filter-select" id="rma-status-filter">
                                <option value="">Todos los estados</option>
                                <option value="active">Activos</option>
                                <option value="used">Usados</option>
                                <option value="expired">Expirados</option>
                            </select>
                            
                            <button class="btn btn-primary" onclick="window.location.reload()">
                                <i class="fas fa-sync"></i> Actualizar
                            </button>
                        </div>
                    </div>
                    
                    <?php if (empty($cupones_rma)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                            <div class="empty-title">No hay devoluciones RMA pendientes</div>
                            <div class="empty-text">Los cupones RMA aparecerán aquí cuando se generen</div>
                        </div>
                    <?php else: ?>
                        <table class="rma-table">
                            <thead>
                                <tr>
                                    <th>Cupón</th>
                                    <th>SKU / Código</th>
                                    <th>Producto</th>
                                    <th>Cliente</th>
                                    <th>Creación</th>
                                    <th>Estado</th>
                                    <th>Vencimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cupones_rma as $cupon_post): 
                                    try {
                                        $coupon = new WC_Coupon($cupon_post->ID);
                                        
                                        if (!$coupon->get_id()) {
                                            continue;
                                        }
                                        
                                        // Obtener información del RMA
                                        $rma_info = self::get_rma_info($cupon_post);
                                        
                                        // Obtener cliente
                                        $customer_emails = $coupon->get_email_restrictions();
                                        $cliente_email = !empty($customer_emails) ? $customer_emails[0] : '';
                                        $user = get_user_by('email', $cliente_email);
                                        $cliente_nombre = $user ? $user->display_name : 'Desconocido';
                                        
                                        // Estado del cupón
                                        $estado_info = self::obtener_estado_cupon($cupon_post, $coupon);
                                        
                                        // Fecha de vencimiento
                                        $fecha_vence_str = 'Sin vencimiento';
                                        $expiry_date = get_post_meta($cupon_post->ID, 'date_expires', true);
                                        if ($expiry_date) {
                                            $expiry_timestamp = is_numeric($expiry_date) ? $expiry_date : strtotime($expiry_date);
                                            if ($expiry_timestamp) {
                                                $fecha_vence_str = date_i18n('d/m/Y', $expiry_timestamp);
                                            }
                                        }
                                        ?>
                                        <tr data-cupon="<?php echo esc_attr(strtolower($rma_info['codigo_cupon'])); ?>" 
                                            data-sku="<?php echo esc_attr(strtolower($rma_info['sku'])); ?>"
                                            data-cliente="<?php echo esc_attr(strtolower($cliente_nombre)); ?>"
                                            data-estado="<?php echo esc_attr($estado_info['codigo']); ?>">
                                            <td>
                                                <div>
                                                    <span class="coupon-code">
                                                        <i class="fas fa-ticket-alt"></i>
                                                        <?php echo esc_html($rma_info['codigo_cupon']); ?>
                                                    </span>
                                                    <?php if ($rma_info['metodo_recuperacion'] !== 'ninguno'): ?>
                                                        <div style="margin-top: 5px;">
                                                            <span class="tooltip" 
                                                                  data-tooltip="Recuperado por: <?php echo esc_attr($rma_info['metodo_recuperacion']); ?>"
                                                              style="font-size: 11px; color: #6b7280;">
                                                            <i class="fas fa-info-circle"></i>
                                                            <?php echo esc_html($rma_info['metodo_recuperacion']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="sku-badge">
                                                <i class="fas fa-barcode"></i>
                                                <?php echo esc_html($rma_info['sku']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="product-info">
                                                <span class="product-name"><?php echo esc_html($rma_info['nombre_producto']); ?></span>
                                                <?php if ($rma_info['nombre_producto'] === 'Producto no encontrado'): ?>
                                                    <span class="product-deleted">
                                                        <i class="fas fa-exclamation-triangle"></i> Sin datos
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <i class="fas fa-user" style="color: #9ca3af;"></i>
                                                <?php echo esc_html($cliente_nombre); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: #6b7280; font-size: 13px;">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo get_the_date('d/m/Y', $cupon_post->ID); ?>
                                            </div>
                                        </td>
                                        <td><?php echo $estado_info['html']; ?></td>
                                        <td>
                                            <div style="color: #6b7280; font-size: 13px;">
                                                <?php echo esc_html($fecha_vence_str); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <div style="display: flex; gap: 8px;">
                                                    <a href="<?php echo admin_url('post.php?post=' . $cupon_post->ID . '&action=edit'); ?>" 
                                                       class="btn btn-outline"
                                                       title="Ver detalles del cupón">
                                                        <i class="fas fa-eye"></i>
                                                        <span>Cupón</span>
                                                    </a>
                                                    
                                                    <?php if ($rma_info['producto_id']): ?>
                                                        <a href="<?php echo admin_url('post.php?post=' . $rma_info['producto_id'] . '&action=edit'); ?>" 
                                                           class="btn btn-info"
                                                           title="Ver producto asociado">
                                                            <i class="fas fa-box"></i>
                                                            <span>Producto</span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($rma_info['garantia_id']): ?>
                                                    <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $rma_info['garantia_id']); ?>" 
                                                       class="btn btn-success"
                                                       style="width: 100%;"
                                                       title="Ver garantía completa">
                                                        <i class="fas fa-shield-alt"></i>
                                                        <span>Garantía</span>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($rma_info['metodo_recuperacion'] === 'ninguno'): ?>
                                                    <button class="btn btn-warning" 
                                                            style="width: 100%;"
                                                            onclick="repararRMA('<?php echo esc_js($rma_info['codigo_cupon']); ?>')"
                                                            title="Diagnosticar y reparar">
                                                        <i class="fas fa-wrench"></i>
                                                        <span>Reparar</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                } catch (Exception $e) {
                                    error_log('Error en cupón RMA ' . $cupon_post->ID . ': ' . $e->getMessage());
                                    continue;
                                }
                            endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        // Función de búsqueda
        document.getElementById('rma-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.rma-table tbody tr');
            
            rows.forEach(row => {
                const cupon = row.dataset.cupon || '';
                const sku = row.dataset.sku || '';
                const cliente = row.dataset.cliente || '';
                
                if (cupon.includes(searchTerm) || 
                    sku.includes(searchTerm) || 
                    cliente.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Filtro por estado
        document.getElementById('rma-status-filter').addEventListener('change', function(e) {
            const filterValue = e.target.value;
            const rows = document.querySelectorAll('.rma-table tbody tr');
            
            rows.forEach(row => {
                if (!filterValue || row.dataset.estado === filterValue) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Funciones existentes
        function repararRMA(codigoCupon) {
            // Necesitamos encontrar el ID del cupón primero
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'buscar_id_cupon_rma',
                    codigo_cupon: codigoCupon
                },
                success: function(response) {
                    if (response.success) {
                        // Ahora sí llamar a diagnosticar con el ID
                        diagnosticarCupon(codigoCupon, response.data.cupon_id);
                    } else {
                        alert('No se pudo encontrar el cupón: ' + codigoCupon);
                    }
                }
            });
        }
        
        function diagnosticarCupon(codigoCupon, cuponId) {
            if (confirm('¿Diagnosticar cupón ' + codigoCupon + '?')) {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'diagnosticar_cupon_rma',
                        cupon_id: cuponId,
                        codigo_cupon: codigoCupon
                    },
                    success: function(response) {
                        alert('Diagnóstico:\n\n' + response);
                        
                        // Buscar si encontró un producto RMA válido
                        if (response.includes('Es RMA: SÍ')) {
                            if (confirm('\n¿Deseas REPARAR este cupón con el producto RMA encontrado?')) {
                                repararCuponDefinitivo(cuponId, codigoCupon);
                            }
                        }
                    }
                });
            }
        }
        
        function repararCuponDefinitivo(cuponId, codigoCupon) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'reparar_cupon_rma',
                    cupon_id: cuponId,
                    codigo_cupon: codigoCupon
                },
                success: function(response) {
                    if (response.success) {
                        alert('✓ ' + response.data.message + '\n\nProducto: ' + response.data.producto_nombre);
                        location.reload();
                    } else {
                        alert('✗ Error: ' + response.data.message);
                    }
                }
            });
        }
        function buscarGarantia(codigoCupon) {
            alert('Buscando garantía para: ' + codigoCupon + '\n\nEsta función está en desarrollo.');
            // Aquí podrías agregar una búsqueda AJAX
        }
        </script>
        <?php
    }
    /**
     * Obtener estado del cupn
     */
    private static function obtener_estado_cupon($cupon_post, $coupon) {
        $estado = [
            'codigo' => 'activo',
            'html' => '<span style="color: orange;">✓ Activo</span>'
        ];
        
        $orden_info = '';
        
        try {
            // Verificar uso del cupón
            $usage_count = get_post_meta($cupon_post->ID, 'usage_count', true);
            if ($usage_count > 0) {
                $estado['codigo'] = 'usado';
                $estado['html'] = '<span style="color: green;">✓ Usado</span>';
                
                // Obtener información de la orden donde se canjeó
                $orden_id = get_post_meta($cupon_post->ID, '_orden_canjeado', true);
                
                // Buscar orden manualmente si no está guardada
                if (!$orden_id) {
                    global $wpdb;
                    
                    $orden_id = $wpdb->get_var($wpdb->prepare("
                        SELECT DISTINCT order_id 
                        FROM {$wpdb->prefix}woocommerce_order_items 
                        WHERE order_item_type = 'coupon' 
                        AND order_item_name = %s
                        ORDER BY order_id DESC
                        LIMIT 1
                    ", $coupon->get_code()));
                    
                    if ($orden_id) {
                        update_post_meta($cupon_post->ID, '_orden_canjeado', $orden_id);
                    }
                }
                
                if ($orden_id) {
                    $orden_info = '<br><small>Orden: <a href="' . admin_url('post.php?post=' . $orden_id . '&action=edit') . '">#' . $orden_id . '</a></small>';
                }
            } else {
                // Verificar expiración
                $expiry_date = get_post_meta($cupon_post->ID, 'date_expires', true);
                if ($expiry_date) {
                    $expiry_timestamp = is_numeric($expiry_date) ? $expiry_date : strtotime($expiry_date);
                    if ($expiry_timestamp && $expiry_timestamp < time()) {
                        $estado['codigo'] = 'expirado';
                        $estado['html'] = '<span style="color: red;"> Expirado</span>';
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error verificando estado del cupón: ' . $e->getMessage());
        }
        
        $estado['html'] .= $orden_info;
        return $estado;
    }
    
    /**
 * Mostrar resumen de métodos de recuperación
 */
    private static function mostrar_resumen_recuperacion($cupones_rma) {
        if (empty($cupones_rma)) {
            return;
        }
        
        $metodos = [
            'meta_producto_rma' => 0,
            'garantia' => 0,
            'sku_garantia' => 0,
            'patron_cupon' => 0,
            'historial_cliente' => 0,
            'ninguno' => 0
        ];
        
        foreach ($cupones_rma as $cupon_post) {
            try {
                $info = self::get_rma_info($cupon_post);
                $metodos[$info['metodo_recuperacion']]++;
            } catch (Exception $e) {
                $metodos['ninguno']++;
            }
        }
        
        ?>
        <div class="notice notice-info" style="padding: 10px;">
            <h3>Resumen de Recuperación de Datos</h3>
            <p>
                <?php foreach ($metodos as $metodo => $count): ?>
                    <?php if ($count > 0): ?>
                        <span style="margin-right: 20px;">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $metodo)); ?>:</strong> 
                            <?php echo $count; ?> cupones
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </p>
            <?php if ($metodos['ninguno'] > 0): ?>
                <p style="color: #d63638;">
                    ⚠️ Hay <?php echo $metodos['ninguno']; ?> cupones sin datos recuperables. 
                    Considere ejecutar el proceso de reparación.
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}