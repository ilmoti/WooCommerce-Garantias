<?php
/**
 * Formulario de garantías con pestañas
 * 
 * Este archivo maneja el formulario de reclamos con múltiples pestañas/productos
 * Se incluye desde myaccount-garantias.php
 */

if (!defined('ABSPATH')) exit;

// Recibir variables del archivo principal
global $productos_js, $motivos, $is_cliente_final, $is_distribuidor;

?>

<div id="garantiaFormContainer" style="display: none; margin-top: 30px;">
    <div style="
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border: 1px solid #e9ecef;
    ">
        <div style="text-align: center; margin-bottom: 25px;">
            <div style="
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
                font-size: 24px;
                color: white;
            ">🛡️</div>
            <h3 style="margin: 0; color: #2c3e50; font-weight: 600;">Nuevo Reclamo de Garantía</h3>
            <p style="margin: 5px 0 0 0; color: #6c757d;">Completa la información para procesar tu solicitud</p>
        </div>

        <form id="garantiaForm" method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="garantia_form_submit" value="1">
            
            <!-- TABS NAVIGATION -->
            <div class="tabs-navigation" style="
                display: flex;
                border-bottom: 2px solid #e9ecef;
                margin-bottom: 30px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            ">
                <!-- Los botones de pestañas se agregarán dinámicamente aquí -->
            </div>
            
            <!-- TABS CONTENT -->
            <div id="productos-container">
                <!-- Aquí se insertarán las pestañas dinámicamente -->
            </div>

            <!-- Mensaje de validación -->
            <div id="mensaje-validacion" style="display: none; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <strong>Atención!</strong> Debes seleccionar al menos un producto antes de enviar el reclamo.
            </div>

            <!-- Consejos -->
            <div style="
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border-radius: 10px;
                padding: 15px;
                margin: 20px 0;
                border-left: 4px solid #ffc107;
            ">
                <div style="display: flex; align-items: center;">
                    <div style="font-size: 20px; margin-right: 10px;">💡</div>
                    <div>
                        <div style="color: #856404; font-size: 14px;">
                            <?php echo wp_kses_post(get_option('garantia_consejos_texto', '<strong>Consejo:</strong> Asegúrate de que la foto o video muestre claramente el problema. Esto acelera el proceso.')); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div style="display: flex; justify-content: center; gap: 20px; padding-top: 20px;">
                <button type="button" id="add-producto-btn" class="btn-agregar-producto">
                    ➕ Agregar Producto
                </button>
            
                <button type="submit" class="btn-enviar-reclamo">
                    ✉️ Enviar Reclamo
                </button>
                
                <?php if ($is_distribuidor): ?>
                <button type="button" id="btn-carga-masiva" class="btn-carga-masiva"
                        onclick="window.location.href='<?php echo add_query_arg('carga_masiva', '1', wc_get_account_endpoint_url('garantias')); ?>'">
                    📊 Carga Masiva
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Template para nueva pestaña (oculto) -->
<script type="text/template" id="template-producto-card">
    <div class="producto-card tab-content" data-tab="{{TAB_NUMBER}}">
        <div style="display: flex; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0; color: #2c3e50;">Información del producto</h4>
            <div style="flex-grow: 1;"></div>
            <button type="button" class="remove-producto" title="Eliminar producto">✕</button>
        </div>

        <!-- Campo de producto -->
        <div style="margin-bottom: 20px;">
            <label class="form-label">🔍 Buscar producto</label>
            <div style="position: relative;">
                <input type="text" 
                       class="producto_autocomplete form-control" 
                       placeholder="Escribe el nombre del producto...">
                <input type="hidden" class="producto_hidden" name="producto[]" value="">
            </div>
        </div>

        <!-- Campos en línea -->
        <div class="form-grid">
            <!-- Cantidad -->
            <div class="form-group-small">
                <label class="form-label"> Cantidad</label>
                <input type="number" 
                       class="cantidad cantidad-input form-control" 
                       name="cantidad[]" 
                       min="1" 
                       max="999"
                       value="1" 
                       required>
            </div>
            
            <!-- Motivo -->
            <div class="form-group">
                <label class="form-label">❓ Motivo del reclamo</label>
                <select class="motivo_select form-control" name="motivo[]" required>
                    <option value="">Seleccione un motivo...</option>
                    <?php foreach ($motivos as $m): ?>
                        <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- N° Orden -->
            <div class="form-group-small">
                <label class="form-label">🧾 N° Orden</label>
                <input type="text" 
                       class="order-number form-control" 
                       name="order_number[]" 
                       placeholder="Ej: 12345"
                       readonly>
            </div>
            
            <!-- Fecha -->
            <div class="form-group-small">
                <label class="form-label">📅 Fecha</label>
                <input type="text" 
                       class="order-date form-control" 
                       name="order_date[]" 
                       placeholder="dd/mm/aaaa"
                       readonly>
            </div>
        </div>

        <!-- Campo otro motivo (oculto) -->
        <div style="margin-bottom: 20px;">
            <input type="text" 
                   class="motivo_otro form-control" 
                   name="motivo_otro[]" 
                   placeholder="Especifica el motivo..."
                   style="display: none;">
        </div>

        <!-- Archivos -->
        <div class="form-grid-files">
            <!-- Foto -->
            <div>
                <label class="form-label">
                    📷 Foto <span style="color: #6c757d;">(opcional)</span>
                </label>
                <div class="file-upload-modern">
                    <div class="file-icon">📷</div>
                    <div class="file-status">Arrastra una imagen o haz clic</div>
                    <input type="file" name="foto[]" accept="image/*" class="file-input">
                    <small>JPG, PNG (máx. 5MB)</small>
                </div>
            </div>
            
            <!-- Video -->
            <div>
                <label class="form-label">
                    🎥 Video <?php echo $is_cliente_final ? '<span style="color: #dc3545;">*</span>' : '<span style="color: #6c757d;">(opcional)</span>'; ?>
                </label>
                <div class="file-upload-modern">
                    <div class="file-icon">🎥</div>
                    <div class="file-status">Arrastra un video o haz clic</div>
                    <input type="file" 
                           name="video[]" 
                           accept="video/*"
                           <?php echo $is_cliente_final ? 'required' : ''; ?>
                           class="file-input">
                    <small>MP4, MOV (máx. 50MB)</small>
                </div>
            </div>
        </div>
    </div>
