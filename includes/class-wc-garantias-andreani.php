<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integración con Andreani para el plugin de garantías
 * Basado en el plugin Wanderlust Andreani existente
 */
class WC_Garantias_Andreani {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_submenu' ], 61 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_andreani_test_connection', [ __CLASS__, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_andreani_generar_etiqueta', [ __CLASS__, 'ajax_generar_etiqueta' ] );
        
        // Hook para mostrar botón "Generar con Andreani" en las garantías
        add_action( 'admin_footer', [ __CLASS__, 'add_andreani_button_script' ] );
    }

    /**
     * Agregar submenú de Andreani en Garantías
     */
    public static function add_admin_submenu() {
        add_submenu_page(
            'wc-garantias-dashboard',
            'Configuración Andreani',
            'Andreani',
            'manage_woocommerce',
            'wc-garantias-andreani',
            [ __CLASS__, 'andreani_config_page' ]
        );
    }

    /**
     * Enqueue assets para la página de Andreani
     */
    public static function enqueue_assets( $hook ) {
        if ( 'garantias_page_wc-garantias-andreani' !== $hook ) {
            return;
        }
    
        wp_enqueue_script( 'wc-garantias-andreani', WC_GARANTIAS_URL . 'assets/js/andreani.js', ['jquery'], '1.0.0', true );
        wp_localize_script( 'wc-garantias-andreani', 'andreani_ajax', [
            'url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'andreani_nonce' )
        ] );
    }

