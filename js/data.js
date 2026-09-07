// ============================================================
// DATOS — CERTUS (carpeta de arranque para el equipo)
// Esta carpeta solo tiene la pantalla de login como punto de partida
// visual, para que el equipo vea el flujo antes de construir el resto.
// Todos los arreglos empiezan vacios a proposito: no hay docentes,
// administradores, capacitaciones ni nada cargado todavia.
// ============================================================

const DOCENTES = [
  {
    id: 1,
    nombres: "Maria",
    apellidos: "Torres",
    correo: "maria.torres@certus.edu.pe",
    dni: "12345678",
    telefono: "987654321",
    especialidad: "Desarrollo de Software",
    anios_experiencia: 5,
    bio: "Docente del area de Desarrollo de Software, enfocada en programacion web y buenas practicas de codigo."
  }
];
const ADMINISTRADORES = [
  {
    id: 1,
    nombres: "Ana",
    apellidos: "Torres",
    correo: "ana.torres@certus.edu.pe"
  }
];
const CAPACITACIONES = [];
const INSCRIPCIONES = [];
const CONSTANCIAS = [];
const INSIGNIAS = [];
const MENTORES = [];
const SESIONES_MENTORIA = [];
const AUTOEVALUACIONES = [];
const PLANES_DESARROLLO = [];
const EVIDENCIAS_INSIGNIA = [];
const SUGERENCIAS_CAPACITACION = [];

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
