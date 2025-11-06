# Instrucciones de Deploy - WooCommerce Garantías

## Configuración Inicial

### 1. Clonar el Repositorio en el Servidor

Conectarse al servidor por SSH y ejecutar:

```bash
cd /path/to/wp-content/plugins/
git clone https://github.com/ilmoti/WooCommerce-Garantias.git
cd WooCommerce-Garantias
```

### 2. Configurar el Webhook en GitHub

1. Ir a: https://github.com/ilmoti/WooCommerce-Garantias/settings/hooks
2. Click en **"Add webhook"**
3. Configurar:
   - **Payload URL**: `https://tudominio.com/deploy-webhook.php?token=6f59d1e63f55b18b682a876d1dc17d1b780216a7102c98e63761d747d9762dd9`
   - **Content type**: `application/json`
   - **Secret**: (dejar vacío)
   - **Events**: Solo `push`
   - **Active**: ✓

### 3. Subir el Script de Deploy al Servidor

El archivo `deploy-webhook.php` debe estar en la **raíz del sitio** (mismo nivel que `wp-config.php`):

```bash
# Desde tu computadora local
scp deploy-webhook.php usuario@servidor:/path/to/wordpress/
```

O subirlo por FTP/cPanel.

### 4. Ajustar la Ruta del Plugin

Editar `deploy-webhook.php` en el servidor y verificar que la línea 52 apunte correctamente:

```php
$plugin_dir = __DIR__ . '/wp-content/plugins/WooCommerce-Garantias';
```

Si tu WordPress está en una subcarpeta, ajustar según corresponda.

### 5. Dar Permisos de Escritura

```bash
chmod 644 deploy-webhook.php
touch deploy.log
chmod 666 deploy.log
```

## Flujo de Deploy Automático

### Cuando haces `git push`:

1. **Local** → GitHub recibe el commit
2. **GitHub** → Dispara el webhook
3. **Webhook** → Llama a `deploy-webhook.php?token=...`
4. **Script** ejecuta:
   ```bash
   git fetch origin main
   git reset --hard origin/main
   ```
5. **Limpia** OPcache automáticamente
6. **Elimina** `garantias-debug.log` si existe
7. **Registra** todo en `deploy.log`

## Comandos Útiles

### Deploy Manual (SSH)

```bash
cd /path/to/wp-content/plugins/WooCommerce-Garantias
git pull origin main
```

### Ver Log de Deploy

```bash
tail -f /path/to/wordpress/deploy.log
```

### Limpiar OPcache Manualmente

```bash
# Si tienes acceso a PHP CLI
php -r "opcache_reset();"
```

O reiniciar PHP-FPM:
```bash
sudo systemctl restart php8.1-fpm
```

### Probar el Webhook Manualmente

```bash
curl -X POST "https://tudominio.com/deploy-webhook.php?token=6f59d1e63f55b18b682a876d1dc17d1b780216a7102c98e63761d747d9762dd9" \
  -H "X-GitHub-Event: push" \
  -d '{"ref":"refs/heads/main"}'
```

## Seguridad

### Cambiar el Token

1. Generar nuevo token:
   ```bash
   openssl rand -hex 32
   ```

2. Actualizar en `deploy-webhook.php` línea 8:
   ```php
   define('DEPLOY_TOKEN', 'TU_NUEVO_TOKEN_AQUI');
   ```

3. Actualizar en GitHub webhook URL

### Proteger el Script

Agregar en `.htaccess` (si usas Apache):

```apache
<Files "deploy-webhook.php">
    Order Deny,Allow
    Deny from all
    # Permitir solo desde IPs de GitHub
    Allow from 140.82.112.0/20
    Allow from 143.55.64.0/20
    Allow from 185.199.108.0/22
    Allow from 192.30.252.0/22
</Files>
```

## Troubleshooting

### El webhook no funciona

1. Verificar en GitHub: Settings → Webhooks → Recent Deliveries
2. Ver si hubo errores en la respuesta
3. Revisar `deploy.log` en el servidor

### Los cambios no aparecen

1. Verificar que el webhook se ejecutó correctamente
2. Limpiar caché de WordPress/WooCommerce
3. Limpiar OPcache manualmente
4. Verificar permisos de archivos

### Errores de permisos

```bash
# El servidor web debe poder ejecutar git
sudo chown -R www-data:www-data /path/to/plugins/WooCommerce-Garantias
```

## Workflow de Desarrollo

### 1. Trabajar localmente

```bash
# Hacer cambios
# Probar localmente
git add .
git commit -m "Fix: descripción del cambio"
```

### 2. Subir a GitHub

```bash
git push origin main
```

### 3. Deploy automático

- GitHub dispara el webhook
- El servidor se actualiza automáticamente
- Se limpia OPcache
- Se puede verificar en `deploy.log`

### 4. Verificar en producción

- Revisar que los cambios se aplicaron
- Probar la funcionalidad modificada
- Revisar logs si hay problemas

## Archivos Importantes

- `deploy-webhook.php` - Script de deploy (raíz del sitio)
- `deploy.log` - Log de deployments (se crea automáticamente)
- `.gitignore` - Archivos ignorados por Git
- `CLAUDE.md` - Documentación para Claude Code
- `DEPLOY.md` - Este archivo

## Notas

- El script **NO** ejecuta `composer install`, si agregas dependencias hazlo manualmente
- Los archivos en `.gitignore` no se sincronizan (logs, cache, etc.)
- El deploy es a branch `main` únicamente
- El token actual es compartido con otro proyecto, **considerar cambiarlo**
