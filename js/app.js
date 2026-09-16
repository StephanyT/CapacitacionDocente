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
  if(estado === "vencida") return "badge-vencido";
  return "";
}

function badgeTexto(estado){
  return estado.charAt(0).toUpperCase() + estado.slice(1);
}

// Badge que prioriza el vencimiento sobre el progreso cuando ambos aplican:
// mostrar "En curso" en azul y aparte "Vencido" en rojo (dos colores para
// dos preguntas distintas) se lee como una contradiccion, aunque no lo sea
// (se puede estar en curso Y vencido a la vez, como un libro de biblioteca
// no devuelto y atrasado). Para evitar esa lectura, cuando esta vencido el
// badge mismo dice "Vencido" y el progreso real pasa a una nota chica y
// gris debajo -- una sola cosa gritando en rojo, no dos sistemas de color
// sueltos compitiendo. Requiere que semaforoClase()/badgeClase()/badgeTexto()
// ya esten cargados (misma dependencia que el resto de estas funciones).
// "vencida" ahora es un estado real que guarda el backend (antes solo se
// inferia comparando fechas), asi que ya no hace falta mostrar un "progreso
// real" aparte cuando el estado mismo es vencida -- no habia otro progreso
// que mostrar, "vencida" ya lo es todo. El calculo por fecha se deja como
// respaldo por si algo llega con el estado aun sin refrescar.
function badgeConVencimiento(inscripcion){
  if(inscripcion.estado === "vencida"){
    return `<span class="badge badge-vencido"><i class="fa-solid fa-triangle-exclamation"></i> Vencido</span>`;
  }
  const vencido = inscripcion.estado !== "completada" && semaforoClase(inscripcion) === "semaforo-rojo";
  if(!vencido){
    return `<span class="badge ${badgeClase(inscripcion.estado)}">${badgeTexto(inscripcion.estado)}</span>`;
  }
  return `<span class="badge badge-vencido"><i class="fa-solid fa-triangle-exclamation"></i> Vencido</span>
          <span style="font-size:10px;color:var(--text-muted);display:block;margin-top:2px;">Progreso real: ${badgeTexto(inscripcion.estado)}</span>`;
}

