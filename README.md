# jeftezmont.me

Rediseño PHP/MySQL de `jeftezmont.me` con home editorial, blog dinámico y CMS privado para Hostinger.

## Instalación en Hostinger

1. Crea una base de datos MySQL/MariaDB desde hPanel.
2. Copia `.env.hostinger.example` como `.env`, pega las credenciales reales, genera `APP_KEY` con `php -r 'echo bin2hex(random_bytes(32));'` y define una clave temporal larga en `SETUP_TOKEN`.
3. Sube el proyecto dejando `public/` como document root del dominio. Si Hostinger no permite cambiar el document root, conserva las reglas incluidas en `.htaccess` para enviar las solicitudes a `public/index.php`.
4. Visita `/admin/setup`, introduce `SETUP_TOKEN` y ejecuta **Aplicar actualizaciones seguras**. También puedes importar `database.sql` previamente con phpMyAdmin; el asistente detectará lo que ya exista.
5. Crea el primer administrador desde el paso 4 del asistente. `database.sql` no contiene usuarios ni contraseñas predeterminadas.
6. Cuando el administrador exista, el setup deja de aceptar la clave temporal y requiere una sesión administrativa. Puedes retirar `SETUP_TOKEN` del entorno.
7. Verifica que `.htaccess` esté activo y que Apache tenga `mod_rewrite`.
8. Da permisos de escritura a `public/uploads/` y `storage/logs/` (`755` suele bastar; usa `775` solo si el hosting lo requiere).
9. Activa HTTPS y prueba `/`, `/blog`, `/admin/login`, `/sitemap.xml` y `/robots.txt`.

## Administración

- Login: `/admin/login`
- Dashboard: `/admin`
- Artículos: `/admin/posts`
- Medios: `/admin/media`
- Categorías: `/admin/categories`
- Etiquetas: `/admin/tags`
- Ajustes del sitio: `/admin/settings`
- Salud del sitio: `/admin/health`
- Configuración y diagnóstico inicial: `/admin/setup`
- Backups de contenido: `/admin/backups`
- Checklist de deploy: `/admin/deploy`
- Podcasts: `/admin/podcasts`
- Episodios: `/admin/podcast-episodes`
- Roles y permisos: `/admin/roles` (solo Super Admin)

Los roles iniciales son Super Admin, Admin, Editor y Autor. El primer usuario queda asignado automáticamente como Super Admin. Los permisos se validan en servidor y el CMS impide degradar o eliminar al último Super Admin.

## Podcast

El módulo usa tablas independientes (`podcasts` y `podcast_episodes`) y admite varios podcasts. Cada episodio puede usar un archivo local MP3/AAC/M4A o un enlace público de Dropbox validado mediante HTTPS, MIME, tamaño, redirecciones y reglas SSRF. El GUID se crea una vez al insertar y no se modifica al editar.

Rutas públicas:

- `/podcast`
- `/podcast/{podcast}`
- `/podcast/{podcast}/{episodio}`
- `/podcast/feed.xml`
- `/podcast/{podcast}/feed.xml`
- `/blog/feed.xml` (RSS independiente del Blog)

El feed RSS 2.0 incluye namespaces iTunes y Content, todos los episodios publicados, metadatos de Apple Podcasts/Spotify y `enclosure` con URL, MIME y longitud. El correo configurado como propietario puede quedar públicamente visible en el RSS.

Para archivos grandes, ajusta en Hostinger `upload_max_filesize`, `post_max_size` y `max_execution_time` por encima de `max_audio_upload_bytes` (250 MB por defecto). PHP necesita `fileinfo` y `curl`; `zip` solo es necesario para backups ZIP. Apache sirve los archivos desde `public/uploads/audio/`, y debe conservar habilitadas las peticiones `HEAD` y `Range`. El `.htaccess` de uploads bloquea la ejecución de scripts y declara los MIME de audio.

Configura la misma zona horaria en PHP y MySQL para que publicaciones programadas y fechas RFC 2822 del RSS coincidan. En Hostinger puede definirse `date.timezone = America/Mexico_City` desde las opciones de PHP.

