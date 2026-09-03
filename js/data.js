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
  const raw = sessionStorage.getItem("sesion_certus");
  if(raw) return JSON.parse(raw);
  // Sin sistema de sesion todavia (login.html no guarda nada en
  // sessionStorage) -- usamos una sesion de prueba segun la carpeta actual
  // (docente/ o admin/) para que el navbar y el contenido se vean completos
  // (iconos incluidos) mientras se conecta el login real.
  const esAdmin = location.pathname.includes("/admin/");
  return esAdmin
    ? { rol: "admin", id: 1, nombre: "Ana Torres" }
    : { rol: "docente", id: 1, nombre: "Maria Torres" };
}
function requireSesion(rolEsperado){
  return getSesionActiva();
}
