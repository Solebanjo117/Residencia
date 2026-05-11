# Despliegue OVH Ubuntu 24.04 - Residencia

Guia para desplegar este proyecto Laravel + Inertia + Vue + Vite en una VPS OVH por IP publica.

## Resumen detectado del proyecto

- Stack backend: Laravel 12.51.0, PHP `^8.2`.
- PHP recomendado en Ubuntu 24.04: PHP 8.3 con PHP-FPM.
- Stack frontend: Inertia Laravel 2.0, Vue 3.5, Vite 7.
- Node requerido por Vite instalado en lockfile: `^20.19.0 || >=22.12.0`. Recomendado: Node.js 22 LTS.
- Base de datos default: SQLite. `config/database.php` usa `DB_CONNECTION=sqlite` y `database/database.sqlite` por defecto.
- Migraciones: el proyecto tiene migraciones para usuarios, cache, jobs, roles, departamentos, tablas academicas, evidencias, asesorias, social login y revisiones. Hay migraciones con rutas especificas para SQLite en asesorias.
- Storage: los archivos de evidencia se guardan con `Storage::disk('local')`, que apunta a `storage/app/private`. `storage:link` crea `public/storage -> storage/app/public`.
- Vite/Inertia: entrada principal `resources/js/app.ts`; Blade carga `@vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])`; Vite compila a `public/build`.
- Tareas programadas: `routes/console.php` ejecuta `notify:windows` cada 5 minutos y `ops:backup --name=auto` diario a las 02:00.

## Archivos creados

- `.env.production.example`: ejemplo de entorno para produccion por IP y SQLite.
- `nginx-residencia.conf`: virtual host Nginx listo para copiar a `/etc/nginx/sites-available/residencia`.
- `deploy.sh`: script de despliegue seguro para dependencias, build, migraciones y cache.
- `DEPLOY_OVH.md`: esta guia.

## 1. Preparar el servidor

Conectate a la VPS:

```bash
ssh ubuntu@162.19.226.144
```

Actualiza paquetes e instala dependencias base:

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y nginx git unzip curl ca-certificates sqlite3 composer \
  php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
```

Instala Node.js 22 LTS. Una opcion comun es NodeSource:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

Verifica que Node sea `>=22.12.0`.

## 2. Descargar el proyecto

Usa `/var/www/residencia` como ruta de despliegue:

```bash
sudo mkdir -p /var/www
sudo chown -R ubuntu:ubuntu /var/www
cd /var/www
git clone https://github.com/Solebanjo117/Residencia.git residencia
cd /var/www/residencia
git checkout yarik
```

Si ya existe el repo:

```bash
cd /var/www/residencia
git pull --ff-only origin yarik
```

## 3. Configurar entorno de produccion

Copia el ejemplo:

```bash
cp .env.production.example .env
```

Edita `.env` y revisa como minimo:

```bash
nano .env
```

Valores esperados para la demo por IP:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://162.19.226.144
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/residencia/database/database.sqlite
```

Crea la base SQLite:

```bash
touch database/database.sqlite
```

No agregues contrasenas, tokens, llaves privadas ni credenciales OAuth reales al repositorio. Si luego configuras Google OAuth o correo SMTP, hazlo solo en `.env` del servidor.

## 4. Ejecutar despliegue

Haz ejecutable el script y correlo:

```bash
chmod +x deploy.sh
./deploy.sh
```

El script ejecuta:

- `composer install --no-dev --optimize-autoloader`
- `npm install`
- `npm run build`
- `php artisan key:generate --force` solo si `APP_KEY` esta vacia
- `php artisan migrate --force`
- `php artisan storage:link`
- `php artisan optimize:clear`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

Tambien elimina `public/hot` si existe, porque ese archivo hace que Laravel intente usar `npm run dev` en produccion.

## 5. Permisos

PHP-FPM corre normalmente como `www-data`. Da permisos de escritura a storage, cache y SQLite:

```bash
sudo chown -R ubuntu:www-data /var/www/residencia
sudo chmod -R ug+rwX /var/www/residencia/storage /var/www/residencia/bootstrap/cache /var/www/residencia/database
sudo chmod 664 /var/www/residencia/database/database.sqlite
```

Si ves errores de permisos en logs, aplica:

```bash
sudo chown -R www-data:www-data /var/www/residencia/storage /var/www/residencia/bootstrap/cache /var/www/residencia/database/database.sqlite
```

## 6. Configurar Nginx

Copia la configuracion:

```bash
sudo cp nginx-residencia.conf /etc/nginx/sites-available/residencia
sudo ln -s /etc/nginx/sites-available/residencia /etc/nginx/sites-enabled/residencia
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl restart php8.3-fpm
```

Abre en navegador:

```text
http://162.19.226.144
```

## 7. Scheduler y colas

El proyecto usa `QUEUE_CONNECTION=database` y tiene tareas programadas. Agrega el scheduler de Laravel al crontab del usuario `ubuntu`:

```bash
crontab -e
```

Agrega:

```cron
* * * * * cd /var/www/residencia && php artisan schedule:run >> /dev/null 2>&1
```

Para procesar colas en produccion, crea un servicio systemd:

```bash
sudo nano /etc/systemd/system/residencia-queue.service
```

Contenido:

```ini
[Unit]
Description=Residencia Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/residencia
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=90

[Install]
WantedBy=multi-user.target
```

Activalo:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now residencia-queue
sudo systemctl status residencia-queue
```

## 8. Configurar correo real

El registro con verificacion de correo y la recuperacion de contrasena usan el mailer de Laravel/Fortify. En produccion debes configurar SMTP en `.env`; no guardes estas credenciales en Git.

Ejemplo con un proveedor SMTP:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=usuario-smtp
MAIL_PASSWORD=password-smtp
MAIL_FROM_ADDRESS="correo-verificado@tu-proveedor.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Si aun no tienes dominio, puedes usar temporalmente un proveedor que permita enviar desde una cuenta verificada, por ejemplo una cuenta Gmail/Google Workspace con app password, Brevo, Mailgun, Resend, SendGrid o Mailtrap. El correo remitente debe ser una direccion permitida por el proveedor; `noreply@162.19.226.144` normalmente no sera aceptado.

Despues de cambiar `.env` en la VPS:

```bash
php artisan optimize:clear
php artisan config:cache
```

Puedes probar el envio creando un usuario desde `/register`, o solicitando recuperacion desde `/forgot-password`.

## 9. Crear usuario administrativo inicial

No uses `db:seed` en produccion sin revisar, porque `database/seeders/DatabaseSeeder.php` crea usuarios demo con password por defecto.

Usa el comando institucional del proyecto:

```bash
read -rsp "Password admin inicial: " ADMIN_PASSWORD
echo
php artisan residencia:bootstrap \
  --admin-name="Jefe de Departamento" \
  --admin-email="admin@example.com" \
  --admin-password="$ADMIN_PASSWORD" \
  --department="Sistemas y Computacion"
unset ADMIN_PASSWORD
```

Cambia el email y password directamente en el servidor. No guardes esos valores en Git.

## 10. Verificaciones utiles

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan storage:link
curl -I http://162.19.226.144
tail -f storage/logs/laravel.log
sudo tail -f /var/log/nginx/residencia-error.log
```

## 11. Riesgos o puntos a revisar

- `public/hot`: si existe en produccion, Laravel intentara cargar Vite dev. El script `deploy.sh` lo borra. Revisa que no exista despues del deploy.
- `config/inertia.php:19`: SSR esta marcado como `enabled => true`. Con `npm run build` normal no se genera bundle SSR, asi que Inertia cae al render cliente. Si decides usar SSR real, cambia el deploy a `npm run build:ssr` y crea un servicio para `php artisan inertia:start-ssr`.
- `routes/console.php:18-19`: hay scheduler para notificaciones y backups. Si no configuras cron, esas tareas no correran.
- `database/seeders/DatabaseSeeder.php:16` y `database/seeders/DatabaseSeeder.php:32-53`: crea usuarios demo con password default si corres `php artisan db:seed`. Para produccion usa `residencia:bootstrap`.
- `app/Services/DocxEditorService.php:7` y `app/Services/DocxEditorService.php:14`: el editor DOCX necesita extensiones PHP `dom/xml` y `zip`. Instala `php8.3-xml` y `php8.3-zip`.
- `config/database.php:37`: si `DB_DATABASE` no esta definido, Laravel usa `database/database.sqlite`. En produccion conviene dejar la ruta absoluta `/var/www/residencia/database/database.sqlite`.
- `app/Models/User.php:8`: el modelo implementa `MustVerifyEmail`; si se quita, Laravel dejara de enviar/verificar correos de registro.
- `.env`: si `MAIL_MAILER=log`, los correos no salen al usuario; se guardan en `storage/logs/laravel.log`.

## 12. Actualizaciones futuras

Para actualizar despues de nuevos commits:

```bash
cd /var/www/residencia
git pull --ff-only origin yarik
./deploy.sh
sudo systemctl restart residencia-queue
sudo systemctl reload nginx
```
