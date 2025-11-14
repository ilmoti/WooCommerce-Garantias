<?php
if (!defined('ABSPATH')) exit;

$customer_id = get_current_user_id();
$dias_limite = get_option('dias_devolucion_error', 20);
$fecha_limite = strtotime("-{$dias_limite} days");

// Obtener pedidos completados dentro del límite
$orders = wc_get_orders([
    'customer_id' => $customer_id,
    'status'      => 'completed',
    'limit'       => -1,
    'date_completed' => '>' . date('Y-m-d', $fecha_limite)
]);

$productos_devolucion = [];
foreach ($orders as $order) {
    $order_time = strtotime($order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : $order->get_date_created()->date('Y-m-d H:i:s'));
    if ($order_time < $fecha_limite) continue;
    
    foreach ($order->get_items() as $item) {
    $pid = $item->get_product_id();
    $qty = $item->get_quantity();
    
    // NUEVO: Verificar si es un BULTO para devoluciones
    $producto = wc_get_product($pid);
    if ($producto) {
        $nombre_producto = $producto->get_name();
        
        // EXCLUIR productos RMA
        if (stripos($nombre_producto, 'RMA') === 0) {
            continue; // Saltar este producto
        }
        
        // Si es un BULTO, extraer la cantidad real
        if (stripos($nombre_producto, 'BULTO') === 0) {
            // Buscar el patrón X seguido de números al final
            if (preg_match('/X(\d+)$/i', $nombre_producto, $matches)) {
                $cantidad_por_bulto = intval($matches[1]);
                $qty = $qty * $cantidad_por_bulto; // Multiplicar por la cantidad del bulto
            }
        }
    }
        
        // Verificar si ya fue devuelto O está en garantía
        $args_todos = [
            'post_type'      => 'garantia',
            'post_status'    => 'publish',
            'meta_query'     => [
                ['key' => '_cliente', 'value' => $customer_id]
            ],
            'posts_per_page' => -1
        ];
        $todas_garantias = get_posts($args_todos);
        
        $cantidad_devuelta = 0;
        $cantidad_en_garantia = 0;
        
        foreach ($todas_garantias as $gar) {
            $items_reclamados = get_post_meta($gar->ID, '_items_reclamados', true);
            $es_devolucion = get_post_meta($gar->ID, '_es_devolucion_error', true);
            
            if (is_array($items_reclamados)) {
                foreach ($items_reclamados as $item_rec) {
                    if ($item_rec['producto_id'] == $pid && isset($item_rec['order_id']) && $item_rec['order_id'] == $order->get_id()) {
                        if ($es_devolucion) {
                            $cantidad_devuelta += intval($item_rec['cantidad'] ?? 1);
                        } else {
                            $cantidad_en_garantia += intval($item_rec['cantidad'] ?? 1);
                        }
                    }
                }
            }
        }
        
        $qty_disponible = $qty - $cantidad_devuelta - $cantidad_en_garantia;
        
        if ($qty_disponible > 0) {
            if (!isset($productos_devolucion[$pid])) {
                $productos_devolucion[$pid] = [
                    'producto' => wc_get_product($pid),
                    'producto_nombre' => $item->get_name(), // Nombre desde la orden
                    'cantidad' => 0,
                    'cantidad_en_garantia' => 0,
                    'order_id' => $order->get_id(),
                    'fecha_compra' => $order->get_date_completed()->date('d/m/Y')
                ];
            }
            $productos_devolucion[$pid]['cantidad'] += $qty_disponible;
            $productos_devolucion[$pid]['cantidad_en_garantia'] = $cantidad_en_garantia;
        }
    }
}
?>

