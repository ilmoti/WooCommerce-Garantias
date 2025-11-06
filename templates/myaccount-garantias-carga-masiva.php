<?php
// Verificar acceso
if (!defined('ABSPATH')) exit;

// Verificar que sea distribuidor
$user = wp_get_current_user();
$user_roles = $user->roles;
$is_distribuidor = false;

foreach ($user_roles as $role) {
    if (in_array($role, ['distri10', 'distri20', 'distri30', 'superdistri30'])) {
        $is_distribuidor = true;
        break;
    }
}

if (!$is_distribuidor) {
    echo '<div class="woocommerce-error">No tienes permisos para acceder a esta sección.</div>';
    return;
}

// Procesar confirmacin de carga masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_carga_masiva'])) {
    $datos = json_decode(base64_decode($_POST['datos_procesados']), true);
    
    if ($datos && !empty($datos['items_validos'])) {
        // Preparar items para guardar
        $items_guardar = [];
        
        foreach ($datos['items_validos'] as $item) {
            $items_guardar[] = [
                'codigo_item' => 'GRT-ITEM-' . strtoupper(wp_generate_password(8, false, false)),
                'producto_id' => $item['producto_id'],
                'cantidad' => $item['cantidad'],
                'motivo' => $item['motivo'],
                'foto_url' => '', // No hay fotos en carga masiva
                'video_url' => '', // No hay videos en carga masiva
                'order_id' => $item['order_id'],
                'estado' => 'Pendiente',
                'carga_masiva' => true // Marcar como carga masiva
            ];
        }
        
        // Crear el post de garantía
        $garantia_post = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'post_title' => 'Garanta - ' . get_current_user_id() . ' - ' . date('Y-m-d H:i:s'),
            'post_author' => get_current_user_id(),
        ];
        
        $post_id = wp_insert_post($garantia_post);
        
        if ($post_id && !is_wp_error($post_id)) {
            $codigo_unico = 'GRT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
            update_post_meta($post_id, '_codigo_unico', $codigo_unico);
            update_post_meta($post_id, '_cliente', get_current_user_id());
            update_post_meta($post_id, '_fecha', current_time('mysql'));
            update_post_meta($post_id, '_estado', 'nueva');
            update_post_meta($post_id, '_items_reclamados', $items_guardar);
            update_post_meta($post_id, '_es_carga_masiva', true);
            update_post_meta($post_id, '_cantidad_items', count($items_guardar));
            
            // Redirigir con mensaje de éxito
            wp_redirect(add_query_arg([
                'carga_masiva_exitosa' => '1',
                'codigo' => $codigo_unico,
                'items' => count($items_guardar)
            ], wc_get_account_endpoint_url('garantias')));
            exit;
        }
    }
}

// Procesar archivo si se envió
$resultado_procesamiento = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    
    // Validar archivo
    $archivo = $_FILES['archivo_excel'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ['xlsx', 'xls'])) {
        $resultado_procesamiento = [
            'error' => true,
            'mensaje' => 'Formato de archivo no válido. Solo se aceptan archivos Excel (.xlsx, .xls)'
        ];
    } else {
        // Procesar el archivo
        require_once WC_GARANTIAS_PATH . 'includes/class-wc-garantias-carga-masiva.php';
        $procesador = new WC_Garantias_Carga_Masiva();
        $resultado_procesamiento = $procesador->procesar_archivo($archivo, get_current_user_id());
    }
}
?>

