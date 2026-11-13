import LegalLayout from './LegalLayout.jsx';
import { OPERADOR, OPERADOR_LEGAL, RETENCION_HORAS, RETENCION_MAX_HORAS } from '../../legal.js';

export default function Privacidad() {
  return (
    <LegalLayout title="Política de privacidad">
      <h2>1. Quién trata tus datos</h2>
      <p>
        Esta política explica cómo se tratan los datos personales en Copiá Fácil, según la Ley 25.326 de Protección de los Datos Personales
        y su Decreto reglamentario 1558/2001.
      </p>
      <ul>
        <li>
          <b>Los datos de tu pedido</b> (nombre, archivo y opciones de impresión) se tratan por cuenta del <b>Local</b> al que le hacés el
          pedido, que es el responsable de ese tratamiento. Sus datos de contacto figuran al pie de su página.
        </li>
        <li>
          <b>{OPERADOR_LEGAL}</b> (CUIT {OPERADOR.cuit}, {OPERADOR.domicilio}), que opera la Plataforma, trata esos datos por cuenta y
          según las instrucciones del Local, en los términos del art. 25 de la Ley 25.326. Además es responsable de los datos técnicos y de
          seguridad de la Plataforma y de los datos de las cuentas de los Locales.
        </li>
      </ul>

      <h2>2. Qué datos tratamos</h2>
      <ul>
        <li><b>Del pedido:</b> el nombre que ingresás, el archivo y su contenido, la cantidad de páginas, las opciones elegidas, la nota para el Local y el estado del pedido.</li>
        <li><b>Del pago:</b> Mercado Pago nos informa el número de operación, el estado, el monto y el medio de pago utilizado. <b>No recibimos ni guardamos datos de tarjetas ni credenciales de Mercado Pago.</b></li>
        <li><b>Técnicos:</b> un código derivado de tu dirección IP mediante una función irreversible (no guardamos la IP), que usamos para prevenir abusos, y los registros normales del servidor.</li>
        <li><b>De quienes piden una demo:</b> nombre, nombre del local, mail y, si los dejás, teléfono, dirección y comentario. Los usamos sólo para contactarte por la demo y los borramos si no la activás dentro de los 6 meses.</li>
        <li><b>De los Locales:</b> nombre del comercio, razón social, CUIT, contacto, mail y contraseña de acceso (guardada con un hash irreversible) y el token de Mercado Pago (guardado cifrado).</li>
      </ul>
      <p>No pedimos datos sensibles. Si tu archivo los contiene (por ejemplo, información de salud), se tratan con la misma reserva y se borran en los mismos plazos.</p>

      <h2>3. Para qué los usamos</h2>
      <ul>
        <li>Recibir, cobrar e imprimir tu pedido, y avisarte en qué estado está.</li>
        <li>Hacer devoluciones cuando corresponda y atender reclamos.</li>
        <li>Prevenir fraudes y usos abusivos, y mantener la seguridad de la Plataforma.</li>
        <li>Cumplir obligaciones legales, contables y fiscales.</li>
      </ul>
      <p>
        Tratamos tus datos con tu consentimiento, que prestás al aceptar esta política al hacer el pedido, y porque son necesarios para
        cumplir el contrato que celebrás con el Local (art. 5 de la Ley 25.326). No usamos tus datos para publicidad ni los vendemos.
      </p>

      <h2>4. Tu archivo</h2>
      <ul>
        <li>Se guarda en servidores no accesibles públicamente. Sólo el Local puede abrirlo, desde su panel y mediante un enlace temporal que vence a los 5 minutos.</li>
        <li>Se borra automáticamente <b>{RETENCION_HORAS} horas</b> después de que el pedido se entrega, se rechaza o se cancela y, en cualquier caso, <b>a las {RETENCION_MAX_HORAS} horas</b> de haberlo subido.</li>
        <li>Los Locales se comprometen a usar el archivo sólo para imprimir tu pedido y a no conservar copias.</li>
        <li>Nosotros no accedemos al contenido de los archivos, salvo que sea indispensable para resolver una falla técnica que reportes o que lo ordene una autoridad competente.</li>
      </ul>

      <h2>5. Cuánto tiempo guardamos el resto</h2>
      <p>
        El registro del pedido, sin el archivo (nombre, opciones, montos, estado e historial), se conserva durante el tiempo necesario para
        atender reclamos y cumplir obligaciones legales, contables y fiscales. Después se elimina o se anonimiza.
      </p>

      <h2>6. Con quién se comparten</h2>
      <ul>
        <li><b>El Local</b> al que le hacés el pedido.</li>
        <li><b>Mercado Pago</b>, para procesar el pago y las devoluciones, según su propia política de privacidad.</li>
        <li><b>Proveedores de infraestructura</b> (alojamiento web y de servidores) que nos prestan servicios con obligación de confidencialidad. Algunos pueden estar fuera de la Argentina. En ese caso, la transferencia se hace con garantías adecuadas, según el art. 12 de la Ley 25.326 y la normativa de la Agencia de Acceso a la Información Pública.</li>
        <li><b>Autoridades</b>, cuando lo exija la ley o una orden judicial.</li>
      </ul>

      <h2>7. Almacenamiento en tu navegador y notificaciones</h2>
      <p>
        No usamos cookies de publicidad ni herramientas de seguimiento de terceros. El panel de los Locales guarda en el navegador un
        identificador de sesión para mantener la sesión iniciada. Si aceptás recibir notificaciones, el permiso lo administra tu navegador y
        lo podés revocar desde su configuración en cualquier momento.
      </p>

      <h2>8. Seguridad</h2>
      <p>
        Usamos conexiones cifradas (HTTPS), almacenamiento no público de archivos, enlaces de descarga firmados y con vencimiento,
        contraseñas con hash, cifrado de las credenciales de pago y límites de intentos contra accesos indebidos. Ningún sistema es
        infalible. Si detectamos un incidente que afecte tus datos, vamos a actuar según la normativa vigente.
      </p>

      <h2>9. Tus derechos</h2>
      <p>
        Podés pedir el acceso, la rectificación, la actualización o la supresión de tus datos escribiendo a {OPERADOR.emailPrivacidad}. Para
        datos de un pedido, también podés dirigirte al Local. Respondemos los pedidos de acceso en un plazo de 10 días corridos y los de
        rectificación o supresión en 5 días hábiles (arts. 14 y 16 de la Ley 25.326). También podés retirar tu consentimiento, sin efecto
        retroactivo.
      </p>
      <div className="box">
        El titular de los datos personales tiene la facultad de ejercer el derecho de acceso a los mismos en forma gratuita a intervalos no
        inferiores a seis meses, salvo que se acredite un interés legítimo al efecto conforme lo establecido en el artículo 14, inciso 3 de la
        Ley Nº 25.326. La AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA, en su carácter de Órgano de Control de la Ley Nº 25.326, tiene la
        atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por incumplimiento de las
        normas vigentes en materia de protección de datos personales.
      </div>

      <h2>10. Menores de edad</h2>
      <p>La Plataforma no está dirigida a menores de 13 años. Si sos menor de edad, usala con la autorización de tus padres o responsables.</p>

      <h2>11. Cambios en esta política</h2>
      <p>Si cambiamos esta política, publicamos la nueva versión en esta página con su fecha. Si el cambio es importante, lo vamos a avisar en la Plataforma.</p>

      <h2>12. Contacto</h2>
      <p>{OPERADOR_LEGAL} · {OPERADOR.domicilio} · {OPERADOR.emailPrivacidad}</p>
    </LegalLayout>
  );
}
