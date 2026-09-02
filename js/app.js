// Funciones compartidas por todas las paginas

// "Aula Virtual" es un valor mas de sede (igual que Lima Centro, Lima Norte o
// Arequipa) — asi es como CERTUS lo maneja realmente, sin un campo aparte.
function esVirtual(cap){
  return cap.sede === "Aula Virtual";
}

function badgeClase(estado){
  if(estado === "pendiente") return "badge-pendiente";
  if(estado === "en curso") return "badge-curso";
  if(estado === "completada") return "badge-completada";
  return "";
}

function badgeTexto(estado){
  return estado.charAt(0).toUpperCase() + estado.slice(1);
}

// Semaforo de cumplimiento (MEJORA): compara fecha_limite con hoy
function semaforoClase(inscripcion){
  if(inscripcion.estado === "completada") return "semaforo-verde";
  const hoy = new Date("2026-08-26"); // fecha simulada del sistema
  const limite = new Date(inscripcion.fecha_limite);
  const diffDias = (limite - hoy) / (1000*60*60*24);
  if(diffDias < 0) return "semaforo-rojo";
  if(diffDias <= 5) return "semaforo-ambar";
  return "semaforo-verde";
}

function semaforoTexto(inscripcion){
  const clase = semaforoClase(inscripcion);
  if(clase === "semaforo-verde" && inscripcion.estado === "completada") return "Al dia";
  if(clase === "semaforo-verde") return "Al dia";
  if(clase === "semaforo-ambar") return "Por vencer";
  return "Vencido";
}

function nombreDocente(id){
  const d = DOCENTES.find(x => x.id === id);
  return d ? d.nombres + " " + d.apellidos : "-";
}

function nombreCapacitacion(id){
  const c = CAPACITACIONES.find(x => x.id === id);
  return c ? c.titulo : "-";
}

