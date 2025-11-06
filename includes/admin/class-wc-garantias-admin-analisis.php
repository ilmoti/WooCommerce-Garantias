<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo de Análisis de Garantías
 */
class WC_Garantias_Admin_Analisis {
    
    public static function render_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'clientes';
        $periodo = isset($_GET['periodo']) ? intval($_GET['periodo']) : 180;
        ?>
        <div class="wrap">
            <h1>📊 Análisis de Garantías</h1>
            
            <!-- Tabs de navegación -->
            <nav class="nav-tab-wrapper">
                <a href="?page=wc-garantias-analisis&tab=clientes" 
                   class="nav-tab <?php echo $active_tab == 'clientes' ? 'nav-tab-active' : ''; ?>">
                    👥 Top Clientes
                </a>
                <a href="?page=wc-garantias-analisis&tab=productos" 
                   class="nav-tab <?php echo $active_tab == 'productos' ? 'nav-tab-active' : ''; ?>">
                    📦 Top Productos
                </a>
                <a href="?page=wc-garantias-analisis&tab=buscador" 
                   class="nav-tab <?php echo $active_tab == 'buscador' ? 'nav-tab-active' : ''; ?>">
                    🔍 Buscar Producto
                </a>
            </nav>
            
            <!-- Filtro de período -->
            <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <form method="get" style="display: flex; align-items: center; gap: 15px;">
                    <input type="hidden" name="page" value="wc-garantias-analisis">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>">
                    
                    <label style="font-weight: bold;">Período de análisis:</label>
                    <select name="periodo" onchange="this.form.submit()" style="padding: 5px 10px; border-radius: 4px;">
                        <option value="30" <?php selected($periodo, 30); ?>>Últimos 30 días</option>
                        <option value="90" <?php selected($periodo, 90); ?>>Últimos 90 días</option>
                        <option value="180" <?php selected($periodo, 180); ?>>Últimos 180 días</option>
                        <option value="365" <?php selected($periodo, 365); ?>>Último año</option>
                    </select>
                    
                    <button type="submit" class="button button-primary">Actualizar</button>
                </form>
            </div>
            