La página individual del episodio utiliza un reproductor accesible en JavaScript vanilla sobre el `<audio>` real. La waveform acepta un futuro array precalculado de amplitudes y, cuando no existe, genera un fallback determinista a partir del GUID sin descargar ni decodificar el audio.

Para cargar el podcast de demostración en un entorno local con FFmpeg disponible:

```bash
php scripts/seed_podcast_demo.php
```

El comando es idempotente: crea o actualiza exclusivamente `Conversaciones con sentido` y sus cinco episodios demo.

El editor acepta un formato Markdown ligero: títulos con `#`, `##`, `###`, negritas con `**texto**`, cursivas con `*texto*`, código inline con backticks, listas con `-`, citas con `>`, imágenes con `![texto](/uploads/imagen.webp)` y enlaces `[texto](https://url)`. Desde `/admin/posts/create` y `/admin/posts/{id}/edit` también hay toolbar, selector de medios y preview renderizado antes de publicar.

Para embeds, pega la URL sola en una línea. Soporta YouTube, Spotify y Apple Music:

```md
https://youtu.be/VIDEO_ID
https://open.spotify.com/album/ALBUM_ID
https://music.apple.com/mx/album/nombre/ID
```

También puedes usar shortcodes explícitos cuando quieras evitar que una URL quede como texto común:

```md
[spotify:https://open.spotify.com/track/TRACK_ID]
[applemusic:https://music.apple.com/mx/album/nombre/ID]
```

## Seguridad incluida

- `password_hash()` y `password_verify()`
- PDO con prepared statements
- Tokens CSRF en formularios sensibles
- Sesión persistente opcional por 30 días con cookie `HttpOnly`, token rotativo y hash en base de datos
- CAPTCHA Cloudflare Turnstile validado del lado servidor cuando `TURNSTILE_SITE_KEY` y `TURNSTILE_SECRET_KEY` están configuradas
- Passkeys WebAuthn con challenges de un solo uso, `userVerification: required`, resident keys, validación de origin/RP ID/firma y sign counter
- Límite básico de intentos fallidos por IP/correo
- Cookies de sesión `HttpOnly`, `SameSite=Lax` y `Secure` en producción
- Regeneración de sesión después del login
- Escape HTML en vistas
- Validación de MIME, extensión y tamaño en uploads
- Bloqueo básico de ejecución PHP dentro de `/uploads`
- Registro centralizado de errores en `storage/logs/app.log` con Error ID y redacción de datos sensibles
- Diagnóstico de base de datos, tablas, columnas, permisos, PHP, Turnstile y WebAuthn desde `/admin/health`

## Logs y permisos

El CMS escribe errores internos en `storage/logs/app.log`. Los directorios `storage/`, `storage/logs/` y `public/uploads/` deben existir y ser escribibles por PHP. En hosting compartido normalmente `755` es suficiente; usa `775` únicamente si el servidor lo requiere.

En `APP_ENV=production` los detalles técnicos no se muestran al visitante. La página de error presenta un Error ID que permite localizar el registro correspondiente sin revelar rutas, SQL ni credenciales.

