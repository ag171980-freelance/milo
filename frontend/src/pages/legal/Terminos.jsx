import { Link } from 'react-router-dom';
import LegalLayout from './LegalLayout.jsx';
import { OPERADOR, OPERADOR_LEGAL, RETENCION_HORAS, RETENCION_MAX_HORAS } from '../../legal.js';

export default function Terminos() {
  return (
    <LegalLayout title="Términos y condiciones">
      <h2>1. Quiénes somos</h2>
      <p>
        Copiá Fácil (la «Plataforma») es un servicio tecnológico operado por {OPERADOR_LEGAL}, CUIT {OPERADOR.cuit}, con
        domicilio en {OPERADOR.domicilio} («nosotros»). La Plataforma permite que cualquier persona (el «Cliente») envíe archivos
        para imprimir a comercios adheridos (cada uno, el «Local»), elija opciones de impresión y pague en línea.
      </p>
      <p>
        <b>El contrato de impresión se celebra entre el Cliente y el Local.</b> El Local fija los precios, imprime, cobra (a través de
        su propia cuenta de Mercado Pago), emite el comprobante fiscal y entrega el pedido. Los datos del Local (razón social, CUIT,
        domicilio y contacto) figuran al pie de su página dentro de la Plataforma. Nosotros proveemos la herramienta y no imprimimos ni
        vendemos impresiones.
      </p>

      <h2>2. Aceptación</h2>
      <p>
        Al hacer un pedido aceptás estos Términos y la <Link to="/privacidad">Política de privacidad</Link>. Si no estás de acuerdo, no
        uses la Plataforma. Para pedir tenés que tener capacidad legal para contratar; si sos menor de edad, hacelo con la autorización
        de tus padres o responsables.
      </p>

      <h2>3. Cómo funciona un pedido</h2>
      <ol>
        <li>Escaneás el QR del Local o entrás a su página, ponés tu nombre, subís el archivo y elegís las opciones.</li>
        <li>Antes de pagar ves el precio final, calculado con los precios vigentes del Local.</li>
        <li>Pagás con Mercado Pago. El pedido llega al Local recién cuando el pago queda aprobado.</li>
        <li>Seguís el estado desde el link de tu pedido y lo retirás en el Local con tu nombre o número.</li>
      </ol>
      <p>
        Si el archivo es una foto o un PDF, la Plataforma cuenta las páginas automáticamente. Si no puede hacerlo (por ejemplo, con
        algunos archivos de Word), las declarás vos; el Local puede revisar la cantidad antes de imprimir y, si no coincide, rechazar el
        pedido con devolución completa.
      </p>

      <h2>4. Precios y pagos</h2>
      <p>
        Los precios los fija cada Local y se muestran como precio final en pesos argentinos, con los impuestos incluidos. El precio que se
        cobra es el que ves al confirmar el pedido. Los pagos se procesan a través de Mercado Pago, según sus propios términos y
        condiciones. Nosotros no recibimos, retenemos ni administramos el dinero, y no accedemos a los datos de tu tarjeta ni de tu cuenta
        de Mercado Pago. El comprobante fiscal de la compra lo emite el Local.
      </p>

      <h2>5. Tus archivos y su contenido</h2>
      <p>Al subir un archivo declarás y garantizás que:</p>
      <ul>
        <li>
          tenés derecho a reproducirlo, porque es tuyo, tenés autorización de quien tiene los derechos o la reproducción está permitida por
          la Ley 11.723 de Propiedad Intelectual;
        </li>
        <li>no contiene material ilícito, como documentación falsa o adulterada, contenido que viole la intimidad de terceros, material de explotación sexual de personas menores de edad o cualquier otro contenido prohibido por la ley.</li>
      </ul>
      <p>
        El Local puede rechazar cualquier pedido que considere ilícito, que infrinja derechos de terceros o que no pueda realizar. Si lo
        rechaza, se te devuelve el importe pagado. Ante contenido manifiestamente ilícito, el Local o nosotros podremos dar aviso a las
        autoridades competentes.
      </p>
      <p>
        El archivo sólo lo puede abrir el Local al que se lo enviaste, para imprimirlo. Se borra automáticamente {RETENCION_HORAS} horas
        después de que el pedido se entrega, se rechaza o se cancela, y en ningún caso se conserva más de {RETENCION_MAX_HORAS} horas
        desde que lo subiste.
      </p>

      <h2>6. Rechazos, cancelaciones y devoluciones</h2>
      <ul>
        <li><b>Si el Local rechaza tu pedido</b>, se inicia en el momento la devolución total por Mercado Pago, al mismo medio con el que pagaste.</li>
        <li>
          <b>Si cancelás antes de que el Local empiece a imprimir</b>, te devolvemos el 100% (ver punto 7).
        </li>
        <li>
          Los plazos de acreditación los define Mercado Pago y tu medio de pago: con dinero en cuenta suele ser inmediato; con tarjeta de
          crédito o débito puede tardar varios días hábiles o figurar en el próximo resumen.
        </li>
        <li>
          Si por algún motivo la devolución por Mercado Pago no pudiera hacerse, el Local te la hace por otro medio, por ejemplo en efectivo
          en el mostrador.
        </li>
        <li>Si no retirás un pedido ya impreso, el Local puede conservarlo por un plazo razonable. No corresponde devolución, porque el trabajo se hizo según lo que pediste.</li>
      </ul>

      <h2>7. Derecho de revocación y botón de arrepentimiento</h2>
      <p>
        Como en toda compra a distancia, tenés derecho a revocar la aceptación dentro de los 10 días corridos (art. 34 de la Ley 24.240 y
        arts. 1110 y siguientes del Código Civil y Comercial). Podés ejercerlo de forma simple desde el{' '}
        <Link to="/arrepentimiento">botón de arrepentimiento</Link>, que está siempre visible en la Plataforma, o desde la página de tu pedido.
        Al hacerlo recibís un código de identificación de tu solicitud.
      </p>
      <p>
        <b>Mientras el pedido no empezó a imprimirse</b>, la cancelación es inmediata y te devolvemos el total sin costo. <b>Una vez que el
        Local empezó a imprimir</b>, el trabajo se hace con tu archivo y según tus especificaciones, y por eso queda alcanzado por la excepción
        del art. 1116, inciso a), del Código Civil y Comercial. Aun así, tu solicitud queda registrada y el Local la va a revisar.
      </p>

      <h2>8. Calidad del trabajo</h2>
      <p>
        Si la impresión tiene fallas atribuibles al Local (hojas faltantes, opciones distintas de las que pediste, defectos de impresión),
        reclamalo en el Local al retirar el pedido. Tenés todos los derechos que te da la Ley 24.240. La calidad final también depende del
        archivo que subís (resolución, márgenes, fuentes).
      </p>

      <h2>9. Responsabilidad</h2>
      <p>
        Hacemos lo razonable para que la Plataforma funcione de forma continua y segura, pero puede haber interrupciones por mantenimiento,
        fallas de terceros (por ejemplo, de Mercado Pago o del proveedor de internet) o causas de fuerza mayor. Nada en estos Términos limita
        los derechos que te otorga la normativa de defensa del consumidor.
      </p>

      <h2>10. Uso indebido</h2>
      <p>
        No está permitido usar la Plataforma para enviar pedidos falsos, intentar acceder a archivos o pedidos ajenos, interferir con su
        funcionamiento ni automatizar pedidos. Podemos limitar o bloquear el acceso ante estos usos.
      </p>

      <h2>11. Cambios</h2>
      <p>
        Podemos actualizar estos Términos. La versión vigente es la publicada en esta página, con su fecha. Cada pedido se rige por la
        versión que aceptaste al hacerlo.
      </p>

      <h2>12. Ley aplicable, reclamos y contacto</h2>
      <p>
        Estos Términos se rigen por las leyes de la República Argentina. Para reclamos sobre un pedido, contactá primero al Local con los
        datos que figuran en su página. Para consultas sobre la Plataforma, escribinos a {OPERADOR.email}. También podés hacer tu reclamo
        ante la autoridad de defensa del consumidor desde{' '}
        <a href="https://www.argentina.gob.ar/produccion/defensadelconsumidor/formulario" target="_blank" rel="noreferrer">este formulario</a>
        {' '}o ante la autoridad de tu jurisdicción. Son competentes los tribunales que correspondan según la Ley 24.240.
      </p>
    </LegalLayout>
  );
}
