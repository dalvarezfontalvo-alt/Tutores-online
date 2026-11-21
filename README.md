# TutoresOn-Line

Plataforma web que conecta estudiantes con tutores. Proyecto final de Ingeniería de Sistemas.

**Tecnologías:** PHP 8 · SQL Server (extensión `sqlsrv`) · HTML5 · CSS3 · JavaScript

## Estructura

| Carpeta | Contenido |
|---|---|
| `index.php` | Página principal (buscador y tutores destacados) |
| `config/` | `conexion.php` (conexión a SQL Server) y `sesion.php` |
| `Registro/` | Procesa registro e inicio de sesión |
| `usuarios/` | Login, registro, logout y panel del estudiante |
| `tutores/` | Panel del tutor |
| `includes/` | Encabezado y pie de página |
| `css/`, `js/`, `img/` | Estilos, scripts e imágenes |
| `database/script.sql` | **Script completo de la base de datos** (tablas + datos de prueba) |
| `Dockerfile` | Imagen para desplegar en la nube (PHP + Apache + driver SQL Server) |

## Instalación local

1. Instala PHP 8.x con las extensiones `sqlsrv` y `pdo_sqlsrv` y el *ODBC Driver 18 for SQL Server*.
2. Crea una base de datos vacía llamada `tutoresonline` en SQL Server.
3. Ejecuta `database/script.sql` sobre esa base (SSMS o Azure Data Studio).
4. Desde la raíz del proyecto: `php -S localhost:8000` y abre <http://localhost:8000>.

Si tu SQL Server necesita usuario y contraseña, define las variables de entorno `DB_SERVER`, `DB_NAME`, `DB_USER` y `DB_PASS` (ver `config/conexion.php`).

## Usuarios de prueba (contraseña `Prueba123*`)

- Tutores: `ana.lopez@demo.com`, `carlos.ruiz@demo.com`, `laura.mejia@demo.com`
- Estudiantes: `sofia.gomez@demo.com`, `pedro.diaz@demo.com`

## Despliegue en la nube

Ver [`DESPLIEGUE.md`](DESPLIEGUE.md).
