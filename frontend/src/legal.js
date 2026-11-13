// Datos del operador de la plataforma.
// COMPLETAR ANTES DE LANZAR: aparecen en los Términos, la Política de
// privacidad y los Términos para comercios.
//
// titular: quien opera legalmente el servicio. Mientras no haya una
// sociedad inscripta, es la persona: nombre y apellido tal como figuran
// en ARCA. Si más adelante armás una SAS, va su razón social completa
// (por ejemplo «Austral Solutions S.A.S.») y se puede borrar `marca`.
// marca: nombre comercial (de fantasía). Es lo que se ve en el pie.
export const OPERADOR = {
  titular: 'Alexis Gutierrez',
  marca: 'Austral Solutions',
  cuit: '[CUIT]',
  domicilio: '[DOMICILIO LEGAL, CABA]',
  email: '[EMAIL DE CONTACTO]',
  emailPrivacidad: '[EMAIL PARA DATOS PERSONALES]',
};

// Cómo se nombra al operador en los textos legales: «Juan Pérez (Austral Solutions)».
export const OPERADOR_LEGAL = OPERADOR.marca ? `${OPERADOR.titular} (${OPERADOR.marca})` : OPERADOR.titular;

// Tiene que coincidir con TERMS_VERSION del backend (.env).
export const TERMS_VERSION = '2026-11';
export const ULTIMA_ACTUALIZACION = '12 de noviembre de 2026';

// Debe coincidir con FILE_RETENTION_HOURS y FILE_MAX_HOURS del backend.
export const RETENCION_HORAS = 24;
export const RETENCION_MAX_HORAS = 72;
