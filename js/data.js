// ============================================================
// DATOS — CERTUS
// Todos los arreglos empiezan vacios: se llenan con datos reales de la
// base de datos via los loaders de app.js (listarDocentesBackend(),
// listarCapacitacionesBackend(), etc.) en cada pagina que los necesita.
// ============================================================

const DOCENTES = [];
const ADMINISTRADORES = [];
const CAPACITACIONES = [];
const INSCRIPCIONES = [];
const CONSTANCIAS = [];
const INSIGNIAS = [];
const MENTORES = [];
const SESIONES_MENTORIA = [];
const SESIONES_COMO_MENTOR = [];
const AUTOEVALUACIONES = [];
const PLANES_DESARROLLO = [];
const EVIDENCIAS_INSIGNIA = [];
const SUGERENCIAS_CAPACITACION = [];
const SOLICITUDES_CAPACITACION = [];
const CICLOS_CONFIRMACION = [];
const SOLICITUDES_AMPLIACION = [];

function getSesionActiva(){
  if(window.__sesionCertusCache) return window.__sesionCertusCache;

  try{
    const xhr = new XMLHttpRequest();
    xhr.open("GET", rutaBackendCertus("sesion.php"), false);
    xhr.setRequestHeader("Accept", "application/json");
    xhr.send(null);

    if(xhr.status >= 200 && xhr.status < 300){
      const datos = JSON.parse(xhr.responseText);
      if(datos.ok && datos.usuario){
        window.__sesionCertusCache = datos.usuario;
        return window.__sesionCertusCache;
      }
    }
  }catch(e){}

  return null;
}
function requireSesion(rolEsperado){
  const sesion = getSesionActiva();

  if(!sesion || (rolEsperado && sesion.rol !== rolEsperado)){
    try{ sessionStorage.removeItem("sesion_certus"); }catch(e){}
    window.location.replace(rutaLoginCertus());
    throw new Error("Sesion no valida.");
  }

  return sesion;
}

function rutaBackendCertus(archivo){
  return estaEnSubcarpetaProtegida() ? `../${archivo}` : archivo;
}

function rutaLoginCertus(){
  return estaEnSubcarpetaProtegida() ? "../login.html" : "login.html";
}

function estaEnSubcarpetaProtegida(){
  return location.pathname.includes("/docente/") || location.pathname.includes("/admin/");
}

function rolEsperadoPorRutaCertus(){
  if(location.pathname.includes("/admin/")) return "admin";
  if(location.pathname.includes("/docente/")) return "docente";
  return null;
}

window.addEventListener("pageshow", (event) => {
  if(!event.persisted) return;

  const rolEsperado = rolEsperadoPorRutaCertus();
  if(!rolEsperado) return;

  window.__sesionCertusCache = null;
  const sesion = getSesionActiva();

  if(!sesion || sesion.rol !== rolEsperado){
    window.location.replace(rutaLoginCertus());
  }
});
