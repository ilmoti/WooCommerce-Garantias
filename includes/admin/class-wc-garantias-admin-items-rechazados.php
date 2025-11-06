<?php
if (!defined('ABSPATH')) exit;

/**
 * Módulo de gestión de items rechazados
 */
class WC_Garantias_Admin_Items_Rechazados {
    
    public static function render_page() {
        // Procesar acciones POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
            self::procesar_acciones();
        }
        
        ?>
        <div class="wrap">
            <h1>Gestión de Items Rechazados</h1>
            
            <div class="notice notice-info">
                <p>Aquí puedes gestionar items rechazados que necesitan ser movidos a "Retorno Cliente" para generar cupones RMA.</p>
            </div>
            
            <?php 
            // Obtener todos los items rechazados
            $items_rechazados = self::obtener_items_rechazados();
            
            if (empty($items_rechazados)) {
                echo '<p>No hay items rechazados en el sistema.</p>';
                return;
            }
            ?>
            
            <form method="post">
                <?php wp_nonce_field('gestionar_items_rechazados'); ?>
                <input type="hidden" name="accion" value="cambiar_estado">
                
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="nuevo_estado">
                            <option value="">-- Seleccionar acción --</option>
                            <option value="retorno_cliente">Cambiar a Retorno Cliente (para RMA)</option>
                            <option value="rechazado_no_recibido">Cambiar a Rechazado No Recibido</option>
                        </select>
                        <button type="submit" class="button button-primary">Aplicar</button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th>Código Item</th>
                            <th>Garantía</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Estado Actual</th>
                            <th>¿Recibido?</th>
                            <th>Motivo Rechazo</th>
                            <th>Fecha Rechazo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_rechazados as $item_data): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="items[]" 
                                       value="<?php echo esc_attr($item_data['garantia_id'] . '|' . $item_data['item']['codigo_item']); ?>">
                            </td>
                            <td>
                                <strong><?php echo esc_html($item_data['item']['codigo_item']); ?></strong>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $item_data['garantia_id']); ?>">
                                    <?php echo esc_html($item_data['codigo_garantia']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($item_data['cliente_nombre']); ?></td>
                            <td>
                                <?php echo esc_html($item_data['producto_nombre']); ?>
                                <?php if ($item_data['cantidad'] > 1): ?>
                                    <small>(x<?php echo $item_data['cantidad']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php echo $item_data['item']['estado'] === 'rechazado' ? '#dc3545' : '#ff9800'; ?>;">
                                    <?php echo esc_html($item_data['item']['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($item_data['item']['fecha_recibido'])): ?>
                                    <span style="color: green;">✓ Sí</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $motivo = $item_data['item']['motivo_rechazo'] ?? '-';
                                echo strlen($motivo) > 50 ? substr($motivo, 0, 50) . '...' : esc_html($motivo);
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($item_data['item']['fecha_rechazo'])) {
                                    echo date('d/m/Y', strtotime($item_data['item']['fecha_rechazo']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#select-all').on('change', function() {
                $('input[name="items[]"]').prop('checked', $(this).prop('checked'));
            });
        });
        </script>
        <?php
    }
    
    private static function obtener_items_rechazados() {
        $items_rechazados = [];
        
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_items_reclamados',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            $codigo_garantia = get_post_meta($garantia->ID, '_codigo_unico', true);
            $cliente_id = get_post_meta($garantia->ID, '_cliente', true);
            
            $cliente_nombre = 'Cliente eliminado';
            if ($cliente_id) {
                $user = get_userdata($cliente_id);
                if ($user) {
                    $cliente_nombre = $user->display_name ?: $user->user_login;
                }
            }
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    // Solo items rechazados (no retorno_cliente)
                    if (isset($item['estado']) && 
                        ($item['estado'] === 'rechazado' || $item['estado'] === 'rechazado_no_recibido')) {
                        
                        $producto_nombre = 'Producto eliminado';
                        if (!empty($item['nombre_producto'])) {
                            $producto_nombre = $item['nombre_producto'];
                        } elseif (!empty($item['producto_id'])) {
                            $producto = wc_get_product($item['producto_id']);
                            if ($producto) {
                                $producto_nombre = $producto->get_name();
                            }
                        }
                        
                        $items_rechazados[] = [
                            'garantia_id' => $garantia->ID,
                            'codigo_garantia' => $codigo_garantia,
                            'cliente_id' => $cliente_id,
                            'cliente_nombre' => $cliente_nombre,
                            'item' => $item,
                            'producto_nombre' => $producto_nombre,
                            'cantidad' => $item['cantidad'] ?? 1
                        ];
                    }
                }
            }
        }
        
        return $items_rechazados;
    }
    
    private static function procesar_acciones() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'gestionar_items_rechazados')) {
            wp_die('Error de seguridad');
        }
        
        if (!isset($_POST['items']) || empty($_POST['items'])) {
            echo '<div class="notice notice-error"><p>No se seleccionaron items.</p></div>';
            return;
        }
        
        $nuevo_estado = sanitize_text_field($_POST['nuevo_estado']);
        if (!in_array($nuevo_estado, ['retorno_cliente', 'rechazado_no_recibido'])) {
            echo '<div class="notice notice-error"><p>Estado no válido.</p></div>';
            return;
        }
        
        $items_procesados = 0;
        
        foreach ($_POST['items'] as $item_data) {
            list($garantia_id, $codigo_item) = explode('|', $item_data);
            
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            
            if (is_array($items)) {
                foreach ($items as &$item) {
                    if ($item['codigo_item'] === $codigo_item) {
                        $item['estado'] = $nuevo_estado;
                        
                        // Agregar historial
                        if (!isset($item['historial_cambios'])) {
                            $item['historial_cambios'] = [];
                        }
                        $item['historial_cambios'][] = [
                            'fecha' => current_time('mysql'),
                            'cambio' => 'Cambiado desde panel de gestión a ' . $nuevo_estado,
                            'usuario' => wp_get_current_user()->display_name
                        ];
                        
                        $items_procesados++;
                        break;
                    }
                }
                
                update_post_meta($garantia_id, '_items_reclamados', $items);
                WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            }
        }
        
        echo '<div class="notice notice-success"><p>' . $items_procesados . ' items actualizados correctamente.</p></div>';
    }
}