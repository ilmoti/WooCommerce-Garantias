Resumen del Plugin de Garantías y Sistema RMA
Plugin Principal: WooCommerce Garantías
Plugin que gestiona reclamos de garantía de productos con flujo completo de aprobación/rechazo.

✅ Sistema de deploy automático FUNCIONANDO correctamente - Deploy desde fuera de public_html
✅ Deploy automático verificado y funcionando
🚀 Deploy completamente funcional con webhook status 200
Nueva Funcionalidad: Sistema RMA (Return Merchandise Authorization)
Propósito: Cuando un reclamo es rechazado definitivamente, permitir que el cliente recupere su producto defectuoso.
Archivos Clave del Sistema RMA:
class-wc-garantias-rma.php
Crea productos RMA (precio $0)
Genera cupones que se auto-aplican
Crea etiquetas PDF con QR
class-wc-garantias-cron-rma.php
Verifica cada hora productos RMA pendientes
Notifica 30 días antes del vencimiento
Notifica al admin cuando expiran
class-wc-garantias-ajax-rma.php
Maneja la generación manual de cupones vía AJAX
Modificaciones en class-wc-garantias-admin.php:
Al rechazar con "Rechazo Definitivo" → cambiar estado a "retorno_cliente"
Generar etiqueta automáticamente
Mostrar botones: etiqueta 🏷️ y generar cupón 🎫
Flujo RMA:
Admin rechaza con checkbox "Rechazo Definitivo"
Estado cambia a "retorno_cliente" + genera etiqueta
Admin crea producto "RMA - [nombre]" en CRM con SKU = código item
Sistema detecta producto y crea cupón (manual con botón o automático cada hora)
Cliente recibe email con cupón que se auto-aplica en su próxima compra
Problema Actual:
Al marcar "Rechazo Definitivo", el estado cambia a "retorno_cliente" momentáneamente pero al recargar (F5) vuelve al estado anterior. El cambio no se está persistiendo en la base de datos.
Configuraciones:
Panel Configuración: Campo "Días de validez cupones RMA" (default: 120)
Panel Emails: 3 templates configurables (confirmación, recordatorio 30 días, expirado)
Panel "Devoluciones Pendientes": Lista productos RMA con sus estados





