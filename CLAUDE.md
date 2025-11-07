# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

**WooCommerce Garantías** es un plugin de WordPress que gestiona un sistema completo de garantías y devoluciones (RMA) para WooCommerce. Permite a los clientes reclamar productos defectuosos, realizar seguimiento de sus garantías, y gestionar recepciones parciales de envíos.

**Versión**: 5.51
**Autor**: WiFix Development

## Arquitectura del Plugin

### Estructura de Archivos Principal

```
woocommerce-garantias.php        # Punto de entrada, carga todos los includes
├── includes/                     # Clases principales del sistema
│   ├── class-wc-garantias-init.php              # Registra CPT 'garantia'
│   ├── class-wc-garantias-processor.php         # Procesador centralizado de garantías
│   ├── class-wc-garantias-customer.php          # Panel frontend del cliente
│   ├── class-wc-garantias-admin.php             # Panel admin principal
│   ├── class-wc-garantias-ajax.php              # Handlers AJAX generales
│   ├── class-wc-garantias-emails.php            # Sistema de emails
│   ├── class-wc-garantias-cupones.php           # Generación de cupones
│   ├── class-wc-garantias-rma.php               # Sistema RMA (Return Merchandise)
│   ├── class-wc-garantias-cron-rma.php          # Cron jobs para RMA
│   ├── class-wc-garantias-ajax-rma.php          # AJAX para RMA
│   ├── class-wc-garantias-rma-cart.php          # Auto-aplicación de cupones RMA
│   ├── class-wc-garantias-andreani.php          # Integración con Andreani (envíos)
│   ├── class-wc-garantias-recepcion-parcial.php # Lógica de recepciones parciales
│   ├── class-wc-garantias-recepcion-parcial-ui.php # UI para recepciones parciales
│   ├── class-wc-garantias-recepcion-parcial-cron.php # Cron para recepciones parciales
│   ├── class-wc-garantias-etiqueta.php          # Generación de etiquetas PDF
│   ├── class-wc-garantias-whatsapp.php          # Notificaciones por WhatsApp
│   ├── class-wc-garantias-timeline.php          # Timeline de eventos
│   ├── class-wc-garantias-dashboard.php         # Dashboard de estadísticas
│   ├── class-wc-garantias-motivos.php           # Gestión de motivos
│   ├── class-wc-garantias-historial.php         # Historial de cambios
│   ├── class-wc-garantias-notifications.php     # Sistema de notificaciones
│   ├── class-wc-garantias-admin-badge.php       # Badges visuales
│   ├── class-wc-garantias-admin-metabox.php     # Metaboxes de admin
│   ├── class-wc-garantias-integrations.php      # Integraciones externas
│   └── admin/                    # Clases admin adicionales
│       ├── class-wc-garantias-admin-view.php    # Vista principal admin
│       ├── class-wc-garantias-admin-view-render.php # Renderizado de UI admin
│       ├── class-wc-garantias-admin-list.php    # Lista de garantías
│       ├── class-wc-garantias-admin-rma.php     # Panel de RMA admin
│       ├── class-wc-garantias-admin-config.php  # Configuraciones
│       ├── class-wc-garantias-admin-dashboard.php # Dashboard admin
│       ├── class-wc-garantias-admin-analisis.php # Análisis y reportes
│       ├── class-wc-garantias-admin-motivos.php # Gestión de motivos admin
│       └── class-wc-garantias-admin-docs.php    # Documentación
├── templates/                    # Plantillas de UI
│   ├── myaccount-garantias-dashboard.php        # Dashboard cliente
│   ├── myaccount-garantias-reclamos.php         # Formulario reclamo
│   ├── myaccount-garantias-timeline.php         # Timeline cliente
│   ├── myaccount-devoluciones.php               # Panel devoluciones
│   ├── myaccount-garantias-carga-masiva.php     # Carga masiva
│   └── emails/                   # Templates de emails
├── assets/                       # CSS y JavaScript
├── inc/                          # Archivos legacy
│   └── ajustes-garantias.php    # Configuración duración garantías
└── vendor/                       # Dependencias Composer
```

### Custom Post Type: 'garantia'

El sistema usa un CPT llamado `garantia` para almacenar cada reclamo de garantía. La información se guarda en post meta:

**Post Metas Principales:**
- `_cliente`: Nombre del cliente
- `_user_id`: ID de usuario de WordPress
- `_producto`: Nombre del producto
- `_order_id`: ID de la orden de WooCommerce
- `_items_reclamados`: Array de items reclamados
- `_status`: Estado de la garantía
- `_tracking`: Número de tracking del envío

