<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo de Configuración de Garantías
 */
class WC_Garantias_Admin_Config {
    
    public static function render_page() {
        // Mostrar mensaje si viene de guardar
        if (isset($_GET['configuracion_guardada'])) {
            echo '<div class="notice notice-success is-dismissible"><p>✓ Configuración guardada correctamente. Todos los cambios han sido guardados exitosamente.</p></div>';
        }
        
        // Procesar formulario
        if (isset($_POST['guardar_config_garantias']) && check_admin_referer('guardar_config_garantias')) {
            self::guardar_configuracion();
            wp_redirect(admin_url('admin.php?page=wc-garantias-config&configuracion_guardada=1'));
            exit;
        }

        // Obtener valores actuales
        $email_actual = get_option('admin_email_garantias', 'rosariotechsrl@gmail.com');
        $duracion_garantia = get_option('duracion_garantia', 180);
        $aprobado_asunto = get_option('garantia_mail_aprobado_asunto', 'Cupón por Garantía Aprobada');
        $aprobado_cuerpo = get_option('garantia_mail_aprobado_cuerpo', 'Hola {cliente}, tu garantía fue aprobada. Tienes un cupón de ${importe} para tu próxima compra. Código: {cupon}');
        $rechazado_asunto = get_option('garantia_mail_rechazado_asunto', 'Garantía Rechazada');
        $rechazado_cuerpo = get_option('garantia_mail_rechazado_cuerpo', 'Hola {cliente}, tu garantía {codigo} fue rechazada. Motivo: {motivo}');
        $postrechazo_asunto = get_option('garantia_mail_postrechazo_asunto', 'Acción post-rechazo de garantía');
        $postrechazo_cuerpo = get_option('garantia_mail_postrechazo_cuerpo', 'El cliente #{cliente_id} ha seleccionado la opción post-rechazo para la garantía {codigo}. Producto: {producto}. Motivo de rechazo: {motivo}. Acción solicitada: {accion}.');
        ?>
        <div class="wrap">
            <h1>Configuración de Garantías</h1>
            <form method="post">
                <?php wp_nonce_field('guardar_config_garantias'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="admin_email_garantias">Email de notificaciones</label></th>
                        <td>
                            <input type="email" id="admin_email_garantias" name="admin_email_garantias" value="<?php echo esc_attr($email_actual); ?>" style="width:350px;max-width:100%;" required>
                            <p class="description">Este email recibirá notificaciones por acciones post-rechazo.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="instrucciones_destruccion">Instrucciones de Destrucción</label></th>
                        <td>
                            <?php 
                            $instrucciones_default = "1. Destruye completamente el producto defectuoso\n2. Asegúrate de que no pueda ser reutilizado\n3. Toma fotos/video claros de la destrucción\n4. Conserva la evidencia por 30 días";
                            $instrucciones_actuales = get_option('instrucciones_destruccion', $instrucciones_default);
                            ?>
                            <textarea id="instrucciones_destruccion" name="instrucciones_destruccion" rows="6" cols="50" style="width: 100%; max-width: 500px;"><?php echo esc_textarea($instrucciones_actuales); ?></textarea>
                            <p class="description">Estas instrucciones se mostrarán al cliente cuando deba destruir el producto.<br>Usa saltos de línea para separar cada instrucción.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="duracion_garantia">Duración de la garantía (días)</label></th>
                        <td>
                            <input type="number" id="duracion_garantia" name="duracion_garantia" value="<?php echo esc_attr($duracion_garantia); ?>" min="1" style="width:100px;" required>
                            <p class="description">Cantidad de días desde la compra en los que el producto es elegible para reclamo de garantía.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="direccion_devolucion_garantias">Dirección de Devolución</label></th>
                        <td>
                            <textarea id="direccion_devolucion_garantias" name="direccion_devolucion_garantias" rows="4" cols="50" style="width: 100%; max-width: 500px;"><?php echo esc_textarea(get_option('direccion_devolucion_garantias', '')); ?></textarea>
                            <p class="description">Esta dirección se mostrará a los clientes cuando deban devolver productos.<br>Incluye: Nombre, Dirección completa, Ciudad, Código postal, Teléfono.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Configuración de Cajas de envío</label></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Tamaños de cajas disponibles</legend>
                                
                                <h4>Caja Pequeña</h4>
                                <p>
                                    <label>Dimensiones (cm):</label><br>
                                    Largo: <input type="number" name="caja_pequena_largo" value="<?php echo get_option('caja_pequena_largo', 40); ?>" style="width: 60px;" min="1">  
                                    Ancho: <input type="number" name="caja_pequena_ancho" value="<?php echo get_option('caja_pequena_ancho', 40); ?>" style="width: 60px;" min="1">  
                                    Alto: <input type="number" name="caja_pequena_alto" value="<?php echo get_option('caja_pequena_alto', 20); ?>" style="width: 60px;" min="1">
                                </p>
                                
                                <h4>Caja Mediana</h4>
                                <p>
                                    <label>Dimensiones (cm):</label><br>
                                    Largo: <input type="number" name="caja_mediana_largo" value="<?php echo get_option('caja_mediana_largo', 40); ?>" style="width: 60px;" min="1">  
                                    Ancho: <input type="number" name="caja_mediana_ancho" value="<?php echo get_option('caja_mediana_ancho', 40); ?>" style="width: 60px;" min="1">  
                                    Alto: <input type="number" name="caja_mediana_alto" value="<?php echo get_option('caja_mediana_alto', 40); ?>" style="width: 60px;" min="1">
                                </p>
                                
                                <h4>Caja Grande</h4>
                                <p>
                                    <label>Dimensiones (cm):</label><br>
                                    Largo: <input type="number" name="caja_grande_largo" value="<?php echo get_option('caja_grande_largo', 40); ?>" style="width: 60px;" min="1"> × 
                                    Ancho: <input type="number" name="caja_grande_ancho" value="<?php echo get_option('caja_grande_ancho', 40); ?>" style="width: 60px;" min="1">  
                                    Alto: <input type="number" name="caja_grande_alto" value="<?php echo get_option('caja_grande_alto', 60); ?>" style="width: 60px;" min="1">
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="garantia_tiempo_limite_info">Tiempo límite para información (horas hábiles)</label></th>
                        <td>
                            <input type="number" id="garantia_tiempo_limite_info" name="garantia_tiempo_limite_info" value="<?php echo get_option('garantia_tiempo_limite_info', 72); ?>" min="24" style="width:100px;" required> horas
                            <p class="description">Tiempo en horas hábiles (lunes a viernes) para que el cliente responda a solicitudes de información o se rechaza automáticamente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="garantia_motivo_rechazo_timeout">Motivo de rechazo por timeout</label></th>
                        <td>
                            <input type="text" id="garantia_motivo_rechazo_timeout" name="garantia_motivo_rechazo_timeout" value="<?php echo esc_attr(get_option('garantia_motivo_rechazo_timeout', 'Fuera de plazo para enviar la información solicitada')); ?>" style="width:100%; max-width:500px;">
                            <p class="description">Este mensaje se usará cuando se rechace automáticamente por no responder a tiempo.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dias_devolucion_error">Días para devolución por error de compra</label></th>
                        <td>
                            <input type="number" id="dias_devolucion_error" name="dias_devolucion_error" value="<?php echo get_option('dias_devolucion_error', 20); ?>" min="1" max="365" style="width:100px;" required> días
                            <p class="description">Cantidad de días corridos desde la compra para permitir devoluciones por error.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dias_validez_cupon_rma">Días de validez para cupones de retorno (RMA)</label></th>
                        <td>
                            <input type="number" id="dias_validez_cupon_rma" name="dias_validez_cupon_rma" value="<?php echo get_option('dias_validez_cupon_rma', 120); ?>" min="30" max="365" style="width:100px;" required> días
                            <p class="description">Los cupones de retorno al cliente expirarán después de estos días. Se notificará 30 días antes del vencimiento.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="instrucciones_devolucion_error">Instrucciones para devolución por error</label></th>
                        <td>
                            <textarea id="instrucciones_devolucion_error" name="instrucciones_devolucion_error" rows="6" cols="50" style="width: 100%; max-width: 500px;"><?php echo esc_textarea(get_option('instrucciones_devolucion_error', "1. Descarga e imprime la etiqueta de devolución\n2. Pégala en el paquete de forma visible\n3. El producto debe estar en perfectas condiciones, sin uso\n4. Incluye todos los accesorios y embalaje original\n5. Envía el paquete por la empresa de tu preferencia (costo a tu cargo)\n6. Guarda el comprobante de envío")); ?></textarea>
                            <p class="description">Estas instrucciones se mostrarán al cliente cuando solicite una devolución por error.<br>Usa saltos de línea para separar cada instrucción.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="garantia_consejos_texto">Consejos para el cliente (HTML)</label></th>
                        <td>
                            <textarea id="garantia_consejos_texto" name="garantia_consejos_texto" rows="8" cols="50" style="width: 100%; max-width: 500px; font-family: monospace;"><?php echo esc_textarea(get_option('garantia_consejos_texto', '<ul><li><strong>Foto clara:</strong> Asegúrate de que la foto muestre claramente el problema</li><li><strong>Video completo:</strong> Muestra el problema desde diferentes ángulos</li><li><strong>Descripción detallada:</strong> Explica exactamente qué está fallando</li></ul>')); ?></textarea>
                            <p class="description">Puedes usar HTML básico: &lt;ul&gt;&lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, etc.<br><strong>Ejemplo:</strong> &lt;ul&gt;&lt;li&gt;&lt;strong&gt;Consejo:&lt;/strong&gt; Tu texto aquí&lt;/li&gt;&lt;/ul&gt;</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="guardar_config_garantias" class="button-primary">Guardar Configuración</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    private static function guardar_configuracion() {
        $nuevo_email = sanitize_email($_POST['admin_email_garantias']);
        if (is_email($nuevo_email)) {
            update_option('admin_email_garantias', $nuevo_email);
        }
        update_option('garantia_mail_aprobado_asunto', sanitize_text_field($_POST['garantia_mail_aprobado_asunto']));
        update_option('duracion_garantia', intval($_POST['duracion_garantia']));
        update_option('direccion_devolucion_garantias', sanitize_textarea_field($_POST['direccion_devolucion_garantias']));
        
        // Guardar configuración de cajas
        update_option('caja_pequena_largo', intval($_POST['caja_pequena_largo']));
        update_option('caja_pequena_ancho', intval($_POST['caja_pequena_ancho']));
        update_option('caja_pequena_alto', intval($_POST['caja_pequena_alto']));
        update_option('caja_mediana_largo', intval($_POST['caja_mediana_largo']));
        update_option('caja_mediana_ancho', intval($_POST['caja_mediana_ancho']));
        update_option('caja_mediana_alto', intval($_POST['caja_mediana_alto']));
        update_option('caja_grande_largo', intval($_POST['caja_grande_largo']));
        update_option('caja_grande_ancho', intval($_POST['caja_grande_ancho']));
        update_option('caja_grande_alto', intval($_POST['caja_grande_alto']));
        
        // Guardar otras configuraciones
        update_option('instrucciones_destruccion', sanitize_textarea_field($_POST['instrucciones_destruccion']));
        update_option('garantia_mail_aprobado_cuerpo', sanitize_textarea_field($_POST['garantia_mail_aprobado_cuerpo']));
        update_option('garantia_mail_rechazado_asunto', sanitize_text_field($_POST['garantia_mail_rechazado_asunto']));
        update_option('garantia_mail_rechazado_cuerpo', sanitize_textarea_field($_POST['garantia_mail_rechazado_cuerpo']));
        update_option('garantia_mail_postrechazo_asunto', sanitize_text_field($_POST['garantia_mail_postrechazo_asunto']));
        update_option('garantia_mail_postrechazo_cuerpo', sanitize_textarea_field($_POST['garantia_mail_postrechazo_cuerpo']));
        update_option('garantia_tiempo_limite_info', intval($_POST['garantia_tiempo_limite_info']));
        update_option('garantia_motivo_rechazo_timeout', sanitize_text_field($_POST['garantia_motivo_rechazo_timeout']));
        update_option('garantia_consejos_texto', wp_kses_post($_POST['garantia_consejos_texto']));
        update_option('dias_devolucion_error', intval($_POST['dias_devolucion_error']));
        update_option('instrucciones_devolucion_error', sanitize_textarea_field($_POST['instrucciones_devolucion_error']));
        update_option('dias_validez_cupon_rma', intval($_POST['dias_validez_cupon_rma']));
    }
}