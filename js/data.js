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
const ADMINISTRADORES = [];
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
  return raw ? JSON.parse(raw) : null;
}
function requireSesion(rolEsperado){
  const s = getSesionActiva();
  if(!s || (rolEsperado && s.rol !== rolEsperado)){
    window.location.href = "../login.html";
  }
  return s;
}
