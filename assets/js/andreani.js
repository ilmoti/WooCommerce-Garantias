jQuery(document).ready(function($) {
    
    // Test de conexión con Andreani
    $('#test_andreani_connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
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
                    $('#andreani_test_result').html(
                        '<div class="notice notice-success"><p><span class="dashicons dashicons-yes"></span> Conexión exitosa con Andreani</p></div>'
                    );
                } else {
                    $('#andreani_test_result').html(
                        '<div class="notice notice-error"><p><span class="dashicons dashicons-no"></span> Error: ' + response.data + '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $('#andreani_test_result').html(
                    '<div class="notice notice-error"><p><span class="dashicons dashicons-no"></span> Error de conexión: ' + error + '</p></div>'
                );
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
                
                // Auto-ocultar el mensaje después de 5 segundos
                setTimeout(function() {
                    $('#andreani_test_result .notice').fadeOut();
                }, 5000);
            }
        });
    });

    // Validación de formulario
    $('form').on('submit', function(e) {
        var requiredFields = [
            '#andreani_api_user',
            '#andreani_api_password', 
            '#andreani_api_key',
            '#andreani_nro_cuenta',
            '#andreani_remitente_nombre',
            '#andreani_remitente_email',
            '#andreani_remitente_cp'
        ];
        
        var hasErrors = false;
        
        requiredFields.forEach(function(field) {
            var $field = $(field);
            if (!$field.val().trim()) {
                $field.css('border-color', '#dc3232');
                hasErrors = true;
            } else {
                $field.css('border-color', '');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            $('<div class="notice notice-error"><p>Por favor completa todos los campos obligatorios marcados en rojo.</p></div>')
                .insertAfter('h1');
            
            setTimeout(function() {
                $('.notice-error').fadeOut();
            }, 5000);
            
            return false;
        }
    });

    // Formatear campos automáticamente
    $('#andreani_remitente_cp').on('input', function() {
        // Solo números para código postal
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    $('#andreani_remitente_telefono').on('input', function() {
        // Solo números para teléfono
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    $('#andreani_remitente_dni').on('input', function() {
        // Solo números para DNI
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Preview de configuración
    function updatePreview() {
        var peso = $('#andreani_reducir_peso').val();
        var tamano = $('#andreani_reducir_tamano').val();
        var valor = $('#andreani_valor_declarado').val();
        
        var previewHtml = '<div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin-top: 10px;">';
        previewHtml += '<h4>Vista previa de configuración:</h4>';
        previewHtml += '<p><strong>Peso:</strong> ' + peso + '% del original</p>';
        previewHtml += '<p><strong>Tamaño:</strong> ' + tamano + '% del original</p>';
        previewHtml += '<p><strong>Valor declarado:</strong> ' + valor + '% del valor real</p>';
        previewHtml += '</div>';
        
        $('#config_preview').remove();
        $(previewHtml).attr('id', 'config_preview').insertAfter('#andreani_valor_declarado').closest('tr');
    }
    
    $('#andreani_reducir_peso, #andreani_reducir_tamano, #andreani_valor_declarado').on('change input', updatePreview);
    
    // Ejecutar preview inicial
    updatePreview();
});