<div style="margin-bottom: 30px;">
    <a href="<?php echo wc_get_account_endpoint_url('garantias'); ?>" class="button" style="margin-bottom: 20px;">
        ← Volver a Garantías
    </a>
    
    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-radius: 15px; padding: 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px; color: white;">
                <i class="fas fa-undo"></i>
            </div>
            <h3 style="margin: 0; color: #2c3e50; font-weight: 600;">Devolución por Error de Compra</h3>
            <p style="margin: 5px 0 0 0; color: #6c757d;">Tienes <?php echo $dias_limite; ?> días corridos desde la compra para devolver productos</p>
        </div>
        
        <!-- Instrucciones -->
        <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 10px; padding: 20px; margin-bottom: 25px;">
            <h4 style="color: #0c5460; margin-top: 0;"><i class="fas fa-info-circle"></i> Instrucciones Importantes</h4>
            <div style="color: #0c5460; line-height: 1.8;">
                <?php echo nl2br(esc_html(get_option('instrucciones_devolucion_error', "1. Descarga e imprime la etiqueta de devolución\n2. Pégala en el paquete de forma visible\n3. El producto debe estar en perfectas condiciones, sin uso\n4. Incluye todos los accesorios y embalaje original\n5. Enva el paquete por la empresa de tu preferencia (costo a tu cargo)\n6. Guarda el comprobante de envío"))); ?>
            </div>
        </div>
        
        <?php if (empty($productos_devolucion)): ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-box-open" style="font-size: 48px; color: #dee2e6; margin-bottom: 20px;"></i>
                <p style="color: #6c757d; font-size: 16px;">No tienes productos disponibles para devolución</p>
                <small style="color: #999;">Los productos deben haber sido comprados en los ltimos <?php echo $dias_limite; ?> días</small>
            </div>
        <?php else: ?>
            <form method="post" id="devolucionForm">
                <input type="hidden" name="devolucion_form_submit" value="1">
                
                <h4 style="margin-bottom: 20px;">Selecciona los productos a devolver:</h4>
                
                <div style="background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e9ecef;">
                                <th style="text-align: left; padding: 10px;">Producto</th>
                                <th style="text-align: center; padding: 10px;">Fecha Compra</th>
                                <th style="text-align: center; padding: 10px;">Disponible</th>
                                <th style="text-align: center; padding: 10px;">Cantidad a Devolver</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_devolucion as $pid => $data): ?>
                                <?php
                                // Obtener nombre del producto (desde orden o desde WC)
                                $nombre_producto = $data['producto_nombre'];

                                // Si el producto existe en WC, verificar que no sea RMA
                                if ($data['producto'] && is_object($data['producto'])) {
                                    $nombre_producto = $data['producto']->get_name();
                                }

                                // Verificar si el producto empieza con RMA y saltarlo
                                if (stripos($nombre_producto, 'RMA') === 0) {
                                    continue;
                                }
                                ?>
                                <tr style="border-bottom: 1px solid #f1f3f4;">
                                    <td style="padding: 15px 10px;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="checkbox"
                                                   class="producto-check"
                                                   data-producto-id="<?php echo $pid; ?>"
                                                   style="margin-right: 10px;">
                                            <?php echo esc_html($nombre_producto); ?>
                                            <?php if (!$data['producto'] || !is_object($data['producto'])): ?>
                                                <span style="margin-left: 8px; font-size: 11px; background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px;">Producto eliminado</span>
                                            <?php endif; ?>
                                        </label>
                                    </td>
                                    <td style="text-align: center; padding: 15px 10px;">
                                        <?php echo $data['cantidad']; ?>
                                        <br><small style="color: #666;">
                                            <?php 
                                            if ($cantidad_en_garantia > 0) {
                                                echo "($cantidad_en_garantia en garantía)";
                                            }
                                            ?>
                                        </small>
                                        <?php echo esc_html($data['fecha_compra']); ?>
                                    </td>
                                    <td style="text-align: center; padding: 15px 10px;">
                                        <?php echo $data['cantidad']; ?>
                                    </td>
                                    <td style="text-align: center; padding: 15px 10px;">
                                        <input type="hidden" 
                                               class="producto-hidden" 
                                               name="producto[]" 
                                               value=""
                                               data-producto-id="<?php echo $pid; ?>">
                                        <input type="number" 
                                               name="cantidad[]" 
                                               min="0" 
                                               max="<?php echo $data['cantidad']; ?>" 
                                               value="0"
                                               class="cantidad-input"
                                               data-producto-id="<?php echo $pid; ?>"
                                               style="width: 80px; padding: 8px; border: 2px solid #e9ecef; border-radius: 5px; text-align: center;"
                                               disabled>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="text-align: center; padding-top: 20px;">
                    <button type="submit" 
                            id="submit-devolucion"
                            disabled
                            style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none; padding: 15px 40px; border-radius: 25px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(23, 162, 184, 0.4); opacity: 0.5;">
                        <i class="fas fa-check"></i> Generar Devolución
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Manejar checkboxes
    $('.producto-check').on('change', function() {
        var productoId = $(this).data('producto-id');
        var $cantidadInput = $('.cantidad-input[data-producto-id="' + productoId + '"]');
        var $hiddenInput = $('.producto-hidden[data-producto-id="' + productoId + '"]');
        
        if ($(this).is(':checked')) {
            $cantidadInput.prop('disabled', false).val(1).focus();
            $hiddenInput.val(productoId);
        } else {
            $cantidadInput.prop('disabled', true).val(0);
            $hiddenInput.val('');
        }
        
        verificarFormulario();
    });
    
    // Verificar si hay productos seleccionados
    function verificarFormulario() {
        var haySeleccionados = $('.producto-check:checked').length > 0;
        $('#submit-devolucion').prop('disabled', !haySeleccionados)
            .css('opacity', haySeleccionados ? '1' : '0.5');
    }
    
    // Validar al enviar
    $('#devolucionForm').on('submit', function(e) {
        var productosValidos = 0;
        $('.cantidad-input:not(:disabled)').each(function() {
            if (parseInt($(this).val()) > 0) {
                productosValidos++;
            }
        });
        
        if (productosValidos === 0) {
            e.preventDefault();
            alert('Debes seleccionar al menos un producto con cantidad válida');
            return false;
        }
        
        return confirm('¿Confirmas que deseas devolver los productos seleccionados?');
    });
});
</script>