// Animacion de conteo para las tarjetas de metricas
function animateCount(el, target, suffix){
  suffix = suffix || "";
  const start = 0;
  const duration = 700;
  const t0 = performance.now();
  function tick(now){
    const p = Math.min(1, (now - t0) / duration);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(start + (target - start) * eased) + suffix;
    if(p < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

// Notificaciones tipo toast (reemplazan alert())
function showToast(mensaje, tipo){
  tipo = tipo || "info";
  let contenedor = document.querySelector(".toast-container");
  if(!contenedor){
    contenedor = document.createElement("div");
    contenedor.className = "toast-container";
    document.body.appendChild(contenedor);
  }
  const iconos = {success:"fa-circle-check", error:"fa-circle-exclamation", info:"fa-circle-info"};
  const toast = document.createElement("div");
  toast.className = "toast " + tipo;
  toast.innerHTML = `<i class="fa-solid ${iconos[tipo] || iconos.info}"></i><span>${mensaje}</span>`;
  contenedor.appendChild(toast);
  setTimeout(() => {
    toast.style.transition = "opacity .3s ease, transform .3s ease";
    toast.style.opacity = "0";
    toast.style.transform = "translateY(8px)";
    setTimeout(() => toast.remove(), 300);
  }, 2800);
}

function logout(){
  sessionStorage.removeItem("sesion_certus");
  window.location.href = "../login.html";
}

// Insignias ganadas por un docente, segun capacitaciones completadas (MEJORA)
// La insignia "Mentor Certus" (requiere_capacitacion null) se gana dando
// al menos 10 sesiones como mentor (cruce con MENTORES por nombre).
// Las insignias con evidencia:true (patron BloomBoard/Digital Promise) exigen
// ademas que el administrador apruebe una evidencia enviada por el docente.
function insigniasDeDocente(idDocente){
  const completadasIds = INSCRIPCIONES
    .filter(i => i.id_docente === idDocente && i.estado === "completada")
    .map(i => i.id_capacitacion);

  const docente = DOCENTES.find(d => d.id === idDocente);
  const mentorPropio = docente
    ? MENTORES.find(m => m.nombres === `${docente.nombres} ${docente.apellidos}`)
    : null;
  const evidencias = evidenciasDeDocente(idDocente);

  return INSIGNIAS.map(ins => {
    const cumpleRequisito = ins.requiere_capacitacion === null
      ? !!(mentorPropio && mentorPropio.sesiones >= 10)
      : completadasIds.includes(ins.requiere_capacitacion);

    if(!ins.evidencia){
      return {...ins, ganada: cumpleRequisito, cumpleRequisito, estadoEvidencia: null};
    }
    const ultimaEvidencia = [...evidencias].reverse().find(e => e.id_insignia === ins.id);
    const estadoEvidencia = ultimaEvidencia ? ultimaEvidencia.estado : null;
    return {...ins, ganada: cumpleRequisito && estadoEvidencia === "aprobada", cumpleRequisito, estadoEvidencia};
  });
}

// ===== Horas de desarrollo profesional acumuladas (MEJORA) =====
// Patron tomado de los registros estatales de PD en EE.UU. (Michigan,
// Massachusetts, Wisconsin, Texas): el dashboard central es el conteo de
// horas acumuladas por docente. Se calcula sumando la duracion de las
// capacitaciones que ya completo.
function horasAcumuladas(idDocente){
  const completadas = INSCRIPCIONES.filter(i => i.id_docente === idDocente && i.estado === "completada");
  return completadas.reduce((total, i) => {
    const cap = CAPACITACIONES.find(c => c.id === i.id_capacitacion);
    const horas = cap ? (parseInt(cap.duracion, 10) || 0) : 0;
    return total + horas;
  }, 0);
}

// Meta institucional de horas de PD por periodo (MEJORA). La mayoria de
// registros estatales de PD en EE.UU. exige entre 15 y 30 horas por ciclo de
// renovacion (ej. Carolina del Norte: 80 horas cada 5 anios, ~16/anio;
// Missouri: 15 horas/anio); CERTUS usa 20 horas por periodo academico como
// referencia equivalente, solo para mostrar avance — no bloquea nada.
const META_HORAS_PERIODO = 20;
function porcentajeHorasMeta(idDocente){
  return Math.min(100, Math.round(horasAcumuladas(idDocente) / META_HORAS_PERIODO * 100));
}

// ===== Docentes de tu misma area (MEJORA — version ligera de una PLC) =====
// Inspirado en las "Professional Learning Communities" de EE.UU.: grupos de
// docentes de la misma especialidad que se apoyan entre si. Aqui es solo un
// directorio (sin chat en tiempo real, que no aplica al alcance del proyecto).
function docentesDeArea(especialidad, excludeId){
  return DOCENTES.filter(d => d.especialidad === especialidad && d.id !== excludeId);
}

/* =========================================================
   SESIONES DE MENTORIA: semilla + nuevas agendadas (MEJORA)
   Las nuevas sesiones que agenda el docente (con su meta) se
   guardan en localStorage, igual que el resto de datos "propios"
   del docente en este prototipo sin backend.
   ========================================================= */
function leerSesionesLocal(idDocente){
  try{ return JSON.parse(localStorage.getItem(`certus_sesiones_${idDocente}`)) || []; }catch(e){ return []; }
}
function guardarSesionLocal(idDocente, sesion){
  const local = leerSesionesLocal(idDocente);
  local.push(sesion);
  localStorage.setItem(`certus_sesiones_${idDocente}`, JSON.stringify(local));
}
function sesionesDeDocente(idDocente){
  const seed = SESIONES_MENTORIA.filter(s => s.id_docente === idDocente);
  return [...seed, ...leerSesionesLocal(idDocente)];
}
function marcarSesionResenada(idDocente, idSesion){
  const seedEntry = SESIONES_MENTORIA.find(s => s.id === idSesion && s.id_docente === idDocente);
  if(seedEntry){ seedEntry.reseniaEnviada = true; return; }
  const local = leerSesionesLocal(idDocente);
  const entry = local.find(s => s.id === idSesion);
  if(entry) entry.reseniaEnviada = true;
  localStorage.setItem(`certus_sesiones_${idDocente}`, JSON.stringify(local));
}
// Para el administrador (coordinador de mentoria): antes ninguna pantalla
// podia pasar una sesion agendada a "completada" — quedaba agendada para
// siempre, y como la resenia del docente exige estado==="completada"
// (ver puedeResenar en docente/mentores.html), esa funcionalidad quedaba
// inalcanzable para cualquier sesion que no viniera ya asi en la semilla.
function actualizarEstadoSesionMentoria(idDocente, idSesion, nuevoEstado){
  const seedEntry = SESIONES_MENTORIA.find(s => s.id === idSesion && s.id_docente === idDocente);
  if(seedEntry){ seedEntry.estado = nuevoEstado; return; }
  const local = leerSesionesLocal(idDocente);
  const entry = local.find(s => s.id === idSesion);
  if(entry) entry.estado = nuevoEstado;
  localStorage.setItem(`certus_sesiones_${idDocente}`, JSON.stringify(local));
}
// Para el administrador: junta las sesiones agendadas de todos los docentes
// (semilla + localStorage), como el "Program Coordinator Dashboard" de
// Mentorloop (Participantes / Emparejamientos / Hitos) o la vista agregada
// de TeachBoost Coach — un coordinador de mentoria ve la actividad real, no
// solo un total estatico por mentor.
function todasLasSesionesMentoria(){
  const resultado = [...SESIONES_MENTORIA];
  DOCENTES.forEach(d => resultado.push(...leerSesionesLocal(d.id)));
  return resultado.sort((a,b) => new Date(b.fecha_creacion) - new Date(a.fecha_creacion));
}

/* =========================================================
   RESENIAS DE MENTORES: semilla + nuevas guardadas (MEJORA)
   El docente puede dejar una resenia despues de una sesion
   completada. Se guarda por mentor, no reemplaza el rating
   general del mentor (que representa muchas mas sesiones de
   las que se muestran como texto).
   ========================================================= */
function leerReviewsLocal(idMentor){
  try{ return JSON.parse(localStorage.getItem(`certus_reviews_${idMentor}`)) || []; }catch(e){ return []; }
}
function guardarReviewLocal(idMentor, autor, comentario, rating){
  const local = leerReviewsLocal(idMentor);
  local.push({autor, comentario, rating});
  localStorage.setItem(`certus_reviews_${idMentor}`, JSON.stringify(local));
}
function reviewsDeMentor(idMentor){
  const mentor = MENTORES.find(m => m.id === idMentor);
  const seed = mentor ? mentor.reviews : [];
  return [...seed, ...leerReviewsLocal(idMentor)];
}

/* =========================================================
   AUTOEVALUACIONES: semilla + nuevas guardadas (EXTENSION)
   ========================================================= */
function leerAutoevalLocal(idDocente){
  try{ return JSON.parse(localStorage.getItem(`certus_autoeval_${idDocente}`)) || []; }catch(e){ return []; }
}
function guardarAutoevalLocal(idDocente, entry){
  const local = leerAutoevalLocal(idDocente);
  local.push(entry);
  localStorage.setItem(`certus_autoeval_${idDocente}`, JSON.stringify(local));
}
function autoevaluacionesDeDocente(idDocente){
  const seed = AUTOEVALUACIONES.filter(a => a.id_docente === idDocente);
  return [...seed, ...leerAutoevalLocal(idDocente)];
}

/* =========================================================
   PLAN DE DESARROLLO PROFESIONAL: semilla + edicion local (MEJORA)
   ========================================================= */
function leerPlanLocal(idDocente){
  try{ return JSON.parse(localStorage.getItem(`certus_plan_${idDocente}`)); }catch(e){ return null; }
}
function guardarPlanLocal(idDocente, plan){
  localStorage.setItem(`certus_plan_${idDocente}`, JSON.stringify(plan));
}
function planDeDocente(idDocente){
  const local = leerPlanLocal(idDocente);
  if(local) return local;
  const seed = PLANES_DESARROLLO.find(p => p.id_docente === idDocente);
  return seed ? {periodo: seed.periodo, metas: seed.metas} : {periodo:"2026-II", metas:[]};
}

/* =========================================================
   EVIDENCIA DE INSIGNIAS: semilla + envios + revision admin (MEJORA)
   ========================================================= */
function leerEvidenciasLocal(idDocente){
  try{ return JSON.parse(localStorage.getItem(`certus_evidencias_${idDocente}`)) || []; }catch(e){ return []; }
}
function guardarEvidenciaLocal(idDocente, idInsignia, texto){
  const local = leerEvidenciasLocal(idDocente);
  local.push({id: Date.now(), id_docente: idDocente, id_insignia: idInsignia, texto, estado:"pendiente", fecha: new Date().toISOString().slice(0,10)});
  localStorage.setItem(`certus_evidencias_${idDocente}`, JSON.stringify(local));
}
function evidenciasDeDocente(idDocente){
  const seed = EVIDENCIAS_INSIGNIA.filter(e => e.id_docente === idDocente);
  return [...seed, ...leerEvidenciasLocal(idDocente)];
}
// Solo revisa las guardadas en localStorage (las de la semilla ya estan aprobadas
// de fabrica, para no depender de que el administrador revise algo en cada demo).
function todasLasEvidenciasPendientes(){
  const resultado = [];
  DOCENTES.forEach(d => {
    leerEvidenciasLocal(d.id).forEach(e => {
      if(e.estado === "pendiente") resultado.push(e);
    });
  });
  return resultado;
}
function actualizarEstadoEvidenciaLocal(idDocente, idEvidencia, nuevoEstado){
  const local = leerEvidenciasLocal(idDocente);
  const entry = local.find(e => e.id === idEvidencia);
  if(entry) entry.estado = nuevoEstado;
  localStorage.setItem(`certus_evidencias_${idDocente}`, JSON.stringify(local));
}

/* =========================================================
   DESACTIVAR / REACTIVAR DOCENTE (MEJORA)
   Patron inspirado en los registros estatales de PD de EE. UU.:
   el historial de un docente (horas, insignias, constancias) le
   pertenece a el mismo, no a la institucion, asi que desactivarlo
   NUNCA borra ni oculta ese historial — solo restringe su estado
   de personal activo. Es reversible y queda un motivo registrado.
   No toca DOCENTES ni INSCRIPCIONES en ningun momento.
   ========================================================= */
function leerInactivos(){
  try{ return JSON.parse(localStorage.getItem("certus_docentes_inactivos")) || []; }catch(e){ return []; }
}
function estadoDocente(idDocente){
  return leerInactivos().find(x => x.id_docente === idDocente) || null;
}
function desactivarDocenteLocal(idDocente, motivo){
  const inactivos = leerInactivos().filter(x => x.id_docente !== idDocente);
  inactivos.push({id_docente: idDocente, motivo, fecha: new Date().toISOString().slice(0,10)});
  localStorage.setItem("certus_docentes_inactivos", JSON.stringify(inactivos));
}
function reactivarDocenteLocal(idDocente){
  const inactivos = leerInactivos().filter(x => x.id_docente !== idDocente);
  localStorage.setItem("certus_docentes_inactivos", JSON.stringify(inactivos));
}

/* =========================================================
   EDITAR / CREAR DOCENTE (MEJORA — guardado real + auditoria)
   El formulario de Gestion de docentes antes no persistia nada.
   Aqui se guardan ediciones y altas en localStorage y se aplican
   sobre el arreglo DOCENTES en memoria al cargar cada pagina,
   con un pequeno registro de "quien y cuando" edito, como en un
   sistema de gestion escolar real.
   ========================================================= */
function leerDocentesOverrides(){
  try{ return JSON.parse(localStorage.getItem("certus_docentes_overrides")) || {}; }catch(e){ return {}; }
}
function guardarDocenteOverride(idDocente, datos){
  const overrides = leerDocentesOverrides();
  overrides[idDocente] = {...datos, editado_por: (getSesionActiva()||{}).nombre || "Admin", fecha_edicion: new Date().toISOString().slice(0,10)};
  localStorage.setItem("certus_docentes_overrides", JSON.stringify(overrides));
}
function leerDocentesNuevosLocal(){
  try{ return JSON.parse(localStorage.getItem("certus_docentes_nuevos")) || []; }catch(e){ return []; }
}
function crearDocenteLocal(datos){
  const nuevos = leerDocentesNuevosLocal();
  const idsExistentes = [...DOCENTES.map(d => d.id), ...nuevos.map(d => d.id)];
  const nuevoId = (idsExistentes.length ? Math.max(...idsExistentes) : 0) + 1;
  const docente = {id: nuevoId, ...datos, editado_por: (getSesionActiva()||{}).nombre || "Admin", fecha_edicion: new Date().toISOString().slice(0,10)};
  nuevos.push(docente);
  localStorage.setItem("certus_docentes_nuevos", JSON.stringify(nuevos));
  return docente;
}
function hidratarDocentesLocal(){
  const overrides = leerDocentesOverrides();
  Object.keys(overrides).forEach(id => {
    const d = DOCENTES.find(x => x.id === parseInt(id));
    if(d) Object.assign(d, overrides[id]);
  });
  leerDocentesNuevosLocal().forEach(nuevo => {
    if(!DOCENTES.some(d => d.id === nuevo.id)) DOCENTES.push(nuevo);
  });
}
hidratarDocentesLocal();

/* =========================================================
   GESTION DE ADMINISTRADORES (RF-03/RF-04/RF-05 — mismo patron
   que Gestion de docentes, aplicado al rol administrador). Antes
   solo existia un admin fijo sin ninguna pantalla de gestion; el
   documento de requisitos pide explicitamente poder registrar,
   editar y desactivar "usuarios docentes Y administradores".
   ========================================================= */
function leerAdminsInactivos(){
  try{ return JSON.parse(localStorage.getItem("certus_admins_inactivos")) || []; }catch(e){ return []; }
}
function estadoAdmin(idAdmin){
  return leerAdminsInactivos().find(x => x.id_admin === idAdmin) || null;
}
function desactivarAdminLocal(idAdmin, motivo){
  const inactivos = leerAdminsInactivos().filter(x => x.id_admin !== idAdmin);
  inactivos.push({id_admin: idAdmin, motivo, fecha: new Date().toISOString().slice(0,10)});
  localStorage.setItem("certus_admins_inactivos", JSON.stringify(inactivos));
}
function reactivarAdminLocal(idAdmin){
  const inactivos = leerAdminsInactivos().filter(x => x.id_admin !== idAdmin);
  localStorage.setItem("certus_admins_inactivos", JSON.stringify(inactivos));
}
function leerAdminsOverrides(){
  try{ return JSON.parse(localStorage.getItem("certus_admins_overrides")) || {}; }catch(e){ return {}; }
}
function guardarAdminOverride(idAdmin, datos){
  const overrides = leerAdminsOverrides();
  overrides[idAdmin] = {...datos, editado_por: (getSesionActiva()||{}).nombre || "Admin", fecha_edicion: new Date().toISOString().slice(0,10)};
  localStorage.setItem("certus_admins_overrides", JSON.stringify(overrides));
}
function leerAdminsNuevosLocal(){
  try{ return JSON.parse(localStorage.getItem("certus_admins_nuevos")) || []; }catch(e){ return []; }
}
function crearAdminLocal(datos){
  const nuevos = leerAdminsNuevosLocal();
  const idsExistentes = [...ADMINISTRADORES.map(a => a.id), ...nuevos.map(a => a.id)];
  const nuevoId = (idsExistentes.length ? Math.max(...idsExistentes) : 0) + 1;
  const admin = {id: nuevoId, ...datos, editado_por: (getSesionActiva()||{}).nombre || "Admin", fecha_edicion: new Date().toISOString().slice(0,10)};
  nuevos.push(admin);
  localStorage.setItem("certus_admins_nuevos", JSON.stringify(nuevos));
  return admin;
}
function hidratarAdministradoresLocal(){
  const overrides = leerAdminsOverrides();
  Object.keys(overrides).forEach(id => {
    const a = ADMINISTRADORES.find(x => x.id === parseInt(id));
    if(a) Object.assign(a, overrides[id]);
  });
  leerAdminsNuevosLocal().forEach(nuevo => {
    if(!ADMINISTRADORES.some(a => a.id === nuevo.id)) ADMINISTRADORES.push(nuevo);
  });
}
hidratarAdministradoresLocal();

/* =========================================================
   CICLO DE ACTUALIZACION PROFESIONAL (MEJORA)
   Inspirado en el "Professional Update" del GTC Scotland (MyGTCS):
   cada periodo del plan de desarrollo se puede cerrar formalmente
   con una confirmacion del administrador (el rol de "supervisor"
   en CERTUS). El docente solicita la confirmacion; el administrador
   la aprueba desde el perfil del docente en Gestion de docentes.
   No reemplaza el Plan de desarrollo — lo cierra.
   ========================================================= */
function leerCiclosLocal(){
  try{ return JSON.parse(localStorage.getItem("certus_ciclos_confirmacion")) || []; }catch(e){ return []; }
}
function estadoCiclo(idDocente, periodo){
  return leerCiclosLocal().find(c => c.id_docente === idDocente && c.periodo === periodo) || null;
}
function solicitarConfirmacionCiclo(idDocente, periodo){
  const ciclos = leerCiclosLocal();
  let entry = ciclos.find(c => c.id_docente === idDocente && c.periodo === periodo);
  if(!entry){ entry = {id_docente: idDocente, periodo}; ciclos.push(entry); }
  entry.estado = "solicitado";
  entry.fecha_solicitud = new Date().toISOString().slice(0,10);
  localStorage.setItem("certus_ciclos_confirmacion", JSON.stringify(ciclos));
}
function confirmarCicloLocal(idDocente, periodo, confirmadoPor){
  const ciclos = leerCiclosLocal();
  let entry = ciclos.find(c => c.id_docente === idDocente && c.periodo === periodo);
  if(!entry){ entry = {id_docente: idDocente, periodo}; ciclos.push(entry); }
  entry.estado = "confirmado";
  entry.confirmado_por = confirmadoPor;
  entry.fecha_confirmacion = new Date().toISOString().slice(0,10);
  localStorage.setItem("certus_ciclos_confirmacion", JSON.stringify(ciclos));
}

/* =========================================================
   SOLICITUD DE ACCESO A CAPACITACIONES DISPONIBLES (MEJORA)
   Inspirado en el flujo real "Request Approval" de Frontline
   Professional Growth / MyLearningPlan, usado por distritos
   escolares de EEUU: el docente pide acceso a un curso del
   catalogo que aun no tiene asignado; un administrador aprueba
   o rechaza. RF-08 no se rompe: el docente nunca se auto-inscribe,
   la inscripcion real solo se crea cuando el admin aprueba.
   ========================================================= */
function leerSolicitudesCapacitacion(){
  try{ return JSON.parse(localStorage.getItem("certus_solicitudes_capacitacion")) || []; }catch(e){ return []; }
}
function solicitudDe(idDocente, idCapacitacion){
  return leerSolicitudesCapacitacion().find(s => s.id_docente === idDocente && s.id_capacitacion === idCapacitacion) || null;
}
function solicitarCapacitacionLocal(idDocente, idCapacitacion){
  const solicitudes = leerSolicitudesCapacitacion().filter(s => !(s.id_docente === idDocente && s.id_capacitacion === idCapacitacion));
  solicitudes.push({id: Date.now(), id_docente: idDocente, id_capacitacion: idCapacitacion, estado: "pendiente", fecha_solicitud: new Date().toISOString().slice(0,10)});
  localStorage.setItem("certus_solicitudes_capacitacion", JSON.stringify(solicitudes));
}
function todasLasSolicitudesPendientes(){
  return leerSolicitudesCapacitacion()
    .filter(s => s.estado === "pendiente")
    .sort((a,b) => new Date(b.fecha_solicitud) - new Date(a.fecha_solicitud));
}
function leerInscripcionesNuevasLocal(){
  try{ return JSON.parse(localStorage.getItem("certus_inscripciones_nuevas")) || []; }catch(e){ return []; }
}
function aprobarSolicitudCapacitacion(idSolicitud, aprobadoPor){
  const solicitudes = leerSolicitudesCapacitacion();
  const s = solicitudes.find(x => x.id === idSolicitud);
  if(!s) return;
  s.estado = "aprobada";
  s.aprobado_por = aprobadoPor;
  s.fecha_resolucion = new Date().toISOString().slice(0,10);
  localStorage.setItem("certus_solicitudes_capacitacion", JSON.stringify(solicitudes));

  // RF-08: la inscripcion real la crea el administrador al aprobar.
  const cap = CAPACITACIONES.find(c => c.id === s.id_capacitacion);
  const nuevas = leerInscripcionesNuevasLocal();
  const idsExistentes = [...INSCRIPCIONES.map(i => i.id), ...nuevas.map(i => i.id)];
  const nuevoId = (idsExistentes.length ? Math.max(...idsExistentes) : 0) + 1;
  const inscripcion = {id: nuevoId, id_docente: s.id_docente, id_capacitacion: s.id_capacitacion, estado: "pendiente", fecha_inscripcion: new Date().toISOString().slice(0,10), fecha_limite: cap ? cap.fecha : ""};
  nuevas.push(inscripcion);
  localStorage.setItem("certus_inscripciones_nuevas", JSON.stringify(nuevas));
  if(!INSCRIPCIONES.some(i => i.id === inscripcion.id)) INSCRIPCIONES.push(inscripcion);
}
function rechazarSolicitudCapacitacion(idSolicitud){
  const solicitudes = leerSolicitudesCapacitacion();
  const s = solicitudes.find(x => x.id === idSolicitud);
  if(!s) return;
  s.estado = "rechazada";
  s.fecha_resolucion = new Date().toISOString().slice(0,10);
  localStorage.setItem("certus_solicitudes_capacitacion", JSON.stringify(solicitudes));
}
function hidratarInscripcionesLocal(){
  leerInscripcionesNuevasLocal().forEach(n => {
    if(!INSCRIPCIONES.some(i => i.id === n.id)) INSCRIPCIONES.push(n);
  });
}
hidratarInscripcionesLocal();

/* =========================================================
   SUGERENCIA DE COACHING (patron "AI-native" honesto)
   Banco de tips por programa — logica simple, NO es un modelo de
   IA real. Se usa despues de que el docente escribe una reflexion,
   inspirado en el "AI Coach" de TeachBoost pero declarado explicitamente
   como una sugerencia automatica basica.
   ========================================================= */
const TIPS_COACHING = {
  "Diseno y Desarrollo de Software": [
    "Prueba grabar un fragmento corto de tu proxima clase y revisalo: suele revelar patrones que no notas en vivo.",
    "Comparte el recurso que usaste con otro docente de tu area; el feedback cruzado acelera el aprendizaje."
  ],
  "Administracion": [
    "Aplica lo aprendido en una sola sesion antes de escalarlo a todo el curso.",
    "Pide a un colega que observe 10 minutos de tu clase y te de un dato concreto."
  ],
  "Marketing": [
    "Convierte esta reflexion en una meta dentro de tu Plan de Desarrollo.",
    "Comparte un ejemplo real con tus estudiantes; el contexto ayuda a fijar el aprendizaje."
  ],
  "Idiomas": [
    "Practica el vocabulario nuevo en una conversacion corta antes de tu proxima clase.",
    "Registra las frases que mas usan tus estudiantes: te sirve como banco de vocabulario vivo."
  ],
  "Psicologia": [
    "Registra un caso real (sin datos sensibles) donde aplicaste esto; te ayuda a validar la tecnica."
  ],
  default: [
    "Comparte esta reflexion con un mentor de tu especialidad para recibir otra perspectiva.",
    "Vuelve a leer esta nota en un mes: te ayuda a ver si el cambio se sostuvo en tu practica."
  ]
};
function sugerenciaCoaching(programa){
  const banco = TIPS_COACHING[programa] || TIPS_COACHING.default;
  return banco[Math.floor(Math.random() * banco.length)];
}

/* =========================================================
   SUGERENCIAS DE TEMA DE CAPACITACION (MEJORA — needs assessment)
   ========================================================= */
function leerSugerenciasLocal(idDocente){
  try{ return JSON.parse(localStorage.getItem(`certus_sugerencias_${idDocente}`)) || []; }catch(e){ return []; }
}
function guardarSugerenciaLocal(idDocente, titulo, detalle){
  const local = leerSugerenciasLocal(idDocente);
  local.push({id: Date.now(), id_docente: idDocente, titulo, detalle, estado:"pendiente", fecha: new Date().toISOString().slice(0,10)});
  localStorage.setItem(`certus_sugerencias_${idDocente}`, JSON.stringify(local));
}
function sugerenciasDeDocente(idDocente){
  const seed = SUGERENCIAS_CAPACITACION.filter(s => s.id_docente === idDocente);
  return [...seed, ...leerSugerenciasLocal(idDocente)];
}
// Para el administrador: junta las sugerencias de todos los docentes (semilla + localStorage).
function todasLasSugerencias(){
  const resultado = [...SUGERENCIAS_CAPACITACION];
  DOCENTES.forEach(d => resultado.push(...leerSugerenciasLocal(d.id)));
  return resultado.sort((a,b) => new Date(b.fecha) - new Date(a.fecha));
}
function marcarSugerenciaAtendida(idDocente, idSugerencia){
  // Si es una sugerencia semilla (vive en SUGERENCIAS_CAPACITACION, no en localStorage),
  // se actualiza en memoria para esta sesion; si es local, se guarda en localStorage.
  const seedEntry = SUGERENCIAS_CAPACITACION.find(s => s.id === idSugerencia && s.id_docente === idDocente);
  if(seedEntry){ seedEntry.estado = "atendida"; return; }
  const local = leerSugerenciasLocal(idDocente);
  const entry = local.find(s => s.id === idSugerencia);
  if(entry) entry.estado = "atendida";
  localStorage.setItem(`certus_sugerencias_${idDocente}`, JSON.stringify(local));
}

/* =========================================================
   MENTOR RECOMENDADO PARA DOCENTES NUEVOS (MEJORA)
   Inspirado en los programas de induccion de EE. UU.: a un docente con
   pocos anios de experiencia se le asigna/recomienda de entrada un mentor
   de su misma especialidad, en vez de dejarlo buscar solo en la lista.
   ========================================================= */
function mentorRecomendado(idDocente){
  const docente = DOCENTES.find(d => d.id === idDocente);
  if(!docente || docente.anios_experiencia > 3) return null;
  const nombreCompleto = `${docente.nombres} ${docente.apellidos}`;
  const mismaEspecialidad = MENTORES.find(m => m.tema === docente.especialidad && m.nombres !== nombreCompleto);
  if(mismaEspecialidad) return mismaEspecialidad;
  // Si no hay ningun mentor disponible en su misma especialidad, se recomienda
  // el mejor calificado disponible — mejor eso que dejarlo sin ninguna sugerencia.
  return [...MENTORES].filter(m => m.nombres !== nombreCompleto).sort((a,b) => b.rating - a.rating)[0] || null;
}

// Icono distintivo por codigo de insignia (con fallback generico)
const ICONOS_INSIGNIA = {
  AD:"fa-laptop-code", EE:"fa-clipboard-check", IA:"fa-robot", MC:"fa-hands-helping",
  ID:"fa-gamepad", CC:"fa-comments", FA:"fa-people-group", GT:"fa-hand-holding-heart",
  AC:"fa-drafting-compass", EI:"fa-universal-access", IP:"fa-lightbulb", PC:"fa-language"
};
function iconoInsignia(codigo){
  return ICONOS_INSIGNIA[codigo] || "fa-award";
}

// Genera el QR de una constancia dentro de un elemento canvas/div
function renderQR(elementId, texto){
  if(typeof QRCode === "undefined") return;
  new QRCode(document.getElementById(elementId), {
    text: texto,
    width: 90,
    height: 90,
    colorDark: "#00205B",
    colorLight: "#ffffff"
  });
}

// Filtro de chips genérico: data-estado en cada chip, data-filter en cada fila/tarjeta
function activarFiltrosChip(chipsSelector, itemsSelector){
  const chips = document.querySelectorAll(chipsSelector);
  chips.forEach(chip => {
    chip.addEventListener("click", () => {
      chips.forEach(c => c.classList.remove("active"));
      chip.classList.add("active");
      const filtro = chip.dataset.estado;
      document.querySelectorAll(itemsSelector).forEach(item => {
        const mostrar = filtro === "todas" || item.dataset.estado === filtro;
        item.style.display = mostrar ? "" : "none";
      });
    });
  });
}

/* =========================================================
   CENTRO DE NOTIFICACIONES (MEJORA)
   Se calcula 100% a partir de datos que ya existen (semaforo,
   constancias recientes). Se inyecta solo via JS, no requiere
   tocar el HTML de cada pagina.
   ========================================================= */
function calcularNotificaciones(rol, idUsuario){
  const notifs = [];

  if(rol === "docente"){
    const mis = INSCRIPCIONES.filter(i => i.id_docente === idUsuario);

    mis.filter(i => i.estado !== "completada").forEach(i => {
      const clase = semaforoClase(i);
      if(clase === "semaforo-rojo" || clase === "semaforo-ambar"){
        const cap = CAPACITACIONES.find(c => c.id === i.id_capacitacion);
        notifs.push({
          icono: clase === "semaforo-rojo" ? "fa-triangle-exclamation" : "fa-clock",
          tipo: clase === "semaforo-rojo" ? "danger" : "warning",
          texto: clase === "semaforo-rojo"
            ? `"${cap.titulo}" esta vencida (limite ${i.fecha_limite})`
            : `"${cap.titulo}" vence pronto (${i.fecha_limite})`,
          link: `detalle.html?id=${i.id}`
        });
      }
    });

    // Constancias mas recientes (las 2 ultimas por fecha de finalizacion)
    [...mis.filter(i => i.estado === "completada")]
      .sort((a,b) => new Date(b.fecha_actualizacion || b.fecha_limite) - new Date(a.fecha_actualizacion || a.fecha_limite))
      .slice(0, 2)
      .forEach(i => {
        const cap = CAPACITACIONES.find(c => c.id === i.id_capacitacion);
        notifs.push({
          icono: "fa-certificate",
          tipo: "success",
          texto: `Tu constancia de "${cap.titulo}" ya esta disponible`,
          link: "constancias.html"
        });
      });
  } else if(rol === "admin"){
    const vencidas = INSCRIPCIONES.filter(i => i.estado !== "completada" && semaforoClase(i) === "semaforo-rojo");
    const porVencer = INSCRIPCIONES.filter(i => i.estado !== "completada" && semaforoClase(i) === "semaforo-ambar");
    if(vencidas.length){
      notifs.push({icono:"fa-triangle-exclamation", tipo:"danger",
        texto:`${vencidas.length} inscripcion(es) vencida(s) sin completar`, link:"gestion_capacitaciones.html"});
    }
    if(porVencer.length){
      notifs.push({icono:"fa-clock", tipo:"warning",
        texto:`${porVencer.length} capacitacion(es) por vencer pronto`, link:"gestion_capacitaciones.html"});
    }
    // Mismas 3 colas de "Requiere tu atencion" del Dashboard (solicitudes,
    // sugerencias, evidencias) — antes solo se veian si el admin entraba
    // justo al Dashboard. La campanita esta en el topbar de TODAS las
    // paginas de admin, asi que debe avisar lo mismo desde cualquiera.
    const solicitudesPend = todasLasSolicitudesPendientes();
    if(solicitudesPend.length){
      notifs.push({icono:"fa-inbox", tipo:"warning",
        texto:`${solicitudesPend.length} solicitud(es) de acceso a capacitaciones esperando revision`, link:"gestion_capacitaciones.html"});
    }
    const sugerenciasPend = todasLasSugerencias().filter(s => s.estado !== "atendida");
    if(sugerenciasPend.length){
      notifs.push({icono:"fa-lightbulb", tipo:"warning",
        texto:`${sugerenciasPend.length} sugerencia(s) de tema de capacitacion sin atender`, link:"gestion_capacitaciones.html"});
    }
    const evidenciasPend = todasLasEvidenciasPendientes();
    if(evidenciasPend.length){
      notifs.push({icono:"fa-file-circle-check", tipo:"warning",
        texto:`${evidenciasPend.length} evidencia(s) de insignia por revisar`, link:"gestion_docentes.html"});
    }
  }
  return notifs;
}

function initNotificaciones(){
  const sesion = getSesionActiva();
  // La campana vive en el topnav superior (global, en todas las paginas de
  // docente/admin), no en el topbar de cada pagina — asi se evita el bug de
  // que dependiera de cuantos elementos tuviera el topbar de esa pagina en
  // particular (eso hacia que en perfil.html, que solo tiene el <h1>, la
  // campana quedara mal alineada al centro en vez de a la derecha).
  const topnavRight = document.getElementById("topnavRight");
  if(!sesion || !topnavRight) return;

  const notifs = calcularNotificaciones(sesion.rol, sesion.id);

  const wrap = document.createElement("div");
  wrap.className = "notif-wrap";
  wrap.innerHTML = `
    <button class="notif-bell" id="notifBellBtn" type="button" aria-label="Notificaciones">
      <i class="fa-solid fa-bell"></i>
      ${notifs.length ? `<span class="notif-count">${notifs.length}</span>` : ""}
    </button>
    <div class="notif-dropdown" id="notifDropdown">
      <div class="notif-header">Notificaciones</div>
      ${notifs.length ? notifs.map(n => `
        <a href="${n.link}" class="notif-item notif-${n.tipo}">
          <i class="fa-solid ${n.icono}"></i>
          <span>${n.texto}</span>
        </a>
      `).join("") : `<div class="notif-empty"><i class="fa-solid fa-mug-hot"></i><br>Estas al dia, sin notificaciones nuevas.</div>`}
    </div>
  `;
  topnavRight.appendChild(wrap);

  document.getElementById("notifBellBtn").addEventListener("click", (e) => {
    e.stopPropagation();
    document.getElementById("notifDropdown").classList.toggle("open");
  });
  document.addEventListener("click", () => {
    const dd = document.getElementById("notifDropdown");
    if(dd) dd.classList.remove("open");
  });
}

/* =========================================================
   ASISTENTE CERTUS (MEJORA — patron "AI-native")
   Chat flotante que responde preguntas sobre los datos propios
   del docente. Es logica basada en reglas sobre datos reales
   del sistema, NO un modelo de IA conectado a internet — se
   deja explicito en el mensaje de bienvenida.
   ========================================================= */
function responderAsistente(pregunta, sesion){
  const q = pregunta.toLowerCase();
  const docente = DOCENTES.find(d => d.id === sesion.id);
  const mis = INSCRIPCIONES.filter(i => i.id_docente === sesion.id);
  const pendientes = mis.filter(i => i.estado !== "completada");
  const completadas = mis.filter(i => i.estado === "completada");

  if(/hola|buenas|buenos dias|buenas tardes/.test(q)){
    return `Hola ${docente.nombres}. Puedo contarte sobre tus capacitaciones, insignias, constancias o mentores. ¿Que necesitas?`;
  }
  if(/falta|pendiente|debo|debo completar/.test(q)){
    if(pendientes.length === 0) return "No te falta nada por ahora — completaste todas tus capacitaciones asignadas.";
    const lista = pendientes.map(i => `"${CAPACITACIONES.find(c=>c.id===i.id_capacitacion).titulo}" (${badgeTexto(i.estado)})`).slice(0,4).join(", ");
    return `Te faltan ${pendientes.length} capacitacion(es): ${lista}${pendientes.length>4 ? "..." : ""}.`;
  }
  if(/proxima|próxima|vence|urgente|prioridad/.test(q)){
    const prox = [...pendientes].sort((a,b) => new Date(a.fecha_limite) - new Date(b.fecha_limite))[0];
    if(!prox) return "No tienes capacitaciones pendientes por ahora.";
    const cap = CAPACITACIONES.find(c => c.id === prox.id_capacitacion);
    return `Tu prioridad es "${cap.titulo}", vence el ${prox.fecha_limite}.`;
  }
  if(/insignia|logro|badge/.test(q)){
    const insignias = insigniasDeDocente(sesion.id);
    const ganadas = insignias.filter(i => i.ganada);
    const siguiente = insignias.find(i => !i.ganada && i.requiere_capacitacion);
    let resp = `Tienes ${ganadas.length} de ${insignias.length} insignias.`;
    if(siguiente) resp += ` Te falta "${siguiente.nombre}": ${siguiente.requisito}.`;
    return resp;
  }
  if(/constancia|certificado|diploma/.test(q)){
    const n = completadas.length;
    return n > 0
      ? `Tienes ${n} constancia(s) disponible(s) para descargar en la seccion "Constancias".`
      : "Aun no tienes constancias — se generan automaticamente al completar una capacitacion.";
  }
  if(/mentor/.test(q)){
    const propio = MENTORES.find(m => m.tema === docente.especialidad);
    return propio
      ? `Busca en "Mentores" a alguien de "${docente.especialidad}" — por ejemplo hay mentores en ese tema. Puedes ver su perfil completo antes de agendar.`
      : `En la seccion "Mentores" puedes ver el perfil completo de cada uno (experiencia, resenas, disponibilidad) antes de agendar una sesion.`;
  }
  if(/perfil|especialidad/.test(q)){
    return `Tu especialidad registrada es "${docente.especialidad}", con ${docente.anios_experiencia} anos de experiencia. Puedes editar mas detalles en "Perfil".`;
  }
  return "No tengo una respuesta puntual para eso todavia. Prueba preguntando por tus capacitaciones pendientes, tu proxima capacitacion, tus insignias, constancias o mentores.";
}

function initAsistente(){
  const sesion = getSesionActiva();
  if(!sesion || sesion.rol !== "docente" || document.getElementById("asistenteFab")) return;

  const fab = document.createElement("button");
  fab.id = "asistenteFab";
  fab.className = "asistente-fab";
  fab.type = "button";
  fab.setAttribute("aria-label", "Asistente CERTUS");
  fab.innerHTML = `<i class="fa-solid fa-sparkles"></i>`;
  document.body.appendChild(fab);

  const panel = document.createElement("div");
  panel.id = "asistentePanel";
  panel.className = "asistente-panel";
  panel.innerHTML = `
    <div class="asistente-head">
      <div><i class="fa-solid fa-sparkles"></i> Asistente CERTUS</div>
      <button type="button" id="asistenteCerrar" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="asistente-msgs" id="asistenteMsgs"></div>
    <div class="asistente-chips" id="asistenteChips">
      <span data-q="que me falta">¿Que me falta?</span>
      <span data-q="proxima capacitacion">Mi proxima capacitacion</span>
      <span data-q="mis insignias">Mis insignias</span>
    </div>
    <form class="asistente-input" id="asistenteForm">
      <input type="text" id="asistenteInput" placeholder="Escribe tu pregunta..." autocomplete="off">
      <button type="submit" aria-label="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
  `;
  document.body.appendChild(panel);

  function agregarMensaje(texto, esUsuario){
    const msgs = document.getElementById("asistenteMsgs");
    const bubble = document.createElement("div");
    bubble.className = "asistente-bubble " + (esUsuario ? "usuario" : "bot");
    bubble.textContent = texto;
    msgs.appendChild(bubble);
    msgs.scrollTop = msgs.scrollHeight;
  }

  fab.addEventListener("click", () => {
    panel.classList.toggle("open");
    if(panel.classList.contains("open") && !panel.dataset.iniciado){
      panel.dataset.iniciado = "1";
      agregarMensaje(`Hola, soy el asistente de CERTUS. Respondo segun tus propios datos en el sistema (no soy un modelo de IA conectado a internet) — pregunta lo que necesites.`, false);
    }
  });
  document.getElementById("asistenteCerrar").addEventListener("click", () => panel.classList.remove("open"));

  document.getElementById("asistenteChips").addEventListener("click", (e) => {
    const q = e.target.dataset.q;
    if(!q) return;
    agregarMensaje(e.target.textContent, true);
    agregarMensaje(responderAsistente(q, sesion), false);
  });

  document.getElementById("asistenteForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const input = document.getElementById("asistenteInput");
    const val = input.value.trim();
    if(!val) return;
    agregarMensaje(val, true);
    agregarMensaje(responderAsistente(val, sesion), false);
    input.value = "";
  });
}

/* =========================================================
   CENTRO DE SOPORTE (sugerencia de equipo)
   Boton en el topnav (docente y admin) que abre un modal para
   dejar una consulta y recibir un numero de referencia. Como es
   un prototipo sin backend, el envio es simulado y se aclara
   en el mensaje de confirmacion.
   ========================================================= */
function initSoporte(){
  const sesion = getSesionActiva();
  const topnavRight = document.getElementById("topnavRight");
  if(!sesion || !topnavRight || document.getElementById("btnSoporte")) return;

  const btn = document.createElement("button");
  btn.id = "btnSoporte";
  btn.className = "topnav-icon-btn";
  btn.type = "button";
  btn.setAttribute("aria-label", "Centro de soporte");
  btn.title = "Centro de soporte";
  btn.innerHTML = `<i class="fa-solid fa-headset"></i>`;
  topnavRight.appendChild(btn);

  const overlay = document.createElement("div");
  overlay.className = "modal-overlay";
  overlay.id = "modalSoporte";
  overlay.innerHTML = `
    <div class="modal-box" style="width:380px;text-align:left;">
      <h3 style="color:var(--navy);margin-bottom:.35rem;"><i class="fa-solid fa-headset"></i> Centro de soporte</h3>
      <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:1rem;">¿Tienes un problema o duda? Cuentanos que pasa y te damos un numero de referencia para el seguimiento.</p>
      <div class="form-group">
        <label>Tu consulta</label>
        <textarea id="soporteMsg" rows="3" style="width:100%;padding:9px 11px;border:1px solid var(--secondary);border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;background:var(--surface);color:var(--text);"></textarea>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:10px 12px;font-size:12px;color:var(--text-muted);margin-bottom:1rem;line-height:1.8;">
        <i class="fa-solid fa-envelope"></i> soporte@certus.edu.pe<br>
        <i class="fa-solid fa-phone"></i> +51 999 888 777
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button class="btn btn-outline btn-sm" id="soporteCerrar" type="button"><i class="fa-solid fa-xmark"></i> Cancelar</button>
        <button class="btn btn-primary btn-sm" id="soporteEnviar" type="button"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
      </div>
    </div>
  `;
  document.body.appendChild(overlay);

  btn.addEventListener("click", () => overlay.classList.add("open"));
  overlay.querySelector("#soporteCerrar").addEventListener("click", () => overlay.classList.remove("open"));
  overlay.addEventListener("click", (e) => { if(e.target === overlay) overlay.classList.remove("open"); });
  overlay.querySelector("#soporteEnviar").addEventListener("click", () => {
    const campo = document.getElementById("soporteMsg");
    const msg = campo.value.trim();
    if(!msg){ showToast("Escribe tu consulta antes de enviar", "error"); return; }
    const ticket = "TCK-" + Math.floor(1000 + Math.random()*9000);
    overlay.classList.remove("open");
    campo.value = "";
    showToast(`Consulta registrada. Tu codigo de referencia es ${ticket}`, "success");
  });
}


/* =========================================================
   MENU DE USUARIO (topnav)
   Avatar con iniciales que abre un menu con nombre, rol y
   "Cerrar sesion". Reemplaza el link/pill de nombre que antes
   vivia suelto en el topbar de cada pagina.
   ========================================================= */
function initUserMenu(){
  const sesion = getSesionActiva();
  const topnavRight = document.getElementById("topnavRight");
  if(!sesion || !topnavRight || document.getElementById("avatarBtn")) return;

  const iniciales = sesion.nombre.trim().split(/\s+/).map(p => p[0]).filter(Boolean).slice(0, 2).join("").toUpperCase();
  const wrap = document.createElement("div");
  wrap.className = "topnav-avatar-wrap";
  wrap.innerHTML = `
    <button class="topnav-avatar" id="avatarBtn" type="button" aria-label="Cuenta">${iniciales || "?"}</button>
    <div class="topnav-avatar-dropdown" id="avatarDropdown">
      <div class="dd-head"><b>${sesion.nombre}</b><span>${sesion.rol === "admin" ? "Administrador" : "Docente"}</span></div>
      <a href="#" onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesion</a>
    </div>
  `;
  topnavRight.appendChild(wrap);

  document.getElementById("avatarBtn").addEventListener("click", (e) => {
    e.stopPropagation();
    document.getElementById("avatarDropdown").classList.toggle("open");
  });
  document.addEventListener("click", () => {
    const dd = document.getElementById("avatarDropdown");
    if(dd) dd.classList.remove("open");
  });
}

document.addEventListener("DOMContentLoaded", () => {
  // Guarda defensiva: todas las paginas actuales cargan data.js junto con
  // app.js, pero si en el futuro se agrega una pagina que solo cargue
  // app.js, esto evita un ReferenceError al llamar getSesionActiva()
  // (definida en data.js) desde initNotificaciones().
  if(typeof getSesionActiva !== "function") return;
  // Orden intencional: cada init agrega su boton al final de #topnavRight,
  // asi que el orden de estas llamadas define el orden visual de izquierda
  // a derecha: campana, soporte, avatar.
  initNotificaciones();
  initSoporte();
  initUserMenu();
  initAsistente();
});