El front controller envía headers de seguridad compatibles con el sitio:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` restrictiva
- `Content-Security-Policy` permitiendo recursos propios, Turnstile, YouTube, Spotify y Apple Music
- `Strict-Transport-Security` cuando `APP_ENV=production` y la petición usa HTTPS

Las sesiones usan cookies `HttpOnly`, `SameSite=Lax`, `Secure` cuando hay HTTPS, `session.use_strict_mode` y regeneración de ID después del login.

## Asistente de instalación

`/admin/setup` compara la base activa con `database.sql`. Puede crear tablas faltantes y añadir columnas o índices únicamente cuando la operación es aditiva y considerada segura. No ejecuta `DROP`, no elimina filas y no sobrescribe tablas existentes. Las columnas `NOT NULL` sin valor predeterminado se dejan para revisión manual.

En producción, si todavía no existe un administrador, el asistente exige `SETUP_TOKEN`. En desarrollo local puede utilizarse sin esa clave. Cuando ya hay un administrador, `/admin/setup` solo está disponible después de iniciar sesión.

## Cloudflare Turnstile

1. Usa el widget Turnstile existente en Cloudflare para `jeftezmont.me`.
2. Copia la Site Key, Secret Key, action y hostnames permitidos en `.env`:

```env
TURNSTILE_SITE_KEY=0x4AAAAAAEd_PwUxO1vbO1ew
TURNSTILE_SECRET_KEY=tu_secret_key
TURNSTILE_ACTION=admin_login
TURNSTILE_HOSTNAMES=jeftezmont.me,www.jeftezmont.me
```

Si ambas variables de key están vacías, el login funciona sin mostrar CAPTCHA. Si configuras ambas, el formulario mostrará Turnstile y el servidor validará cada intento antes de revisar usuario/contraseña. La validación revisa `success`, `action`, hostname y vigencia del token.

## Passkeys / WebAuthn

El login por passkey funciona junto al login tradicional con contraseña. Para producción:

```env
WEBAUTHN_RP_ID=jeftezmont.me
WEBAUTHN_RP_NAME=jeftezmont.me
WEBAUTHN_ORIGINS=https://jeftezmont.me,https://www.jeftezmont.me
```

En local puedes usar:

```env
WEBAUTHN_RP_ID=localhost
WEBAUTHN_RP_NAME=jeftezmont.me
WEBAUTHN_ORIGINS=http://localhost:8000
```

Para registrar passkeys entra a `/admin/passkeys` con una sesión activa. La autenticación usa WebAuthn estándar compatible con Apple Passwords/iCloud Keychain, Safari, Chrome, Edge, Windows Hello y gestores compatibles.

## Personalización

- Texto biográfico: `/admin/settings`
- Links sociales: `/admin/settings`
- Menú de blog/artículos: `/admin/settings`
- Color de acento y modo coming soon: `/admin/settings`
- Imagen principal duotono: reemplaza `public/assets/img/hero-portrait.png`
- Estilos: `public/assets/css/site.css`

## Backups

`/admin/backups` permite descargar un respaldo ZIP, JSON o SQL del contenido editorial: posts, categorías, etiquetas, medios, navegación y ajustes. Por seguridad no exporta usuarios, passkeys, sesiones, logs ni intentos de login. En el SQL, `author_id` y `uploaded_by` se exportan como `NULL` para que pueda importarse en una base nueva después de crear el administrador.

El ZIP incluye:

- `database.sql`
- `uploads/`
- `manifest.json`
- `README.txt`

No incluye `.env`, logs ni secretos.

## Deploy

`/admin/deploy` muestra una checklist automática para producción/Hostinger: PHP, extensiones, HTTPS, conexión MySQL, tablas, administrador, uploads, logs, `.env` o variables de hosting, `APP_ENV=production`, Turnstile, WebAuthn, favicon, robots, sitemap, archivos sensibles y backups.

Antes de subir:

1. Revisa `/admin/health`.
2. Crea un respaldo desde `/admin/backups`.
3. Revisa `/admin/deploy`.
4. Sube el ZIP sin `.env` real.
5. Configura las variables reales desde Hostinger.

## Migración

El workspace entregado no contenía archivos previos que migrar. Si existen artículos antiguos en CSV, usa:

```bash
php tools/import_posts_csv.php posts.csv
```

Columnas requeridas: `title`, `slug`, `excerpt`, `content`, `status`, `published_at`. Conserva los slugs antiguos para no romper enlaces.
# Tareas programadas

Configura un Cron Job cada cinco minutos usando PHP CLI desde la raíz del proyecto:

```sh
*/5 * * * * /usr/bin/php /ruta/al/proyecto/bin/cron.php
```

En Hostinger selecciona la versión de PHP CLI correspondiente al sitio y sustituye únicamente la ruta genérica por la ruta real del proyecto. El comando usa `flock`, registra su ejecución en `storage/logs/app.log` y no expone ningún endpoint HTTP.

## Caché y recursos XML

El sitemap dinámico (`/sitemap.xml`), el RSS del Blog (`/blog/feed.xml`) y los feeds de Podcast usan caché de archivos en `storage/cache/`, ETag y Last-Modified. Crear, editar, publicar o eliminar contenido invalida la caché pública; el cron elimina entradas vencidas. `storage/cache/` debe ser escribible y permanecer fuera del acceso público.