    /**
     * Página de configuración de Andreani
     */
    public static function andreani_config_page() {
        // Procesar guardado de configuración
        if ( isset( $_POST['guardar_andreani_config'] ) && check_admin_referer( 'andreani_config_save' ) ) {
            self::save_andreani_config();
            echo '<div class="notice notice-success"><p>Configuración de Andreani guardada correctamente.</p></div>';
        }

        $config = self::get_andreani_config();
        ?>
        <div class="wrap">
            <h1>Configuración Andreani - Garantías</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'andreani_config_save' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2">
                            <h2>Credenciales API</h2>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_api_user">Usuario API</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_api_user" name="andreani_api_user" 
                                   value="<?php echo esc_attr( $config['api_user'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_api_password">Contraseña API</label>
                        </th>
                        <td>
                            <input type="password" id="andreani_api_password" name="andreani_api_password" 
                                   value="<?php echo esc_attr( $config['api_password'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_api_key" name="andreani_api_key" 
                                   value="<?php echo esc_attr( $config['api_key'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_nro_cuenta">Código de Cliente</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_nro_cuenta" name="andreani_nro_cuenta" 
                                   value="<?php echo esc_attr( $config['nro_cuenta'] ); ?>" 
                                   style="width: 300px;" />
                            <p class="description">Ejemplo: 0012007618</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_entorno">Entorno</label>
                        </th>
                        <td>
                            <select id="andreani_entorno" name="andreani_entorno">
                                <option value="test" <?php selected( $config['entorno'], 'test' ); ?>>Testeo</option>
                                <option value="prod" <?php selected( $config['entorno'], 'prod' ); ?>>Producción</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2">
                            <h2>Datos del Remitente (WiFix)</h2>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_nombre">Nombre y Apellido</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_nombre" name="andreani_remitente_nombre" 
                                   value="<?php echo esc_attr( $config['remitente_nombre'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_email">Email</label>
                        </th>
                        <td>
                            <input type="email" id="andreani_remitente_email" name="andreani_remitente_email" 
                                   value="<?php echo esc_attr( $config['remitente_email'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_calle">Calle</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_calle" name="andreani_remitente_calle" 
                                   value="<?php echo esc_attr( $config['remitente_calle'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_numero">Número</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_numero" name="andreani_remitente_numero" 
                                   value="<?php echo esc_attr( $config['remitente_numero'] ); ?>" 
                                   style="width: 100px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_cp">Código Postal</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_cp" name="andreani_remitente_cp" 
                                   value="<?php echo esc_attr( $config['remitente_cp'] ); ?>" 
                                   style="width: 100px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_localidad">Localidad</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_localidad" name="andreani_remitente_localidad" 
                                   value="<?php echo esc_attr( $config['remitente_localidad'] ); ?>" 
                                   style="width: 300px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_provincia">Provincia</label>
                        </th>
                        <td>
                            <select id="andreani_remitente_provincia" name="andreani_remitente_provincia">
                                <option value="">Seleccionar provincia</option>
                                <?php
                                $provincias = self::get_provincias_argentina();
                                foreach ( $provincias as $codigo => $nombre ) {
                                    printf( 
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr( $codigo ),
                                        selected( $config['remitente_provincia'], $codigo, false ),
                                        esc_html( $nombre )
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_telefono">Teléfono</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_telefono" name="andreani_remitente_telefono" 
                                   value="<?php echo esc_attr( $config['remitente_telefono'] ); ?>" 
                                   style="width: 200px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_remitente_dni">DNI</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_remitente_dni" name="andreani_remitente_dni" 
                                   value="<?php echo esc_attr( $config['remitente_dni'] ); ?>" 
                                   style="width: 200px;" />
                        </td>
                    </tr>

                    <tr>
                        <th colspan="2">
                            <h2>Configuracin de Envío</h2>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_contrato">Número de Contrato</label>
                        </th>
                        <td>
                            <input type="text" id="andreani_contrato" name="andreani_contrato" 
                                   value="<?php echo esc_attr( $config['contrato'] ?: '400018508' ); ?>" 
                                   style="width: 200px;" />
                            <p class="description">Contrato fijo para Sucursal a Puerta</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_reducir_peso">Reducir Peso (%)</label>
                        </th>
                        <td>
                            <input type="number" id="andreani_reducir_peso" name="andreani_reducir_peso" 
                                   value="<?php echo esc_attr( $config['reducir_peso'] ?: '50' ); ?>" 
                                   min="1" max="100" style="width: 100px;" />
                            <p class="description">Porcentaje del peso original (50 = mitad del peso)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_reducir_tamano">Reducir Tamaño (%)</label>
                        </th>
                        <td>
                            <input type="number" id="andreani_reducir_tamano" name="andreani_reducir_tamano" 
                                   value="<?php echo esc_attr( $config['reducir_tamano'] ?: '50' ); ?>" 
                                   min="1" max="100" style="width: 100px;" />
                            <p class="description">Porcentaje del tamaño original (50 = mitad del tamaño)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="andreani_valor_declarado">Porcentaje Valor Declarado (%)</label>
                        </th>
                        <td>
                            <input type="number" id="andreani_valor_declarado" name="andreani_valor_declarado" 
                                   value="<?php echo esc_attr( $config['valor_declarado'] ?: '10' ); ?>" 
                                   min="1" max="100" style="width: 100px;" />
                            <p class="description">Porcentaje del valor real de la mercadería (10 = 10% del valor)</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="guardar_andreani_config" class="button-primary" value="Guardar Configuración" />
                    <button type="button" id="test_andreani_connection" class="button">Probar Conexión</button>
                </p>
            </form>

            <div id="andreani_test_result" style="margin-top: 20px;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#test_andreani_connection').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Probando...');
                
                $.ajax({
                    url: andreani_ajax.url,
                    type: 'POST',
                    data: {
                        action: 'andreani_test_connection',
                        nonce: andreani_ajax.nonce,
                        api_user: $('#andreani_api_user').val(),
                        api_password: $('#andreani_api_password').val(),
                        api_key: $('#andreani_api_key').val(),
                        nro_cuenta: $('#andreani_nro_cuenta').val(),
                        entorno: $('#andreani_entorno').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#andreani_test_result').html('<div class="notice notice-success"><p>✓ Conexión exitosa con Andreani</p></div>');
                        } else {
                            $('#andreani_test_result').html('<div class="notice notice-error"><p>✗ Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#andreani_test_result').html('<div class="notice notice-error"><p>✗ Error de conexión</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Probar Conexión');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Guardar configuración de Andreani
     */
    private static function save_andreani_config() {
        $config = [
            'api_user' => sanitize_text_field( $_POST['andreani_api_user'] ?? '' ),
            'api_password' => sanitize_text_field( $_POST['andreani_api_password'] ?? '' ),
            'api_key' => sanitize_text_field( $_POST['andreani_api_key'] ?? '' ),
            'nro_cuenta' => sanitize_text_field( $_POST['andreani_nro_cuenta'] ?? '' ),
            'entorno' => sanitize_text_field( $_POST['andreani_entorno'] ?? 'test' ),
            'remitente_nombre' => sanitize_text_field( $_POST['andreani_remitente_nombre'] ?? '' ),
            'remitente_email' => sanitize_email( $_POST['andreani_remitente_email'] ?? '' ),
            'remitente_calle' => sanitize_text_field( $_POST['andreani_remitente_calle'] ?? '' ),
            'remitente_numero' => sanitize_text_field( $_POST['andreani_remitente_numero'] ?? '' ),
            'remitente_cp' => sanitize_text_field( $_POST['andreani_remitente_cp'] ?? '' ),
            'remitente_localidad' => sanitize_text_field( $_POST['andreani_remitente_localidad'] ?? '' ),
            'remitente_provincia' => sanitize_text_field( $_POST['andreani_remitente_provincia'] ?? '' ),
            'remitente_telefono' => sanitize_text_field( $_POST['andreani_remitente_telefono'] ?? '' ),
            'remitente_dni' => sanitize_text_field( $_POST['andreani_remitente_dni'] ?? '' ),
            'contrato' => sanitize_text_field( $_POST['andreani_contrato'] ?? '400018508' ),
            'reducir_peso' => intval( $_POST['andreani_reducir_peso'] ?? 50 ),
            'reducir_tamano' => intval( $_POST['andreani_reducir_tamano'] ?? 50 ),
            'valor_declarado' => intval( $_POST['andreani_valor_declarado'] ?? 10 ),
        ];

        update_option( 'wc_garantias_andreani_config', $config );
    }

    /**
     * Obtener configuración de Andreani
     */
    public static function get_andreani_config() {
        $defaults = [
            'api_user' => '',
            'api_password' => '',
            'api_key' => '',
            'nro_cuenta' => '',
            'entorno' => 'test',
            'remitente_nombre' => '',
            'remitente_email' => '',
            'remitente_calle' => '',
            'remitente_numero' => '',
            'remitente_cp' => '',
            'remitente_localidad' => '',
            'remitente_provincia' => '',
            'remitente_telefono' => '',
            'remitente_dni' => '',
            'contrato' => '400018508',
            'reducir_peso' => 50,
            'reducir_tamano' => 50,
            'valor_declarado' => 10,
        ];

        $config = get_option( 'wc_garantias_andreani_config', [] );
        return wp_parse_args( $config, $defaults );
    }

    /**
     * Provincias de Argentina
     */
    public static function get_provincias_argentina() {
        return [
            'AR-C' => 'Ciudad Autónoma de Buenos Aires',
            'AR-B' => 'Buenos Aires',
            'AR-K' => 'Catamarca',
            'AR-H' => 'Chaco',
            'AR-U' => 'Chubut',
            'AR-X' => 'Córdoba',
            'AR-W' => 'Corrientes',
            'AR-E' => 'Entre Ríos',
            'AR-P' => 'Formosa',
            'AR-Y' => 'Jujuy',
            'AR-L' => 'La Pampa',
            'AR-F' => 'La Rioja',
            'AR-M' => 'Mendoza',
            'AR-N' => 'Misiones',
            'AR-Q' => 'Neuquén',
            'AR-R' => 'Río Negro',
            'AR-A' => 'Salta',
            'AR-J' => 'San Juan',
            'AR-D' => 'San Luis',
            'AR-Z' => 'Santa Cruz',
            'AR-S' => 'Santa Fe',
            'AR-G' => 'Santiago del Estero',
            'AR-V' => 'Tierra del Fuego',
            'AR-T' => 'Tucumán'
        ];
    }

    /**
     * AJAX: Probar conexión con Andreani
     */
    public static function ajax_test_connection() {
        check_ajax_referer( 'andreani_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Sin permisos suficientes' );
        }

        $api_user = sanitize_text_field( $_POST['api_user'] ?? '' );
        $api_password = sanitize_text_field( $_POST['api_password'] ?? '' );
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
        $nro_cuenta = sanitize_text_field( $_POST['nro_cuenta'] ?? '' );
        $entorno = sanitize_text_field( $_POST['entorno'] ?? 'test' );

        if ( empty( $api_user ) || empty( $api_password ) || empty( $api_key ) || empty( $nro_cuenta ) ) {
            wp_send_json_error( 'Faltan datos de configuración' );
        }

        // Usar la misma URL que el plugin Wanderlust
        $url = 'https://andreani.wanderlust-webdesign.com/';

        $params = [
            'method' => [
                'test_connection' => [
                    'api_user' => $api_user,
                    'api_password' => $api_password,
                    'api_key' => $api_key,
                    'api_nrocuenta' => $nro_cuenta,
                    'api_confirmarretiro' => $entorno
                ]
            ]
        ];
        // DEBUG: Log de los parámetros que enviamos
        error_log( 'ANDREANI DEBUG - URL: ' . $url );
        error_log( 'ANDREANI DEBUG - Params: ' . print_r( $params, true ) );
        
        $response = wp_remote_post( $url, [
            'method' => 'POST',
            'timeout' => 15,
            'headers' => [],
            'body' => $params,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Error de conexión: ' . $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            wp_send_json_error( $data['error'] );
        }

        wp_send_json_success( 'Conexión exitosa' );
    }

    /**
     * Generar etiqueta de Andreani para una garanta
     */
    public static function generar_etiqueta_garantia( $garantia_id ) {
        $config = self::get_andreani_config();
        
        // Validar configuración
        if ( empty( $config['api_user'] ) || empty( $config['api_password'] ) || empty( $config['api_key'] ) ) {
            return new WP_Error( 'config_error', 'Configuracin de Andreani incompleta' );
        }

        // Obtener datos de la garantía
        $garantia = get_post( $garantia_id );
        if ( ! $garantia ) {
            return new WP_Error( 'garantia_error', 'Garanta no encontrada' );
        }

        $cliente_id = get_post_meta( $garantia_id, '_cliente', true );
        $user = get_userdata( $cliente_id );
        if ( ! $user ) {
            return new WP_Error( 'cliente_error', 'Cliente no encontrado' );
        }

        // Obtener datos del cliente desde WooCommerce
        $cliente_nombre = $user->first_name . ' ' . $user->last_name;
        $cliente_email = $user->user_email;
        $cliente_telefono = get_user_meta( $cliente_id, 'billing_phone', true );
        $cliente_direccion = get_user_meta( $cliente_id, 'billing_address_1', true );
        $cliente_ciudad = get_user_meta( $cliente_id, 'billing_city', true );
        $cliente_cp = get_user_meta( $cliente_id, 'billing_postcode', true );
        $cliente_estado = get_user_meta( $cliente_id, 'billing_state', true );

        // Obtener información de los productos de la garantía
        $items = get_post_meta( $garantia_id, '_items_reclamados', true );
        $peso_total = 0;
        $valor_total = 0;
        $volumen_total = 0;

        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $producto = wc_get_product( $item['producto_id'] );
                if ( $producto ) {
                    $cantidad = intval( $item['cantidad'] ?? 1 );
                    
                    // Peso
                    $peso = floatval( $producto->get_weight() ?: 1 );
                    $peso_total += $peso * $cantidad;
                    
                    // Valor
                    $precio = floatval( $producto->get_price() );
                    $valor_total += $precio * $cantidad;
                    
                    // Dimensiones para volumen
                    $largo = floatval( $producto->get_length() ?: 10 );
                    $ancho = floatval( $producto->get_width() ?: 10 );
                    $alto = floatval( $producto->get_height() ?: 5 );
                    $volumen_total += ( $largo * $ancho * $alto ) * $cantidad;
                }
            }
        }

        // Aplicar reducciones configuradas
        $peso_final = $peso_total * ( $config['reducir_peso'] / 100 );
        $valor_declarado = $valor_total * ( $config['valor_declarado'] / 100 );
        
        // Reducir dimensiones proporcionalmente
        $factor_reduccion = sqrt( $config['reducir_tamano'] / 100 );
        $volumen_final = $volumen_total * ( $config['reducir_tamano'] / 100 );

        // Preparar datos para Andreani - INVERTIDO: WiFix es destino, cliente es origen
        $codigo_unico = get_post_meta( $garantia_id, '_codigo_unico', true );
        
        // WiFix es el DESTINATARIO
        $destino_datos = [
            'nroremito' => $codigo_unico,
            'apellido' => explode(' ', $config['remitente_nombre'])[1] ?? 'WiFix',
            'nombre' => explode(' ', $config['remitente_nombre'])[0] ?? 'WiFix',
            'calle' => $config['remitente_calle'],
            'nro' => $config['remitente_numero'],
            'piso' => '',
            'depto' => '- WiFix - Nave 18',
            'localidad' => $config['remitente_localidad'],
            'provincia' => $config['remitente_provincia'],
            'cp' => $config['remitente_cp'],
            'telefono' => $config['remitente_telefono'],
            'email' => $config['remitente_email'],
            'celular' => $config['remitente_telefono'],
            'dni' => $config['remitente_dni'],
            
        ];
        
        // Cliente es el ORIGEN (quien envía)
        $origen_datos = [
            'origin' => $cliente_cp ?: '1000',
            'api_key' => $config['api_key'],
            'origin_contacto' => $user->display_name,
            'origin_email' => $cliente_email,
            'origin_telefono' => $cliente_telefono ?: '1234567890',
            'origin_dni' => get_user_meta( $cliente_id, 'billing_wooccm12', true ) ?: '12345678',
            'origin_calle' => $cliente_direccion ?: 'Sin dirección',
            'origin_numero' => '1', // Por defecto
            'origin_localidad' => $cliente_ciudad ?: 'Sin ciudad',
            'origin_provincia' => $cliente_estado ?: 'AR-B',
            'api_user' => $config['api_user'],
            'api_password' => $config['api_password'],
            'api_nrocuenta' => $config['nro_cuenta'],
            'api_confirmarretiro' => $config['entorno'],
            'andreani_lenth' => 10, // Dimensiones por defecto
            'andreani_width' => 10,
            'andreani_height' => 5,
            'andreani_amount' => $valor_declarado,
            'andreani_weightb' => $peso_final,
        ];

        // Preparar request para Andreani
        $params = [
            'method' => [
                'get_etiquetas' => [
                    'sucursal_andreani_c' => json_encode(['Sucursal' => '', 'Direccion' => 'Sucursal a Puerta']),
                    'origen_datos' => json_encode( [ $origen_datos ] ),
                    'destino_datos' => json_encode( [ $destino_datos ] ),
                    'chosen_shipping' => 'andreani_wanderlust-sap-400018508api_nrocuenta' . $config['nro_cuenta'] . 'operativa400018508instance_id1',
                ]
            ]
        ];

        // Llamar a la API de Andreani usando la misma URL que Wanderlust
        $url = 'https://andreani.wanderlust-webdesign.com/';
        
        $response = wp_remote_post( $url, [
            'method' => 'POST',
            'timeout' => 90,
            'headers' => [],
            'body' => $params,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Error de conexión con Andreani: ' . $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            // DEBUG: Log completo de la respuesta
            error_log( 'ANDREANI ERROR - Params enviados: ' . print_r( $params, true ) );
            error_log( 'ANDREANI ERROR - Respuesta completa: ' . print_r( $data, true ) );
            return new WP_Error( 'andreani_error', 'Error de Andreani: ' . $data['error'] );
        }

        if ( ! isset( $data['results']['etiqueta'] ) ) {
            return new WP_Error( 'etiqueta_error', 'No se pudo generar la etiqueta' );
        }

        // Guardar etiqueta
        $etiqueta_data = $data['results']['etiqueta'];
        $numero_envio = $data['results']['numeroenvio'] ?? '';

        // Crear directorio si no existe
        $upload_dir = wp_upload_dir();
        $andreani_dir = $upload_dir['basedir'] . '/andreani-garantias/';
        
        if ( ! file_exists( $andreani_dir ) ) {
            wp_mkdir_p( $andreani_dir );
        }
        
        // Verificar permisos de escritura
        if ( ! is_writable( $andreani_dir ) ) {
            error_log( 'ANDREANI PDF - No se puede escribir en directorio: ' . $andreani_dir );
            return new WP_Error( 'pdf_error', 'No se puede escribir en el directorio de etiquetas' );
        }

        // Debug: Ver qué tipo de datos recibimos
        error_log( 'ANDREANI PDF - Tipo de etiqueta_data: ' . gettype( $etiqueta_data ) );
        error_log( 'ANDREANI PDF - Primeros 100 caracteres: ' . substr( $etiqueta_data, 0, 100 ) );
        
        // Guardar PDF
        $filename = 'etiqueta-' . $codigo_unico . '-' . $numero_envio . '.pdf';
        $file_path = $andreani_dir . $filename;
        
        // Determinar si es base64 o URL
        if ( is_string( $etiqueta_data ) ) {
            if ( strpos( $etiqueta_data, 'data:application/pdf;base64,' ) === 0 ) {
                // Es base64 con header completo
                $pdf_data = base64_decode( substr( $etiqueta_data, 28 ) ); // Quitar "data:application/pdf;base64,"
            } elseif ( strpos( $etiqueta_data, 'base64,' ) !== false ) {
                // Es base64 con header parcial
                $pdf_data = base64_decode( substr( $etiqueta_data, strpos( $etiqueta_data, 'base64,' ) + 7 ) );
            } elseif ( filter_var( $etiqueta_data, FILTER_VALIDATE_URL ) ) {
                // Es una URL
                $pdf_response = wp_remote_get( $etiqueta_data, [
                    'timeout' => 30,
                    'headers' => [
                        'User-Agent' => 'WordPress/' . get_bloginfo( 'version' )
                    ]
                ] );
                
                if ( is_wp_error( $pdf_response ) ) {
                    error_log( 'ANDREANI PDF - Error al descargar URL: ' . $pdf_response->get_error_message() );
                    return new WP_Error( 'pdf_error', 'Error al descargar PDF desde URL: ' . $pdf_response->get_error_message() );
                }
                
                $pdf_data = wp_remote_retrieve_body( $pdf_response );
                
                if ( empty( $pdf_data ) ) {
                    return new WP_Error( 'pdf_error', 'El PDF descargado está vacío' );
                }
            } else {
                // Asumir que es base64 sin header
                $pdf_data = base64_decode( $etiqueta_data );
            }
        } else {
            return new WP_Error( 'pdf_error', 'Formato de etiqueta no reconocido' );
        }
        
        // Verificar que tenemos datos válidos
        if ( empty( $pdf_data ) ) {
            return new WP_Error( 'pdf_error', 'Los datos del PDF están vacíos después del procesamiento' );
        }
        
        // Verificar que es realmente un PDF
        if ( substr( $pdf_data, 0, 4 ) !== '%PDF' ) {
            error_log( 'ANDREANI PDF - No es un PDF válido. Primeros 20 bytes: ' . bin2hex( substr( $pdf_data, 0, 20 ) ) );
            return new WP_Error( 'pdf_error', 'Los datos no corresponden a un archivo PDF válido' );
        }

        file_put_contents( $file_path, $pdf_data );

        // Guardar información en la garantía
        $etiqueta_url = $upload_dir['baseurl'] . '/andreani-garantias/' . $filename;
        update_post_meta( $garantia_id, '_andreani_etiqueta_url', $etiqueta_url );
        update_post_meta( $garantia_id, '_andreani_numero_envio', $numero_envio );
        update_post_meta( $garantia_id, '_andreani_fecha_generacion', current_time( 'mysql' ) );
        
        // Guardar también como tracking general para que aparezca el botón de seguimiento
        update_post_meta( $garantia_id, '_numero_tracking_devolucion', $numero_envio );
        
        // NUEVO: Actualizar tracking en todos los items que están en devolución
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (isset($item['estado']) && in_array($item['estado'], ['aprobado_devolver', 'devolucion_en_transito'])) {
                    // Actualizar el tracking del item con el nuevo número
                    $item['tracking_devolucion'] = $numero_envio;
                }
            }
            // Guardar los items actualizados
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
        
        // NUEVO: Notificar al cliente que la etiqueta está disponible
            $cliente_id = get_post_meta($garantia_id, '_cliente', true);
            $user = get_userdata($cliente_id);
            if ($user && $user->user_email) {
                $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
                
                // IMPORTANTE: Obtener el primer item aprobado para devolver
                $items = get_post_meta($garantia_id, '_items_reclamados', true);
                $primer_item_codigo = 'SIN-ITEM';
                
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (isset($item['estado']) && in_array($item['estado'], ['aprobado_devolver', 'devolucion_en_transito'])) {
                            $primer_item_codigo = $item['codigo_item'] ?? 'SIN-ITEM';
                            break;
                        }
                    }
                }
                
                // Obtener dimensiones de cajas desde la configuración
                $caja_pequena_largo = get_option('caja_pequena_largo', 20);
                $caja_pequena_ancho = get_option('caja_pequena_ancho', 17);
                $caja_pequena_alto = get_option('caja_pequena_alto', 12);
                
                $caja_mediana_largo = get_option('caja_mediana_largo', 33);
                $caja_mediana_ancho = get_option('caja_mediana_ancho', 33);
                $caja_mediana_alto = get_option('caja_mediana_alto', 22);
                
                $caja_grande_largo = get_option('caja_grande_largo', 60);
                $caja_grande_ancho = get_option('caja_grande_ancho', 40);
                $caja_grande_alto = get_option('caja_grande_alto', 30);
                
                // Determinar qué caja usar basado en el volumen
                if ($volumen_final > 0) {
                    $vol_pequena = $caja_pequena_largo * $caja_pequena_ancho * $caja_pequena_alto;
                    $vol_mediana = $caja_mediana_largo * $caja_mediana_ancho * $caja_mediana_alto;
                    
                    if ($volumen_final <= $vol_pequena) {
                        $dimensiones_str = $caja_pequena_largo . 'x' . $caja_pequena_ancho . 'x' . $caja_pequena_alto . ' cm';
                    } elseif ($volumen_final <= $vol_mediana) {
                        $dimensiones_str = $caja_mediana_largo . 'x' . $caja_mediana_ancho . 'x' . $caja_mediana_alto . ' cm';
                    } else {
                        $dimensiones_str = $caja_grande_largo . 'x' . $caja_grande_ancho . 'x' . $caja_grande_alto . ' cm';
                    }
                } else {
                    // Por defecto usar caja mediana
                    $dimensiones_str = $caja_mediana_largo . 'x' . $caja_mediana_ancho . 'x' . $caja_mediana_alto . ' cm';
                }
                
                WC_Garantias_Emails::enviar_email('etiqueta_devolucion', $user->user_email, [
                    'cliente' => $user->display_name,
                    'codigo' => $codigo_unico,
                    'dimensiones' => $dimensiones_str,
                    'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                    'numero_tracking' => $numero_envio,
                    'tipo_etiqueta' => 'Andreani',
                    'item_codigo_procesado' => $primer_item_codigo,  // CRÍTICO: Agregar esta línea
                    'garantia_id' => $garantia_id  // NUEVO: Pasar también el ID de garantía
                ]);
            }
        
        return [
            'etiqueta_url' => $etiqueta_url,
            'numero_envio' => $numero_envio,
            'peso_usado' => $peso_final,
            'valor_declarado' => $valor_declarado
        ];
    }

    /**
 * AJAX: Generar etiqueta desde admin
 */
public static function ajax_generar_etiqueta() {
    check_ajax_referer( 'andreani_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Sin permisos suficientes' );
    }
    $garantia_id = intval( $_POST['garantia_id'] ?? 0 );
    
    if ( ! $garantia_id ) {
        wp_send_json_error( 'ID de garantía inválido' );
    }
    $result = self::generar_etiqueta_garantia( $garantia_id );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    
    // NUEVO: Obtener dimensiones de la caja usada para Andreani
    $dimensiones_str = '';
    $dimensiones = get_post_meta($garantia_id, '_dimensiones_caja_devolucion', true);
    if ($dimensiones && is_array($dimensiones)) {
        $dimensiones_str = $dimensiones['largo'] . 'x' . $dimensiones['ancho'] . 'x' . $dimensiones['alto'] . ' cm';
    } else {
        // Si no hay dimensiones guardadas, usar las default o las que Andreani usó
        $dimensiones_str = '40x40x20 cm'; // O el tamaño que uses por defecto
    }
    
    wp_send_json_success( $result );
}

    /**
     * Agregar botn de Andreani en la página de ver garantía
     */
    public static function add_andreani_button_script() {
        $screen = get_current_screen();
        
        if ( $screen && $screen->id === 'garantias_page_wc-garantias-ver' ) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Buscar el botn de subir etiqueta y agregar botn de Andreani
                var $subirBtn = $('input[type="submit"][value*="Subir Etiqueta"], button:contains("Subir Etiqueta")');
                
                if ($subirBtn.length) {
                    var garantiaId = new URLSearchParams(window.location.search).get('garantia_id');
                    
                    var $andreaniBtn = $('<button type="button" class="btn btn-info" style="margin-left: 10px;" id="generar-andreani-btn">' +
                        '<i class="fas fa-truck"></i> Generar con Andreani' +
                        '</button>');
                    
                    $subirBtn.after($andreaniBtn);
                    
                    $andreaniBtn.on('click', function() {
                        var $btn = $(this);
                        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generando...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'andreani_generar_etiqueta',
                                garantia_id: garantiaId,
                                nonce: '<?php echo wp_create_nonce( "andreani_nonce" ); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Etiqueta generada exitosamente!\n\nNúmero de envo: ' + response.data.numero_envio);
                                    location.reload();
                                } else {
                                    alert('Error: ' + response.data);
                                }
                            },
                            error: function() {
                                alert('Error de conexión');
                            },
                            complete: function() {
                                $btn.prop('disabled', false).html('<i class="fas fa-truck"></i> Generar con Andreani');
                            }
                        });
                    });
                }
            });
            </script>
            <?php
        }
    }
}