</script>

<!-- JavaScript del formulario -->
<script>
// Configuración global
window.garantiasFormConfig = {
    productos: <?php echo json_encode($productos_js); ?>,
    motivos: <?php echo json_encode($motivos); ?>,
    isClienteFinal: <?php echo json_encode($is_cliente_final); ?>,
    ajaxUrl: '<?php echo admin_url("admin-ajax.php"); ?>',
    nonce: '<?php echo wp_create_nonce("wcgarantias_nonce"); ?>'
};

// ELIMINA ESTO si existe:
// jQuery(document).ready(function() {
//     if (typeof initGarantiasForm === 'function') {
//         initGarantiasForm();
//     }
// });
</script>

<!-- Estilos -->
<style>
/* Estilos base del formulario */
.form-label {
    display: block;
    margin-bottom: 8px;
    color: #495057;
    font-weight: 500;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    border-color: #667eea;
    outline: none;
}

.form-grid {
    display: grid;
    grid-template-columns: 85px 1fr 150px 140px;
    gap: 20px;
    margin-bottom: 20px;
}

.form-grid-files {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group-small {
    min-width: 0;
}

/* Pestañas */
.tab-button {
    background: none;
    border: none;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 600;
    color: #6c757d;
    cursor: pointer;
    position: relative;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tab-button.active {
    color: #667eea;
}

.tab-number {
    background: #e9ecef;
    color: #6c757d;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    margin-right: 8px;
}

.tab-button.active .tab-number {
    background: #667eea;
    color: white;
}

.tab-indicator {
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 3px;
    background: #667eea;
    border-radius: 3px 3px 0 0;
    display: none;
}

.tab-button.active .tab-indicator {
    display: block;
}

/* Tarjetas de producto */
.producto-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 2px solid #f1f3f4;
    transition: all 0.3s ease;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Botón eliminar */
.remove-producto {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.3s ease;
}

.remove-producto:hover {
    background: #c82333;
    transform: scale(1.1);
}

/* Upload de archivos */
.file-upload-modern {
    border: 2px dashed #e9ecef;
    border-radius: 8px;
    padding: 15px 20px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    background: #f8f9fa;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}

.file-upload-modern:hover {
    border-color: #667eea;
    background: #f0f4ff;
}

.file-icon {
    font-size: 20px;
    margin-bottom: 4px;
}

.file-status {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 4px;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    top: 0;
    left: 0;
}

/* Botones principales */
.btn-agregar-producto,
.btn-enviar-reclamo,
.btn-carga-masiva {
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-agregar-producto {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.btn-enviar-reclamo {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    min-width: 200px;
}

.btn-carga-masiva {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.4);
}

.btn-agregar-producto:hover,
.btn-enviar-reclamo:hover,
.btn-carga-masiva:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid-files {
        grid-template-columns: 1fr;
    }
    
    .tabs-navigation {
        overflow-x: auto;
    }
}
</style>