**Estados de Garantía (`_status`):**
- `pendiente`: Recién creada
- `procesando`: En revisión
- `aprobado`: Aprobada
- `rechazado`: Rechazada
- `cupon_generado`: Cupón generado
- `retorno_cliente`: RMA - Cliente debe devolver producto
- `parcialmente_recibido`: Recepción parcial completada
- `esperando_recepcion`: Items pendientes de recibir
- `rechazado_no_recibido`: Rechazado por no recibir en plazo

### Sistema RMA (Return Merchandise Authorization)

Cuando una garantía es rechazada definitivamente, el cliente puede recuperar su producto defectuoso:

**Flujo RMA:**
1. Admin rechaza garantía con "Rechazo Definitivo"
2. Estado cambia a `retorno_cliente`
3. Se genera etiqueta PDF con QR automáticamente
4. Admin crea producto "RMA - [nombre]" en WooCommerce (precio $0, SKU = código item)
5. Cron job detecta producto RMA y genera cupón automáticamente
6. Cliente recibe email con cupón que se auto-aplica en próxima compra
7. Cupón válido por X días (configurable, default: 120)

**Archivos Clave:**
- `class-wc-garantias-rma.php`: Lógica principal
- `class-wc-garantias-cron-rma.php`: Verificación horaria de productos RMA
- `class-wc-garantias-rma-cart.php`: Auto-aplicación de cupones

### Sistema de Recepción Parcial

Permite recibir items de forma parcial y gestionar automáticamente lo faltante:

**Flujo:**
1. Cliente reclama 10 unidades con tracking ABC123
2. WiFix recibe solo 6 unidades
3. Sistema divide automáticamente:
   - Item A: 6 unidades [RECIBIDO] - Se procesan
   - Item B: 4 unidades [ESPERANDO_RECEPCION] - Timer 7 días
4. Si pasan 7 días sin recepción → auto-rechazo
5. Cliente puede cancelar envío manualmente desde frontend

**Archivos Clave:**
- `class-wc-garantias-recepcion-parcial.php`: Lógica de división
- `class-wc-garantias-recepcion-parcial-ui.php`: Modal y UI
- `class-wc-garantias-recepcion-parcial-cron.php`: Auto-rechazo y recordatorios

**Opciones de Cliente:**
- Ver días restantes para envío
- Cancelar envío voluntariamente (sin apelación)
- Crear nueva garantía después del rechazo

### Sistema de Emails

El plugin gestiona múltiples templates de email configurables desde el panel admin:

**Templates Principales:**
- `garantia-status-update.php`: Actualización de estado
- `garantia-reminder.php`: Recordatorios
- Emails de RMA (confirmación, recordatorio 30 días, expirado)
- Emails de recepción parcial (notificación, recordatorio, rechazo)

**Integración WhatsApp:**
La clase `WC_Garantias_WhatsApp` permite enviar notificaciones vía WhatsApp en paralelo a los emails.

### Generación de PDFs y Etiquetas

El sistema usa TCPDF (incluido en `includes/TCPDF/`) para generar:
- Etiquetas de devolución con QR code
- Documentos de garantía
- Plantillas descargables

**Archivo Clave:** `class-wc-garantias-etiqueta.php`

### Sistema de Cupones

Generación automática de cupones de WooCommerce basados en el monto aprobado:

**Lógica:**
- Cupón de descuento fijo
- Se auto-aplica en el carrito (clase `WC_Garantias_RMA_Cart`)
- Validez configurable
- Uso único por cliente

**Archivo Clave:** `class-wc-garantias-cupones.php`

### Cron Jobs

El sistema usa varios cron jobs de WordPress:

**Jobs Activos:**
- `verificar_productos_rma_pendientes`: Cada hora, verifica productos RMA y genera cupones
- `recordar_rma_proximos_vencer`: Notifica 30 días antes del vencimiento
- `notificar_rma_expirados`: Notifica cuando expiran cupones RMA
- `verificar_items_esperando`: Cada 6 horas, verifica items pendientes de recepción
- `auto_rechazar_items`: Diario a las 2 AM, rechaza items no recibidos en 7 días

### AJAX Handlers

**General (`class-wc-garantias-ajax.php`):**
- Procesamiento de formularios
- Subida de archivos (fotos/videos)
- Actualización de estados

**RMA (`class-wc-garantias-ajax-rma.php`):**
- Generación manual de cupones
- Consulta de estado de RMA

**Recepción Parcial:**
- `procesar_recepcion_parcial`
- `extender_plazo_recepcion`
- `rechazar_manual_item`
- `cancelar_envio_item` (frontend cliente)

### Integraciones Externas

**Andreani (`class-wc-garantias-andreani.php`):**
- Integración con servicio de envíos Andreani
- Generación de etiquetas de envío
- Tracking de paquetes

## Comandos de Desarrollo

### Composer

```bash
# Instalar dependencias
composer install

# Actualizar dependencias
composer update
```

