import LegalLayout from './LegalLayout.jsx';
import { OPERADOR, OPERADOR_LEGAL, RETENCION_HORAS, RETENCION_MAX_HORAS } from '../../legal.js';

export default function Comercios() {
  return (
    <LegalLayout title="Términos para comercios">
      <p>
        Estos términos regulan la relación entre {OPERADOR_LEGAL}, CUIT {OPERADOR.cuit} («Copiá Fácil»), y cada comercio que usa la
        Plataforma para recibir pedidos de impresión (el «Local»). Al activar su cuenta, el Local los acepta.
      </p>

      <h2>1. El servicio</h2>
      <p>
        Copiá Fácil le da al Local una página para recibir pedidos (con su QR), un panel para gestionarlos y la integración con la cuenta de
        Mercado Pago del Local para cobrarlos y devolverlos. Copiá Fácil no imprime, no vende, no cobra por cuenta del Local y no recibe
        el dinero de los pedidos.
      </p>

      <h2>2. Cuenta y credenciales</h2>
      <p>
        El Local es responsable de mantener la confidencialidad de su usuario y contraseña, y de todo lo que se haga desde su cuenta. Si
        sospecha un acceso indebido, tiene que cambiar la contraseña y avisarnos de inmediato a {OPERADOR.email}.
      </p>

      <h2>3. Obligaciones del Local</h2>
      <ul>
        <li><b>Es el vendedor.</b> Fija precios finales con impuestos incluidos, cumple los pedidos que acepta y atiende a sus clientes según la Ley 24.240 de Defensa del Consumidor.</li>
        <li><b>Facturación:</b> emite a sus clientes los comprobantes fiscales que correspondan por cada venta, según la normativa de ARCA.</li>
        <li><b>Datos verdaderos:</b> carga y mantiene actualizados su razón social, CUIT, domicilio y contacto, que se muestran a los clientes.</li>
        <li><b>Devoluciones:</b> mantiene saldo suficiente en su cuenta de Mercado Pago para las devoluciones de pedidos rechazados o cancelados. Si una devolución automática falla, la resuelve por otro medio a la brevedad y la registra en el panel.</li>
        <li><b>Arrepentimiento:</b> respeta la cancelación con devolución total de los pedidos que todavía no empezó a imprimir, y revisa las solicitudes que queden registradas.</li>
        <li><b>Contenido:</b> rechaza pedidos con contenido ilícito o que infrinjan derechos de propiedad intelectual (Ley 11.723). El Local es el único responsable de las reproducciones que realiza.</li>
      </ul>

      <h2>4. Datos personales de los clientes</h2>
      <p>
        Respecto de los datos de los pedidos, el Local es el <b>responsable del tratamiento</b> y Copiá Fácil presta el servicio de tratamiento
        por su cuenta, según el art. 25 de la Ley 25.326. En consecuencia:
      </p>
      <ul>
        <li>Copiá Fácil trata esos datos sólo para prestar el servicio y según estas instrucciones, con medidas de seguridad y confidencialidad, y no los usa para fines propios ni los cede, salvo a los proveedores de infraestructura necesarios o por obligación legal.</li>
        <li>Los archivos se eliminan automáticamente {RETENCION_HORAS} horas después de cerrado el pedido y, como máximo, a las {RETENCION_MAX_HORAS} horas de subidos.</li>
        <li>El Local se obliga a usar los archivos sólo para imprimir el pedido, a no descargarlos más allá de lo necesario para hacerlo y a no conservar copias.</li>
        <li>El Local atiende, con nuestra colaboración, los pedidos de acceso, rectificación o supresión de sus clientes.</li>
        <li>Si la relación termina, eliminamos los datos de los pedidos del Local, salvo los que debamos conservar por obligación legal.</li>
      </ul>

      <h2>5. Mercado Pago</h2>
      <p>
        El Local cobra con su propia cuenta de Mercado Pago y queda sujeto a los términos, comisiones y plazos de acreditación de Mercado
        Pago, que corren por su cuenta. El Local carga su access token en el panel. Copiá Fácil lo guarda cifrado y lo usa sólo para crear
        los cobros, consultar su estado y hacer devoluciones de los pedidos del Local. El Local puede desconectarlo desde el panel o
        revocarlo desde Mercado Pago en cualquier momento. Copiá Fácil no es agente de pago ni procesador de pagos.
      </p>

      <h2>6. Precio del servicio</h2>
      <p>
        [COMPLETAR: plan, abono mensual o comisión por pedido, medio y fecha de pago, y período de prueba si lo hay.] Copiá Fácil emite la
        factura correspondiente por su servicio. Avisaremos cualquier cambio de precio con al menos 30 días de anticipación. Si el Local no
        está de acuerdo, puede dar de baja la cuenta antes de que el cambio entre en vigencia.
      </p>

      <h2>7. Disponibilidad</h2>
      <p>
        Hacemos lo razonable para mantener la Plataforma disponible, pero no garantizamos que funcione sin interrupciones. Puede haber
        pausas por mantenimiento (que, en lo posible, avisaremos) o por fallas de terceros, como Mercado Pago o los proveedores de
        infraestructura. El Local puede cerrar su página de pedidos desde el panel en cualquier momento.
      </p>

      <h2>8. Responsabilidad</h2>
      <p>
        Copiá Fácil no responde por el contenido de los archivos, por la calidad de las impresiones ni por la relación comercial entre el Local
        y sus clientes. Salvo dolo o culpa grave, la responsabilidad total de Copiá Fácil frente al Local se limita al monto que el Local
        haya pagado por el servicio en los tres meses anteriores al hecho que origine el reclamo.
      </p>

      <h2>9. Propiedad intelectual</h2>
      <p>
        La Plataforma, su código, diseño y marca pertenecen a Copiá Fácil. El Local recibe una licencia de uso no exclusiva e intransferible
        mientras su cuenta esté activa. El Local nos autoriza a mostrar su nombre en la Plataforma y en el QR que genera.
      </p>

      <h2>10. Suspensión y baja</h2>
      <p>
        El Local puede dar de baja su cuenta en cualquier momento escribiendo a {OPERADOR.email}. Podemos suspender o dar de baja una cuenta
        si el Local incumple estos términos, usa la Plataforma para fines ilícitos o no paga el servicio, avisándole cuando sea posible.
        Los pedidos ya pagados al momento de la baja deben cumplirse o devolverse.
      </p>

      <h2>11. Cambios</h2>
      <p>Podemos modificar estos términos avisando al Local con al menos 30 días de anticipación. Si sigue usando la Plataforma después de esa fecha, se entiende que acepta los cambios.</p>

      <h2>12. Ley aplicable y jurisdicción</h2>
      <p>
        Estos términos se rigen por las leyes de la República Argentina. Ante cualquier controversia, las partes se someten a los tribunales
        ordinarios de la Ciudad Autónoma de Buenos Aires.
      </p>
    </LegalLayout>
  );
}
