# Copiá Fácil

Pedidos de impresión por QR para kioscos, locutorios y fotocopiadoras.
El cliente escanea el QR del local, sube el archivo, elige opciones y paga
con Mercado Pago. El local lo recibe en su panel, lo imprime y le avisa.

```
copia-facil/
├── frontend/     React + Vite (cliente, panel /admin y páginas legales)
├── backend/      API en PHP sin frameworks + MySQL
├── demo/         Demo estática para mostrar en los locales (sin backend)
├── netlify.toml  Front en Netlify, /api por proxy al backend
└── LANZAMIENTO.md  Lo legal y operativo antes de abrir al público
```

Misma arquitectura que fried-box: PHP 8.2+, sin Composer, PDO con
prepared statements reales y multi-local con `tenant_id` en cada tabla.

## Demo para vender (`demo/`)

Una sola página HTML con el celular del cliente y el panel del local lado a
lado, datos simulados y un recorrido guiado (driver.js). No necesita backend.

- Verla en local: `cd demo && python3 -m http.server 8090`.
- Publicarla: en Netlify, un **sitio aparte** con *Base directory* `demo`, sin
  comando de build y con *Publish directory* `demo`. O arrastrar la carpeta a
  [app.netlify.com/drop](https://app.netlify.com/drop).
- Las notificaciones de la demo necesitan https: funcionan publicada, no con
  la IP local.

## Arrancar en local

```bash
# 1. Base (MySQL 8 local)
mysql -u root -h 127.0.0.1 < backend/database/schema.sql

# 2. Backend
cp backend/.env.example backend/.env
php backend/tools/generar-clave.php          # pegar el APP_KEY en backend/.env
php backend/tools/nuevo-local.php kiosco-el-puente "Kiosco El Puente" vos@mail.com
cd backend/public && php -d upload_max_filesize=30M -d post_max_size=32M -S 127.0.0.1:8081 index.php

# 3. Front, en otra terminal
cd frontend && npm install && npm run dev
```

| URL | Qué es |
| --- | --- |
| `http://localhost:5173/kiosco-el-puente` | La página del local (la del QR) |
| `http://localhost:5173/kiosco-el-puente/pedido/{id}` | El estado de un pedido |
| `http://localhost:5173/admin` | El panel del local (pide usuario) |
| `/terminos`, `/privacidad`, `/comercios`, `/arrepentimiento` | Legales |

Sin Mercado Pago conectado y con `ALLOW_FAKE_PAYMENTS=true`, el pago se
simula (y la página lo avisa). Con un token cargado, el simulador se apaga
solo para ese local.

## Cómo funciona

1. `GET /t/{slug}`: el local, sus precios y si acepta pedidos.
2. `POST /t/{slug}/orders` (multipart): valida, guarda el archivo **fuera de
   `public/`**, cuenta páginas (PDF, imágenes y la mayoría de los .docx),
   **calcula el precio en el servidor** y crea la preferencia de Mercado Pago
   con el token del local. Devuelve `checkoutUrl`.
3. El cliente paga y vuelve a `/{slug}/pedido/{id}`. Esa página consulta
   `POST …/sincronizar`, que le pregunta a Mercado Pago. Lo que viene en la
   URL de vuelta no se usa para nada. En producción además llega el
   webhook a `POST /webhooks/mercadopago/{slug}`.
4. Aprobado: el pedido pasa a «nuevo» y aparece en el panel con un aviso
   sonoro. El local lo abre con un link firmado que vence a los 5 minutos,
   lo imprime desde el visor del navegador y lo marca listo.
5. Si lo rechaza, se devuelve la plata por la API de Mercado Pago. Si la
   devolución falla (por ejemplo, por falta de saldo), queda «devolución
   pendiente» y el local elige entre reintentar o marcar que devolvió en
   efectivo.

### Conectar con Mercado Pago (OAuth)

El local toca **«Conectar con Mercado Pago»** en Configuración, entra con su
cuenta, autoriza y vuelve al panel conectado. No crea aplicaciones ni copia
tokens.

**Configuración, una sola vez (vos, como plataforma):**

1. En [Tus integraciones](https://www.mercadopago.com.ar/developers/panel/app)
   creá UNA aplicación con tu cuenta (Pagos online → Checkout Pro).
2. En la configuración de la app cargá la **URL de redirección**:
   `https://TU_API/oauth/mercadopago/callback`. Tiene que ser https y
   coincidir carácter por carácter con `MP_REDIRECT_URI`.
3. Activá **PKCE** en la app (si no, poné `MP_PKCE=false`).
4. Copiá el Client ID y el Client Secret al `.env`:
   `MP_CLIENT_ID=…` y `MP_CLIENT_SECRET=…`.

Para probar en local hace falta una dirección https: usá un túnel
(`cloudflared tunnel --url http://localhost:8081`) y poné esa URL en la app y
en `MP_REDIRECT_URI`. Autorizá con un **usuario de prueba vendedor**, con
`MP_OAUTH_TEST=true`.

**Renovación:** el access token dura 180 días. `tools/mantenimiento.php` lo
renueva con el refresh token 30 días antes de que venza y guarda siempre el
último refresh token que devuelve Mercado Pago. Si la renovación falla (por
ejemplo, porque el local revocó el permiso), al vencer el panel le pide que
vuelva a conectar y su página deja de tomar pedidos hasta que lo haga.

Sin `MP_CLIENT_ID` el panel muestra sólo la opción manual (pegar el token),
que también sigue disponible como «opción avanzada».

Bases creadas antes de esto: `mysql -u root < backend/database/migraciones/2026-11-12-mp-oauth.sql`.

### Mercado Pago por local

Cada local pega su **access token** en Configuración. El backend lo valida
contra `/users/me` y lo guarda **cifrado con `APP_KEY`** (libsodium). La
plata va directo a la cuenta del local; la plataforma nunca la toca.

- `TEST-…`: modo prueba (sandbox). El panel lo marca.
- `APP_USR-…`: producción.
- Webhook: el panel muestra la URL para cargar en «Tus integraciones →
  Webhooks» (evento «Pagos»). No se valida la firma porque cada local tiene
  su propia clave. Es seguro igual: el aviso sólo dispara una consulta a la
  API con el token del local, y lo que vale es lo que responde la API.

**Si se pierde `APP_KEY`, los tokens guardados no se pueden leer** y cada
local tiene que volver a cargar el suyo. Guardala aparte del servidor.

### Archivos y privacidad

- Se aceptan PDF, JPG, PNG, DOC y DOCX hasta `MAX_UPLOAD_MB`. El tipo se
  decide por el contenido (finfo), no por la extensión.
- Se borran a las `FILE_RETENTION_HOURS` de cerrado el pedido y siempre a
  las `FILE_MAX_HOURS`. Esto es lo que promete la Política de privacidad:
  **configurá el cron** para que se cumpla aunque el local no abra el panel:

  ```
  */15 * * * * php /ruta/backend/tools/mantenimiento.php
  ```

- La IP no se guarda: se guarda un HMAC, sólo para limitar abusos.

### Seguridad

- Sesiones del panel con token opaco (en la base, su SHA-256) y bcrypt.
- Límites de intentos: login (10 cada 15 minutos por IP), pedidos (15 cada
  10 minutos por IP y local) y arrepentimiento.
- Cambios de estado sólo hacia adelante. Toda consulta filtra por local.
- Los montos se calculan en centavos enteros.

## Demo gratis de una semana

La home tiene el botón «Pedí tu demo gratis de una semana», que lleva a un
formulario (con campo trampa contra bots y límite de 5 envíos por hora por IP).
Cada solicitud queda en `demo_requests` y, si configurás `DEMO_NOTIFY_EMAIL`,
también te llega por mail.

```bash
php backend/tools/solicitudes-demo.php               # las nuevas
php backend/tools/solicitudes-demo.php 3 contactada  # cambiar estado
php backend/tools/nuevo-local.php libreria-patricios "Librería Patricios" carla@mail.com --prueba=7
php backend/tools/solicitudes-demo.php 3 activada
```

Con `--prueba=7` el local queda con `trial_ends_at` a 7 días. El panel le
muestra cuándo termina y, al vencer, su página deja de tomar pedidos.
Para pasarlo a cuenta paga:
`UPDATE tenants SET trial_ends_at = NULL WHERE slug = 'libreria-patricios';`

Bases creadas antes de esto: `mysql -u root < backend/database/migraciones/2026-11-12-demo.sql`.

## Producción

1. **Backend** en un hosting con PHP 8.2+ (extensiones `pdo_mysql`, `curl`,
   `sodium`, `fileinfo`, `zip`) y MySQL 8. El document root apunta a
   `backend/public/`. Configurá `.env` con `APP_ENV=production`,
   `APP_DEBUG=false`, `ALLOW_FAKE_PAYMENTS=false`, `FRONT_URL` y
   `API_PUBLIC_URL` (https), y un `STORAGE_DIR` fuera del document root.
   Para la subida de archivos, ajustá `upload_max_filesize` y `post_max_size`
   (ver `public/.user.ini`).
2. **Front** en Netlify: conectá el repo. `netlify.toml` ya tiene el build;
   reemplazá `API_HOST`.
3. **Cron** de `tools/mantenimiento.php`.
4. Completá `frontend/src/legal.js` con tus datos y seguí
   [LANZAMIENTO.md](LANZAMIENTO.md).

## Lo que sigue

- **Web Push**: hoy el aviso de «listo» llega mientras el cliente tiene la
  página abierta (aunque esté en segundo plano). Para avisar con el
  navegador cerrado hace falta Web Push con claves VAPID desde el backend.
- **Recuperar contraseña por mail.** Hoy la resetea el operador con
  `tools/nuevo-local.php` o por SQL.
- **Panel del operador** para dar de alta locales desde la web y ver métricas.
- Impresión directa sin abrir el visor (requiere un agente instalado en la PC
  del local).