<div style="max-width: 1200px; margin: 0 auto;">
    <!-- Header -->
    <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; border-radius: 15px 15px 0 0; margin-bottom: 0;">
        <h2 style="margin: 0; color: white;">
            <i class="fas fa-file-excel"></i> Carga Masiva de Garantías
        </h2>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">
            Procesa múltiples reclamos de garantía de una sola vez
        </p>
    </div>

    <!-- Contenido principal -->
    <div style="background: white; padding: 30px; border-radius: 0 0 15px 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
        
        <!-- Botn volver -->
        <a href="<?php echo wc_get_account_endpoint_url('garantias'); ?>" 
           style="display: inline-block; margin-bottom: 20px; color: #17a2b8; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Volver a garantías
        </a>

        <!-- Instrucciones -->
        <div style="background: #f8f9fa; padding: 25px; border-radius: 10px; margin-bottom: 30px;">
            <h3 style="margin-top: 0; color: #333;">
                <i class="fas fa-info-circle"></i> Instrucciones para el archivo Excel
            </h3>
            
            <p style="color: #666; margin-bottom: 20px;">
                Tu archivo Excel debe contener <strong>3 columnas obligatorias</strong> sin encabezados:
            </p>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background: white; border-radius: 5px; overflow: hidden;">
                <thead>
                    <tr style="background: #17a2b8; color: white;">
                        <th style="padding: 12px; text-align: left; width: 15%;">Columna</th>
                        <th style="padding: 12px; text-align: left; width: 25%;">Campo</th>
                        <th style="padding: 12px; text-align: left; width: 25%;">Ejemplo</th>
                        <th style="padding: 12px; text-align: left;">Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e9ecef;">
                        <td style="padding: 12px; font-weight: bold;">A</td>
                        <td style="padding: 12px;">SKU/Código</td>
                        <td style="padding: 12px; font-family: monospace; background: #f8f9fa;">ABC123</td>
                        <td style="padding: 12px; color: #666;">Codigo exacto del producto</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e9ecef;">
                        <td style="padding: 12px; font-weight: bold;">B</td>
                        <td style="padding: 12px;">Cantidad</td>
                        <td style="padding: 12px; font-family: monospace; background: #f8f9fa;">5</td>
                        <td style="padding: 12px; color: #666;">Numero entero mayor a 0</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold;">C</td>
                        <td style="padding: 12px;">Motivo</td>
                        <td style="padding: 12px; font-family: monospace; background: #f8f9fa;">Producto defectuoso</td>
                        <td style="padding: 12px; color: #666;">Razón del reclamo</td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0 0 10px 0; color: #856404;"><strong> Importante:</strong></p>
                <ul style="margin: 0; padding-left: 20px; color: #856404;">
                    <li>Mximo 150 productos por archivo</li>
                    <li>Formatos aceptados: .xlsx, .xls</li>
                </ul>
            </div>
            <!-- Botón de descarga -->
            <div style="text-align: center;">
                <a href="<?php echo WC_GARANTIAS_URL . 'templates/descargar-plantilla-garantias.php'; ?>"
                   style="display: inline-block; background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">
                    <i class="fas fa-download"></i> Descargar Plantilla
                </a>
                <p style="color: #666; font-size: 12px; margin-top: 5px;">
                    Completa el excell y volve a subirlo para iniciar la carga masiva.
                </p>
            </div>
        </div>
        <!-- Formulario de carga -->
        <div style="background: #f8f9fa; padding: 25px; border-radius: 10px; text-align: center;">
            <h3 style="margin-top: 0; color: #333;">
                <i class="fas fa-upload"></i> Subir Archivo
            </h3>
            
            <form method="post" enctype="multipart/form-data" id="form-carga-masiva">
                <div style="margin: 20px 0;">
                    <input type="file" 
                           name="archivo_excel" 
                           id="archivo_excel"
                           accept=".xlsx,.xls"
                           required
                           style="display: none;">
                    
                    <label for="archivo_excel" 
                           style="display: inline-block; background: #17a2b8; color: white; padding: 12px 30px; border-radius: 5px; cursor: pointer; transition: all 0.3s;">
                        <i class="fas fa-file-excel"></i> Seleccionar Archivo Excel
                    </label>
                    
                    <div id="archivo-seleccionado" style="margin-top: 10px; color: #666;"></div>
                </div>
                
                <button type="submit" 
                        id="btn-procesar"
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 40px; border-radius: 25px; font-size: 16px; font-weight: 600; cursor: pointer; display: none;">
                    <i class="fas fa-cog"></i> Procesar Archivo
                </button>
            </form>
        </div>

        <!-- Área de resultados -->
        <?php if ($resultado_procesamiento): ?>
        <div id="resultados-procesamiento" style="margin-top: 30px;">
            
            <?php if (isset($resultado_procesamiento['error']) && $resultado_procesamiento['error']): ?>
                <!-- Error general -->
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 10px;">
                    <h4 style="margin-top: 0;">
                        <i class="fas fa-exclamation-circle"></i> Error al procesar el archivo
                    </h4>
                    <p style="margin-bottom: 0;"><?php echo esc_html($resultado_procesamiento['mensaje']); ?></p>
                </div>
                
            <?php else: ?>
                <!-- Resultados exitosos -->
                <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">
                        <i class="fas fa-check-circle"></i> Archivo procesado correctamente
                    </h4>
                    
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 15px;">
                        <div style="text-align: center;">
                            <div style="font-size: 32px; font-weight: bold;">
                                <?php echo $resultado_procesamiento['total_filas']; ?>
                            </div>
                            <div style="color: #666;">Total filas</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 32px; font-weight: bold; color: #28a745;">
                                <?php echo $resultado_procesamiento['validas']; ?>
                            </div>
                            <div style="color: #666;">Vlidas</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 32px; font-weight: bold; color: #dc3545;">
                                <?php echo $resultado_procesamiento['rechazadas']; ?>
                            </div>
                            <div style="color: #666;">Rechazadas</div>
                        </div>
                    </div>
                </div>
                
                <!-- Detalles de items -->
                <?php if (!empty($resultado_procesamiento['items'])): ?>
                <div style="background: white; border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden;">
                    <div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #dee2e6;">
                        <h4 style="margin: 0;">Detalle de procesamiento</h4>
                    </div>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; position: sticky; top: 0;">
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Fila</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">SKU</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Producto</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #dee2e6;">Cantidad</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Estado</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #dee2e6;">Mensaje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultado_procesamiento['items'] as $index => $item): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <td style="padding: 10px;"><?php echo $index + 1; ?></td>
                                    <td style="padding: 10px; font-family: monospace;"><?php echo esc_html($item['sku']); ?></td>
                                    <td style="padding: 10px;"><?php echo esc_html($item['nombre_producto'] ?? '-'); ?></td>
                                    <td style="padding: 10px; text-align: center;"><?php echo esc_html($item['cantidad']); ?></td>
                                    <td style="padding: 10px;">
                                        <?php if ($item['valido']): ?>
                                            <span style="color: #28a745;"><i class="fas fa-check-circle"></i> Vlido</span>
                                        <?php else: ?>
                                            <span style="color: #dc3545;"><i class="fas fa-times-circle"></i> Rechazado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; color: #666; font-size: 14px;">
                                        <?php echo esc_html($item['mensaje'] ?? 'OK'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <?php if ($resultado_procesamiento['validas'] > 0): ?>
                <div style="text-align: center; margin-top: 30px;">
                    <form method="post" style="display: inline-block;">
                        <input type="hidden" name="confirmar_carga_masiva" value="1">
                        <input type="hidden" name="datos_procesados" value="<?php echo esc_attr(base64_encode(json_encode($resultado_procesamiento))); ?>">
                        
                        <button type="submit" 
                                style="background: #28a745; color: white; border: none; padding: 15px 40px; border-radius: 25px; font-size: 16px; font-weight: 600; cursor: pointer; margin-right: 10px;">
                            <i class="fas fa-check"></i> Confirmar y Crear Reclamo (<?php echo $resultado_procesamiento['validas']; ?> items)
                        </button>
                    </form>
                    
                    <a href="<?php echo wc_get_account_endpoint_url('garantias'); ?>?carga_masiva=1" 
                       style="display: inline-block; background: #6c757d; color: white; padding: 15px 40px; border-radius: 25px; font-size: 16px; font-weight: 600; text-decoration: none;">
                        <i class="fas fa-redo"></i> Cargar Otro Archivo
                    </a>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Mostrar nombre del archivo seleccionado
    $('#archivo_excel').on('change', function() {
        var fileName = this.files[0] ? this.files[0].name : '';
        if (fileName) {
            $('#archivo-seleccionado').html('<i class="fas fa-check-circle" style="color: #28a745;"></i> Archivo seleccionado: <strong>' + fileName + '</strong>');
            $('#btn-procesar').slideDown();
        } else {
            $('#archivo-seleccionado').html('');
            $('#btn-procesar').slideUp();
        }
    });
    
    // Procesar formulario
    $('#form-carga-masiva').on('submit', function(e) {
        $('#btn-procesar').html('<i class="fas fa-spinner fa-spin"></i> Procesando...').prop('disabled', true);
    });
});
</script>