### Dependencias

El plugin requiere:
- `phpoffice/phpspreadsheet`: Para manejo de Excel (carga masiva)
- TCPDF: Para generación de PDFs (incluido manualmente)
- SimpleXLSX: Para lectura de archivos Excel (incluido en `includes/libs/`)

## Testing Local

**Requisitos:**
- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+

**Instalación:**
1. Copiar carpeta `WooCommerce-Garantias` a `wp-content/plugins/`
2. Ejecutar `composer install` dentro de la carpeta
3. Activar plugin desde panel de WordPress
4. Configurar en WooCommerce > Garantías

## Base de Datos

El plugin NO crea tablas custom, usa:
- Post Type `garantia` con post meta
- Opciones de WordPress (`wp_options`)
- Post meta de órdenes de WooCommerce

**Opciones Clave:**
- `duracion_garantia`: Días de validez de garantías (default: 180)
- `dias_validez_cupones_rma`: Días de validez cupones RMA (default: 120)
- Templates de emails configurables por tipo

## Notas de Arquitectura

### Patrón de Inicialización

Todas las clases principales usan el patrón estático `init()`:

```php
if ( class_exists( 'WC_Garantias_Customer' ) ) {
    WC_Garantias_Customer::init();
}
```

### Procesamiento Centralizado

La clase `WC_Garantias_Processor` centraliza lógica común:
- Validación de permisos
- Procesamiento de archivos
- Respuestas a solicitudes de información

### Separación Admin/Frontend

- **Admin**: Clases en `includes/class-wc-garantias-admin*.php` y `includes/admin/`
- **Frontend**: Templates en `templates/myaccount-*.php`
- **Shared**: Procesadores, emails, cron jobs

### Sistema de Mensajes

El plugin usa un sistema de mensajes flash personalizado:
- `garantias_set_mensaje($mensaje, $tipo)`
- `garantias_get_mensaje()`

## Troubleshooting Común

### El estado de garantía no persiste
Verificar que `update_post_meta()` se ejecuta correctamente. El problema reportado en `readme.txt` indica que el estado `retorno_cliente` no se guarda al recargar.

### Los cron jobs no ejecutan
- Verificar que WP-Cron esté activo
- Revisar página de estado: WooCommerce > Garantías > Estado del Cron
- Los cron jobs se registran en cada clase con `wp_schedule_event()`

### Los emails no llegan
- Verificar templates en panel de configuración
- Comprobar función `wp_mail()` funcionando
- Revisar integración con WhatsApp si está activa

### Archivos no se suben
- Verificar permisos de `wp-content/uploads/`
- Límites: Fotos 5MB, Videos 50MB
- La clase `WC_Garantias_Processor::process_file_uploads()` maneja la lógica

### Balance de EANs incorrecto
- **Bug corregido en v5.51**: El autocomplete calculaba balance globalmente en vez de por orden específica
- La función `get_claimed_quantity_by_order()` en [class-wc-garantias-ajax.php:809](includes/class-wc-garantias-ajax.php#L809) calcula cuánto se ha reclamado de UNA orden específica
- **Problema anterior**: Si un cliente compraba 3 unidades en orden A y 1 en orden B, al reclamar 1 de B, el sistema mostraba 2 disponibles en A (incorrecto)
- **Comportamiento correcto**: Calcula `cantidad_comprada` y `cantidad_reclamada` de la MISMA orden, no mezclando órdenes
- El autocomplete en línea 184 llama a `get_claimed_quantity_by_order()` pasando el `order_id` específico
- Solo cuenta garantías cuya orden asociada esté dentro del período válido de garantía (default: 180 días)

### Scripts de diagnóstico
- **diagnostic-garantias.php**: Shortcode `[diagnostico_garantias customer_id=X product_id=Y]` para diagnosticar problemas de balance
  - Muestra todas las garantías del cliente con sus items
  - Indica si tienen `order_id` o es NULL
  - Muestra fecha de orden y si está dentro del período válido
  - Resalta en amarillo el producto específico que se está investigando
  - Lista todas las órdenes del cliente con sus items
- **clear-cache.php**: Script para forzar limpieza de caché después de deployments
  - URL: `https://dominio.com/wp-content/plugins/WooCommerce-Garantias/clear-cache.php?token=CACHE_CLEAR_2025`
  - Limpia OPcache y Realpath cache
  - Verifica fechas de modificación de archivos
  - Verifica que las clases y métodos nuevos existan
  - Útil cuando un deploy no se refleja inmediatamente por caché de PHP

## Extensibilidad

El plugin está diseñado para ser extensible:
- Hooks de WordPress estándar
- Filtros para templates de email
- Actions en cambios de estado
- Sistema de integrations (`class-wc-garantias-integrations.php`)