            <!-- Contenido según tab activo -->
            <?php if ($active_tab == 'clientes'): ?>
                <?php self::render_analisis_clientes($periodo); ?>
            <?php elseif ($active_tab == 'productos'): ?>
                <?php self::render_analisis_productos($periodo); ?>
            <?php elseif ($active_tab == 'buscador'): ?>
                <?php self::render_buscador_producto($periodo); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar análisis de clientes
     */
    private static function render_analisis_clientes($periodo) {
        ?>
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0;">👥 Top 10 Clientes por Tasa de Reclamo (Últimos <?php echo $periodo; ?> días)</h2>
            
            <?php
            // Fecha límite según período
            $fecha_limite = date('Y-m-d', strtotime("-{$periodo} days"));
            
            // Obtener todos los clientes con garantías
            $garantias = get_posts([
                'post_type' => 'garantia',
                'post_status' => 'publish',
                'date_query' => [
                    [
                        'after' => $fecha_limite,
                        'inclusive' => true,
                    ]
                ],
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            
            $clientes_data = [];
            
            // Agrupar por cliente
            foreach ($garantias as $garantia_id) {
                $cliente_id = get_post_meta($garantia_id, '_cliente', true);
                if (!$cliente_id) continue;
                
                if (!isset($clientes_data[$cliente_id])) {
                    $clientes_data[$cliente_id] = [
                        'garantias' => [],
                        'items_reclamados' => 0
                    ];
                }
                
                $clientes_data[$cliente_id]['garantias'][] = $garantia_id;
                
                // Contar items
                $items = get_post_meta($garantia_id, '_items_reclamados', true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $clientes_data[$cliente_id]['items_reclamados'] += intval($item['cantidad'] ?? 1);
                    }
                }
            }
            
            // Calcular tasas
            $clientes_con_tasa = [];
            
            foreach ($clientes_data as $cliente_id => $data) {
                $user = get_userdata($cliente_id);
                if (!$user) continue;
                
                // Obtener el nombre real del cliente
                $nombre_cliente = get_user_meta($cliente_id, 'billing_first_name', true) . ' ' . 
                                 get_user_meta($cliente_id, 'billing_last_name', true);
                if (trim($nombre_cliente) == '') {
                    $nombre_cliente = $user->display_name;
                }
                
                // Calcular items APROBADOS (no rechazados ni retorno_cliente)
                $items_aprobados = 0;
                foreach ($data['garantias'] as $garantia_id) {
                    $items = get_post_meta($garantia_id, '_items_reclamados', true);
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $estado_item = $item['estado'] ?? '';
                            // Solo contar items aprobados (estados que S generan cupón)
                            if (in_array($estado_item, ['aprobado', 'destruccion_aprobada', 'devolucion_recibida'])) {
                                $items_aprobados += intval($item['cantidad'] ?? 1);
                            }
                        }
                    }
                }
                
                // Calcular items comprados con una sola consulta
                global $wpdb;
                $total_items_comprados = $wpdb->get_var($wpdb->prepare("
                    SELECT SUM(oim.meta_value) 
                    FROM {$wpdb->prefix}woocommerce_order_items oi
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                    INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status IN ('wc-completed')
                    AND p.post_date >= %s
                    AND pm.meta_key = '_customer_user'
                    AND pm.meta_value = %d
                    AND oim.meta_key = '_qty'
                ", $fecha_limite, $cliente_id));
                
                $total_items_comprados = intval($total_items_comprados);
                
                if ($total_items_comprados > 0) {
                    // Tasa basada en items APROBADOS, no en reclamados
                    $tasa = ($items_aprobados / $total_items_comprados) * 100;
                    
                    $clientes_con_tasa[] = [
                        'id' => $cliente_id,
                        'nombre' => $nombre_cliente,
                        'email' => $user->user_email,
                        'reclamados' => $data['items_reclamados'],
                        'aprobados' => $items_aprobados,
                        'comprados' => $total_items_comprados,
                        'tasa' => $tasa,
                        'num_garantias' => count($data['garantias'])
                    ];
                }
            }
            
            // Ordenar por tasa
            usort($clientes_con_tasa, function($a, $b) {
                return $b['tasa'] <=> $a['tasa'];
            });
            
            // Limitar a top 20
            $clientes_con_tasa = array_slice($clientes_con_tasa, 0, 10);
            ?>
            
            <table class="wp-list-table widefat fixed striped">
            <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th style="text-align: center;">Garantías</th>
                        <th style="text-align: center;">Reclamados</th>
                        <th style="text-align: center;">Aprobados</th>
                        <th style="text-align: center;">Items Comprados</th>
                        <th style="text-align: center;">Tasa</th>
                        <th style="text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $contador = 1;
                    foreach ($clientes_con_tasa as $cliente): 
                        // Color según tasa
                        if ($cliente['tasa'] > 10) {
                            $color = '#dc3545';
                            $bg = '#ffebee';
                        } elseif ($cliente['tasa'] > 5) {
                            $color = '#ffc107';
                            $bg = '#fff3cd';
                        } else {
                            $color = '#28a745';
                            $bg = '#e8f5e9';
                        }
                    ?>
                    <tr>
                        <td style="font-weight: bold; color: #666;"><?php echo $contador++; ?></td>
                        <td>
                            <strong><?php echo esc_html($cliente['nombre']); ?></strong>
                        </td>
                        <td><?php echo esc_html($cliente['email']); ?></td>
                        <td style="text-align: center;">
                            <span style="background: #e3f2fd; padding: 5px 10px; border-radius: 15px;">
                                <?php echo $cliente['num_garantias']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #ffebee; padding: 5px 10px; border-radius: 15px; color: #c62828; font-weight: bold;">
                                <?php echo $cliente['reclamados']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #fff3cd; padding: 5px 10px; border-radius: 15px; color: #856404; font-weight: bold;">
                                <?php echo $cliente['aprobados']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #e8f5e9; padding: 5px 10px; border-radius: 15px; color: #2e7d32;">
                                <?php echo $cliente['comprados']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>; padding: 8px 12px; border-radius: 20px; font-weight: bold; font-size: 14px;">
                                <?php echo number_format($cliente['tasa'], 1); ?>%
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?php echo admin_url('admin.php?page=wc-garantias&s=' . urlencode($cliente['email'])); ?>" 
                               class="button button-small">Ver garantías</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($clientes_con_tasa)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            No hay datos para el período seleccionado
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <p style="margin: 0; color: #666;">
                    <strong>Leyenda de colores:</strong> 
                    <span style="color: #28a745;"> Menor a 5%: Aceptable</span> | 
                    <span style="color: #ffc107;">● 5-10%: Atención</span> | 
                    <span style="color: #dc3545;">● Mayor a 10%: Crítico</span>
                </p>
            </div>
        </div>
        <?php
    }
    
        /**
     * Renderizar análisis de productos
     */
    private static function render_analisis_productos($periodo) {
        ?>
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0;">📦 Top 10 Productos por Tasa de Reclamo (Últimos <?php echo $periodo; ?> días)</h2>
            <p style="color: #666;">Solo se muestran productos con mínimo 10 ventas en el período</p>
            
            <?php
            // Fecha límite según período
            $fecha_limite = date('Y-m-d', strtotime("-{$periodo} days"));
            
            // Obtener garantías del período
            $garantias_recientes = get_posts([
                'post_type' => 'garantia',
                'post_status' => 'publish',
                'date_query' => [
                    [
                        'after' => $fecha_limite,
                        'inclusive' => true,
                    ]
                ],
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            
            // Contar items por producto y su estado
            $productos_data = [];
            foreach ($garantias_recientes as $garantia_id) {
                $items = get_post_meta($garantia_id, '_items_reclamados', true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $producto_id = $item['producto_id'] ?? 0;
                        if (!$producto_id) continue;
                        
                        if (!isset($productos_data[$producto_id])) {
                            $productos_data[$producto_id] = [
                                'reclamados' => 0,
                                'aprobados' => 0
                            ];
                        }
                        
                        $cantidad = intval($item['cantidad'] ?? 1);
                        $estado = $item['estado'] ?? '';
                        
                        // Contar total reclamados
                        $productos_data[$producto_id]['reclamados'] += $cantidad;
                        
                        // Contar solo aprobados
                        if (in_array($estado, ['aprobado', 'destruccion_aprobada', 'devolucion_recibida'])) {
                            $productos_data[$producto_id]['aprobados'] += $cantidad;
                        }
                    }
                }
            }
            
            // Si no hay productos con reclamos, mostrar mensaje
            if (empty($productos_data)) {
                ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No hay productos con reclamos en el período seleccionado.
                </p>
                </div>
                <?php
                return;
            }
            
            // Obtener ventas de TODOS los productos CON RECLAMOS en una sola consulta
            global $wpdb;
            $product_ids = implode(',', array_keys($productos_data));
            
            // CAMBIO IMPORTANTE: Incluir múltiples estados de pedidos
            $ventas_query = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    oim2.meta_value as product_id,
                    SUM(oim.meta_value) as cantidad_vendida
                FROM {$wpdb->prefix}woocommerce_order_items oi
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id
                INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-delivered', 'wc-shipped')
                AND p.post_date >= %s
                AND oim.meta_key = '_qty'
                AND oim2.meta_key = '_product_id'
                AND oim2.meta_value IN ($product_ids)
                GROUP BY oim2.meta_value
            ", $fecha_limite));
            
            // Convertir resultados a array asociativo
            $ventas_por_producto = [];
            foreach ($ventas_query as $venta) {
                $ventas_por_producto[$venta->product_id] = intval($venta->cantidad_vendida);
            }
            
            // Construir array final con tasas
            $productos_con_tasa = [];
            foreach ($productos_data as $producto_id => $data) {
                // Solo procesar si tiene ventas
                $cantidad_vendida = $ventas_por_producto[$producto_id] ?? 0;
                
                // Solo incluir productos con mínimo 10 ventas
                if ($cantidad_vendida >= 10) {
                    $producto = wc_get_product($producto_id);
                    if (!$producto) continue;
                    
                    // Tasa basada en APROBADOS
                    $tasa_real = ($data['aprobados'] / $cantidad_vendida) * 100;
                    $tasa_reclamados = ($data['reclamados'] / $cantidad_vendida) * 100;
                    
                    // CAMBIO IMPORTANTE: Obtener el código de barras _alg_ean en lugar del SKU
                    $codigo_barras = get_post_meta($producto_id, '_alg_ean', true);
                    if (empty($codigo_barras)) {
                        $codigo_barras = '-';
                    }
                    
                    // Obtener stock
                    $stock = $producto->get_stock_quantity();
                    if ($stock === null) {
                        $stock = '-';
                    }
                    
                    $productos_con_tasa[] = [
                        'id' => $producto_id,
                        'nombre' => $producto->get_name(),
                        'codigo_barras' => $codigo_barras,  // Cambiado de 'sku' a 'codigo_barras'
                        'stock' => $stock,
                        'reclamados' => $data['reclamados'],
                        'aprobados' => $data['aprobados'],
                        'vendidos' => $cantidad_vendida,
                        'tasa_real' => $tasa_real,
                        'tasa_reclamados' => $tasa_reclamados
                    ];
                }
            }
            
            // Ordenar por tasa real (aprobados) descendente
            usort($productos_con_tasa, function($a, $b) {
                return $b['tasa_real'] <=> $a['tasa_real'];
            });
            
            // Limitar a top 10
            $productos_con_tasa = array_slice($productos_con_tasa, 0, 10);
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Producto</th>
                        <th style="text-align: center;">Código</th>
                        <th style="text-align: center;">Stock</th>
                        <th style="text-align: center;">Vendidos</th>
                        <th style="text-align: center;">Reclamados</th>
                        <th style="text-align: center;">Aprobados</th>
                        <th style="text-align: center;">Tasa Real</th>
                        <th style="text-align: center;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $contador = 1;
                    foreach ($productos_con_tasa as $producto): 
                        // Color según tasa real
                        if ($producto['tasa_real'] > 10) {
                            $color = '#dc3545';
                            $bg = '#ffebee';
                        } elseif ($producto['tasa_real'] >= 5) {
                            $color = '#ffc107';
                            $bg = '#fff3cd';
                        } else {
                            $color = '#28a745';
                            $bg = '#e8f5e9';
                        }
                        
                        // Color para stock
                        $stock_color = '#28a745';
                        $stock_bg = '#e8f5e9';
                        if ($producto['stock'] === '-') {
                            $stock_color = '#666';
                            $stock_bg = '#f5f5f5';
                        } elseif ($producto['stock'] === 0 || $producto['stock'] === '0') {
                            $stock_color = '#dc3545';
                            $stock_bg = '#ffebee';
                        } elseif (is_numeric($producto['stock']) && $producto['stock'] < 10) {
                            $stock_color = '#ffc107';
                            $stock_bg = '#fff3cd';
                        }
                    ?>
                    <tr>
                        <td style="font-weight: bold; color: #666;"><?php echo $contador++; ?></td>
                        <td>
                            <strong><?php echo esc_html($producto['nombre']); ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                <?php echo esc_html($producto['codigo_barras']); ?>
                            </code>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: <?php echo $stock_bg; ?>; color: <?php echo $stock_color; ?>; padding: 5px 10px; border-radius: 15px; font-weight: bold;">
                                <?php echo $producto['stock']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #e8f5e9; padding: 5px 10px; border-radius: 15px; color: #2e7d32;">
                                <?php echo $producto['vendidos']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #ffebee; padding: 5px 10px; border-radius: 15px; color: #c62828;">
                                <?php echo $producto['reclamados']; ?>
                            </span>
                            <?php if ($producto['reclamados'] > 0): ?>
                            <br><small style="color: #999;">(<?php echo number_format($producto['tasa_reclamados'], 1); ?>%)</small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #fff3cd; padding: 5px 10px; border-radius: 15px; color: #856404; font-weight: bold;">
                                <?php echo $producto['aprobados']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>; padding: 8px 12px; border-radius: 20px; font-weight: bold; font-size: 14px;">
                                <?php echo number_format($producto['tasa_real'], 1); ?>%
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?php echo get_edit_post_link($producto['id']); ?>" 
                               class="button button-small" target="_blank">Ver producto</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($productos_con_tasa)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            No hay productos con más de 10 ventas y reclamos en el período seleccionado
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                <p style="margin: 5px 0; color: #666;">
                    <strong>Leyenda de Tasa Real:</strong> 
                    <span style="color: #28a745;">● Menor a 5%: Aceptable</span> | 
                    <span style="color: #ffc107;">● 5-10%: Atención</span> | 
                    <span style="color: #dc3545;">● Mayor a 10%: Crítico</span>
                </p>
                <p style="margin: 5px 0; color: #666;">
                    <strong>Stock:</strong> 
                    <span style="color: #dc3545;">● Sin stock</span> | 
                    <span style="color: #ffc107;">● Menos de 10</span> | 
                    <span style="color: #28a745;">● 10 o más</span>
                </p>
                <p style="margin: 5px 0; color: #666; font-size: 12px;">
                    <strong>Nota:</strong> La "Tasa Real" se calcula solo con items aprobados. 
                    El porcentaje entre parntesis en "Reclamados" muestra el total de reclamos incluyendo rechazados.
                </p>
            </div>
        </div>
        <?php
    }
        /**
     * Renderizar buscador de productos
     */
    private static function render_buscador_producto($periodo) {
        $busqueda = isset($_GET['buscar']) ? sanitize_text_field($_GET['buscar']) : '';
        $producto_encontrado = null;
        
        // Si hay búsqueda, buscar producto
        if (!empty($busqueda)) {
            global $wpdb;
            
            // Buscar por código de barras (_alg_ean) primero
            $producto_id = $wpdb->get_var($wpdb->prepare("
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_alg_ean' 
                AND meta_value = %s 
                LIMIT 1
            ", $busqueda));
            
            // Si no encuentra por código de barras, buscar por nombre
            if (!$producto_id) {
                $producto_id = $wpdb->get_var($wpdb->prepare("
                    SELECT ID 
                    FROM {$wpdb->posts} 
                    WHERE post_type = 'product' 
                    AND post_status = 'publish'
                    AND (post_title LIKE %s OR post_name LIKE %s)
                    LIMIT 1
                ", '%' . $wpdb->esc_like($busqueda) . '%', '%' . $wpdb->esc_like($busqueda) . '%'));
            }
            
            if ($producto_id) {
                $producto_encontrado = wc_get_product($producto_id);
            }
        }
        ?>
        
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2 style="margin-top: 0;">🔍 Buscar Producto - Análisis Individual</h2>
            
            <!-- Formulario de búsqueda -->
            <form method="get" style="margin-bottom: 30px;">
                <input type="hidden" name="page" value="wc-garantias-analisis">
                <input type="hidden" name="tab" value="buscador">
                <input type="hidden" name="periodo" value="<?php echo $periodo; ?>">
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" 
                           name="buscar" 
                           value="<?php echo esc_attr($busqueda); ?>" 
                           placeholder="Buscar por código de barras o nombre del producto..." 
                           style="flex: 1; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                    <button type="submit" class="button button-primary button-hero">Buscar</button>
                </div>
                <p style="color: #666; margin-top: 5px; font-size: 13px;">
                    💡 Puedes buscar por: Código de barras EAN, nombre del producto o parte del nombre
                </p>
            </form>
            
            <?php if (!empty($busqueda)): ?>
                <?php if ($producto_encontrado): ?>
                    <?php
                    // Obtener datos del producto
                    $producto_id = $producto_encontrado->get_id();
                    $codigo_barras = get_post_meta($producto_id, '_alg_ean', true) ?: '-';
                    $stock = $producto_encontrado->get_stock_quantity();
                    if ($stock === null) $stock = 'Sin gestión';
                    
                    // Fecha límite según período
                    $fecha_limite = date('Y-m-d', strtotime("-{$periodo} days"));
                    
                    // Obtener ventas del producto
                    global $wpdb;
                    $ventas = $wpdb->get_var($wpdb->prepare("
                        SELECT SUM(oim.meta_value) as cantidad_vendida
                        FROM {$wpdb->prefix}woocommerce_order_items oi
                        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id
                        INNER JOIN {$wpdb->posts} p ON oi.order_id = p.ID
                        WHERE p.post_type = 'shop_order'
                        AND p.post_status IN ('wc-completed', 'wc-delivered', 'wc-shipped')
                        AND p.post_date >= %s
                        AND oim.meta_key = '_qty'
                        AND oim2.meta_key = '_product_id'
                        AND oim2.meta_value = %d
                    ", $fecha_limite, $producto_id));
                    
                    $ventas = intval($ventas);
                    
                    // Obtener garantías del producto
                    $garantias = get_posts([
                        'post_type' => 'garantia',
                        'post_status' => 'publish',
                        'date_query' => [
                            ['after' => $fecha_limite, 'inclusive' => true]
                        ],
                        'posts_per_page' => -1,
                        'fields' => 'ids'
                    ]);
                    
                    // Analizar garantías
                    $total_reclamados = 0;
                    $total_aprobados = 0;
                    $garantias_del_producto = [];
                    $clientes_con_reclamos = [];
                    
                    foreach ($garantias as $garantia_id) {
                        $items = get_post_meta($garantia_id, '_items_reclamados', true);
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                if (($item['producto_id'] ?? 0) == $producto_id) {
                                    $cantidad = intval($item['cantidad'] ?? 1);
                                    $estado = $item['estado'] ?? '';
                                    
                                    $total_reclamados += $cantidad;
                                    
                                    if (in_array($estado, ['aprobado', 'destruccion_aprobada', 'devolucion_recibida'])) {
                                        $total_aprobados += $cantidad;
                                    }
                                    
                                    // Guardar info de la garantía
                                    $cliente_id = get_post_meta($garantia_id, '_cliente', true);
                                    $garantias_del_producto[] = [
                                        'garantia_id' => $garantia_id,
                                        'fecha' => get_the_date('Y-m-d', $garantia_id),
                                        'cliente_id' => $cliente_id,
                                        'cantidad' => $cantidad,
                                        'estado' => $estado,
                                        'motivo' => $item['motivo'] ?? '-'
                                    ];
                                    
                                    // Contar clientes únicos
                                    if ($cliente_id && !in_array($cliente_id, $clientes_con_reclamos)) {
                                        $clientes_con_reclamos[] = $cliente_id;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Calcular tasas
                    $tasa_reclamados = $ventas > 0 ? ($total_reclamados / $ventas) * 100 : 0;
                    $tasa_aprobados = $ventas > 0 ? ($total_aprobados / $ventas) * 100 : 0;
                    
                    // Colores según tasa
                    if ($tasa_aprobados > 10) {
                        $color_tasa = '#dc3545';
                        $bg_tasa = '#ffebee';
                        $estado_producto = '⚠️ CRÍTICO';
                    } elseif ($tasa_aprobados > 5) {
                        $color_tasa = '#ffc107';
                        $bg_tasa = '#fff3cd';
                        $estado_producto = '⚠️ ATENCIÓN';
                    } else {
                        $color_tasa = '#28a745';
                        $bg_tasa = '#e8f5e9';
                        $estado_producto = '✅ ACEPTABLE';
                    }
                    ?>
                    
                    <!-- Información del producto -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                        <h3 style="margin-top: 0; color: #333;">📦 <?php echo esc_html($producto_encontrado->get_name()); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                            <div style="background: white; padding: 15px; border-radius: 5px;">
                                <div style="color: #666; font-size: 12px; text-transform: uppercase;">Código de Barras</div>
                                <div style="font-size: 20px; font-weight: bold; color: #333; margin-top: 5px;">
                                    <?php echo esc_html($codigo_barras); ?>
                                </div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px;">
                                <div style="color: #666; font-size: 12px; text-transform: uppercase;">Stock Actual</div>
                                <div style="font-size: 20px; font-weight: bold; color: <?php echo is_numeric($stock) && $stock < 10 ? '#ffc107' : '#333'; ?>; margin-top: 5px;">
                                    <?php echo $stock; ?>
                                </div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px;">
                                <div style="color: #666; font-size: 12px; text-transform: uppercase;">Vendidos (<?php echo $periodo; ?> días)</div>
                                <div style="font-size: 20px; font-weight: bold; color: #2e7d32; margin-top: 5px;">
                                    <?php echo $ventas; ?>
                                </div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px;">
                                <div style="color: #666; font-size: 12px; text-transform: uppercase;">Items Reclamados</div>
                                <div style="font-size: 20px; font-weight: bold; color: #dc3545; margin-top: 5px;">
                                    <?php echo $total_reclamados; ?>
                                    <?php if ($total_reclamados > 0): ?>
                                    <span style="font-size: 14px; color: #999;">(<?php echo number_format($tasa_reclamados, 1); ?>%)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="background: white; padding: 15px; border-radius: 5px;">
                                <div style="color: #666; font-size: 12px; text-transform: uppercase;">Items Aprobados</div>
                                <div style="font-size: 20px; font-weight: bold; color: #ffc107; margin-top: 5px;">
                                    <?php echo $total_aprobados; ?>
                                    <?php if ($total_aprobados > 0): ?>
                                    <span style="font-size: 14px; color: #999;">(<?php echo number_format($tasa_aprobados, 1); ?>%)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="background: <?php echo $bg_tasa; ?>; padding: 15px; border-radius: 5px;">
                                <div style="color: #666; font-size: 12px; text-transform: uppercase;">Estado del Producto</div>
                                <div style="font-size: 18px; font-weight: bold; color: <?php echo $color_tasa; ?>; margin-top: 5px;">
                                    <?php echo $estado_producto; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6;">
                            <p style="margin: 5px 0; color: #666;">
                                <strong>📊 Resumen:</strong> 
                                De <?php echo $ventas; ?> unidades vendidas, se reclamaron <?php echo $total_reclamados; ?> 
                                (<?php echo number_format($tasa_reclamados, 1); ?>%) y se aprobaron <?php echo $total_aprobados; ?> 
                                (<?php echo number_format($tasa_aprobados, 1); ?>%).
                            </p>
                            <p style="margin: 5px 0; color: #666;">
                                <strong>👥 Clientes únicos con reclamos:</strong> <?php echo count($clientes_con_reclamos); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Historial de garantías -->
                    <?php if (!empty($garantias_del_producto)): ?>
                    <h3>📋 Historial de Garantías del Producto</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>ID Garantía</th>
                                <th>Cliente</th>
                                <th>Cantidad</th>
                                <th>Estado</th>
                                <th>Motivo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Ordenar por fecha descendente
                            usort($garantias_del_producto, function($a, $b) {
                                return strcmp($b['fecha'], $a['fecha']);
                            });
                            
                            foreach ($garantias_del_producto as $garantia): 
                                // Obtener nombre del cliente
                                $cliente_nombre = '-';
                                if ($garantia['cliente_id']) {
                                    $user = get_userdata($garantia['cliente_id']);
                                    if ($user) {
                                        $cliente_nombre = get_user_meta($garantia['cliente_id'], 'billing_first_name', true) . ' ' . 
                                                         get_user_meta($garantia['cliente_id'], 'billing_last_name', true);
                                        if (trim($cliente_nombre) == '') {
                                            $cliente_nombre = $user->display_name;
                                        }
                                    }
                                }
                                
                                // Color según estado
                                $estado_color = '#666';
                                if (in_array($garantia['estado'], ['aprobado', 'destruccion_aprobada', 'devolucion_recibida'])) {
                                    $estado_color = '#28a745';
                                } elseif ($garantia['estado'] == 'rechazado') {
                                    $estado_color = '#dc3545';
                                }
                            ?>
                            <tr>
                                <td><?php echo $garantia['fecha']; ?></td>
                                <td>#<?php echo $garantia['garantia_id']; ?></td>
                                <td><?php echo esc_html($cliente_nombre); ?></td>
                                <td style="text-align: center;">
                                    <span style="background: #ffebee; padding: 3px 8px; border-radius: 12px; color: #c62828;">
                                        <?php echo $garantia['cantidad']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: <?php echo $estado_color; ?>; font-weight: bold;">
                                        <?php echo ucfirst(str_replace('_', ' ', $garantia['estado'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($garantia['motivo']); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $garantia['garantia_id'] . '&action=edit'); ?>" 
                                       class="button button-small" target="_blank">Ver</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                        <p style="color: #2e7d32; font-size: 16px; margin: 0;">
                            ✅ No hay garantías registradas para este producto en los últimos <?php echo $periodo; ?> días
                        </p>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- No se encontró el producto -->
                    <div style="background: #ffebee; padding: 20px; border-radius: 5px; text-align: center;">
                        <p style="color: #c62828; font-size: 16px; margin: 0;">
                            ❌ No se encontró ningún producto con: "<strong><?php echo esc_html($busqueda); ?></strong>"
                        </p>
                        <p style="color: #666; margin-top: 10px;">
                            Intenta buscar por el código de barras completo o parte del nombre del producto.
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}