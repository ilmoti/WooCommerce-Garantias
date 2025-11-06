<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo de Lista de Garantías
 */
class WC_Garantias_Admin_List {
    
    private static $estados = [
        'nueva'       => 'Nueva',
        'en_proceso'  => 'En proceso', 
        'finalizada'  => 'Finalizada',
    ];
    
    public static function render_page() {
        // Procesar eliminación de garantía
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['garantia_id'])) {
            self::procesar_eliminacion();
        }
        
        // Obtener parámetros de búsqueda
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $estado_filter = isset($_GET['estado_filter']) ? (array)$_GET['estado_filter'] : array();
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Exportar CSV si se solicita
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            self::export_csv();
            exit;
        }
        
        // Query de garantías
        $args = self::build_query_args($search, $estado_filter, $paged);
        $garantias = new WP_Query($args);
        
        // Renderizar HTML
        self::render_html($garantias, $search, $estado_filter, $paged);
    }
    
    private static function procesar_eliminacion() {
        $garantia_id = intval($_GET['garantia_id']);
        
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_garantia_' . $garantia_id)) {
            $result = wp_delete_post($garantia_id, true);
            
            if ($result) {
                $redirect_url = add_query_arg(array(
                    'page' => 'wc-garantias',
                    'mensaje' => 'eliminado'
                ), admin_url('admin.php'));
            } else {
                $redirect_url = add_query_arg(array(
                    'page' => 'wc-garantias',
                    'mensaje' => 'error'
                ), admin_url('admin.php'));
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    private static function build_query_args($search, $estado_filter, $paged) {
        $args = array(
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => 25,
            'paged' => $paged,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Filtrar por items pendientes
        if (isset($_GET['items_pendientes']) && $_GET['items_pendientes'] == '1') {
            $garantias_con_items_pendientes = [];
            
            $todas_garantias = get_posts([
                'post_type' => 'garantia',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);
            
            foreach ($todas_garantias as $garantia_id) {
                $items = get_post_meta($garantia_id, '_items_reclamados', true);
                $tiene_items_pendientes = false;
                
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $estado_item = $item['estado'] ?? 'Pendiente';
                        if (in_array($estado_item, ['Pendiente', 'solicitar_info', 'recibido', 'aprobado_destruir', 'aprobado_devolver', 'destruccion_subida', 'devolucion_en_transito', 'apelacion'])) {
                            $tiene_items_pendientes = true;
                            break;
                        }
                    }
                } else {
                    $estado_garantia = get_post_meta($garantia_id, '_estado', true);
                    if (in_array($estado_garantia, ['nueva', 'en_proceso'])) {
                        $tiene_items_pendientes = true;
                    }
                }
                
                if ($tiene_items_pendientes) {
                    $garantias_con_items_pendientes[] = $garantia_id;
                }
            }
            
            if (!empty($garantias_con_items_pendientes)) {
                $args['post__in'] = $garantias_con_items_pendientes;
            } else {
                $args['post__in'] = [0];
            }
        }
        
        // Búsqueda mejorada
        if (!empty($search)) {
            $user_query = new WP_User_Query(array(
                'search' => '*' . $search . '*',
                'search_columns' => array('user_login', 'user_email', 'display_name'),
                'fields' => 'ID'
            ));
            $user_ids = $user_query->get_results();
            
            $phone_query = new WP_User_Query(array(
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'billing_phone',
                        'value' => $search,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'phone',
                        'value' => $search,
                        'compare' => 'LIKE'
                    )
                ),
                'fields' => 'ID'
            ));
            $phone_user_ids = $phone_query->get_results();
            
            $all_user_ids = array_unique(array_merge($user_ids, $phone_user_ids));
            
            $meta_query = array('relation' => 'OR');
            
            $meta_query[] = array(
                'key' => '_codigo_unico',
                'value' => $search,
                'compare' => 'LIKE'
            );
            
            $meta_query[] = array(
                'key' => '_order_id',
                'value' => $search,
                'compare' => 'LIKE'
            );
            
            if (!empty($all_user_ids)) {
                $meta_query[] = array(
                    'key' => '_cliente',
                    'value' => $all_user_ids,
                    'compare' => 'IN'
                );
            }
            
            $args['meta_query'] = $meta_query;
        }
        
        // Filtro de estados múltiples
        if (!empty($estado_filter) && !in_array('', $estado_filter)) {
            // Si se selecciona "en_proceso", incluir también "parcialmente_recibido"
            $estados_buscar = $estado_filter;
            if (in_array('en_proceso', $estado_filter)) {
                $estados_buscar[] = 'parcialmente_recibido';
                $estados_buscar = array_unique($estados_buscar);
            }
            
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = array();
            }
            
            if (!empty($search)) {
                $search_query = $args['meta_query'];
                $args['meta_query'] = array(
                    'relation' => 'AND',
                    $search_query,
                    array(
                        'key' => '_estado',
                        'value' => $estados_buscar,
                        'compare' => 'IN'
                    )
                );
            } else {
                $args['meta_query'][] = array(
                    'key' => '_estado',
                    'value' => $estados_buscar,
                    'compare' => 'IN'
                );
            }
        }
        
        return $args;
    }
    
    private static function render_html($garantias, $search, $estado_filter, $paged) {
        ?>
        <div class="wrap">
            <?php self::render_mensajes(); ?>
            <?php self::render_estilos(); ?>
            
            <!-- Header Principal -->
            <div class="garantias-header">
                <div>
                    <h1 style="margin: 0; font-size: 32px; font-weight: 300;">
                        Panel de <strong>Garantías</strong>
                        <?php if (isset($_GET['items_pendientes']) && $_GET['items_pendientes'] == '1'): ?>
                            <span style="font-size: 18px; opacity: 0.8;"> - Items Pendientes</span>
                        <?php endif; ?>
                    </h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">
                        <?php if (isset($_GET['items_pendientes']) && $_GET['items_pendientes'] == '1'): ?>
                            Mostrando solo garantías con items pendientes de procesamiento
                        <?php else: ?>
                            Gestiona todas las solicitudes de garantía en un solo lugar
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <!-- Contenedor de Filtros -->
            <?php self::render_filtros($search, $estado_filter); ?>
            
            <!-- Tabla de garantas -->
            <div class="garantias-table">
                <?php if ($garantias->have_posts()): ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 220px;">Código</th>
                                <th>Cliente</th>
                                <th style="width: 120px;">Teléfono</th>
                                <th style="width: 100px;">Fecha</th>
                                <th style="width: 220px;">Estado</th>
                                <th style="text-align: center; width: 60px;">Items</th>
                                <th style="text-align: center; width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php self::render_tabla_filas($garantias); ?>
                        </tbody>
                    </table>
                    
                    <!-- Paginación -->
                    <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e9ecef;">
                        <div style="color: #6c757d; font-size: 14px;">
                            Mostrando <?php echo $garantias->post_count; ?> de <?php echo $garantias->found_posts; ?> registros
                        </div>
                        <div>
                            <?php
                            echo paginate_links(array(
                                'total' => $garantias->max_num_pages,
                                'current' => $paged,
                                'format' => '?paged=%#%',
                                'prev_text' => '<i class="fas fa-chevron-left"></i> Anterior',
                                'next_text' => 'Siguiente <i class="fas fa-chevron-right"></i>',
                            ));
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding: 60px; text-align: center;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #dee2e6; margin-bottom: 20px;"></i>
                        <p style="color: #6c757d; font-size: 16px;">No se encontraron garantías</p>
                    </div>
                <?php endif; ?>
                
                <?php wp_reset_postdata(); ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_mensajes() {
        if (isset($_GET['mensaje'])):
            if ($_GET['mensaje'] === 'eliminado'): ?>
                <div id="mensaje-garantia" style="background: #55efc4; color: #00b894; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">✓</span>
                        <span>Garantía eliminada correctamente.</span>
                    </div>
                    <button onclick="document.getElementById('mensaje-garantia').style.display='none'" style="background: none; border: none; color: #00b894; font-size: 20px; cursor: pointer;">&times;</button>
                </div>
            <?php elseif ($_GET['mensaje'] === 'error'): ?>
                <div id="mensaje-garantia" style="background: #ff7675; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">✗</span>
                        <span>Error al eliminar la garantía.</span>
                    </div>
                    <button onclick="document.getElementById('mensaje-garantia').style.display='none'" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
                </div>
            <?php endif; ?>
            
            <script>
            setTimeout(function() {
                var mensaje = document.getElementById('mensaje-garantia');
                if (mensaje) {
                    mensaje.style.transition = 'opacity 0.5s';
                    mensaje.style.opacity = '0';
                    setTimeout(function() {
                        mensaje.style.display = 'none';
                    }, 500);
                }
            }, 5000);
            
            if (window.history.replaceState) {
                var url = new URL(window.location);
                url.searchParams.delete('mensaje');
                window.history.replaceState({}, document.title, url);
            }
            </script>
        <?php endif;
    }
    
    private static function render_estilos() {
        ?>
        <style>
            body { background: #f5f6fa; }
            .wrap { max-width: 1400px; margin: 0 auto; }
            
            .garantias-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px;
                margin: -20px -20px 30px -20px;
                border-radius: 0 0 20px 20px;
            }
            
            .filtros-container {
                background: white;
                border-radius: 12px;
                padding: 25px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                margin-bottom: 25px;
            }
            
            .garantias-table {
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            
            .garantias-table table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .garantias-table th {
                background: #f8f9fa;
                padding: 16px 20px;
                text-align: left;
                font-weight: 600;
                color: #495057;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 2px solid #e9ecef;
            }
            
            .garantias-table td {
                padding: 16px 20px;
                border-bottom: 1px solid #f1f3f5;
                vertical-align: middle;
            }
            
            .garantias-table tr:hover {
                background: #f8f9fa;
            }
            
            .estado-badge {
                display: inline-block;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
            }
            
            .estado-nueva { background: #ffeaa7 !important; color: #f39c12 !important; }
            .estado-en_proceso { background: #74b9ff !important; color: #0984e3 !important; }
            .estado-finalizada { background: #55efc4 !important; color: #00b894 !important; }
            .estado-finalizado_cupon { background: #55efc4 !important; color: #00b894 !important; }
            
            .btn-action {
                padding: 6px 12px;
                border-radius: 6px;
                border: none;
                font-size: 13px;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center; 
                transition: all 0.3s ease;
                width: 32px;
                height: 32px;
                margin: 0 2px; 
            }
           
            .btn-view { background: #e3f2fd; color: #1976d2; }
            .btn-view:hover { background: #bbdefb; transform: translateY(-1px); }
            
            .btn-delete { background: #ffebee; color: #c62828; margin-left: 5px; }
            .btn-delete:hover { background: #ffcdd2; transform: translateY(-1px); }
            
            .modern-input {
                padding: 10px 16px;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            
            .modern-input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .btn-primary {
                background: #667eea;
                color: white;
                padding: 10px 24px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .btn-primary:hover {
                background: #5a67d8;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }
            
            .btn-success {
                background: #10b981;
                color: white;
                padding: 10px 24px;
                border: none;
                border-radius: 8px;
                font-size: 14px;
                cursor: pointer;
            }
            
            .estado-checkbox-compact input[type="checkbox"]:checked + .estado-badge {
                opacity: 1 !important;
                box-shadow: 0 0 0 2px #667eea;
                transform: scale(1.05);
            }
            
            .estado-checkbox-compact:hover .estado-badge {
                opacity: 0.8;
                transform: translateY(-1px);
            }
            
            .filtros-container {
                padding: 20px !important;
            }
        </style>
        <?php
    }
    
    private static function render_filtros($search, $estado_filter) {
        ?>
        <div class="filtros-container">
            <form method="get" action="">
                <input type="hidden" name="page" value="wc-garantias">
                
                <div style="display: grid; grid-template-columns: 300px 1fr auto; gap: 20px; align-items: start;">
                    <!-- Búsqueda -->
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057; font-size: 13px;">
                            <i class="fas fa-search"></i> Buscar
                        </label>
                        <input type="text" 
                               name="s" 
                               value="<?php echo esc_attr($search); ?>" 
                               placeholder="Código, cliente, teléfono..." 
                               class="modern-input"
                               style="width: 100%; height: 36px; font-size: 13px;">
                    </div>
                    
                    <!-- Estados en línea -->
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #495057; font-size: 13px;">
                            <i class="fas fa-filter"></i> Estados (puedes seleccionar varios)
                        </label>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach (self::$estados as $key => $label): ?>
                                <label class="estado-checkbox-compact">
                                    <input type="checkbox" 
                                           name="estado_filter[]" 
                                           value="<?php echo esc_attr($key); ?>"
                                           <?php checked(in_array($key, $estado_filter)); ?>
                                           style="display: none;">
                                    <span class="estado-badge estado-<?php echo esc_attr($key); ?>" 
                                          style="cursor: pointer; user-select: none; opacity: 0.6; transition: all 0.2s;">
                                        <?php echo esc_html($label); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Botones -->
                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 13px;">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        
                        <a href="<?php echo admin_url('admin.php?page=wc-garantias'); ?>" 
                           class="btn-primary" style="text-decoration: none; background: #6c757d; padding: 8px 16px; font-size: 13px;">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=wc-garantias&export=csv'); ?>" 
                           class="btn-success" style="text-decoration: none; padding: 8px 16px; font-size: 13px;">
                            <i class="fas fa-download"></i> Exportar CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    private static function render_tabla_filas($garantias) {
        while ($garantias->have_posts()): $garantias->the_post(); 
            $garantia_id = get_the_ID();
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            $cliente_id = get_post_meta($garantia_id, '_cliente', true);
            $user = get_userdata($cliente_id);
            $nombre_cliente = $user ? $user->display_name : 'Usuario eliminado';
            $telefono = get_user_meta($cliente_id, 'billing_phone', true) ?: '-';
            $fecha = get_the_date('d/m/Y');
            $estado = get_post_meta($garantia_id, '_estado', true);
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            $cantidad_items = is_array($items) ? count($items) : 1;
        ?>
            <tr>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id); ?>" 
                       style="text-decoration: none;">
                        <strong style="color: #667eea;">
                            <?php echo esc_html($codigo_unico); ?>
                        </strong>
                    </a>
                </td>
                <td><?php echo esc_html($nombre_cliente); ?></td>
                <td><?php echo esc_html($telefono); ?></td>
                <td><?php echo esc_html($fecha); ?></td>
                <td>
                    <?php 
                    // Mapear parcialmente_recibido a en_proceso para mostrarlo igual
                    $estado_display = $estado;
                    if ($estado === 'parcialmente_recibido') {
                        $estado_display = 'en_proceso';
                    }
                    ?>
                    <span class="estado-badge estado-<?php echo esc_attr($estado_display); ?>">
                        <?php echo esc_html(self::$estados[$estado_display] ?? ucwords(str_replace('_', ' ', $estado_display))); ?>
                    </span>
                </td>
                <td style="text-align: center;">
                    <span style="background: #e9ecef; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                        <?php echo $cantidad_items; ?>
                    </span>
                </td>
                <td style="text-align: center; white-space: nowrap;">
                    <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id); ?>" 
                       class="btn-action btn-view" 
                       title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <?php 
                    $tracking_numero = get_post_meta($garantia_id, '_numero_tracking_devolucion', true);
                    if (!empty($tracking_numero)): 
                    ?>
                    <a href="http://andreani.com/envio/<?php echo urlencode($tracking_numero); ?>" 
                       class="btn-action" 
                       style="background: #17a2b8; color: white;"
                       target="_blank"
                       title="Seguir envío - <?php echo esc_attr($tracking_numero); ?>">
                        <i class="fas fa-truck"></i>
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-garantias&action=delete&garantia_id=' . $garantia_id), 'delete_garantia_' . $garantia_id); ?>" 
                       class="btn-action btn-delete"
                       title="Eliminar"
                       onclick="return confirm('¿Estás seguro de que deseas eliminar la garantía <?php echo esc_html($codigo_unico); ?>?');">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endwhile;
    }
    
    public static function export_csv() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=garantias.csv');
        
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ]);
        
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Código', 'Nombre de cliente', 'Telfono', 'Fecha', 'Estado', 'Cantidad de items', 'Comentario']);
        
        foreach ($garantias as $garantia) {
            $codigo_unico = get_post_meta($garantia->ID, '_codigo_unico', true);
            $cliente_id = get_post_meta($garantia->ID, '_cliente', true);
            $nombre_cliente = 'Usuario eliminado';
            $telefono = '-';
            
            if ($cliente_id) {
                $user_info = get_userdata($cliente_id);
                if ($user_info) {
                    $nombre_cliente = $user_info->display_name ?: $user_info->user_login;
                    $telefono = get_user_meta($cliente_id, 'billing_phone', true);
                    if (!$telefono) $telefono = get_user_meta($cliente_id, 'phone', true);
                    if (!$telefono) $telefono = '-';
                }
            }
            
            $fecha = get_the_date('d/m/Y', $garantia);
            $estado = get_post_meta($garantia->ID, '_estado', true);
            $cantidad_items = get_post_meta($garantia->ID, '_cantidad', true);
            if (!$cantidad_items) $cantidad_items = 1;
            $comentario = get_post_meta($garantia->ID, '_comentario_interno', true);
            
            fputcsv($out, [
                $codigo_unico,
                $nombre_cliente,
                $telefono,
                $fecha,
                $estado,
                $cantidad_items,
                $comentario
            ]);
        }
        
        fclose($out);
    }
}