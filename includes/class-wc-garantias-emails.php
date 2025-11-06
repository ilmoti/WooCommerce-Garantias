<?php
if (!defined('ABSPATH')) exit;

class WC_Garantias_Emails {
    
    public static function init() {
    add_action('admin_menu', [__CLASS__, 'add_email_settings_page'], 99); // <-- Prioridad 99
    add_action('admin_init', [__CLASS__, 'register_email_settings']);
}

public static function add_email_settings_page() {
    add_submenu_page(
        'wc-garantias-dashboard',
        'Configuración de Emails',
        '📧 Emails',
        'manage_woocommerce',
        'wc-garantias-emails',
        [__CLASS__, 'emails_settings_page']
    );
}
    
    public static function register_email_settings() {
    // Emails al Cliente
    register_setting('wc_garantias_emails_cliente', 'garantia_email_confirmacion_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_confirmacion_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_aprobada_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_aprobada_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rechazada_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rechazada_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_info_solicitada_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_info_solicitada_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_etiqueta_disponible_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_etiqueta_disponible_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_destruccion_aprobada_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_destruccion_aprobada_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_destruccion_rechazada_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_destruccion_rechazada_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_producto_recibido_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_producto_recibido_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_etiqueta_devolucion_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_etiqueta_devolucion_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_aceptada_analisis_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_aceptada_analisis_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_aprobada_devolucion_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_aprobada_devolucion_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_recordatorio_info_24h_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_recordatorio_info_24h_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_devolucion_confirmada_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_devolucion_confirmada_cuerpo');
    // NUEVO: Emails para RMA
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rma_confirmado_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rma_confirmado_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rma_vencimiento_30_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rma_vencimiento_30_cuerpo');
    
    // NUEVO: Emails para Recepción Parcial
    register_setting('wc_garantias_emails_cliente', 'garantia_email_recepcion_parcial_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_recepcion_parcial_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_recordatorio_recepcion_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_recordatorio_recepcion_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rechazo_no_recibido_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rechazo_no_recibido_cuerpo');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rechazo_manual_parcial_asunto');
    register_setting('wc_garantias_emails_cliente', 'garantia_email_rechazo_manual_parcial_cuerpo');
    
    // Emails al Admin
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_nuevo_reclamo_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_nuevo_reclamo_cuerpo');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_apelacion_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_apelacion_cuerpo');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_etiqueta_descargada_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_etiqueta_descargada_cuerpo');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_respuesta_cliente_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_respuesta_cliente_cuerpo');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_destruccion_subida_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_destruccion_subida_cuerpo');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_tracking_subido_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_tracking_subido_cuerpo');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_rma_expirado_asunto');
    register_setting('wc_garantias_emails_admin', 'garantia_email_admin_rma_expirado_cuerpo');
}
    
    public static function emails_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'cliente';
        ?>
        <div class="wrap">
            <h1> Configuración de Emails - Garantias</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=wc-garantias-emails&tab=cliente" class="nav-tab <?php echo $active_tab == 'cliente' ? 'nav-tab-active' : ''; ?>">
                    👤 Emails al Cliente
                </a>
                <a href="?page=wc-garantias-emails&tab=admin" class="nav-tab <?php echo $active_tab == 'admin' ? 'nav-tab-active' : ''; ?>">
                    ‍💼 Emails al Administrador
                </a>
                <a href="?page=wc-garantias-emails&tab=variables" class="nav-tab <?php echo $active_tab == 'variables' ? 'nav-tab-active' : ''; ?>">
                     Variables Disponibles
                </a>
            </nav>
            
            <div class="tab-content">
                <?php if ($active_tab == 'cliente'): ?>
                    <?php self::render_cliente_emails(); ?>
                <?php elseif ($active_tab == 'admin'): ?>
                    <?php self::render_admin_emails(); ?>
                <?php elseif ($active_tab == 'variables'): ?>
                    <?php self::render_variables_help(); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .email-template {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .email-template h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        .form-table th {
            width: 150px;
        }
        .large-text {
            width: 100%;
        }
        textarea.large-text {
            height: 150px;
        }
        .variable-tag {
            background: #0073aa;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
        }
        </style>
        <?php
    }
    
    public static function render_cliente_emails() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('wc_garantias_emails_cliente'); ?>
            
            <!-- Email Confirmación -->
            <div class="email-template">
                <h3>✅ Confirmación de Reclamo Recibido</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_confirmacion_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_confirmacion_asunto', 'Reclamo de Garantía Recibido - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_confirmacion_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_confirmacion_cuerpo', 
                                    "Hola {cliente},\n\nHemos recibido tu reclamo de garantía con cdigo {codigo}.\n\nLo revisaremos en las prximas 24-48 horas y te notificaremos el resultado.\n\nGracias por tu paciencia."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{fecha}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Aprobada -->
            <div class="email-template">
                <h3> Garantía Aprobada (con Cupn)</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_aprobada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_aprobada_asunto', 'Garantía Aprobada - Cupón de ${importe} disponible')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_aprobada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_aprobada_cuerpo', 
                                    "¡Excelente noticia {cliente}!\n\nTu garantia {codigo} ha sido APROBADA.\n\nTienes un cupón de ${importe} disponible para tu próxima compra.\n\nCdigo del cupón: {cupon}\n\nEl cupn se aplicar automticamente en tu próxima compra.\n\nGracias por confiar en nosotros!"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{importe}</span> <span class="variable-tag">{cupon}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Rechazada -->
            <div class="email-template">
                <h3> Garantía Rechazada</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_rechazada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_rechazada_asunto', 'Garantía {codigo} - Información importante')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_rechazada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_rechazada_cuerpo', 
                                    "Hola {cliente},\n\nHemos revisado tu garantía {codigo} y lamentablemente no podemos proceder con el reclamo.\n\nMotivo: {motivo}\n\nSi no estás de acuerdo con esta decisión, puedes apelar desde tu panel de garantias.\n\nGracias por tu comprensin."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{motivo}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email Destrucción Rechazada -->
            <div class="email-template">
                <h3>❌ Destrucción Rechazada</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_destruccion_rechazada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_destruccion_rechazada_asunto', 'Evidencia de destrucción rechazada - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_destruccion_rechazada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_destruccion_rechazada_cuerpo', 
                                    "Hola {cliente},\n\nLa evidencia de destruccin que subiste NO fue aprobada.\n\nMotivo: {motivo}\n\nPor favor, realiza nuevamente la destruccin siguiendo las instrucciones y sube nueva evidencia.\n\nIngresa aquí: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{motivo}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Producto Recibido -->
            <div class="email-template">
                <h3> Producto Recibido - Cupón Generado</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_producto_recibido_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_producto_recibido_asunto', 'Producto recibido - Cupón generado - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_producto_recibido_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_producto_recibido_cuerpo', 
                                    "Hola {cliente},\n\nHemos recibido el producto devuelto de tu garantia.\n\nTu cupn ha sido generado: {cupon}\nMonto: ${importe}\n\nPuedes usarlo en tu próxima compra.\n\n¡Gracias!"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{cupon}</span> <span class="variable-tag">{importe}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Etiqueta Devolución -->
            <div class="email-template">
                <h3>️ Etiqueta de Devolución Disponible</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_etiqueta_devolucion_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_etiqueta_devolucion_asunto', 'Etiqueta de devolución disponible - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_etiqueta_devolucion_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_etiqueta_devolucion_cuerpo', 
                                    "Hola {cliente},\n\nLa etiqueta para devolver los productos de tu garantia ya est disponible.\n\nDimensiones del paquete: {dimensiones}\n\nDescarga la etiqueta desde tu cuenta e imprímela para pegarla en el paquete.\n\n{link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{dimensiones}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email Información Solicitada -->
            <div class="email-template">
                <h3> Información Adicional Solicitada</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_info_solicitada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_info_solicitada_asunto', 'Información adicional requerida - Garantía {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_info_solicitada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_info_solicitada_cuerpo', 
                                    "Hola {cliente},\n\nNecesitamos informacin adicional sobre algunos items de tu garantía.\n\nMensaje del administrador:\n{mensaje_admin}\n\nPor favor, proporciona:\n{tipo_informacion}\n\nIngresa a tu cuenta para subir la informacin solicitada:\n{link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{mensaje_admin}</span> <span class="variable-tag">{tipo_informacion}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Etiqueta Disponible (Distribuidores) -->
            <div class="email-template">
                <h3> Etiqueta de Envo Disponible</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_etiqueta_disponible_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_etiqueta_disponible_asunto', 'Etiqueta de envo disponible - Garantía {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_etiqueta_disponible_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_etiqueta_disponible_cuerpo', 
                                    "Hola {cliente},\n\nLa etiqueta de envío para tu garantia {codigo} ya está disponible.\n\nPuedes descargarla desde tu cuenta: {link_cuenta}\n\nPor favor, enva el paquete lo antes posible."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Destrucción Aprobada -->
            <div class="email-template">
                <h3> Garantía Aprobada - Destruir Producto</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_destruccion_aprobada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_destruccion_aprobada_asunto', 'Garantía aprobada - Acción requerida - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_destruccion_aprobada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_destruccion_aprobada_cuerpo', 
                                    "Hola {cliente},\n\nTu garantía {codigo} ha sido APROBADA.\n\nACCIÓN REQUERIDA:\n1. Destruye completamente el producto defectuoso\n2. Toma fotos/video claros de la destruccin\n3. Sube la evidencia en tu panel de garantías\n\nUna vez verificada la destrucción, recibirás tu cupn automáticamente.\n\nIngresa aquí para subir la evidencia: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email garantia Aceptada para Anlisis -->
            <div class="email-template">
                <h3> Garantia Aceptada para Análisis</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_aceptada_analisis_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_aceptada_analisis_asunto', 'Tu garantia ha sido aceptada para análisis - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_aceptada_analisis_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_aceptada_analisis_cuerpo', 
                                    "Hola {cliente},\n\nHemos aceptado tu garanta {codigo} y está siendo analizada.\n\nTe notificaremos el resultado en las próximas 24-48 horas.\n\nPuedes ver el estado en: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Aprobada para Devolución -->
            <div class="email-template">
                <h3> Garantia Aprobada - Devolver Producto</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_aprobada_devolucion_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_aprobada_devolucion_asunto', 'Garanta aprobada - Devolucin requerida - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_aprobada_devolucion_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_aprobada_devolucion_cuerpo', 
                                    "Hola {cliente},\n\nTu garantia {codigo} ha sido APROBADA.\n\nACCIÓN REQUERIDA:\n1. Envía el producto defectuoso a nuestra dirección:\n   {direccion_devolucion}\n\n2. Una vez enviado, sube una foto de la guía de envío o número de tracking en tu panel\n\nIngresa aqu para subir la informacin de envío: {link_cuenta}\n\nUna vez confirmemos la recepcin, generaremos tu cupn automticamente."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{direccion_devolucion}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email Recordatorio 24h -->
            <div class="email-template">
                <h3> Recordatorio - Información Pendiente (24h restantes)</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_recordatorio_info_24h_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_recordatorio_info_24h_asunto', '⏰ URGENTE: Solo te quedan {horas_restantes} horas - Garantía {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_recordatorio_info_24h_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_recordatorio_info_24h_cuerpo', 
                                    "Hola {cliente},\n\n RECORDATORIO IMPORTANTE\n\nSolo te quedan {horas_restantes} horas hábiles para responder a nuestra solicitud de informacin.\n\nGaranta: {codigo}\nItem: {item_codigo}\n\nMensaje original:\n{mensaje_original}\n\nSi no recibimos la informacin antes del plazo, el item ser rechazado automticamente.\n\nResponde ahora: {link_cuenta}\n\n¡No pierdas tu garantía!"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{item_codigo}</span> <span class="variable-tag">{horas_restantes}</span> <span class="variable-tag">{mensaje_original}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
                <!-- Email Devolucin Confirmada -->
            <div class="email-template">
                <h3>↩ Devolucin por Error de Compra Confirmada</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_devolucion_confirmada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_devolucion_confirmada_asunto', 'Devolución confirmada - {codigo} - Descarga tu etiqueta')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_devolucion_confirmada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_devolucion_confirmada_cuerpo', 
                                    "Hola {cliente},\n\nHemos recibido tu solicitud de devolución por error de compra.\n\nCódigo: {codigo}\nFecha: {fecha}\n\n📋 PRXIMOS PASOS:\n1. Descarga e imprime tu etiqueta: {link_etiqueta}\n2. Pega la etiqueta en el paquete\n3. Enva el paquete (costo a tu cargo)\n4. Sube el número de seguimiento en tu cuenta\n\nIMPORTANTE: El producto debe estar en perfectas condiciones con todos sus accesorios y embalaje original.\n\nUna vez recibamos y verifiquemos el producto, generaremos tu cupón.\n\nVer estado: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{fecha}</span> <span class="variable-tag">{link_etiqueta}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email RMA Confirmado -->
            <div class="email-template">
                <h3>🔄 Cupón de Retorno Cliente Generado (RMA)</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_rma_confirmado_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_rma_confirmado_asunto', 'Cupn de retorno generado - {codigo} - Producto: {producto}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_rma_confirmado_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_rma_confirmado_cuerpo', 
                                    "Hola {cliente},\n\nTu reclamo ha sido rechazado definitivamente pero se ha generado un cupn para que recibas el producto de vuelta.\n\nDetalles:\n- Código de garantía: {codigo}\n- Producto a devolver: {producto}\n- Cupón generado: {cupon_rma}\n- Validez: {dias_validez} días\n\nEste cupón se aplicará automticamente en tu prxima compra y agregará el producto a tu pedido sin costo.\n\nIMPORTANTE: Este cupón vence el {fecha_vencimiento}. Te notificaremos 30 días antes del vencimiento.\n\nGracias por tu comprensin."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{producto}</span> <span class="variable-tag">{cupon_rma}</span> <span class="variable-tag">{dias_validez}</span> <span class="variable-tag">{fecha_vencimiento}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Recordatorio Vencimiento RMA -->
            <div class="email-template">
                <h3> Recordatorio - Cupón RMA por vencer (30 días)</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_rma_vencimiento_30_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_rma_vencimiento_30_asunto', '⏰ Tu cupón de retorno vence en 30 das - {producto}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_rma_vencimiento_30_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_rma_vencimiento_30_cuerpo', 
                                    "Hola {cliente},\n\n⏰ RECORDATORIO IMPORTANTE\n\nTu cupón de retorno para el siguiente producto vencerá en 30 das:\n\nProducto: {producto}\nCdigo del cupn: {cupon_rma}\nFecha de vencimiento: {fecha_vencimiento}\n\nEste cupón te permite recibir el producto en tu prxima compra sin costo adicional.\n\nNo pierdas la oportunidad de recuperar tu producto. Realiza tu pedido antes del vencimiento.\n\nIngresa a tu cuenta: {link_cuenta}\n\nSaludos"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{producto}</span> <span class="variable-tag">{cupon_rma}</span> <span class="variable-tag">{fecha_vencimiento}</span> <span class="variable-tag">{link_cuenta}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email Recepción Parcial -->
            <div class="email-template">
                <h3>📦 Recepcin Parcial de Items</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_recepcion_parcial_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_recepcion_parcial_asunto', '️ Recepcin Parcial - Garantía {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_recepcion_parcial_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_recepcion_parcial_cuerpo', 
                                    "Hola {cliente},\n\nHemos recibido tu paquete de la garantía {codigo}, pero solo contiene:\n\n {cantidad_recibida} unidades de {producto} (recibidas y procesadas)\n⏳ {cantidad_pendiente} unidades faltan por recibir\n\nTienes 7 días para enviar las unidades faltantes. Si no las recibimos en este plazo, serán rechazadas automáticamente.\n\nIngresa a tu cuenta para ver el estado: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{producto}</span> <span class="variable-tag">{cantidad_recibida}</span> <span class="variable-tag">{cantidad_pendiente}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Recordatorio Recepcin -->
            <div class="email-template">
                <h3>⏰ Recordatorio - Items Pendientes de Recepción</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_recordatorio_recepcion_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_recordatorio_recepcion_asunto', ' Quedan {dias_restantes} días - Items pendientes {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_recordatorio_recepcion_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_recordatorio_recepcion_cuerpo', 
                                    "Hola {cliente},\n\n⏰ RECORDATORIO IMPORTANTE\n\nQuedan solo {dias_restantes} días para enviar los siguientes items:\n\n• {cantidad} unidades de {producto}\n Código de item: {codigo_item}\n\nFecha límite: {fecha_limite}\n\nSi no recibimos estos items antes del plazo, serán rechazados automáticamente sin posibilidad de apelación.\n\nPuedes cancelar el envío desde tu cuenta si no vas a enviarlos: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{producto}</span> <span class="variable-tag">{cantidad}</span> <span class="variable-tag">{dias_restantes}</span> <span class="variable-tag">{fecha_limite}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Rechazo No Recibido -->
            <div class="email-template">
                <h3> Items Rechazados - No Recibidos</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_rechazo_no_recibido_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_rechazo_no_recibido_asunto', ' Items rechazados por no recepción - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_rechazo_no_recibido_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_rechazo_no_recibido_cuerpo', 
                                    "Hola {cliente},\n\nLos siguientes items fueron rechazados por no ser recibidos en el plazo establecido:\n\n❌ {cantidad} unidades de {producto}\n• Cdigo: {codigo_item}\n• Fecha límite: {fecha_limite}\n\nEstos items ya no pueden ser procesados en esta garantía.\n\nSi aún necesitas hacer el reclamo, puedes crear una nueva garanta desde tu cuenta: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{producto}</span> <span class="variable-tag">{cantidad}</span> <span class="variable-tag">{codigo_item}</span> <span class="variable-tag">{fecha_limite}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Rechazo Manual Parcial -->
            <div class="email-template">
                <h3> Items Pendientes Rechazados Manualmente</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_rechazo_manual_parcial_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_rechazo_manual_parcial_asunto', 'Items pendientes rechazados - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_rechazo_manual_parcial_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_rechazo_manual_parcial_cuerpo', 
                                    "Hola {cliente},\n\nLos items pendientes de recepcin han sido rechazados.\n\nMotivo: {motivo}\n\nPuedes crear una nueva garantía si lo deseas desde tu cuenta: {link_cuenta}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cliente}</span> <span class="variable-tag">{codigo}</span> <span class="variable-tag">{motivo}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            </div>
            <?php submit_button('Guardar Configuración de Emails al Cliente'); ?>
        </form>
        <?php
    }
    
    public static function render_admin_emails() {
        // Similar estructura para emails del admin
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('wc_garantias_emails_admin'); ?>
            
            <!-- Email Admin Nuevo Reclamo -->
            <div class="email-template">
                <h3> Nuevo Reclamo Recibido</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_nuevo_reclamo_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_nuevo_reclamo_asunto', '🔔 Nuevo reclamo de garantía - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_nuevo_reclamo_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_nuevo_reclamo_cuerpo', 
                                    "NUEVO RECLAMO RECIBIDO\n\nCódigo: {codigo}\nCliente: {cliente}\nFecha: {fecha}\n\nRevisar en: {link_admin}\n\nACCIN REQUERIDA: Revisar y procesar el reclamo."
                                )); 
                            ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email Admin Apelación -->
            <div class="email-template">
                <h3>️ Apelacin Recibida</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_apelacion_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_apelacion_asunto', ' Apelacin recibida - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_apelacion_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_apelacion_cuerpo', 
                                    "APELACIÓN RECIBIDA\n\nGarantia: {codigo}\nCliente: {cliente}\nItem: {item}\n\nRazn de la apelacin:\n{razon}\n\n{archivos_adjuntos}\n\nRevisar en: {link_admin}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{codigo}</span> <span class="variable-tag">{cliente}</span> <span class="variable-tag">{item}</span> <span class="variable-tag">{razon}</span> <span class="variable-tag">{archivos_adjuntos}</span> <span class="variable-tag">{link_admin}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Admin Etiqueta Descargada -->
            <div class="email-template">
                <h3> Etiqueta Descargada</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_etiqueta_descargada_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_etiqueta_descargada_asunto', 'Etiqueta descargada - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_etiqueta_descargada_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_etiqueta_descargada_cuerpo', 
                                    "El {tipo_usuario} ha descargado la etiqueta de envo para {cantidad} item(s) de la garantia {codigo}.\n\nEspera el envío del paquete."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{tipo_usuario}</span> <span class="variable-tag">{cantidad}</span> <span class="variable-tag">{codigo}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Admin Respuesta Cliente -->
            <div class="email-template">
                <h3> Respuesta del Cliente</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_respuesta_cliente_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_respuesta_cliente_asunto', ' RESPUESTA CLIENTE - Garantía {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_respuesta_cliente_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_respuesta_cliente_cuerpo', 
                                    " ACCIN REQUERIDA - RESPUESTA DE CLIENTE\n\nEl cliente ha respondido a la solicitud de informacin para la garantia {codigo}.\n\nLa garantia ha vuelto al estado PENDIENTE y requiere tu revisión.\n\n{archivos_info}\n\n{comentario_cliente}\n\n Revisar ahora en: {link_admin}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{codigo}</span> <span class="variable-tag">{archivos_info}</span> <span class="variable-tag">{comentario_cliente}</span> <span class="variable-tag">{link_admin}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Admin Destrucción Subida -->
            <div class="email-template">
                <h3> Evidencia de Destruccin Subida</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_destruccion_subida_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_destruccion_subida_asunto', 'Cliente subi evidencia de destruccin - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_destruccion_subida_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_destruccion_subida_cuerpo', 
                                    "El cliente ha subido evidencia de destruccin para la garantia {codigo}.\n\nACCIN REQUERIDA: Revisar la evidencia y aprobar/rechazar.\n\n{archivos_destruccion}\n\nRevisar en: {link_admin}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{codigo}</span> <span class="variable-tag">{archivos_destruccion}</span> <span class="variable-tag">{link_admin}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Email Admin Tracking Subido -->
            <div class="email-template">
                <h3> Tracking de Envo Recibido</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_tracking_subido_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_tracking_subido_asunto', 'Nueva devolución en trnsito - {codigo}')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_tracking_subido_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_tracking_subido_cuerpo', 
                                    "Se ha registrado un envo de devolucin para la garantia {codigo}.\n\nNmero de tracking: {tracking}\n\nRevisar en: {link_admin}"
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{codigo}</span> <span class="variable-tag">{tracking}</span> <span class="variable-tag">{link_admin}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Email Admin RMA Expirado -->
            <div class="email-template">
                <h3>⚠ Cupn RMA Expirado</h3>
                <table class="form-table">
                    <tr>
                        <th>Asunto:</th>
                        <td>
                            <input type="text" class="large-text" name="garantia_email_admin_rma_expirado_asunto" 
                                   value="<?php echo esc_attr(get_option('garantia_email_admin_rma_expirado_asunto', '️ Cupón RMA expirado - {cupon_rma} - Eliminar producto')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th>Mensaje:</th>
                        <td>
                            <textarea class="large-text" name="garantia_email_admin_rma_expirado_cuerpo"><?php 
                                echo esc_textarea(get_option('garantia_email_admin_rma_expirado_cuerpo', 
                                    "CUPÓN RMA EXPIRADO\n\nEl siguiente cupón RMA ha expirado y el cliente no lo utilizó:\n\nCódigo del cupn: {cupon_rma}\nProducto: {producto}\nSKU (cdigo item): {sku}\nCliente: {cliente}\n\nACCIÓN REQUERIDA:\n1. Eliminar el producto RMA del sistema\n2. Desechar fsicamente el producto\n\nVer producto: {link_producto}\n\nEste producto ya no ser enviado al cliente."
                                )); 
                            ?></textarea>
                            <p class="description">
                                Variables: <span class="variable-tag">{cupon_rma}</span> <span class="variable-tag">{producto}</span> <span class="variable-tag">{sku}</span> <span class="variable-tag">{cliente}</span> <span class="variable-tag">{link_producto}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            <?php submit_button('Guardar Configuración de Emails al Admin'); ?>
        </form>
        <?php
    }
    
    public static function render_variables_help() {
        ?>
        <div class="email-template">
            <h3> Variables Disponibles para Emails</h3>
            
            <h4>Variables Generales:</h4>
            <ul>
                <li><span class="variable-tag">{cliente}</span> - Nombre del cliente</li>
                <li><span class="variable-tag">{codigo}</span> - Cdigo nico de la garantía</li>
                <li><span class="variable-tag">{fecha}</span> - Fecha actual</li>
                <li><span class="variable-tag">{link_admin}</span> - Enlace al panel de administracin</li>
            </ul>
            
            <h4>Variables para Cupones:</h4>
            <ul>
                <li><span class="variable-tag">{cupon}</span> - Código del cupón generado</li>
                <li><span class="variable-tag">{importe}</span> - Monto del cupn</li>
            </ul>
            
            <h4>Variables para Rechazos:</h4>
            <ul>
                <li><span class="variable-tag">{motivo}</span> - Motivo del rechazo</li>
            </ul>
            
            <h4>Variables para Informacin Solicitada:</h4>
            <ul>
                <li><span class="variable-tag">{mensaje_admin}</span> - Mensaje del administrador solicitando información</li>
            </ul>
            <h4>Variables para Destruccin:</h4>
            <ul>
                <li><span class="variable-tag">{archivos_destruccion}</span> - Enlaces a fotos/videos de destruccin</li>
                <li><span class="variable-tag">{dimensiones}</span> - Dimensiones del paquete</li>
            </ul>
            
            <h4>Variables para Tracking:</h4>
            <ul>
                <li><span class="variable-tag">{tracking}</span> - Número de tracking/gua</li>
                <li><span class="variable-tag">{tipo_usuario}</span> - "distribuidor" o "cliente"</li>
            </ul>
            
            <h4>Variables para Items:</h4>
            <ul>
                <li><span class="variable-tag">{item}</span> - Cdigo del item</li>
                <li><span class="variable-tag">{razon}</span> - Razón de apelacin</li>
                <li><span class="variable-tag">{archivos_adjuntos}</span> - Archivos adjuntos</li>
                <li><span class="variable-tag">{archivos_info}</span> - Informacin de archivos subidos</li>
                <li><span class="variable-tag">{comentario_cliente}</span> - Comentario del cliente</li>
            </ul>
            
            <h4>Enlaces:</h4>
            <ul>
                <li><span class="variable-tag">{link_cuenta}</span> - Enlace al panel de garantías del cliente</li>
            </ul>
            <h4>Variables para Recepcin Parcial:</h4>
            <ul>
                <li><span class="variable-tag">{cantidad_recibida}</span> - Cantidad de unidades recibidas</li>
                <li><span class="variable-tag">{cantidad_pendiente}</span> - Cantidad de unidades pendientes</li>
                <li><span class="variable-tag">{cantidad_faltante}</span> - Cantidad de unidades faltantes</li>
                <li><span class="variable-tag">{dias_restantes}</span> - Días restantes para recibir</li>
                <li><span class="variable-tag">{fecha_limite}</span> - Fecha lmite de recepción</li>
                <li><span class="variable-tag">{codigo_item}</span> - Código del item</li>
                <li><span class="variable-tag">{producto}</span> - Nombre del producto</li>
            </ul>
        </div>
        <?php
    }
    
    // Funcin para enviar emails usando los templates
    public static function enviar_email($tipo, $destinatario, $variables = []) {

        
        // Verificar si es recepcion_parcial
        if ($tipo === 'recepcion_parcial') {
            error_log('ES RECEPCIÓN PARCIAL!');
        }

        // Justo antes del wp_mail
        error_log('Asunto final: ' . $asunto);
        error_log('Cuerpo (primeros 200 chars): ' . substr($cuerpo_html, 0, 200));
      
        // ESTA LNEA DEBE ESTAR AQUÍ - SIN NADA EN MEDIO
        $resultado = wp_mail($destinatario, $asunto, $cuerpo_html, $headers);
        
        // Y JUSTO DESPUÉS ESTE LOG
        if ($tipo === 'destruccion_rechazada') {
            error_log('Resultado del envío EMAIL: ' . ($resultado ? 'ÉXITO' : 'FALLO'));
        }
        
        // Determinar si es email de admin o cliente
        $prefijo = strpos($tipo, 'admin_') === 0 ? '' : '';
    
        // Mapeo correcto de tipos
        $tipo_corregido = $tipo;
        if ($tipo == 'nuevo_reclamo') {
            $tipo_corregido = 'confirmacion';
        }

        $asunto = get_option("garantia_email_{$tipo}_asunto", '');
        $cuerpo = get_option("garantia_email_{$tipo}_cuerpo", '');
        
        // Si no hay template guardado, usar valores por defecto
        if (empty($asunto) || empty($cuerpo)) {
            $defaults = self::get_default_templates();
            if (isset($defaults[$tipo])) {
                $asunto = $asunto ?: $defaults[$tipo]['asunto'];
                $cuerpo = $cuerpo ?: $defaults[$tipo]['cuerpo'];
            }
        }
        
        // Agregar variables comunes automáticamente
        if (!isset($variables['fecha'])) {
            $variables['fecha'] = date('d/m/Y H:i');
        }
        if (!isset($variables['link_cuenta'])) {
            $variables['link_cuenta'] = wc_get_account_endpoint_url('garantias');
        }

        
        // Reemplazar variables
        foreach ($variables as $variable => $valor) {
            // Solo procesar valores que sean strings o números
            if (is_string($valor) || is_numeric($valor)) {
                $asunto = str_replace('{' . $variable . '}', $valor, $asunto);
                $cuerpo = str_replace('{' . $variable . '}', $valor, $cuerpo);
            } elseif (is_array($valor)) {
                // Si es un array, ignorarlo o convertir a string vacío
                $asunto = str_replace('{' . $variable . '}', '', $asunto);
                $cuerpo = str_replace('{' . $variable . '}', '', $cuerpo);
            }
        }
        
        // Convertir saltos de línea para HTML
        $cuerpo_html = nl2br($cuerpo);
        
        // Crear email noreply basado en el dominio del sitio
        $site_url = parse_url(home_url());
        $domain = $site_url['host'];
        $noreply_email = 'noreply@' . $domain;
        
        // Configurar headers para HTML y NOREPLY
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . $noreply_email . '>',
            'Reply-To: ' . $noreply_email
        );
        
        // ENVIAR EL EMAIL (UNA SOLA VEZ)
        $resultado = wp_mail($destinatario, $asunto, $cuerpo_html, $headers);
        
        // DESPUS enviar WhatsApp si está habilitado
        if (class_exists('WC_Garantias_WhatsApp') && !empty($variables)) {
            self::enviar_whatsapp($tipo, $destinatario, $variables);
        }
        
        return $resultado;
    }
    
    // Función para obtener templates por defecto
    private static function get_default_templates() {
        return [
            'confirmacion' => [
                'asunto' => 'Reclamo de Garantía Recibido - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nHemos recibido tu reclamo de garantia con cdigo {codigo}.\n\nLo revisaremos en las prximas 24-48 horas y te notificaremos el resultado.\n\nGracias por tu paciencia."
            ],
            'aprobada' => [
                'asunto' => 'Garanta Aprobada - Cupn de ${importe} disponible',
                'cuerpo' => "¡Excelente noticia {cliente}!\n\nTu garantia {codigo} ha sido APROBADA.\n\nTienes un cupn de ${importe} disponible para tu prxima compra.\n\nCdigo del cupn: {cupon}\n\nEl cupón se aplicará automáticamente en tu prxima compra.\n\nGracias por confiar en nosotros!"
            ],
            'rechazada' => [
                'asunto' => 'Garantia {codigo} - Informacin importante',
                'cuerpo' => "Hola {cliente},\n\nHemos revisado tu garanta {codigo} y lamentablemente no podemos proceder con el reclamo.\n\nMotivo: {motivo}\n\nSi no estás de acuerdo con esta decisión, puedes apelar desde tu panel de garantias.\n\nGracias por tu comprensión."
            ],
            'info_solicitada' => [
                'asunto' => 'Informacin adicional requerida - Garantia {codigo}',
                'cuerpo' => "Hola {cliente},\n\nNecesitamos información adicional sobre algunos items de tu garantía.\n\nMensaje del administrador:\n{mensaje_admin}\n\nPor favor, proporciona:\n{tipo_informacion}\n\nIngresa a tu cuenta para subir la información solicitada:\n{link_cuenta}"
            ],
            'destruccion_aprobada' => [
                'asunto' => 'Garanta aprobada - Accin requerida - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nTu garantía {codigo} ha sido APROBADA.\n\nACCIN REQUERIDA:\n1. Destruye completamente el producto defectuoso\n2. Toma fotos/video claros de la destruccin\n3. Sube la evidencia en tu panel de garantias\n\nUna vez verificada la destruccin, recibirás tu cupón automticamente.\n\nIngresa aquí para subir la evidencia: {link_cuenta}"
            ],
            'destruccion_rechazada' => [
                'asunto' => 'Evidencia de destrucción rechazada - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nLa evidencia de destrucción que subiste NO fue aprobada.\n\nMotivo: {motivo}\n\nPor favor, realiza nuevamente la destrucción siguiendo las instrucciones y sube nueva evidencia.\n\nIngresa aquí: {link_cuenta}"
            ],
            'etiqueta_disponible' => [
                'asunto' => 'Etiqueta de envío disponible - Garantia {codigo}',
                'cuerpo' => "Hola {cliente},\n\nLa etiqueta de envo para tu garantia {codigo} ya est disponible.\n\nPuedes descargarla desde tu cuenta: {link_cuenta}\n\nPor favor, envía el paquete lo antes posible."
            ],
            'etiqueta_devolucion' => [
                'asunto' => 'Etiqueta de devolución disponible - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nLa etiqueta para devolver los productos de tu garanta ya est disponible.\n\nDimensiones del paquete: {dimensiones}\n\nDescarga la etiqueta desde tu cuenta e imprímela para pegarla en el paquete.\n\n{link_cuenta}"
            ],
            'producto_recibido' => [
                'asunto' => 'Producto recibido - Cupón generado - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nHemos recibido el producto devuelto de tu garantía.\n\nTu cupón ha sido generado: {cupon}\nMonto: ${importe}\n\nPuedes usarlo en tu prxima compra.\n\nGracias!"
            ],
            'aceptada_analisis' => [
                'asunto' => 'Tu garantía ha sido aceptada para anlisis - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nHemos aceptado tu garantia {codigo} y est siendo analizada.\n\nTe notificaremos el resultado en las próximas 24-48 horas.\n\nPuedes ver el estado en: {link_cuenta}"
            ],
            'aprobada_devolucion' => [
                'asunto' => 'Garantia aprobada - Devolucin requerida - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nTu garantia {codigo} ha sido APROBADA.\n\nACCIN REQUERIDA:\n1. Enva el producto defectuoso a nuestra dirección:\n   {direccion_devolucion}\n\n2. Una vez enviado, sube una foto de la guía de envo o nmero de tracking en tu panel\n\nIngresa aqu para subir la informacin de envo: {link_cuenta}\n\nUna vez confirmemos la recepcin, generaremos tu cupn automáticamente."
            ],
            'admin_nuevo_reclamo' => [
                'asunto' => ' Nuevo reclamo de garantía - {codigo}',
                'cuerpo' => "NUEVO RECLAMO RECIBIDO\n\nCdigo: {codigo}\nCliente: {cliente}\nFecha: {fecha}\n\nRevisar en: {link_admin}\n\nACCIÓN REQUERIDA: Revisar y procesar el reclamo."
            ],
            'recordatorio_info_24h' => [
                'asunto' => ' URGENTE: Solo te quedan {horas_restantes} horas - Garantía {codigo}',
                'cuerpo' => "Hola {cliente},\n\n⏰ RECORDATORIO IMPORTANTE\n\nSolo te quedan {horas_restantes} horas hábiles para responder a nuestra solicitud de informacin.\n\garantia: {codigo}\nItem: {item_codigo}\n\nMensaje original:\n{mensaje_original}\n\nSi no recibimos la informacin antes del plazo, el item ser rechazado automticamente.\n\nResponde ahora: {link_cuenta}\n\n¡No pierdas tu garanta!"
            ]
            ,
            'devolucion_confirmada' => [
                'asunto' => 'Devolucin confirmada - {codigo} - Descarga tu etiqueta',
                'cuerpo' => "Hola {cliente},\n\nHemos recibido tu solicitud de devolución por error de compra.\n\nCódigo: {codigo}\nFecha: {fecha}\n\n PRÓXIMOS PASOS:\n1. Descarga e imprime tu etiqueta: {link_etiqueta}\n2. Pega la etiqueta en el paquete\n3. Enva el paquete (costo a tu cargo)\n4. Sube el nmero de seguimiento en tu cuenta\n\nIMPORTANTE: El producto debe estar en perfectas condiciones con todos sus accesorios y embalaje original.\n\nUna vez recibamos y verifiquemos el producto, generaremos tu cupn.\n\nVer estado: {link_cuenta}"
            ],
            'rma_confirmado' => [
                'asunto' => 'Cupón de retorno generado - {codigo} - Producto: {producto}',
                'cuerpo' => "Hola {cliente},\n\nTu reclamo ha sido rechazado definitivamente pero se ha generado un cupn para que recibas el producto de vuelta.\n\nDetalles:\n- Cdigo de garantia: {codigo}\n- Producto a devolver: {producto}\n- Cupn generado: {cupon_rma}\n- Validez: {dias_validez} das\n\nEste cupón se aplicará automticamente en tu próxima compra y agregará el producto a tu pedido sin costo.\n\nIMPORTANTE: Este cupn vence el {fecha_vencimiento}. Te notificaremos 30 días antes del vencimiento.\n\nGracias por tu comprensión."
            ],
            'rma_vencimiento_30' => [
                'asunto' => '⏰ Tu cupn de retorno vence en 30 das - {producto}',
                'cuerpo' => "Hola {cliente},\n\n RECORDATORIO IMPORTANTE\n\nTu cupn de retorno para el siguiente producto vencer en 30 días:\n\nProducto: {producto}\nCódigo del cupn: {cupon_rma}\nFecha de vencimiento: {fecha_vencimiento}\n\nEste cupón te permite recibir el producto en tu prxima compra sin costo adicional.\n\nNo pierdas la oportunidad de recuperar tu producto. Realiza tu pedido antes del vencimiento.\n\nIngresa a tu cuenta: {link_cuenta}\n\nSaludos"
            ],
            'admin_rma_expirado' => [
                'asunto' => ' Cupn RMA expirado - {cupon_rma} - Eliminar producto',
                'cuerpo' => "CUPÓN RMA EXPIRADO\n\nEl siguiente cupn RMA ha expirado y el cliente no lo utiliz:\n\nCdigo del cupón: {cupon_rma}\nProducto: {producto}\nSKU (cdigo item): {sku}\nCliente: {cliente}\n\nACCIÓN REQUERIDA:\n1. Eliminar el producto RMA del sistema\n2. Desechar fsicamente el producto\n\nVer producto: {link_producto}\n\nEste producto ya no ser enviado al cliente."
            ]     
            ,
            'recepcion_parcial' => [
                'asunto' => '⚠ Recepción Parcial - Garantía {codigo}',
                'cuerpo' => "Hola {cliente},\n\nHemos recibido tu paquete de la garantía {codigo}, pero solo contiene:\n\n {cantidad_recibida} unidades de {producto} (recibidas y procesadas)\n {cantidad_pendiente} unidades faltan por recibir\n\nTienes 7 días para enviar las unidades faltantes. Si no las recibimos en este plazo, serán rechazadas automáticamente.\n\nIngresa a tu cuenta para ver el estado: {link_cuenta}"
            ],
            'recordatorio_recepcion' => [
                'asunto' => '⏰ Quedan {dias_restantes} días - Items pendientes {codigo}',
                'cuerpo' => "Hola {cliente},\n\n⏰ RECORDATORIO IMPORTANTE\n\nQuedan solo {dias_restantes} das para enviar los siguientes items:\n\n• {cantidad} unidades de {producto}\n• Código de item: {codigo_item}\n\nFecha límite: {fecha_limite}\n\nSi no recibimos estos items antes del plazo, serán rechazados automticamente sin posibilidad de apelacin.\n\nPuedes cancelar el envo desde tu cuenta si no vas a enviarlos: {link_cuenta}"
            ],
            'rechazo_no_recibido' => [
                'asunto' => ' Items rechazados por no recepción - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nLos siguientes items fueron rechazados por no ser recibidos en el plazo establecido:\n\n {cantidad} unidades de {producto}\n• Código: {codigo_item}\n Fecha límite: {fecha_limite}\n\nEstos items ya no pueden ser procesados en esta garantía.\n\nSi aún necesitas hacer el reclamo, puedes crear una nueva garantía desde tu cuenta: {link_cuenta}"
            ],
            'rechazo_manual_parcial' => [
                'asunto' => 'Items pendientes rechazados - {codigo}',
                'cuerpo' => "Hola {cliente},\n\nLos items pendientes de recepción han sido rechazados.\n\nMotivo: {motivo}\n\nPuedes crear una nueva garantía si lo deseas desde tu cuenta: {link_cuenta}"
            ]
        ];
    }
    
    // Enviar notificacin por WhatsApp
    private static function enviar_whatsapp($tipo, $email, $variables) {

        
        // Si es un mensaje para admin, no enviar WhatsApp
        if (strpos($tipo, 'admin_') === 0) {
            return; // Salir sin hacer nada
        }

        // Para clientes, obtener el teléfono del usuario por su email
        $user = get_user_by('email', $email);
        if (!$user) {
            return;
        }

        $telefono = get_user_meta($user->ID, 'billing_phone', true);
        if (empty($telefono)) {
            $telefono = get_user_meta($user->ID, 'phone', true);
        }
        
        if (empty($telefono)) {
            return;
        }

        // Limpiar teléfono (quitar espacios, guiones, parntesis, etc)
        $telefono = preg_replace('/[^0-9+]/', '', $telefono);
        
        // Si empieza con +, quitarlo
        if (substr($telefono, 0, 1) === '+') {
            $telefono = substr($telefono, 1);
        }

    // Para clientes, obtener el telfono del usuario por su email
    $user = get_user_by('email', $email);
    if (!$user) {
        return;
    }
    
    $telefono = get_user_meta($user->ID, 'billing_phone', true);
    if (empty($telefono)) {
        $telefono = get_user_meta($user->ID, 'phone', true);
    }
    
    if (empty($telefono)) {
        return;
    }
    
    // Limpiar teléfono (quitar espacios, guiones, parntesis, etc)
    $telefono = preg_replace('/[^0-9+]/', '', $telefono);
    
    // Si empieza con +, quitarlo
    if (substr($telefono, 0, 1) === '+') {
        $telefono = substr($telefono, 1);
    }
        
        // Mapear tipo de email a plantilla WhatsApp configurada
        $template_map = array(
            'confirmacion' => 'nuevo_reclamo',
            'aprobada' => 'aprobada',
            'rechazada' => 'rechazada',
            'info_solicitada' => 'info_solicitada',
            'destruccion_aprobada' => 'destruccion_aprobada',
            'etiqueta_disponible' => 'etiqueta_disponible',
            'etiqueta_devolucion' => 'etiqueta_disponible',
            'admin_nuevo_reclamo' => 'nuevo_reclamo',
            'producto_recibido' => 'cupon_generado',
            'aprobada_devolucion' => 'aprobada',
            'destruccion_rechazada' => 'rechazada',
            'admin_respuesta_cliente' => 'nuevo_reclamo',
            'admin_destruccion_subida' => 'nuevo_reclamo',
            'admin_apelacion' => 'nuevo_reclamo',
            'admin_tracking_subido' => 'nuevo_reclamo',
            'admin_etiqueta_descargada' => 'nuevo_reclamo',
            'recordatorio_info_24h' => 'info_solicitada',
            'devolucion_confirmada' => 'confirmacion',
            'recepcion_parcial' => 'recepcion_parcial',
        );
        
        // Si no hay mapeo para este tipo, salir
        if (!isset($template_map[$tipo])) {
            return;
        }
        
        // Ver si la plantilla est configurada
        $template_whatsapp = $template_map[$tipo];
        
        // Preparar parmetros segn el tipo de mensaje
        $parameters = array();
        
        // NUEVO: Leer configuración de variables guardada
        $variables_config = get_option('garantias_whatsapp_variables', array());
        $config_evento = $variables_config[$tipo] ?? array();
        
        // Antes del switch para recepcion_parcial
        if ($tipo === 'recepcion_parcial') {
        }
        
        // Si hay configuracin de variables, usarla
        if (!empty($config_evento)) {
            $parameters = array();

                // Procesar cada parámetro configurado
                for ($i = 1; $i <= 10; $i++) { // Máximo 10 parámetros
                    $param_key = 'param_' . $i;
                    if (isset($config_evento[$param_key]) && !empty($config_evento[$param_key])) {
                        $variable_name = $config_evento[$param_key];
                        
                        // Mapear variables especiales
                        switch($variable_name) {
                            case 'cliente':
                                $parameters[] = $variables['cliente'] ?? 'Cliente';
                                break;
                                
                            case 'codigo':
                                $parameters[] = $variables['codigo'] ?? 'SIN-CODIGO';
                                break;
                                
                            case 'codigo_item':
                            if (isset($variables['item_codigo_procesado'])) {
                                }
                            
                            // Obtener cdigo del item especfico que est siendo procesado
                            $codigo_item = 'SIN-ITEM';
                            if (isset($variables['item_codigo_procesado']) && !empty($variables['item_codigo_procesado'])) {
                                // Si tenemos el código del item especfico que se est procesando
                                $codigo_item = $variables['item_codigo_procesado'];
                                
                            } elseif (isset($variables['garantia_id'])) {
                                // Fallback: tomar el primer item
                                
                                $items = get_post_meta($variables['garantia_id'], '_items_reclamados', true);
                                if (is_array($items) && !empty($items)) {
                                    $primer_item = reset($items);
                                    $codigo_item = $primer_item['codigo_item'] ?? 'SIN-CODIGO-ITEM';
                                }
                            }
                            
                            $parameters[] = $codigo_item;
                            break;
                                
                            case 'motivo_info':
                                $valor_motivo = $variables['mensaje_admin'] ?? 'Se requiere información adicional';
                                $parameters[] = $valor_motivo;
                                break;
                                
                            case 'producto':
                            // Usar directamente la variable que ya viene preparada
                            $parameters[] = $variables['producto'] ?? 'Producto';
                            break;
                                
                            case 'motivo':
                                // Para rechazos, usar el motivo real que viene del admin
                                $motivo_real = $variables['motivo'] ?? 'Sin motivo especificado';
                                $parameters[] = $motivo_real;
                                break;
                                
                            case 'cupon':
                                $parameters[] = $variables['cupon'] ?? 'SIN-CUPON';
                                break;
                                
                            case 'importe':
                                $parameters[] = '$' . ($variables['importe'] ?? '0');
                                break;
                                
                            default:
                                // Variables directas que no necesitan procesamiento especial
                                $parameters[] = $variables[$variable_name] ?? 'Sin valor';
                                break;
                        }
                } else {
                    break;
                }
            }
        } else {
            // FALLBACK: usar el código anterior si no hay configuracin
            switch($tipo) {
                case 'confirmacion':
                case 'admin_nuevo_reclamo':
                    $parameters = array(
                        $variables['cliente'] ?? 'Cliente',
                        $variables['codigo'] ?? 'SIN-CODIGO'
                    );
                    break;
                    
                case 'aprobada':
                case 'producto_recibido':
                    $parameters = array(
                        $variables['cupon'] ?? 'SIN-CUPON',
                        '$' . ($variables['importe'] ?? '0'),
                        $variables['codigo'] ?? 'SIN-CODIGO'
                    );
                    break;
                    
                case 'rechazada':
                case 'destruccion_rechazada':
                    $parameters = array(
                        $variables['cliente'] ?? 'Cliente',
                        $variables['codigo'] ?? 'SIN-CODIGO',
                        $variables['motivo'] ?? 'Sin motivo especificado'
                    );
                    break;
                    
                case 'recepcion_parcial':
                    $parameters = array(
                        $variables['codigo'] ?? 'SIN-CODIGO',      // Parámetro 1: Código de garantía
                        $variables['producto'] ?? 'Producto'        // Parámetro 2: NOMBRE del producto
                    );
                    break;    
                    
                case 'info_solicitada':
                    $parameters = array(
                        $variables['cliente'] ?? 'Cliente',
                        $variables['codigo'] ?? 'SIN-CODIGO'
                    );
                    break;
                    
                case 'destruccion_aprobada':
                    // Obtener informacin del producto
                    $producto_nombre = 'Producto';
                    if (isset($variables['garantia_id'])) {
                        $items = get_post_meta($variables['garantia_id'], '_items_reclamados', true);
                        if (is_array($items) && !empty($items)) {
                            $primer_item = reset($items);
                            $producto = wc_get_product($primer_item['producto_id']);
                            if ($producto) {
                                $producto_nombre = $producto->get_name();
                            }
                        }
                    }
                    
                    $parameters = array(
                        $variables['cliente'] ?? 'Cliente',
                        $variables['codigo'] ?? 'SIN-CODIGO',
                        $producto_nombre
                    );
                    break;
                    
                case 'etiqueta_disponible':
                case 'aprobada_devolucion':
                    $parameters = array(
                        $variables['cliente'] ?? 'Cliente',
                        $variables['codigo'] ?? 'SIN-CODIGO'
                    );
                    break;
                    
                default:
                    // Para mensajes de admin, usar formato genrico
                    $parameters = array(
                        $variables['codigo'] ?? 'SIN-CODIGO',
                        $variables['cliente'] ?? 'Cliente'
                    );
            }
        }
        
        // Enviar por WhatsApp usando la plantilla configurada
            try {
                WC_Garantias_WhatsApp::send_template(
                    $template_map[$tipo],
                    $telefono,
                    $parameters
                );
            } catch (Exception $e) {
        }
    }
}

// Inicializar
WC_Garantias_Emails::init();