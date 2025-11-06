<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Clase para manejar integraciones con WhatsApp via Oct8ne
 */
class WC_Garantias_WhatsApp {
    
    private static $api_base = 'https://messaging-usa-api.oct8ne.com/api/v1.0';
    
    /**
     * Inicializar la clase
     */
    public static function init() {
        // Agregar menú de admin
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 15);
        
        // Ajax para obtener plantillas
        add_action('wp_ajax_garantias_get_whatsapp_templates', array(__CLASS__, 'ajax_get_templates'));
        
        // Ajax para probar envío
        add_action('wp_ajax_garantias_test_whatsapp', array(__CLASS__, 'ajax_test_message'));
        add_action('wp_ajax_garantias_guardar_variables', array(__CLASS__, 'ajax_guardar_variables'));
    }
    
    /**
     * Agregar menú en admin
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'wc-garantias-dashboard',
            'WhatsApp',
            'WhatsApp',
            'manage_woocommerce',
            'wc-garantias-whatsapp',
            array(__CLASS__, 'admin_page')
        );
    }
    
    /**
     * Obtener configuración de API
     */
    private static function get_api_config() {
        return array(
            'host' => get_option('garantias_whatsapp_host', 'https://messaging-usa-api.oct8ne.com'),
            'account_id' => get_option('garantias_whatsapp_account_id', ''),
            'token' => get_option('garantias_whatsapp_token', ''),
            'provider' => get_option('garantias_whatsapp_provider', '4'),
            'sender' => get_option('garantias_whatsapp_sender', ''),
            'namespace' => get_option('garantias_whatsapp_namespace', '')
        );
    }
    
    /**
/**
     * Realizar llamada a API Oct8ne
     */
    private static function api_call($endpoint, $method = 'GET', $data = null) {
        $config = self::get_api_config();
        
        if (empty($config['token']) || empty($config['account_id'])) {
            return array('error' => 'Configuración de API incompleta');
        }
        
        $url = $config['host'] . '/api/v1.0' . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'x-oct8ne-token' => $config['token'],
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        return json_decode($body, true);
    }
    
    /**
     * Obtener plantillas disponibles
     */
    public static function get_templates() {
        $config = self::get_api_config();
        $endpoint = sprintf(
            '/whatsapp/templates/%s/%s/%s/Templates',
            $config['account_id'],
            $config['provider'],
            $config['sender']
        );
        
        return self::api_call($endpoint);
    }
    
    /**
     * Enviar mensaje de plantilla
     */
    public static function send_template($template_name, $phone, $parameters = array(), $options = array()) {
        $config = self::get_api_config();
        
        // Validar que la plantilla esté configurada
        $template_config = get_option('garantias_whatsapp_template_' . $template_name);
        if (!$template_config || empty($template_config['name'])) {
            return false;
        }
        
        // Verificar si está habilitada
        if (empty($template_config['enabled'])) {
            return false;
        }
    
        $endpoint = sprintf(
            '/whatsapp/templates/%s/%s/%s',
            $config['account_id'],
            $config['provider'],
            $config['sender']
        );
        
        // Construir componentes según los parámetros
        $components = array();
        
        // Si hay parámetros para el body
        if (!empty($parameters)) {
            $body_params = array();
            foreach ($parameters as $param) {
                $body_params[] = array(
                    'type' => 'text',
                    'text' => $param
                );
            }
            
            $components[] = array(
                'type' => 'body',
                'parameters' => $body_params
            );
        }
        
        $data = array(
            'template' => array(
                'name' => $template_config['name'],
                'language' => $template_config['language'] ?? 'es',
                'namespace' => $config['namespace']
            ),
            'targets' => array(
                array(
                    'number' => $phone,
                    'components' => $components
                )
            )
        );
        
        // Agregar opciones adicionales si existen
        if (!empty($options['targetQueueId'])) {
            $data['targetQueueId'] = $options['targetQueueId'];
        }
        
        $result = self::api_call($endpoint, 'POST', $data);
        
        // Log del resultado
        if (isset($result['deliveryId'])) {
            return $result['deliveryId'];
        } else {
            return false;
        }
    }
    
    /**
     * Página de administración
     */
    public static function admin_page() {
        // Guardar configuración si se envió
        if (isset($_POST['guardar_config']) && wp_verify_nonce($_POST['_wpnonce'], 'garantias_whatsapp_config')) {
            update_option('garantias_whatsapp_host', sanitize_text_field($_POST['host']));
            update_option('garantias_whatsapp_account_id', sanitize_text_field($_POST['account_id']));
            update_option('garantias_whatsapp_token', sanitize_text_field($_POST['token']));
            update_option('garantias_whatsapp_provider', sanitize_text_field($_POST['provider']));
            update_option('garantias_whatsapp_sender', sanitize_text_field($_POST['sender']));
            update_option('garantias_whatsapp_namespace', sanitize_text_field($_POST['namespace']));

            echo '<div class="notice notice-success"><p>Configuración guardada correctamente.</p></div>';
        }
        
        // Guardar plantillas si se enviaron
        if (isset($_POST['guardar_plantillas']) && wp_verify_nonce($_POST['_wpnonce'], 'garantias_whatsapp_plantillas')) {
            $eventos = array(
                'nuevo_reclamo',
                'confirmacion',
                'aprobada',
                'rechazada',
                'info_solicitada',
                'destruccion_aprobada',
                'etiqueta_disponible',
                'recepcion_parcial',
                'devolucion_error'
                
            );
            
            foreach ($eventos as $evento) {
                $template_data = array(
                    'enabled' => isset($_POST[$evento . '_enabled']) ? 1 : 0,
                    'name' => sanitize_text_field($_POST[$evento . '_name'] ?? ''),
                    'language' => sanitize_text_field($_POST[$evento . '_language'] ?? 'es')
                );
                update_option('garantias_whatsapp_template_' . $evento, $template_data);
            }
            
            echo '<div class="notice notice-success"><p>Plantillas guardadas correctamente.</p></div>';
        }
        
        $config = self::get_api_config();
        ?>
        
        <div class="wrap">
            <h1>Configuracin WhatsApp - Oct8ne</h1>
            
            <!-- Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="#config" class="nav-tab nav-tab-active" onclick="switchTab('config')">Configuración API</a>
                <a href="#templates" class="nav-tab" onclick="switchTab('templates')">Plantillas</a>
                <a href="#test" class="nav-tab" onclick="switchTab('test')">Probar Envío</a>
            </h2>
            
            <!-- Tab Configuración -->
            <div id="tab-config" class="tab-content" style="display: block;">
                <form method="post">
                    <?php wp_nonce_field('garantias_whatsapp_config'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Host API</th>
                            <td>
                                <input type="text" name="host" value="<?php echo esc_attr($config['host']); ?>" class="regular-text" />
                                <p class="description">URL base de la API Oct8ne</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Account ID</th>
                            <td>
                                <input type="text" name="account_id" value="<?php echo esc_attr($config['account_id']); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Token</th>
                            <td>
                                <input type="text" name="token" value="<?php echo esc_attr($config['token']); ?>" class="regular-text" />
                                <p class="description">Token de autenticación Oct8ne</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Provider</th>
                            <td>
                                <select name="provider">
                                    <option value="2" <?php selected($config['provider'], '2'); ?>>Vonage</option>
                                    <option value="4" <?php selected($config['provider'], '4'); ?>>360Dialog</option>
                                    <option value="6" <?php selected($config['provider'], '6'); ?>>WhatsApp Business Cloud API</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Número Sender</th>
                            <td>
                                <input type="text" name="sender" value="<?php echo esc_attr($config['sender']); ?>" class="regular-text" />
                                <p class="description">Número de WhatsApp que envía (con cdigo de país)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Namespace</th>
                            <td>
                                <input type="text" name="namespace" value="<?php echo esc_attr($config['namespace']); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="guardar_config" class="button-primary">Guardar Configuración</button>
                        <button type="button" onclick="testConnection()" class="button">Probar Conexión</button>
                    </p>
                </form>
            </div>
            
            <!-- Tab Plantillas -->
            <div id="tab-templates" class="tab-content" style="display: none;">
                <form method="post">
                    <?php wp_nonce_field('garantias_whatsapp_plantillas'); ?>
                    
                    <p>Configura qué plantilla de WhatsApp usar para cada evento del sistema:</p>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Habilitado</th>
                                <th>Nombre Plantilla</th>
                                <th>Idioma</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $eventos = array(
                                'nuevo_reclamo' => 'Nuevo Reclamo Creado',
                                'confirmacion' => 'Confirmacin de Recepción',
                                'aprobada' => 'Garantía Aprobada',
                                'rechazada' => 'Garantía Rechazada',
                                'info_solicitada' => 'Informacin Solicitada',
                                'destruccion_aprobada' => 'Destrucción Aprobada',
                                'etiqueta_disponible' => 'Etiqueta de Envío Lista',
                                'recepcion_parcial' => 'Recepcion Parcial',
                                'devolucion_error' => 'Devolucin por Error de Compra'
                            );
                            
                            foreach ($eventos as $key => $label):
                                $template = get_option('garantias_whatsapp_template_' . $key, array());
                            ?>
                            <tr>
                                <td><strong><?php echo $label; ?></strong></td>
                                <td>
                                    <input type="checkbox" name="<?php echo $key; ?>_enabled" value="1" 
                                           <?php checked(!empty($template['enabled'])); ?> />
                                </td>
                                <td>
                                    <input type="text" name="<?php echo $key; ?>_name" 
                                           value="<?php echo esc_attr($template['name'] ?? ''); ?>" 
                                           class="regular-text" />
                                </td>
                                <td>
                                    <input type="text" name="<?php echo $key; ?>_language" 
                                           value="<?php echo esc_attr($template['language'] ?? 'es'); ?>" 
                                           style="width: 50px;" />
                                </td>
                                <td>
                                    <button type="button" class="button-small" onclick="loadTemplates('<?php echo $key; ?>')">
                                        Cargar Plantillas
                                    </button>
                                    <button type="button" class="button-small" onclick="configurarVariables('<?php echo $key; ?>')" style="margin-left: 5px;">
                                        <i class="fas fa-cog"></i> Variables
                                    </button>
                                </td>
                                <td>
                                    <button type="button" class="button-small" onclick="loadTemplates('<?php echo $key; ?>')">
                                        Cargar Plantillas
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="guardar_plantillas" class="button-primary">Guardar Plantillas</button>
                    </p>
                </form>
                
                <!-- Modal para configurar variables -->
                <div id="variables-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border: 1px solid #ccc; z-index: 9999; max-height: 80vh; overflow-y: auto; width: 700px; border-radius: 8px; box-shadow: 0 5px 30px rgba(0,0,0,0.3);">
                    <h3>Configurar Variables de la Plantilla</h3>
                    <div id="variables-content">
                        <p>Selecciona qué variable usar para cada parámetro de la plantilla:</p>
                        <form id="variables-form">
                            <input type="hidden" id="var-evento" name="evento" value="">
                            <div id="parametros-list"></div>
                            <div style="margin-top: 20px;">
                                <button type="button" onclick="guardarVariables()" class="button-primary">Guardar Variables</button>
                                <button type="button" onclick="closeVariablesModal()" class="button" style="margin-left: 10px;">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <style>
                .variable-row {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f5f5f5;
                    border-radius: 5px;
                }
                .variable-row label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .variable-row select {
                    width: 100%;
                    padding: 5px;
                }
                </style>
                
                <!-- Modal para seleccionar plantillas -->
                <div id="templates-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border: 1px solid #ccc; z-index: 9999; max-height: 80vh; overflow-y: auto; width: 600px;">
                    <h3>Seleccionar Plantilla</h3>
                    <div id="templates-list"></div>
                    <button onclick="closeTemplatesModal()" class="button">Cerrar</button>
                </div>
            </div>
            
            <!-- Tab Test -->
            <div id="tab-test" class="tab-content" style="display: none;">
                <h3>Probar Envío de WhatsApp</h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Número de Telfono</th>
                        <td>
                            <input type="text" id="test-phone" class="regular-text" placeholder="5491234567890" />
                            <p class="description">Incluir cdigo de país sin +</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Plantilla</th>
                        <td>
                            <select id="test-template">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($eventos as $key => $label): 
                                    $template = get_option('garantias_whatsapp_template_' . $key, array());
                                    if (!empty($template['name'])):
                                ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?> (<?php echo $template['name']; ?>)</option>
                                <?php endif; endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Parámetros</th>
                        <td>
                            <input type="text" id="test-param1" class="regular-text" placeholder="Parmetro 1" /><br>
                            <input type="text" id="test-param2" class="regular-text" placeholder="Parámetro 2" style="margin-top: 5px;" /><br>
                            <input type="text" id="test-param3" class="regular-text" placeholder="Parámetro 3" style="margin-top: 5px;" />
                        </td>
                    </tr>
                </table>
                
                <p>
                    <button onclick="sendTestMessage()" class="button-primary">Enviar Mensaje de Prueba</button>
                </p>
                
                <div id="test-result" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <script>
        // Variables PHP para JavaScript
        var garantiasWhatsAppConfig = {
            variablesGuardadas: <?php echo json_encode(get_option('garantias_whatsapp_variables', [])); ?>,
            nonce: '<?php echo wp_create_nonce("garantias_whatsapp_nonce"); ?>'
        };
        </script>
        
        <script>
        function switchTab(tab) {
            // Ocultar todos los tabs
            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.style.display = 'none';
            });
            document.querySelectorAll('.nav-tab').forEach(function(el) {
                el.classList.remove('nav-tab-active');
            });
            
            // Mostrar tab seleccionado
            document.getElementById('tab-' + tab).style.display = 'block';
            event.target.classList.add('nav-tab-active');
        }
        
        function testConnection() {
            // Implementar test de conexión
            jQuery.post(ajaxurl, {
                action: 'garantias_test_whatsapp_connection'
            }, function(response) {
                if (response.success) {
                    alert('Conexión exitosa!');
                } else {
                    alert('Error de conexión: ' + response.data);
                }
            });
        }
        
        function loadTemplates(evento) {
            jQuery.post(ajaxurl, {
                action: 'garantias_get_whatsapp_templates'
            }, function(response) {
                if (response.success && response.data) {
                    var html = '<table class="widefat"><thead><tr><th>Nombre</th><th>Estado</th><th>Idioma</th><th>Accin</th></tr></thead><tbody>';
                    response.data.forEach(function(template) {
                        html += '<tr>';
                        html += '<td>' + template.name + '</td>';
                        html += '<td>' + template.status + '</td>';
                        html += '<td>' + template.language + '</td>';
                        html += '<td><button onclick="selectTemplate(\'' + evento + '\', \'' + template.name + '\', \'' + template.language + '\')" class="button-small">Seleccionar</button></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    
                    document.getElementById('templates-list').innerHTML = html;
                    document.getElementById('templates-modal').style.display = 'block';
                }
            });
        }
        
        function selectTemplate(evento, name, language) {
            document.querySelector('input[name="' + evento + '_name"]').value = name;
            document.querySelector('input[name="' + evento + '_language"]').value = language;
            closeTemplatesModal();
        }
        
        function closeTemplatesModal() {
            document.getElementById('templates-modal').style.display = 'none';
        }
        
        function sendTestMessage() {
            var phone = document.getElementById('test-phone').value;
            var template = document.getElementById('test-template').value;
            var params = [];
            
            if (document.getElementById('test-param1').value) {
                params.push(document.getElementById('test-param1').value);
            }
            if (document.getElementById('test-param2').value) {
                params.push(document.getElementById('test-param2').value);
            }
            if (document.getElementById('test-param3').value) {
                params.push(document.getElementById('test-param3').value);
            }
            
            jQuery.post(ajaxurl, {
                action: 'garantias_test_whatsapp',
                phone: phone,
                template: template,
                parameters: params
            }, function(response) {
                var resultDiv = document.getElementById('test-result');
                if (response.success) {
                    resultDiv.innerHTML = '<div class="notice notice-success"><p>Mensaje enviado! ID: ' + response.data + '</p></div>';
                } else {
                    resultDiv.innerHTML = '<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>';
                }
            });
        }
        
        // Variables disponibles para WhatsApp
        var variablesDisponibles = {
            'cliente': 'Nombre del cliente',
            'codigo': 'Número de reclamo GENERAL',
            'codigo_item': 'Número de reclamo del ITEM',
            'producto': 'Nombre del producto',
            'motivo_info': 'Motivo de solicitud de información',
            'motivo': 'Motivo del rechazo',
            'cupon': 'Número del cupón generado',
            'importe': 'Importe del cupón generado'
        };
        
        function configurarVariables(evento) {
            document.getElementById('var-evento').value = evento;
            
            // Mostrar configuración según el evento
            var parametrosHtml = '';
            var numParametros = 3; // Por defecto
            
            // Definir cuántos parámetros necesita cada plantilla
            switch(evento) {
                case 'nuevo_reclamo':
                case 'confirmacion':
                    numParametros = 2;
                    break;
                case 'aprobada':
                    numParametros = 3;
                    break;
                case 'recepcion_parcial':
                    numParametros = 2;
                    break;
                case 'rechazada':
                    numParametros = 2;
                    break;
                case 'destruccion_rechazada':
                    numParametros = 3;
                    break;
                case 'info_solicitada':
                    numParametros = 2;
                    break;
                case 'destruccion_aprobada':
                    numParametros = 2;
                    break;
                 case 'etiqueta_disponible':
                    numParametros = 1;
                    break;         
                case 'devolucion_error':
                    numParametros = 2;
                    break;    
            }
            
            // Obtener configuración guardada
            var configGuardada = garantiasWhatsAppConfig.variablesGuardadas;
            var configEvento = configGuardada[evento] || {};
            
            for (var i = 1; i <= numParametros; i++) {
                parametrosHtml += '<div class="variable-row">';
                parametrosHtml += '<label>Parámetro ' + i + ':</label>';
                parametrosHtml += '<select name="param_' + i + '">';
                parametrosHtml += '<option value="">-- Seleccionar --</option>';
                
                for (var key in variablesDisponibles) {
                    var selected = (configEvento['param_' + i] === key) ? 'selected' : '';
                    parametrosHtml += '<option value="' + key + '" ' + selected + '>' + variablesDisponibles[key] + '</option>';
                }
                
                parametrosHtml += '</select>';
                parametrosHtml += '</div>';
            }
            
            document.getElementById('parametros-list').innerHTML = parametrosHtml;
            document.getElementById('variables-modal').style.display = 'block';
        }
        
        function closeVariablesModal() {
            document.getElementById('variables-modal').style.display = 'none';
        }
        
        function guardarVariables() {
            var evento = document.getElementById('var-evento').value;
            var config = {};
            
            jQuery('#variables-form select').each(function() {
                var name = jQuery(this).attr('name');
                config[name] = jQuery(this).val();
            });
            
            // Guardar va AJAX
            jQuery.post(ajaxurl, {
                action: 'garantias_guardar_variables',
                evento: evento,
                config: config,
                nonce: garantiasWhatsAppConfig.nonce
            }, function(response) {
                if (response.success) {
                    alert('Variables guardadas correctamente');
                    closeVariablesModal();
                } else {
                    alert('Error al guardar las variables');
                }
            });
        }
        </script>
        
        <?php
    }
    
    /**
     * Ajax handler para obtener plantillas
     */
    public static function ajax_get_templates() {
        $templates = self::get_templates();
        
        if (is_array($templates) && !isset($templates['error'])) {
            wp_send_json_success($templates);
        } else {
            wp_send_json_error($templates['error'] ?? 'Error desconocido');
        }
    }
    
    /**
     * Ajax handler para test de mensaje
     */
    public static function ajax_test_message() {
        $phone = sanitize_text_field($_POST['phone']);
        $template = sanitize_text_field($_POST['template']);
        $parameters = $_POST['parameters'] ?? array();
        
        $result = self::send_template($template, $phone, $parameters);
        
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Error al enviar mensaje');
        }
    }
    /**
     * Ajax handler para guardar configuracin de variables
     */
    public static function ajax_guardar_variables() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'garantias_whatsapp_nonce')) {
            wp_send_json_error('Nonce invlido');
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        $evento = sanitize_text_field($_POST['evento']);
        $config = $_POST['config'];
        
        // Obtener configuracin actual
        $variables_config = get_option('garantias_whatsapp_variables', array());
        
        // Actualizar configuración para este evento
        $variables_config[$evento] = array();
        foreach ($config as $key => $value) {
            $variables_config[$evento][sanitize_text_field($key)] = sanitize_text_field($value);
        }
        
        // Guardar
        update_option('garantias_whatsapp_variables', $variables_config);
        
        wp_send_json_success('Variables guardadas correctamente');
    }
}

// Inicializar
WC_Garantias_WhatsApp::init();