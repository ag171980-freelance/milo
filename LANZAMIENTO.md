# Antes de lanzar: lo legal y lo operativo

> **Esto no es asesoramiento legal.** Los textos de `/terminos`, `/privacidad`
> y `/comercios` son una base sólida redactada para la normativa argentina,
> pero un abogado tiene que revisarlos y adaptarlos a tu caso (forma
> societaria, modelo de cobro, proveedores) antes de abrir al público.
> Es una consulta de pocas horas y te puede ahorrar problemas grandes.

## 1. Completar tus datos

- [ ] `frontend/src/legal.js`: razón social, CUIT, domicilio, mail de contacto
      y mail para datos personales. Aparecen en todos los textos legales y en
      el pie de cada página.
- [ ] `frontend/src/pages/legal/Comercios.jsx`, punto 6: precio del servicio
      (abono o comisión, medio de pago, período de prueba).
- [ ] Mencionar en la Política de privacidad (punto 6) a tus proveedores de
      hosting y dónde están sus servidores.

## 2. Tu figura legal e impuestos

- [ ] Definir cómo vas a facturar: monotributo, o una SAS si pensás crecer o
      sumar socios.
- [ ] Inscribirte en ARCA (ex AFIP) y en Ingresos Brutos (AGIP en CABA) por
      la actividad de servicios informáticos o plataformas.
- [ ] Emitir factura a cada local por tu servicio.

## 3. Datos personales (Ley 25.326)

- [ ] **Inscribir las bases de datos** en el Registro Nacional de Bases de
      Datos de la Agencia de Acceso a la Información Pública (AAIP). Tenés al
      menos dos: los datos de los locales (clientes tuyos) y los pedidos (que
      tratás por cuenta de los locales). Verificá con el abogado cómo
      declararlas.
- [ ] Revisar que los plazos de borrado de la Política de privacidad
      coincidan con el `.env` (`FILE_RETENTION_HOURS=24`, `FILE_MAX_HOURS=72`)
      y con `frontend/src/legal.js`.
- [ ] **Cron de `tools/mantenimiento.php` funcionando.** Sin el cron, la
      promesa de borrado no se cumple.
- [ ] Hosting con HTTPS en el front y en la API.
- [ ] Backups de la base cifrados. Los archivos de clientes **no** van a
      backup: duran horas.

## 4. Defensa del Consumidor (Ley 24.240)

Ya está implementado. Verificá que siga visible después de cada cambio de diseño:

- [x] **Botón de arrepentimiento** (Res. SCI 424/2020): se accede desde la
      primera pantalla y desde el pie de cada página, no pide registrarse,
      cancela con devolución total si el pedido no empezó a imprimirse y
      entrega un código de solicitud.
- [x] **Enlace a Defensa del Consumidor** («Defensa de las y los
      consumidores. Para reclamos ingrese aquí», Res. SCI 38/2020) en el pie.
      Confirmá con el abogado que la URL sigue siendo la vigente.
- [x] **Identificación del vendedor** (el local): razón social, CUIT,
      domicilio y contacto al pie de su página. Cada local los carga en
      Configuración. Conviene exigirlos antes de activar a un local.
- [x] Precio final antes de pagar, con impuestos incluidos.
- [x] Aceptación expresa de términos y privacidad. Cada pedido guarda la
      versión aceptada (`terms_version`) y la fecha.

## 5. Propiedad intelectual (Ley 11.723)

- [x] El cliente declara tener derecho a imprimir el archivo y el local puede
      rechazar. Queda en Términos y en Términos para comercios.
- [ ] Con el abogado: la fotocopia de libros enteros es el punto sensible del
      rubro. Los términos dejan la responsabilidad en el local, que es quien
      reproduce. Confirmá que la redacción alcanza.

## 6. Mercado Pago

- [ ] Cada local usa su propia cuenta y su propio access token. Ustedes no
      intermedian dinero, y eso evita tener que registrarse como proveedor de
      servicios de pago ante el BCRA. Si en algún momento querés cobrar
      comisión dentro del mismo pago (split / marketplace), revisalo con el
      abogado antes de hacerlo.
- [ ] Crear la aplicación de la plataforma en Mercado Pago, cargar la URL de
      redirección y PKCE, y poner `MP_CLIENT_ID`/`MP_CLIENT_SECRET` en el `.env`
      (ver README, «Conectar con Mercado Pago»). Probar la conexión con un
      usuario de prueba vendedor.
- [ ] Probar el flujo completo con credenciales de prueba (`TEST-`) y las
      [tarjetas de prueba](https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/additional-content/your-integrations/test/cards):
      pago aprobado, rechazado, rechazo del local con devolución y
      arrepentimiento.
- [ ] Verificar si Mercado Pago devuelve su comisión al hacer una devolución,
      para explicárselo a los locales.
- [ ] Pedirle a cada local que mantenga saldo para devoluciones, o que sepa
      usar «Devolví en efectivo».

## 7. Marca y dominio

- [ ] Buscar «Copiá Fácil» en el INPI y registrar la marca (clase 42, y
      evaluar la 40).
- [ ] Registrar el dominio en NIC Argentina (`.com.ar`) y, si está libre, el
      `.com`.

## 8. Operación

- [ ] `APP_DEBUG=false` y `ALLOW_FAKE_PAYMENTS=false` en producción.
- [ ] `APP_KEY` guardada fuera del servidor (gestor de contraseñas).
- [ ] Un mail de soporte que alguien lea, porque figura en los términos.
- [ ] Revisar periódicamente la tabla `withdrawal_requests`: son las
      solicitudes de arrepentimiento, y las que quedaron como «registrado»
      necesitan respuesta.