// Semaforo de cumplimiento (MEJORA): compara fecha_limite con hoy
function semaforoClase(inscripcion){
  if(inscripcion.estado === "completada") return "semaforo-verde";
  // El backend ya escribe "vencida" cuando corresponde (ver
  // actualizar_inscripciones_vencidas() en api/inscripciones/_helpers.php),
  // asi que no hace falta recalcular por fecha si el estado ya lo dice.
  if(inscripcion.estado === "vencida") return "semaforo-rojo";
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

function normalizarCapacitacion(cap){
  ["objetivos", "temario", "habilidades"].forEach(campo => {
    if(Array.isArray(cap[campo])) return;
    if(typeof cap[campo] === "string" && cap[campo].trim()){
      try{
        const parsed = JSON.parse(cap[campo]);
        cap[campo] = Array.isArray(parsed) ? parsed : [];
      }catch(e){
        cap[campo] = cap[campo].split(/\r?\n/).map(x => x.trim()).filter(Boolean);
      }
    } else {
      cap[campo] = [];
    }
  });

  cap.id = parseInt(cap.id, 10);
  cap.activo = cap.activo === true || cap.activo === 1 || cap.activo === "1";
  return cap;
}

function sincronizarCapacitacionesBackend(lista){
  CAPACITACIONES.splice(0, CAPACITACIONES.length, ...lista.map(c => normalizarCapacitacion({...c})));
}

async function apiCapacitaciones(ruta, opciones){
  const url = rutaBackendCertus(`api/capacitaciones/${ruta}`);
  const config = {
    credentials: "same-origin",
    headers: { "Accept": "application/json" },
    ...(opciones || {})
  };

  if(config.body && !(config.body instanceof FormData)){
    config.headers = {
      ...config.headers,
      "Content-Type": "application/json"
    };
    if(typeof config.body !== "string"){
      config.body = JSON.stringify(config.body);
    }
  }

  const respuesta = await fetch(url, config);
  let datos = null;

  try{
    datos = await respuesta.json();
  }catch(e){
    datos = { ok: false, mensaje: "Respuesta invalida del servidor." };
  }

  if(respuesta.status === 401 || respuesta.status === 403){
    try{ sessionStorage.removeItem("sesion_certus"); }catch(e){}
    if(respuesta.status === 401){
      window.location.replace(rutaLoginCertus());
    }
  }

  if(!respuesta.ok || !datos.ok){
    throw new Error(datos.mensaje || "No se pudo completar la operacion.");
  }

  return datos;
}

async function listarCapacitacionesBackend(){
  const datos = await apiCapacitaciones("listar.php");
  const capacitaciones = (datos.capacitaciones || []).map(cap => normalizarCapacitacion(cap));
  sincronizarCapacitacionesBackend(capacitaciones);
  return capacitaciones;
}

async function obtenerCapacitacionBackend(id){
  const datos = await apiCapacitaciones(`detalle.php?id=${encodeURIComponent(id)}`);
  return normalizarCapacitacion(datos.capacitacion);
}

async function crearCapacitacionBackend(datos){
  const respuesta = await apiCapacitaciones("crear.php", {
    method: "POST",
    body: datos
  });
  return normalizarCapacitacion(respuesta.capacitacion);
}

async function editarCapacitacionBackend(id, datos){
  const respuesta = await apiCapacitaciones("editar.php", {
    method: "POST",
    body: { id, ...datos }
  });
  return normalizarCapacitacion(respuesta.capacitacion);
}

async function cambiarEstadoCapacitacionBackend(id, activo){
  const respuesta = await apiCapacitaciones("cambiar_estado.php", {
    method: "POST",
    body: { id, activo }
  });
  return normalizarCapacitacion(respuesta.capacitacion);
}

/* =========================================================
   USUARIOS (DOCENTES / ADMINISTRADORES) — backend real sobre la
   tabla unica "usuarios" (rol = docente | admin). Mismo patron de
   fetch que apiCapacitaciones, apuntando a api/docentes/... o
   api/administradores/... segun corresponda.
   ========================================================= */
async function apiUsuarios(carpeta, ruta, opciones){
  const url = rutaBackendCertus(`api/${carpeta}/${ruta}`);
  const config = {
    credentials: "same-origin",
    headers: { "Accept": "application/json" },
    ...(opciones || {})
  };

  if(config.body && !(config.body instanceof FormData)){
    config.headers = {
      ...config.headers,
      "Content-Type": "application/json"
    };
    if(typeof config.body !== "string"){
      config.body = JSON.stringify(config.body);
    }
  }

  const respuesta = await fetch(url, config);
  let datos = null;

  try{
    datos = await respuesta.json();
  }catch(e){
    datos = { ok: false, mensaje: "Respuesta invalida del servidor." };
  }

  if(respuesta.status === 401 || respuesta.status === 403){
    try{ sessionStorage.removeItem("sesion_certus"); }catch(e){}
    if(respuesta.status === 401){
      window.location.replace(rutaLoginCertus());
    }
  }

  if(!respuesta.ok || !datos.ok){
    const error = new Error(datos.mensaje || "No se pudo completar la operacion.");
    error.status = respuesta.status;
    throw error;
  }

  return datos;
}

function normalizarUsuario(u){
  u.id = parseInt(u.id, 10);
  u.anios_experiencia = parseInt(u.anios_experiencia, 10) || 0;
  return u;
}

async function listarDocentesBackend(){
  const datos = await apiUsuarios("docentes", "listar.php");
  const docentes = (datos.docentes || []).map(normalizarUsuario);
  DOCENTES.splice(0, DOCENTES.length, ...docentes);
  return docentes;
}
// Directorio liviano de docentes (nombre/especialidad/experiencia/bio, sin
// correo/DNI/telefono) que SI puede llamar un docente, no solo el admin —
// usado por "Docentes de tu area" (perfil.html) y la recomendacion de
// mentor (mentores.html). Puebla el mismo arreglo global DOCENTES; en
// paginas de docente nunca se llama junto con listarDocentesBackend()
// (admin-only), asi que no hay conflicto entre las dos formas de poblarlo.
async function listarDirectorioDocentesBackend(){
  const datos = await apiRuta("docentes/directorio.php");
  const docentes = (datos.docentes || []).map(d => ({...d, id: parseInt(d.id, 10)}));
  DOCENTES.splice(0, DOCENTES.length, ...docentes);
  return docentes;
}

async function crearDocenteBackend(datos){
  const respuesta = await apiUsuarios("docentes", "crear.php", { method: "POST", body: datos });
  return normalizarUsuario(respuesta.docente);
}
async function editarDocenteBackend(id, datos){
  const respuesta = await apiUsuarios("docentes", "editar.php", { method: "POST", body: { id, ...datos } });
  return normalizarUsuario(respuesta.docente);
}
async function cambiarEstadoDocenteBackend(id, activo, motivoBaja){
  const respuesta = await apiUsuarios("docentes", "cambiar_estado.php", {
    method: "POST",
    body: { id, activo, motivo_baja: motivoBaja || "" }
  });
  return normalizarUsuario(respuesta.docente);
}

async function listarAdministradoresBackend(){
  const datos = await apiUsuarios("administradores", "listar.php");
  const administradores = (datos.administradores || []).map(normalizarUsuario);
  ADMINISTRADORES.splice(0, ADMINISTRADORES.length, ...administradores);
  return administradores;
}
async function crearAdministradorBackend(datos){
  const respuesta = await apiUsuarios("administradores", "crear.php", { method: "POST", body: datos });
  return { administrador: normalizarUsuario(respuesta.administrador), passwordTemporal: respuesta.password_temporal || null };
}
async function editarAdministradorBackend(id, datos){
  const respuesta = await apiUsuarios("administradores", "editar.php", { method: "POST", body: { id, ...datos } });
  return normalizarUsuario(respuesta.administrador);
}
async function cambiarEstadoAdministradorBackend(id, activo, motivoBaja){
  const respuesta = await apiUsuarios("administradores", "cambiar_estado.php", {
    method: "POST",
    body: { id, activo, motivo_baja: motivoBaja || "" }
  });
  return normalizarUsuario(respuesta.administrador);
}

/* =========================================================
   INSCRIPCIONES + CONSTANCIAS — backend real. Mismo patron de
   fetch generico que apiCapacitaciones/apiUsuarios.
   ========================================================= */
async function apiRuta(ruta, opciones){
  const url = rutaBackendCertus(`api/${ruta}`);
  const config = {
    credentials: "same-origin",
    headers: { "Accept": "application/json" },
    ...(opciones || {})
  };

  if(config.body && !(config.body instanceof FormData)){
    config.headers = {
      ...config.headers,
      "Content-Type": "application/json"
    };
    if(typeof config.body !== "string"){
      config.body = JSON.stringify(config.body);
    }
  }

  const respuesta = await fetch(url, config);
  let datos = null;

  try{
    datos = await respuesta.json();
  }catch(e){
    datos = { ok: false, mensaje: "Respuesta invalida del servidor." };
  }

  if(respuesta.status === 401 || respuesta.status === 403){
    try{ sessionStorage.removeItem("sesion_certus"); }catch(e){}
    if(respuesta.status === 401){
      window.location.replace(rutaLoginCertus());
    }
  }

  if(!respuesta.ok || !datos.ok){
    const error = new Error(datos.mensaje || "No se pudo completar la operacion.");
    error.status = respuesta.status;
    throw error;
  }

  return datos;
}

function normalizarInscripcionAdmin(i){
  return {
    id: parseInt(i.id, 10),
    id_docente: parseInt(i.docente.id, 10),
    id_capacitacion: parseInt(i.capacitacion.id, 10),
    estado: i.estado,
    fecha_inscripcion: i.fecha_inscripcion,
    fecha_limite: i.fecha_limite,
    fecha_actualizacion: i.fecha_actualizacion,
    actualizado_por: i.actualizado_por || null
  };
}

async function listarInscripcionesBackend(){
  const datos = await apiRuta("inscripciones/listar.php");
  const inscripciones = (datos.inscripciones || []).map(normalizarInscripcionAdmin);
  INSCRIPCIONES.splice(0, INSCRIPCIONES.length, ...inscripciones);
  return inscripciones;
}

// Inscripciones del docente que tiene la sesion activa — a diferencia de
// listarInscripcionesBackend() (admin-only), esta si la puede llamar un
// docente sobre si mismo. Ademas completa CAPACITACIONES con los datos
// basicos (titulo, fecha, sede, etc.) que ya vienen en la misma respuesta,
// para paginas de docente que solo necesitan eso (ej. perfil.html).
async function misInscripcionesBackend(){
  const datos = await apiRuta("inscripciones/mis_inscripciones.php");
  const crudas = datos.inscripciones || [];
  const idDocente = (getSesionActiva() || {}).id;

  const inscripciones = crudas.map(i => ({
    id: parseInt(i.id, 10),
    id_docente: idDocente,
    id_capacitacion: parseInt(i.capacitacion.id, 10),
    estado: i.estado,
    fecha_inscripcion: i.fecha_inscripcion,
    fecha_limite: i.fecha_limite,
    fecha_actualizacion: i.fecha_actualizacion
  }));
  INSCRIPCIONES.splice(0, INSCRIPCIONES.length, ...inscripciones);

  crudas.forEach(i => {
    const cap = i.capacitacion;
    const idCap = parseInt(cap.id, 10);
    if(!CAPACITACIONES.some(c => c.id === idCap)){
      CAPACITACIONES.push({
        id: idCap,
        titulo: cap.titulo,
        fecha: cap.fecha,
        duracion: cap.duracion,
        sede: cap.sede,
        programa: cap.programa
      });
    }
  });

  return inscripciones;
}

async function asignarDocentesBackend(idCapacitacion, idsDocentes){
  return apiRuta("inscripciones/asignar.php", {
    method: "POST",
    body: { id_capacitacion: idCapacitacion, ids_docentes: idsDocentes }
  });
}

async function cambiarEstadoInscripcionBackend(idInscripcion, estado){
  return apiRuta("inscripciones/cambiar_estado.php", {
    method: "POST",
    body: { id: idInscripcion, estado }
  });
}

// Reprograma el plazo de UN solo docente (a diferencia de editar una
// capacitacion completa, que desplaza la fecha de todo el grupo aun
// pendiente -- ver RN-05 en api/capacitaciones/editar.php).
async function cambiarFechaLimiteBackend(idInscripcion, fechaLimite){
  return apiRuta("inscripciones/cambiar_fecha_limite.php", {
    method: "POST",
    body: { id: idInscripcion, fecha_limite: fechaLimite }
  });
}

/* =========================================================
   SOLICITUDES DE AMPLIACION DE PLAZO (logica de gestion de
   vencidas). Cuando una inscripcion queda "vencida", el docente
   puede pedir mas plazo con un motivo; el admin aprueba (define
   nueva fecha_limite, la inscripcion vuelve a "en curso") o
   rechaza (queda igual, vencida). Mismo patron de fetch/tabla que
   solicitudes de acceso a capacitaciones (api/solicitudes/...).
   ========================================================= */
function normalizarSolicitudAmpliacion(s){
  return {
    id: parseInt(s.id, 10),
    estado: s.estado,
    motivo: s.motivo,
    motivo_rechazo: s.motivo_rechazo || null,
    fecha_solicitud: s.fecha_solicitud,
    fecha_resolucion: s.fecha_resolucion,
    nueva_fecha_limite: s.nueva_fecha_limite,
    inscripcion: {
      id: parseInt(s.inscripcion.id, 10),
      estado: s.inscripcion.estado,
      fecha_limite: s.inscripcion.fecha_limite
    },
    docente: s.docente ? {
      id: parseInt(s.docente.id, 10),
      nombres: s.docente.nombres || "",
      apellidos: s.docente.apellidos || "",
      correo: s.docente.correo || ""
    } : null,
    capacitacion: {
      id: parseInt(s.capacitacion.id, 10),
      titulo: s.capacitacion.titulo || "Capacitacion eliminada"
    }
  };
}

async function crearSolicitudAmpliacionBackend(idInscripcion, motivo){
  return apiRuta("solicitudes_ampliacion/crear.php", {
    method: "POST",
    body: { id_inscripcion: idInscripcion, motivo }
  });
}

// Docente-scoped: sus propias solicitudes (para saber si una capacitacion
// vencida ya tiene una solicitud pendiente/aprobada/rechazada).
async function misSolicitudesAmpliacionBackend(){
  const datos = await apiRuta("solicitudes_ampliacion/mis_solicitudes.php");
  const solicitudes = (datos.solicitudes || []).map(normalizarSolicitudAmpliacion);
  SOLICITUDES_AMPLIACION.splice(0, SOLICITUDES_AMPLIACION.length, ...solicitudes);
  return solicitudes;
}

// Admin-scoped: todas las solicitudes de todos los docentes.
async function listarSolicitudesAmpliacionBackend(){
  const datos = await apiRuta("solicitudes_ampliacion/listar.php");
  const solicitudes = (datos.solicitudes || []).map(normalizarSolicitudAmpliacion);
  SOLICITUDES_AMPLIACION.splice(0, SOLICITUDES_AMPLIACION.length, ...solicitudes);
  return solicitudes;
}

async function aprobarSolicitudAmpliacionBackend(idSolicitud, nuevaFechaLimite){
  return apiRuta("solicitudes_ampliacion/aprobar.php", {
    method: "POST",
    body: { id_solicitud: idSolicitud, nueva_fecha_limite: nuevaFechaLimite }
  });
}

async function rechazarSolicitudAmpliacionBackend(idSolicitud, motivoRechazo){
  return apiRuta("solicitudes_ampliacion/rechazar.php", {
    method: "POST",
    body: { id_solicitud: idSolicitud, motivo_rechazo: motivoRechazo || "" }
  });
}

// SOLICITUDES_AMPLIACION ya debe estar poblado (via listarSolicitudesAmpliacionBackend,
// admin) antes de llamar a esto -- mismo patron que todasLasSolicitudesPendientes().
function todasLasSolicitudesAmpliacionPendientes(){
  return SOLICITUDES_AMPLIACION.filter(s => s.estado === "pendiente");
}

function normalizarConstancia(c){
  c.id = parseInt(c.id, 10);
  c.id_inscripcion = parseInt(c.id_inscripcion, 10);
  return c;
}

async function listarConstanciasBackend(){
  const datos = await apiRuta("constancias/listar.php");
  const constancias = (datos.constancias || []).map(normalizarConstancia);
  CONSTANCIAS.splice(0, CONSTANCIAS.length, ...constancias);
  return constancias;
}

async function misConstanciasBackend(){
  const datos = await apiRuta("constancias/mis_constancias.php");
  const constancias = (datos.constancias || []).map(normalizarConstancia);
  CONSTANCIAS.splice(0, CONSTANCIAS.length, ...constancias);
  return constancias;
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

// Aplica a un boton el mismo patron que ya usaba "Descargar PDF": lo
// deshabilita y le muestra un spinner mientras la accion async esta en
// vuelo, y lo devuelve a su estado original al terminar (exito o error).
// Sin esto, un click en "Guardar"/"Enviar"/"Aprobar" no daba ninguna señal
// visual hasta que llegaba el toast, lo que invitaba a doble-click.
async function conEstadoCarga(boton, textoCargando, accion){
  if(!boton){ return accion(); }
  const original = boton.innerHTML;
  const anchoOriginal = boton.offsetWidth;
  boton.style.minWidth = anchoOriginal + "px";
  boton.disabled = true;
  boton.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${textoCargando}`;
  try{
    return await accion();
  } finally {
    boton.disabled = false;
    boton.innerHTML = original;
    boton.style.minWidth = "";
  }
}

function logout(){
  try{ sessionStorage.removeItem("sesion_certus"); }catch(e){}
  const enSubcarpeta = location.pathname.includes("/docente/") || location.pathname.includes("/admin/");
  window.location.href = enSubcarpeta ? "../logout.php" : "logout.php";
}

// Insignias ganadas por un docente, segun capacitaciones completadas (MEJORA)
// La insignia "Mentor Certus" (requiere_capacitacion null) se gana dando
// al menos 10 sesiones como mentor (cruce con MENTORES por id_docente).
// Bug real corregido: antes se cruzaba por nombre completo usando DOCENTES,
// pero DOCENTES solo se carga en paginas de admin (listar.php es admin-only);
// en las paginas del propio docente (mi_progreso.html, perfil.html) DOCENTES
// siempre estaba vacio, asi que un mentor nunca veia su propia insignia
// "Mentor Certus" como alcanzable. mentores/listar.php ya trae id_docente,
// asi que se puede cruzar directo sin depender de DOCENTES.
// Las insignias con evidencia:true (patron BloomBoard/Digital Promise) exigen
// ademas que el administrador apruebe una evidencia enviada por el docente.
function insigniasDeDocente(idDocente){
  const completadasIds = INSCRIPCIONES
    .filter(i => i.id_docente === idDocente && i.estado === "completada")
    .map(i => i.id_capacitacion);

  const mentorPropio = MENTORES.find(m => m.id_docente === idDocente);
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
// renovacion (ej. Carolina del Norte: 80 horas cada 5 años, ~16/año;
// Missouri: 15 horas/año); CERTUS usa 20 horas por periodo academico como
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
   MENTORES + SESIONES DE MENTORIA + RESENIAS — backend real
   (tablas mentores / sesiones_mentoria / resenas_mentoria).
   Mismo patron de fetch generico (apiRuta) que capacitaciones,
   docentes/administradores e inscripciones.
   ========================================================= */
function normalizarMentorBackend(m){
  m.id = parseInt(m.id, 10);
  m.id_docente = m.id_docente !== null && m.id_docente !== undefined ? parseInt(m.id_docente, 10) : null;
  m.anios_experiencia = parseInt(m.anios_experiencia, 10) || 0;
  m.sesiones = parseInt(m.sesiones, 10) || 0;
  m.rating = m.rating !== null && m.rating !== undefined ? parseFloat(m.rating) : null;
  m.disponibilidad = Array.isArray(m.disponibilidad) ? m.disponibilidad : [];
  return m;
}

async function listarMentoresBackend(){
  const datos = await apiRuta("mentores/listar.php");
  const mentores = (datos.mentores || []).map(normalizarMentorBackend);
  MENTORES.splice(0, MENTORES.length, ...mentores);
  return mentores;
}

async function obtenerMentorBackend(id){
  const datos = await apiRuta(`mentores/detalle.php?id=${encodeURIComponent(id)}`);
  return normalizarMentorBackend(datos.mentor);
}

// Sesiones de mentoria del docente que tiene la sesion activa (donde el es
// quien asiste — el mentor es otra persona).
async function misSesionesMentoriaBackend(){
  const datos = await apiRuta("sesiones_mentoria/mis_sesiones.php");
  return datos.sesiones || [];
}

// Sesiones donde el docente que tiene la sesion activa es el MENTOR (quien
// las agendaron con el). Se guarda en un arreglo global (a diferencia de
// misSesionesMentoriaBackend, que solo devuelve el dato) porque
// calcularNotificaciones() la necesita leer de forma sincronica para armar
// el aviso en la campanita.
async function misSesionesComoMentorBackend(){
  const datos = await apiRuta("sesiones_mentoria/mis_sesiones_como_mentor.php");
  const sesiones = datos.sesiones || [];
  SESIONES_COMO_MENTOR.splice(0, SESIONES_COMO_MENTOR.length, ...sesiones);
  return sesiones;
}

async function agendarSesionMentoriaBackend(idMentor, slot, meta){
  return apiRuta("sesiones_mentoria/crear.php", {
    method: "POST",
    body: { id_mentor: idMentor, slot, meta }
  });
}

// Todas las sesiones agendadas, para el coordinador de mentoria (admin) —
// mismo patron que el "Program Coordinator Dashboard" de Mentorloop.
async function listarSesionesMentoriaAdminBackend(){
  const datos = await apiRuta("sesiones_mentoria/listar.php");
  return datos.sesiones || [];
}

async function cambiarEstadoSesionMentoriaBackend(idSesion, estado){
  return apiRuta("sesiones_mentoria/cambiar_estado.php", {
    method: "POST",
    body: { id: idSesion, estado }
  });
}

async function dejarResenaMentoriaBackend(idSesion, comentario, rating){
  return apiRuta("resenas_mentoria/crear.php", {
    method: "POST",
    body: { id_sesion: idSesion, comentario, rating }
  });
}

/* =========================================================
   AUTOEVALUACIONES — backend real (tablas autoevaluaciones /
   comentarios_autoevaluacion). Antes vivian en localStorage
   (certus_autoeval_<id>); las tablas ya existian sin usar.
   ========================================================= */
async function misAutoevaluacionesBackend(){
  const datos = await apiRuta('autoevaluaciones/mis_autoevaluaciones.php');
  const autoevaluaciones = datos.autoevaluaciones || [];
  AUTOEVALUACIONES.splice(0, AUTOEVALUACIONES.length, ...autoevaluaciones);
  return autoevaluaciones;
}

async function crearAutoevaluacionBackend(idCapacitacion, videoLink, checklist, notaPropia){
  return apiRuta('autoevaluaciones/crear.php', {
    method: 'POST',
    body: { id_capacitacion: idCapacitacion, video_link: videoLink, checklist, nota_propia: notaPropia }
  });
}

// "Coaching colaborativo": autoevaluaciones de OTROS docentes (para leer y
// comentar) + el endpoint para dejar ese comentario. Antes el texto de la
// pagina prometia esto pero no existia ningun endpoint para verlo ni para
// escribirlo.
async function listarAutoevaluacionesColegasBackend(){
  const datos = await apiRuta('autoevaluaciones/listar_colegas.php');
  return datos.autoevaluaciones || [];
}

async function comentarAutoevaluacionBackend(idAutoevaluacion, texto){
  return apiRuta('autoevaluaciones/comentar.php', {
    method: 'POST',
    body: { id_autoevaluacion: idAutoevaluacion, texto }
  });
}

// AUTOEVALUACIONES ya debe estar poblado (via misAutoevaluacionesBackend)
// antes de llamar a esto.
function autoevaluacionesDeDocente(idDocente){
  return AUTOEVALUACIONES.filter(a => a.id_docente === idDocente);
}

/* =========================================================
   PLAN DE DESARROLLO PROFESIONAL — backend real (tablas
   planes_desarrollo / metas_desarrollo). Antes vivia en
   localStorage (certus_plan_<id>); las tablas ya existian sin usar.
   ========================================================= */
const PERIODO_ACTIVO_PLAN = "2026-II";

function upsertPlanEnGlobal(plan){
  const idx = PLANES_DESARROLLO.findIndex(p => p.id_docente === plan.id_docente && p.periodo === plan.periodo);
  if(idx >= 0){ PLANES_DESARROLLO[idx] = plan; } else { PLANES_DESARROLLO.push(plan); }
}

// Docente-scoped: trae (o arma vacio) el plan propio del periodo activo.
async function miPlanBackend(){
  const datos = await apiRuta("planes_desarrollo/mi_plan.php");
  upsertPlanEnGlobal(datos.plan);
  return datos.plan;
}

// Admin-scoped: todos los planes de todos los docentes (para el perfil en
// Gestion de docentes).
async function listarPlanesBackend(){
  const datos = await apiRuta("planes_desarrollo/listar.php");
  const planes = datos.planes || [];
  PLANES_DESARROLLO.splice(0, PLANES_DESARROLLO.length, ...planes);
  return planes;
}

async function agregarMetaBackend(texto){
  const datos = await apiRuta("planes_desarrollo/agregar_meta.php", {
    method: "POST",
    body: { texto }
  });
  upsertPlanEnGlobal(datos.plan);
  return datos.plan;
}

async function toggleMetaBackend(idMeta){
  const datos = await apiRuta("planes_desarrollo/toggle_meta.php", {
    method: "POST",
    body: { id_meta: idMeta }
  });
  upsertPlanEnGlobal(datos.plan);
  return datos.plan;
}

// PLANES_DESARROLLO ya debe estar poblado (via miPlanBackend en paginas de
// docente, o listarPlanesBackend en paginas de admin) antes de llamar a esto.
function planDeDocente(idDocente){
  const plan = PLANES_DESARROLLO.find(p => p.id_docente === idDocente && p.periodo === PERIODO_ACTIVO_PLAN);
  return plan || {periodo: PERIODO_ACTIVO_PLAN, metas: []};
}

/* =========================================================
   INSIGNIAS + EVIDENCIAS — backend real (tablas insignias /
   evidencias_insignia). Mismo patron de fetch generico (apiRuta)
   que el resto de modulos.
   ========================================================= */
function normalizarInsigniaBackend(ins){
  ins.id = parseInt(ins.id, 10);
  ins.requiere_capacitacion = ins.requiere_capacitacion !== null && ins.requiere_capacitacion !== undefined
    ? parseInt(ins.requiere_capacitacion, 10) : null;
  return ins;
}

// Catalogo fijo de insignias (no cambia por docente) — lo puede pedir
// cualquier rol con sesion.
async function listarInsigniasBackend(){
  const datos = await apiRuta("insignias/listar.php");
  const insignias = (datos.insignias || []).map(normalizarInsigniaBackend);
  INSIGNIAS.splice(0, INSIGNIAS.length, ...insignias);
  return insignias;
}

function normalizarEvidenciaBackend(e){
  e.id = parseInt(e.id, 10);
  e.id_docente = parseInt(e.id_docente, 10);
  e.id_insignia = parseInt(e.id_insignia, 10);
  return e;
}

// Evidencias del docente que tiene la sesion activa (para ver su propio
// progreso de insignias).
async function misEvidenciasBackend(){
  const datos = await apiRuta("evidencias_insignia/mis_evidencias.php");
  const evidencias = (datos.evidencias || []).map(normalizarEvidenciaBackend);
  EVIDENCIAS_INSIGNIA.splice(0, EVIDENCIAS_INSIGNIA.length, ...evidencias);
  return evidencias;
}

// Todas las evidencias de todos los docentes (admin) — para revisarlas
// desde el perfil de cada docente y para la bandeja del Dashboard.
async function listarEvidenciasBackend(){
  const datos = await apiRuta("evidencias_insignia/listar.php");
  const evidencias = (datos.evidencias || []).map(normalizarEvidenciaBackend);
  EVIDENCIAS_INSIGNIA.splice(0, EVIDENCIAS_INSIGNIA.length, ...evidencias);
  return evidencias;
}

async function crearEvidenciaBackend(idInsignia, texto){
  return apiRuta("evidencias_insignia/crear.php", {
    method: "POST",
    body: { id_insignia: idInsignia, texto }
  });
}

async function cambiarEstadoEvidenciaBackend(idEvidencia, estado){
  return apiRuta("evidencias_insignia/cambiar_estado.php", {
    method: "POST",
    body: { id: idEvidencia, estado }
  });
}

// EVIDENCIAS_INSIGNIA ya debe estar poblado (via misEvidenciasBackend en
// paginas de docente, o listarEvidenciasBackend en paginas de admin) antes
// de llamar a esto — igual que INSCRIPCIONES/CAPACITACIONES en el resto
// del sistema.
function evidenciasDeDocente(idDocente){
  return EVIDENCIAS_INSIGNIA.filter(e => e.id_docente === idDocente);
}
function todasLasEvidenciasPendientes(){
  return EVIDENCIAS_INSIGNIA.filter(e => e.estado === "pendiente");
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
   con un pequeño registro de "quien y cuando" edito, como en un
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
function upsertCicloEnGlobal(ciclo){
  if(!ciclo) return;
  const idx = CICLOS_CONFIRMACION.findIndex(c => c.id_docente === ciclo.id_docente && c.periodo === ciclo.periodo);
  if(idx >= 0){ CICLOS_CONFIRMACION[idx] = ciclo; } else { CICLOS_CONFIRMACION.push(ciclo); }
}

// CICLOS_CONFIRMACION ya debe estar poblado (via miCicloBackend en paginas
// de docente, o listarCiclosBackend en paginas de admin) antes de llamar a esto.
function estadoCiclo(idDocente, periodo){
  return CICLOS_CONFIRMACION.find(c => c.id_docente === idDocente && c.periodo === periodo) || null;
}

async function miCicloBackend(){
  const datos = await apiRuta("ciclos_confirmacion/mi_ciclo.php");
  if(datos.ciclo) upsertCicloEnGlobal(datos.ciclo);
  return datos.ciclo;
}

async function listarCiclosBackend(){
  const datos = await apiRuta("ciclos_confirmacion/listar.php");
  const ciclos = datos.ciclos || [];
  CICLOS_CONFIRMACION.splice(0, CICLOS_CONFIRMACION.length, ...ciclos);
  return ciclos;
}

async function solicitarConfirmacionCicloBackend(){
  const datos = await apiRuta("ciclos_confirmacion/solicitar.php", { method: "POST", body: {} });
  upsertCicloEnGlobal(datos.ciclo);
  return datos.ciclo;
}

async function confirmarCicloBackend(idDocente, periodo){
  const datos = await apiRuta("ciclos_confirmacion/confirmar.php", {
    method: "POST",
    body: { id_docente: idDocente, periodo }
  });
  upsertCicloEnGlobal(datos.ciclo);
  return datos.ciclo;
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
// Bug real: esta funcion leia solo de localStorage, pero el flujo de
// solicitudes de acceso (crear.php, listar.php, aprobar.php, rechazar.php)
// ya se habia migrado por completo al backend real (tabla
// solicitudes_capacitacion) tanto del lado del docente (mis_capacitaciones.html
// llama a crearSolicitudBackend) como del admin (gestion_capacitaciones.html
// ya tiene su propio listarSolicitudesAdminBackend/aprobar/rechazar). Como
// nada seguia escribiendo en localStorage, esta funcion siempre devolvia
// vacio — asi que el Dashboard ("Requiere tu atencion") y la campanita de
// notificaciones nunca mostraban las solicitudes pendientes reales, aunque
// si eran visibles y funcionales dentro de Gestion de capacitaciones.
function todasLasSolicitudesPendientes(){
  return SOLICITUDES_CAPACITACION
    .filter(s => s.estado === "pendiente")
    .sort((a,b) => new Date(b.fecha_solicitud) - new Date(a.fecha_solicitud));
}

function normalizarSolicitudBackendGlobal(solicitud){
  const docente = solicitud.docente || {};
  const capacitacion = solicitud.capacitacion || {};
  return {
    id: parseInt(solicitud.id, 10),
    id_docente: parseInt(docente.id, 10),
    id_capacitacion: parseInt(capacitacion.id, 10),
    estado: solicitud.estado,
    fecha_solicitud: solicitud.fecha_solicitud,
    fecha_resolucion: solicitud.fecha_resolucion,
    docente: {
      id: parseInt(docente.id, 10),
      nombres: docente.nombres || "",
      apellidos: docente.apellidos || "",
      correo: docente.correo || ""
    },
    capacitacion: {
      id: parseInt(capacitacion.id, 10),
      titulo: capacitacion.titulo || "Capacitacion eliminada"
    }
  };
}

// Loader admin-only (api/solicitudes/listar.php), para usar desde cualquier
// pagina que necesite saber cuantas solicitudes reales estan pendientes
// (Dashboard, campanita de notificaciones) sin duplicar el fetch a mano
// como hace gestion_capacitaciones.html.
async function listarSolicitudesBackend(){
  const datos = await apiRuta("solicitudes/listar.php");
  const solicitudes = (datos.solicitudes || []).map(normalizarSolicitudBackendGlobal);
  SOLICITUDES_CAPACITACION.splice(0, SOLICITUDES_CAPACITACION.length, ...solicitudes);
  return solicitudes;
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
  "Diseño y Desarrollo de Software": [
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
   SUGERENCIAS DE TEMA DE CAPACITACION — backend real (tabla
   sugerencias_capacitacion). Antes vivian en localStorage
   (certus_sugerencias_<id>); la tabla ya existia sin usar.
   ========================================================= */
async function misSugerenciasBackend(){
  const datos = await apiRuta("sugerencias/mis_sugerencias.php");
  const propias = datos.sugerencias || [];
  // Se reemplazan solo las de este docente dentro del arreglo global (que
  // en paginas de admin puede traer las de todos) para no perder datos de
  // otros docentes si ambos loaders se usaran en la misma pagina.
  const idDocente = propias.length ? propias[0].id_docente : (getSesionActiva() || {}).id;
  const resto = SUGERENCIAS_CAPACITACION.filter(s => s.id_docente !== idDocente);
  SUGERENCIAS_CAPACITACION.splice(0, SUGERENCIAS_CAPACITACION.length, ...resto, ...propias);
  return propias;
}

async function crearSugerenciaBackend(titulo, detalle){
  return apiRuta("sugerencias/crear.php", {
    method: "POST",
    body: { titulo, detalle }
  });
}

// Admin-scoped: todas las sugerencias de todos los docentes.
async function listarSugerenciasBackend(){
  const datos = await apiRuta("sugerencias/listar.php");
  const sugerencias = datos.sugerencias || [];
  SUGERENCIAS_CAPACITACION.splice(0, SUGERENCIAS_CAPACITACION.length, ...sugerencias);
  return sugerencias;
}

async function marcarSugerenciaAtendidaBackend(idSugerencia){
  return apiRuta("sugerencias/marcar_atendida.php", {
    method: "POST",
    body: { id: idSugerencia }
  });
}

// SUGERENCIAS_CAPACITACION ya debe estar poblado (via misSugerenciasBackend
// o listarSugerenciasBackend) antes de llamar a esto.
function sugerenciasDeDocente(idDocente){
  return SUGERENCIAS_CAPACITACION.filter(s => s.id_docente === idDocente);
}
function todasLasSugerencias(){
  return [...SUGERENCIAS_CAPACITACION].sort((a,b) => new Date(b.fecha) - new Date(a.fecha));
}

/* =========================================================
   MENTOR RECOMENDADO PARA DOCENTES NUEVOS (MEJORA)
   Inspirado en los programas de induccion de EE. UU.: a un docente con
   pocos años de experiencia se le asigna/recomienda de entrada un mentor
   de su misma especialidad, en vez de dejarlo buscar solo en la lista.
   ========================================================= */
function mentorRecomendado(idDocente, yaTuvoSesion){
  const docente = DOCENTES.find(d => d.id === idDocente);
  // Ademas de "pocos años de experiencia", el docente no debe tener ya una
  // sesion de mentoria propia — si ya tuvo una, el empujon de induccion
  // ("te lo sugerimos porque recien te estas incorporando") ya no aplica,
  // se contradice con su propio historial de "Mis sesiones de mentoria".
  if(!docente || docente.anios_experiencia > 3 || yaTuvoSesion) return null;
  const nombreCompleto = `${docente.nombres} ${docente.apellidos}`;
  const mismaEspecialidad = MENTORES.find(m => m.tema === docente.especialidad && m.nombres !== nombreCompleto);
  if(mismaEspecialidad) return mismaEspecialidad;
  // Si no hay ningun mentor disponible en su misma especialidad, se recomienda
  // el mejor calificado disponible — mejor eso que dejarlo sin ninguna sugerencia.
  return [...MENTORES].filter(m => m.nombres !== nombreCompleto).sort((a,b) => (b.rating || 0) - (a.rating || 0))[0] || null;
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

// "Vistas" solo aplica a notificaciones de tipo anuncio (constancia
// disponible), no a las de tarea pendiente (vencida/por vencer/etc, que se
// recalculan siempre desde el dato real). Guardado en localStorage porque
// es un detalle de "que ya vio este docente en este navegador", no algo que
// el resto del sistema (ni el admin) necesite saber — no amerita columna en
// la base de datos.
function leerConstanciasVistas(idDocente){
  try{
    return JSON.parse(localStorage.getItem(`certus_constancias_vistas_${idDocente}`)) || [];
  }catch(e){
    return [];
  }
}
function marcarConstanciaVista(idDocente, idInscripcion){
  const vistas = leerConstanciasVistas(idDocente);
  if(!vistas.includes(idInscripcion)){
    vistas.push(idInscripcion);
    try{ localStorage.setItem(`certus_constancias_vistas_${idDocente}`, JSON.stringify(vistas)); }catch(e){}
  }
}

// Mismo patron para "tu solicitud de ampliacion de plazo ya fue resuelta"
// (aprobada o rechazada) -- es un anuncio de algo que ya paso, no una tarea
// pendiente (mientras esta "pendiente" no hace falta vista, simplemente no
// se le avisa nada al docente todavia porque no hay nada nuevo que ver).
function leerSolicitudesAmpliacionVistas(idDocente){
  try{
    return JSON.parse(localStorage.getItem(`certus_ampliacion_vistas_${idDocente}`)) || [];
  }catch(e){
    return [];
  }
}
function marcarSolicitudAmpliacionVista(idDocente, idSolicitud){
  const vistas = leerSolicitudesAmpliacionVistas(idDocente);
  if(!vistas.includes(idSolicitud)){
    vistas.push(idSolicitud);
    try{ localStorage.setItem(`certus_ampliacion_vistas_${idDocente}`, JSON.stringify(vistas)); }catch(e){}
  }
}

// Misma idea pero para "ya envie mi solicitud, esperando respuesta" -- clave
// de localStorage DISTINTA a la de arriba a proposito: si usara la misma,
// marcar como vista la de "esperando respuesta" tambien ocultaria para
// siempre el aviso de "fue aprobada/rechazada" de esa MISMA solicitud mas
// adelante (mismo id, misma lista). Bug real que se evito separando las dos.
function leerSolicitudesAmpliacionEnviadasVistas(idDocente){
  try{
    return JSON.parse(localStorage.getItem(`certus_ampliacion_enviada_vistas_${idDocente}`)) || [];
  }catch(e){
    return [];
  }
}
function marcarSolicitudAmpliacionEnviadaVista(idDocente, idSolicitud){
  const vistas = leerSolicitudesAmpliacionEnviadasVistas(idDocente);
  if(!vistas.includes(idSolicitud)){
    vistas.push(idSolicitud);
    try{ localStorage.setItem(`certus_ampliacion_enviada_vistas_${idDocente}`, JSON.stringify(vistas)); }catch(e){}
  }
}

// Mismo patron para "alguien agendo una sesion de mentoria contigo".
function leerSesionesMentorVistas(idDocente){
  try{
    return JSON.parse(localStorage.getItem(`certus_sesiones_mentor_vistas_${idDocente}`)) || [];
  }catch(e){
    return [];
  }
}
function marcarSesionMentorVista(idDocente, idSesion){
  const vistas = leerSesionesMentorVistas(idDocente);
  if(!vistas.includes(idSesion)){
    vistas.push(idSesion);
    try{ localStorage.setItem(`certus_sesiones_mentor_vistas_${idDocente}`, JSON.stringify(vistas)); }catch(e){}
  }
}

function calcularNotificaciones(rol, idUsuario){
  const notifs = [];

  if(rol === "docente"){
    const mis = INSCRIPCIONES.filter(i => i.id_docente === idUsuario);
    const ampliacionEnviadaVistas = leerSolicitudesAmpliacionEnviadasVistas(idUsuario);

    mis.filter(i => i.estado !== "completada").forEach(i => {
      const clase = semaforoClase(i);
      if(clase === "semaforo-rojo" || clase === "semaforo-ambar"){
        const cap = CAPACITACIONES.find(c => c.id === i.id_capacitacion);
        // Bug real: si el docente ya envio una solicitud de ampliacion para
        // esta inscripcion vencida, la campana seguia repitiendo la misma
        // alarma "esta vencida" como si no hubiera hecho nada -- la
        // inscripcion sigue en estado 'vencida' hasta que el admin resuelva,
        // asi que la condicion de arriba siempre la volvia a encontrar. Se
        // reemplaza por un aviso mas tranquilo mientras haya una solicitud
        // pendiente, en vez de la misma alarma de peligro. Y, a diferencia de
        // la alarma original (que se repite a proposito porque sigue sin
        // resolverse), este SI se puede marcar como visto -- el docente ya
        // hizo lo que tenia que hacer, no gana nada con seguir viendola.
        const solicitudPendiente = i.estado === "vencida"
          ? SOLICITUDES_AMPLIACION.find(s => s.inscripcion.id === i.id && s.estado === "pendiente")
          : null;
        if(solicitudPendiente && !ampliacionEnviadaVistas.includes(solicitudPendiente.id)){
          notifs.push({
            icono: "fa-hourglass-half",
            tipo: "info",
            texto: `Ya enviaste tu solicitud de ampliacion para "${cap.titulo}" — esperando respuesta del administrador`,
            link: `detalle.html?id=${i.id}`,
            onclick: `marcarSolicitudAmpliacionEnviadaVista(${idUsuario}, ${solicitudPendiente.id})`
          });
        } else if(!solicitudPendiente){
          notifs.push({
            icono: clase === "semaforo-rojo" ? "fa-triangle-exclamation" : "fa-clock",
            tipo: clase === "semaforo-rojo" ? "danger" : "warning",
            texto: clase === "semaforo-rojo"
              ? `"${cap.titulo}" esta vencida (limite ${i.fecha_limite})`
              : `"${cap.titulo}" vence pronto (${i.fecha_limite})`,
            link: `detalle.html?id=${i.id}`
          });
        }
      }
    });

    // Constancias mas recientes (las 2 ultimas por fecha de finalizacion).
    // A diferencia de las notificaciones de arriba (vencida/por vencer, que
    // siguen mostrandose mientras el problema de fondo siga sin resolverse),
    // esta es un anuncio de algo que ya paso, no una tarea pendiente — asi
    // que si aplica el patron normal de "la viste, se quita" (marcarVista()
    // guarda un id "vista" en localStorage cuando el docente hace clic).
    const constanciasVistas = leerConstanciasVistas(idUsuario);
    [...mis.filter(i => i.estado === "completada")]
      .sort((a,b) => new Date(b.fecha_actualizacion || b.fecha_limite) - new Date(a.fecha_actualizacion || a.fecha_limite))
      .slice(0, 2)
      .filter(i => !constanciasVistas.includes(i.id))
      .forEach(i => {
        const cap = CAPACITACIONES.find(c => c.id === i.id_capacitacion);
        notifs.push({
          icono: "fa-certificate",
          tipo: "success",
          texto: `Tu constancia de "${cap.titulo}" ya esta disponible`,
          link: "constancias.html",
          onclick: `marcarConstanciaVista(${idUsuario}, ${i.id})`
        });
      });

    // Solicitudes de ampliacion de plazo ya resueltas por el admin.
    const ampliacionVistas = leerSolicitudesAmpliacionVistas(idUsuario);
    SOLICITUDES_AMPLIACION.filter(s => s.estado !== "pendiente" && !ampliacionVistas.includes(s.id))
      .forEach(s => {
        notifs.push({
          icono: s.estado === "aprobada" ? "fa-circle-check" : "fa-circle-info",
          tipo: s.estado === "aprobada" ? "success" : "info",
          texto: s.estado === "aprobada"
            ? `Tu solicitud de ampliacion para "${s.capacitacion.titulo}" fue aprobada (nuevo plazo: ${s.nueva_fecha_limite})`
            : `Tu solicitud de ampliacion para "${s.capacitacion.titulo}" no fue aprobada`,
          link: `detalle.html?id=${s.inscripcion.id}`,
          onclick: `marcarSolicitudAmpliacionVista(${idUsuario}, ${s.id})`
        });
      });

    // Sesiones que otros docentes agendaron CONTIGO como mentor (distinto
    // de las de arriba, donde el docente es quien asiste). Mismo patron de
    // "vista" que las constancias: es un anuncio de que alguien te agendo,
    // no un problema pendiente.
    const sesionesMentorVistas = leerSesionesMentorVistas(idUsuario);
    SESIONES_COMO_MENTOR.filter(s => s.estado === "agendada" && !sesionesMentorVistas.includes(s.id))
      .forEach(s => {
        notifs.push({
          icono: "fa-hand-holding-heart",
          tipo: "info",
          texto: `${s.docente_nombres} agendo una sesion de mentoria contigo (${s.slot})`,
          link: "mentores.html",
          onclick: `marcarSesionMentorVista(${idUsuario}, ${s.id})`
        });
      });
  } else if(rol === "admin"){
    const vencidas = INSCRIPCIONES.filter(i => i.estado !== "completada" && semaforoClase(i) === "semaforo-rojo");
    const porVencer = INSCRIPCIONES.filter(i => i.estado !== "completada" && semaforoClase(i) === "semaforo-ambar");
    // Enlazan al Dashboard (tarjeta "Semaforo de cumplimiento"), que es la
    // vista pensada para esto: un docente por fila, con su capacitacion,
    // estado y fecha limite, ordenada por urgencia -- no a la tabla de
    // capacitaciones, que agrupa por capacitacion y obliga a abrir "Docentes"
    // para encontrar al docente especifico.
    if(vencidas.length){
      notifs.push({icono:"fa-triangle-exclamation", tipo:"danger",
        texto:`${vencidas.length} inscripcion(es) vencida(s) sin completar`, link:"dashboard.html"});
    }
    if(porVencer.length){
      notifs.push({icono:"fa-clock", tipo:"warning",
        texto:`${porVencer.length} capacitacion(es) por vencer pronto`, link:"dashboard.html"});
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
    const ampliacionPend = todasLasSolicitudesAmpliacionPendientes();
    if(ampliacionPend.length){
      notifs.push({icono:"fa-calendar-plus", tipo:"warning",
        texto:`${ampliacionPend.length} solicitud(es) de ampliacion de plazo esperando revision`, link:"gestion_capacitaciones.html"});
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

async function initNotificaciones(){
  const sesion = getSesionActiva();
  // La campana vive en el topnav superior (global, en todas las paginas de
  // docente/admin), no en el topbar de cada pagina — asi se evita el bug de
  // que dependiera de cuantos elementos tuviera el topbar de esa pagina en
  // particular (eso hacia que en perfil.html, que solo tiene el <h1>, la
  // campana quedara mal alineada al centro en vez de a la derecha).
  const topnavRight = document.getElementById("topnavRight");
  if(!sesion || !topnavRight) return;

  // La campana se inserta en el DOM de inmediato, con la lista vacia, y
  // solo su contenido (contador + notificaciones) se completa cuando
  // lleguen los datos del backend. Bug real que esto evita: al ser esta
  // funcion async y hacer un await antes de insertar el boton, initSoporte()
  // e initUserMenu() (llamadas justo despues en DOMContentLoaded, sin
  // esperar esta promesa) alcanzaban a insertar sus propios botones
  // primero, y la campana terminaba al final del topnav en vez de al
  // principio (orden que si esta documentado como intencional mas abajo).
  const wrap = document.createElement("div");
  wrap.className = "notif-wrap";
  wrap.innerHTML = `
    <button class="notif-bell" id="notifBellBtn" type="button" aria-label="Notificaciones">
      <i class="fa-solid fa-bell"></i>
    </button>
    <div class="notif-dropdown" id="notifDropdown">
      <div class="notif-header">Notificaciones</div>
      <div class="notif-empty"><i class="fa-solid fa-mug-hot"></i><br>Estas al dia, sin notificaciones nuevas.</div>
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

  await refrescarNotificaciones(sesion);
}

// Separada de initNotificaciones() para poder llamarla de nuevo despues de
// una accion que cambia el conteo de pendientes (ej. aprobar/rechazar una
// evidencia desde el modal rapido del Dashboard), sin insertar una segunda
// campana en el topnav.
async function refrescarNotificaciones(sesion){
  sesion = sesion || getSesionActiva();
  if(!sesion) return;

  // Bug real: esta funcion se llama en DOMContentLoaded, que dispara casi
  // de inmediato — mucho antes de que termine el Promise.all de la carga
  // propia de cada pagina (iniciarDashboard, iniciarGestionDocentes, etc.).
  // calcularNotificaciones() lee INSCRIPCIONES/EVIDENCIAS_INSIGNIA, que en
  // ese momento todavia estaban vacios ([]) porque son datos reales del
  // backend, no localStorage sincronico como cuando se escribio esta
  // funcion originalmente. Resultado: la campanita nunca mostraba nada,
  // aunque la tarjeta "Requiere tu atencion" del Dashboard (que si espera
  // su propia carga) mostrara el mismo pendiente correctamente. Se arregla
  // haciendo que la campana cargue sus propios datos, sin depender del
  // timing de la pagina en la que este.
  try{
    if(sesion.rol === "docente"){
      await Promise.all([misInscripcionesBackend(), misSesionesComoMentorBackend(), misSolicitudesAmpliacionBackend()]);
    } else if(sesion.rol === "admin"){
      await Promise.all([listarInscripcionesBackend(), listarEvidenciasBackend(), listarSolicitudesBackend(), listarSugerenciasBackend(), listarSolicitudesAmpliacionBackend()]);
    }
  }catch(error){
    if(error.status === 401) return;
    // Si falla la carga, seguimos con lo que haya en los arreglos globales
    // en vez de romper el resto del topnav (soporte, avatar, asistente).
  }

  const notifs = calcularNotificaciones(sesion.rol, sesion.id);
  const bellBtn = document.getElementById("notifBellBtn");
  const dropdown = document.getElementById("notifDropdown");
  if(!bellBtn || !dropdown) return; // la pagina pudo haber navegado mientras se cargaba

  bellBtn.innerHTML = `
    <i class="fa-solid fa-bell"></i>
    ${notifs.length ? `<span class="notif-count">${notifs.length}</span>` : ""}
  `;
  dropdown.innerHTML = `
    <div class="notif-header">Notificaciones</div>
    ${notifs.length ? notifs.map(n => `
      <a href="${n.link}" class="notif-item notif-${n.tipo}" ${n.onclick ? `onclick="${n.onclick}"` : ""}>
        <i class="fa-solid ${n.icono}"></i>
        <span>${n.texto}</span>
      </a>
    `).join("") : `<div class="notif-empty"><i class="fa-solid fa-mug-hot"></i><br>Estas al dia, sin notificaciones nuevas.</div>`}
  `;
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
    resp += ganadas.length
      ? ` Ya ganaste: ${ganadas.map(i => `"${i.nombre}"`).join(", ")}.`
      : " Aun no ganas ninguna.";
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
      : `En la seccion "Mentores" puedes ver el perfil completo de cada uno (experiencia, reseñas, disponibilidad) antes de agendar una sesion.`;
  }
  if(/perfil|especialidad/.test(q)){
    return `Tu especialidad registrada es "${docente.especialidad}", con ${docente.anios_experiencia} años de experiencia. Puedes editar mas detalles en "Perfil".`;
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
  fab.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles"></i>`;
  document.body.appendChild(fab);

  const panel = document.createElement("div");
  panel.id = "asistentePanel";
  panel.className = "asistente-panel";
  panel.innerHTML = `
    <div class="asistente-head">
      <div><i class="fa-solid fa-wand-magic-sparkles"></i> Asistente CERTUS</div>
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

  // El asistente vive en TODAS las paginas de docente (via este mismo
  // initAsistente global), pero insignias/mentores/evidencias solo se
  // cargan en las paginas que realmente los muestran (perfil.html,
  // mi_progreso.html). En cualquier otra pagina (mentores.html,
  // mis_capacitaciones.html, etc.) esos arreglos globales seguian vacios
  // ([] iniciales de data.js), asi que "Mis insignias" siempre respondia
  // "0 de 0" sin importar cuantas tuviera el docente en realidad. Se
  // cargan aqui bajo demanda (una sola vez) si todavia estan vacios.
  let datosListos = false;
  async function asegurarDatosAsistente(){
    if(datosListos) return;
    const pendientes = [];
    if(INSIGNIAS.length === 0) pendientes.push(listarInsigniasBackend());
    if(MENTORES.length === 0) pendientes.push(listarMentoresBackend());
    if(EVIDENCIAS_INSIGNIA.length === 0) pendientes.push(listarEvidenciasBackend());
    if(pendientes.length){
      try{ await Promise.all(pendientes); }catch(error){ /* si falla, responderAsistente sigue con lo que haya */ }
    }
    datosListos = true;
  }

  fab.addEventListener("click", () => {
    panel.classList.toggle("open");
    if(panel.classList.contains("open") && !panel.dataset.iniciado){
      panel.dataset.iniciado = "1";
      agregarMensaje(`Hola, soy el asistente de CERTUS. Respondo segun tus propios datos en el sistema (no soy un modelo de IA conectado a internet) — pregunta lo que necesites.`, false);
      asegurarDatosAsistente();
    }
  });
  document.getElementById("asistenteCerrar").addEventListener("click", () => panel.classList.remove("open"));

  document.getElementById("asistenteChips").addEventListener("click", async (e) => {
    const q = e.target.dataset.q;
    if(!q) return;
    agregarMensaje(e.target.textContent, true);
    await asegurarDatosAsistente();
    agregarMensaje(responderAsistente(q, sesion), false);
  });

  document.getElementById("asistenteForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const input = document.getElementById("asistenteInput");
    const val = input.value.trim();
    if(!val) return;
    agregarMensaje(val, true);
    input.value = "";
    await asegurarDatosAsistente();
    agregarMensaje(responderAsistente(val, sesion), false);
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
        <i class="fa-solid fa-phone"></i> +51 999 888 777<br>
        <a href="https://wa.me/51993418685" target="_blank" rel="noopener" style="color:var(--text-muted);"><i class="fa-brands fa-whatsapp"></i> WhatsApp: +51 993 418 685</a>
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

// Menu hamburguesa (responsive): en pantallas chicas .topnav-links (los
// links de navegacion) dejan de mostrarse como franja horizontal y pasan a
// un panel desplegable, activado por este boton. No dependia de datos del
// backend como los otros init de la campana/soporte/avatar, asi que no
// necesita ser async ni esperar sesion -- solo reorganiza el DOM que ya
// esta en la pagina.
function initTopnavMobile(){
  const topnav = document.querySelector(".topnav");
  const links = document.querySelector(".topnav-links");
  if(!topnav || !links || document.getElementById("topnavHamburger")) return;

  const btn = document.createElement("button");
  btn.id = "topnavHamburger";
  btn.className = "topnav-hamburger";
  btn.type = "button";
  btn.setAttribute("aria-label", "Abrir menu de navegacion");
  btn.innerHTML = `<i class="fa-solid fa-bars"></i>`;
  // Va PRIMERO en el topnav, antes del logo -- no junto a los iconos de la
  // derecha (eso fue un intento anterior equivocado). Referencia exacta de
  // la usuaria: "el menu icono esta primero luego el logo".
  topnav.insertBefore(btn, topnav.firstChild);

  // Icono fijo: se mantiene siempre como hamburguesa (no cambia a X al
  // abrir), por preferencia explicita de la usuaria sobre el patron
  // hamburguesa/X.
  function cerrarMenu(){
    links.classList.remove("open");
  }

  btn.addEventListener("click", (e) => {
    e.stopPropagation();
    links.classList.toggle("open");
  });
  // Al elegir una seccion se navega a otra pagina de todos modos, pero
  // cerrar el panel de una vez evita el parpadeo de verlo todavia abierto
  // durante el instante antes de que cargue la siguiente pagina.
  links.querySelectorAll("a").forEach(a => a.addEventListener("click", cerrarMenu));
  document.addEventListener("click", (e) => {
    if(links.classList.contains("open") && !links.contains(e.target) && e.target !== btn){
      cerrarMenu();
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  initTopnavMobile();

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
