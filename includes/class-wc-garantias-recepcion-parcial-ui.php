<?php
/**
 * Sistema de Recepción Parcial - Interfaz de Usuario
 * Maneja los modales, formularios y elementos visuales
 * 
 * @package WC_Garantias
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_Garantias_Recepcion_Parcial_UI {
    
    /**
     * Renderizar modal de recepción parcial
     */
    public static function render_modal_recepcion($garantia_id) {
        ?>
        <!-- Modal de Recepción Parcial -->
        <div id="modal-recepcion-parcial" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 10px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0;">
                    <h3 style="margin: 0; color: white; display: flex; align-items: center;">
                        <i class="fas fa-box-open" style="margin-right: 10px;"></i>
                        Recepción de Items
                    </h3>
                    <button type="button" onclick="cerrarModalRecepcion()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
                </div>
                
                <!-- Body -->
                <div style="padding: 25px;">
                    <form id="form-recepcion-parcial">
                        <input type="hidden" id="rp-garantia-id" value="<?php echo $garantia_id; ?>">
                        <input type="hidden" id="rp-codigo-item" value="">
                        <input type="hidden" id="rp-cantidad-esperada" value="">
                        
                        <!-- Información del item -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <label style="font-size: 12px; color: #6c757d;">Código Item:</label>
                                    <div id="rp-info-codigo" style="font-weight: bold; color: #333;">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 12px; color: #6c757d;">Producto:</label>
                                    <div id="rp-info-producto" style="font-weight: bold; color: #333;">-</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cantidad esperada -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #495057;">
                                <i class="fas fa-cube"></i> Cantidad esperada:
                            </label>
                            <div style="font-size: 24px; font-weight: bold; color: #667eea;">
                                <span id="rp-cantidad-esperada-texto">0</span> unidades
                            </div>
                        </div>
                        
                        <!-- Input cantidad recibida -->
                        <div style="margin-bottom: 20px;">
                            <label for="rp-cantidad-recibida" style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">
                                <i class="fas fa-check-circle"></i> ¿Cuántas unidades recibiste?
                            </label>
                            <input type="number" 
                                   id="rp-cantidad-recibida" 
                                   min="0" 
                                   step="1"
                                   style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 18px; font-weight: bold; text-align: center;"
                                   onchange="actualizarPreviewRecepcion()"
                                   oninput="actualizarPreviewRecepcion()">
                        </div>
                        
                        <!-- Preview del resultado -->
                        <div id="rp-preview" style="display: none; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <h5 style="margin: 0 0 10px 0; color: #0066cc;">
                                <i class="fas fa-info-circle"></i> Resultado de la división:
                            </h5>
                            <div id="rp-preview-contenido"></div>
                        </div>
                        
                        <!-- Alerta de exceso -->
                        <div id="rp-alerta-exceso" style="display: none; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                            <h5 style="margin: 0 0 10px 0; color: #856404;">
                                <i class="fas fa-exclamation-triangle"></i> Exceso de unidades
                            </h5>
                            <p style="margin: 0; color: #856404;">
                                Se recibieron más unidades de las esperadas. El exceso será marcado para devolución al cliente.
                            </p>
                        </div>
                        
                        <!-- Opciones adicionales -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="rp-notificar-cliente" checked style="margin-right: 10px;">
                                <span>Notificar al cliente por email</span>
                            </label>
                        </div>
                        
                        <!-- Botones -->
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button" onclick="cerrarModalRecepcion()" 
                                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Cancelar
                            </button>
                            <button type="submit" 
                                    style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                                <i class="fas fa-check"></i> Confirmar Recepción
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        // Funciones del modal de recepción parcial
        function abrirModalRecepcion(codigoItem, cantidadEsperada, nombreProducto) {
            // Limpiar y preparar el modal
            document.getElementById('rp-codigo-item').value = codigoItem;
            document.getElementById('rp-cantidad-esperada').value = cantidadEsperada;
            document.getElementById('rp-info-codigo').textContent = codigoItem;
            document.getElementById('rp-info-producto').textContent = nombreProducto;
            document.getElementById('rp-cantidad-esperada-texto').textContent = cantidadEsperada;
            document.getElementById('rp-cantidad-recibida').value = cantidadEsperada;
            // Quitar límite máximo para permitir cualquier exceso
            document.getElementById('rp-cantidad-recibida').removeAttribute('max');
            
            // Mostrar preview inicial
            actualizarPreviewRecepcion();
            
            // Mostrar modal
            document.getElementById('modal-recepcion-parcial').style.display = 'block';
        }
        
        function cerrarModalRecepcion() {
            document.getElementById('modal-recepcion-parcial').style.display = 'none';
            document.getElementById('form-recepcion-parcial').reset();
            document.getElementById('rp-preview').style.display = 'none';
            document.getElementById('rp-alerta-exceso').style.display = 'none';
        }
        
        function actualizarPreviewRecepcion() {
            const esperada = parseInt(document.getElementById('rp-cantidad-esperada').value) || 0;
            const recibida = parseInt(document.getElementById('rp-cantidad-recibida').value) || 0;
            const preview = document.getElementById('rp-preview');
            const previewContenido = document.getElementById('rp-preview-contenido');
            const alertaExceso = document.getElementById('rp-alerta-exceso');
            
            if (recibida === 0) {
                preview.style.display = 'none';
                alertaExceso.style.display = 'none';
                return;
            }
            
            preview.style.display = 'block';
            
            if (recibida === esperada) {
                // Recepción completa
                previewContenido.innerHTML = `
                    <div style="color: #28a745;">
                        <i class="fas fa-check-circle"></i> <strong>Recepción completa</strong><br>
                        Se marcarán ${recibida} unidades como recibidas.
                    </div>
                `;
                alertaExceso.style.display = 'none';
                
            } else if (recibida < esperada) {
                // Recepción parcial
                const faltante = esperada - recibida;
                previewContenido.innerHTML = `
                    <div style="margin-bottom: 10px;">
                        <strong>Se crearán 2 items:</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li style="color: #28a745; margin-bottom: 5px;">
                            <strong>${recibida} unidades</strong> → Estado: <span class="badge bg-success">Recibido</span>
                            <br><small>Se procesarán inmediatamente</small>
                        </li>
                        <li style="color: #ff9800;">
                            <strong>${faltante} unidades</strong> → Estado: <span class="badge bg-warning">Esperando Recepción</span>
                            <br><small>7 días de plazo para recibir o serán rechazadas</small>
                        </li>
                    </ul>
                `;
                alertaExceso.style.display = 'none';
                
            } else {
                // Exceso de unidades
                const exceso = recibida - esperada;
                previewContenido.innerHTML = `
                    <div style="margin-bottom: 10px;">
                        <strong>División por exceso:</strong>
                    </div>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li style="color: #28a745; margin-bottom: 5px;">
                            <strong>${esperada} unidades</strong> → Estado: <span class="badge bg-success">Recibido</span>
                            <br><small>Cantidad solicitada - Se procesarán</small>
                        </li>
                        <li style="color: #dc3545;">
                            <strong>${exceso} unidades</strong> → Estado: <span class="badge bg-danger">Retorno Cliente</span>
                            <br><small>Exceso - Se devolverán al cliente</small>
                        </li>
                    </ul>
                `;
                alertaExceso.style.display = 'block';
            }
        }
        
        // Manejar submit del formulario
        document.getElementById('form-recepcion-parcial').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const garantiaId = document.getElementById('rp-garantia-id').value;
            const codigoItem = document.getElementById('rp-codigo-item').value;
            const cantidadEsperada = parseInt(document.getElementById('rp-cantidad-esperada').value);
            const cantidadRecibida = parseInt(document.getElementById('rp-cantidad-recibida').value);
            const notificarCliente = document.getElementById('rp-notificar-cliente').checked;
            
            if (cantidadRecibida === 0) {
                alert('Por favor ingresa la cantidad recibida');
                return false;
            }
            
            // Confirmación para casos especiales
            if (cantidadRecibida < cantidadEsperada) {
                if (!confirm(`¿Confirmas que solo recibiste ${cantidadRecibida} de ${cantidadEsperada} unidades?\n\nSe creará un item pendiente para las ${cantidadEsperada - cantidadRecibida} unidades faltantes.`)) {
                    return false;
                }
            } else if (cantidadRecibida > cantidadEsperada) {
                if (!confirm(`¿Confirmas que recibiste ${cantidadRecibida} unidades (${cantidadRecibida - cantidadEsperada} más de lo esperado)?\n\nEl exceso será marcado para devolución al cliente.`)) {
                    return false;
                }
            }
            
            // Deshabilitar botón
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            
            // Enviar por AJAX
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'procesar_recepcion_parcial',
                    garantia_id: garantiaId,
                    codigo_item: codigoItem,
                    cantidad_esperada: cantidadEsperada,
                    cantidad_recibida: cantidadRecibida,
                    notificar_cliente: notificarCliente,
                    nonce: '<?php echo wp_create_nonce("procesar_recepcion_parcial"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirmar Recepción';
                    }
                },
                error: function() {
                    alert('❌ Error de conexión');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirmar Recepción';
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar botones de acción para items esperando recepción
     */
    public static function render_botones_esperando($garantia_id, $item) {
        $fecha_limite = strtotime($item['auto_rechazo']['fecha_limite'] ?? '+7 days');
        $ahora = current_time('timestamp');
        $dias_restantes = ceil(($fecha_limite - $ahora) / 86400);
        
        // Color del contador según días restantes
        $color_contador = '#28a745'; // Verde
        if ($dias_restantes <= 2) {
            $color_contador = '#dc3545'; // Rojo
        } elseif ($dias_restantes <= 4) {
            $color_contador = '#ff9800'; // Naranja
        }
        
                $extensiones = $item['auto_rechazo']['extensiones'] ?? 0;
        ?>
        
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <!-- Contador de dias -->
            <div style="background: <?php echo $color_contador; ?>; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold;">
                <i class="fas fa-clock"></i> <?php echo $dias_restantes; ?> dias
            </div>
            
            <!-- Botón extender plazo -->
            <?php if ($extensiones < 2): ?>
                <button type="button" 
                        onclick="extenderPlazo('<?php echo $garantia_id; ?>', '<?php echo esc_attr($item['codigo_item']); ?>')"
                        class="btn btn-sm btn-warning" 
                        title="Extender plazo 7 días ms (<?php echo 2 - $extensiones; ?> extensiones disponibles)"
                        style="padding: 4px 8px; font-size: 11px;">
                    <i class="fas fa-plus"></i> +7 días
                </button>
            <?php else: ?>
                <span class="badge bg-secondary" style="font-size: 11px;">
                    Máx. extensiones
                </span>
            <?php endif; ?>
            
            <!-- Botón rechazar manual -->
            <button type="button" 
                    onclick="rechazarManual('<?php echo $garantia_id; ?>', '<?php echo esc_attr($item['codigo_item']); ?>')"
                    class="btn btn-sm btn-danger" 
                    title="Rechazar manualmente"
                    style="padding: 4px 8px; font-size: 11px;">
                <i class="fas fa-times"></i> Rechazar
            </button>
        </div>
        
        <!-- Información adicional -->
        <?php if ($extensiones > 0): ?>
            <div style="margin-top: 5px; font-size: 11px; color: #6c757d;">
                <i class="fas fa-info-circle"></i> Plazo extendido <?php echo $extensiones; ?> vez/veces
            </div>
        <?php endif; ?>
        
        <script>
        function extenderPlazo(garantiaId, codigoItem) {
            if (!confirm('¿Extender el plazo 7 días más?')) return;
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'extender_plazo_item',
                    garantia_id: garantiaId,
                    codigo_item: codigoItem,
                    dias: 7,
                    nonce: '<?php echo wp_create_nonce("extender_plazo"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data);
                    }
                },
                error: function() {
                    alert(' Error de conexión');
                }
            });
        }
        
        function rechazarManual(garantiaId, codigoItem) {
            const motivo = prompt('Motivo del rechazo (opcional):');
            if (motivo === null) return;
            
            if (!confirm('¿Rechazar definitivamente este item?\n\nEl cliente NO podrá apelar esta decisión.')) return;
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rechazar_manual_item',
                    garantia_id: garantiaId,
                    codigo_item: codigoItem,
                    motivo: motivo || 'Rechazado manualmente por el administrador',
                    nonce: '<?php echo wp_create_nonce("rechazar_manual"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ Item rechazado correctamente');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + response.data);
                    }
                },
                error: function() {
                    alert(' Error de conexión');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Renderizar informacin de recepción parcial en el detalle del item
     */
    public static function render_info_recepcion_parcial($item) {
        if (!isset($item['recepcion_parcial'])) {
            return;
        }
        
        $info = $item['recepcion_parcial'];
        ?>
        <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 3px solid #2196f3;">
            <h6 style="margin: 0 0 8px 0; color: #1976d2; font-size: 13px;">
                <i class="fas fa-info-circle"></i> Información de Recepción Parcial
            </h6>
            <div style="font-size: 12px; color: #333;">
                <div style="margin-bottom: 4px;">
                    <strong>Fecha recepcin:</strong> 
                    <?php echo date('d/m/Y H:i', strtotime($info['fecha_recepcion'])); ?>
                </div>
                <div style="margin-bottom: 4px;">
                    <strong>Cantidad recibida:</strong> 
                    <span style="color: #28a745; font-weight: bold;">
                        <?php echo $info['cantidad_recibida']; ?> unidades
                    </span>
                </div>
                <div style="margin-bottom: 4px;">
                    <strong>Cantidad faltante:</strong> 
                    <span style="color: #ff9800; font-weight: bold;">
                        <?php echo $info['cantidad_faltante']; ?> unidades
                    </span>
                </div>
                <?php if (isset($info['item_hijo'])): ?>
                    <div>
                        <strong>Item pendiente:</strong> 
                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                            <?php echo $info['item_hijo']; ?>
                        </code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar badge de estado para items con recepción parcial
     */
    public static function render_badge_parcial($item) {
        // Para item padre (parcialmente recibido)
        if (isset($item['recepcion_parcial'])) {
            ?>
            <span class="badge bg-info" style="font-size: 10px; margin-left: 5px;" 
                  title="Este item fue recibido parcialmente">
                PARCIAL
            </span>
            <?php
        }
        
        // Para item hijo (esperando)
        if (isset($item['es_division']) && $item['es_division']) {
            ?>
            <span class="badge bg-warning" style="font-size: 10px; margin-left: 5px;" 
                  title="Item creado por recepción parcial">
                PENDIENTE
            </span>
            <?php
        }
        
        // Para item exceso
        if (isset($item['es_exceso']) && $item['es_exceso']) {
            ?>
            <span class="badge bg-danger" style="font-size: 10px; margin-left: 5px;" 
                  title="Exceso de unidades a devolver">
                EXCESO
            </span>
            <?php
        }
    }
    
    /**
     * Renderizar resumen de recepción parcial en la vista general
     */
    public static function render_resumen_recepcion_parcial($garantia_id) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) return;
        
        $items_esperando = 0;
        $items_parciales = 0;
        $total_pendiente = 0;
        
        foreach ($items as $item) {
            if ($item['estado'] === 'esperando_recepcion') {
                $items_esperando++;
                $total_pendiente += $item['cantidad'];
            }
            if (isset($item['recepcion_parcial'])) {
                $items_parciales++;
            }
        }
        
        if ($items_esperando > 0 || $items_parciales > 0) {
            ?>
            <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
                        border-radius: 10px; 
                        padding: 20px; 
                        margin: 20px 0; 
                        border-left: 4px solid #ff9800;">
                <h4 style="margin: 0 0 15px 0; color: #856404;">
                    <i class="fas fa-exclamation-triangle"></i> Estado de Recepción
                </h4>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                    <?php if ($items_parciales > 0): ?>
                        <div style="background: white; padding: 12px; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #17a2b8;">
                                <?php echo $items_parciales; ?>
                            </div>
                            <div style="font-size: 12px; color: #6c757d;">
                                Items recibidos parcialmente
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($items_esperando > 0): ?>
                        <div style="background: white; padding: 12px; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #ff9800;">
                                <?php echo $items_esperando; ?>
                            </div>
                            <div style="font-size: 12px; color: #6c757d;">
                                Items esperando recepción
                            </div>
                        </div>
                        
                        <div style="background: white; padding: 12px; border-radius: 8px;">
                            <div style="font-size: 24px; font-weight: bold; color: #dc3545;">
                                <?php echo $total_pendiente; ?>
                            </div>
                            <div style="font-size: 12px; color: #6c757d;">
                                Unidades pendientes total
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($items_esperando > 0): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1);">
                        <p style="margin: 0; font-size: 14px; color: #856404;">
                            <i class="fas fa-info-circle"></i> 
                            Los items pendientes sern rechazados automáticamente si no se reciben en el plazo establecido.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}