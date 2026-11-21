# Despliegue en la nube

La app necesita PHP con la extensión `sqlsrv`, que los hostings PHP normales no traen. Por eso se despliega como **contenedor Docker** (ver `Dockerfile`), con la base de datos en **Azure SQL**.

## 1. Base de datos (Azure SQL)

1. En <https://portal.azure.com> crea un recurso **SQL Database** (usa la oferta gratuita si aparece). Crea un servidor nuevo con autenticación SQL y guarda el usuario y la contraseña.
2. Nombre de la base: `tutoresonline`.
3. En el servidor, sección **Redes**, activa *Permitir que los servicios y recursos de Azure accedan a este servidor* y agrega tu IP actual.
4. Abre la base en el *Editor de consultas* (o Azure Data Studio), pega `database/script.sql` y ejecútalo.

## 2. Aplicación web (contenedor)

Opción A – Azure App Service (Web App for Containers) o Azure Container Apps, construyendo la imagen desde el `Dockerfile` del repositorio.
Opción B – cualquier servicio que despliegue desde GitHub con Docker (por ejemplo Render).

En cualquiera, define estas **variables de entorno**:

| Variable | Valor |
|---|---|
| `DB_SERVER` | `tuservidor.database.windows.net` |
| `DB_NAME` | `tutoresonline` |
| `DB_USER` | usuario del servidor SQL |
| `DB_PASS` | contraseña del servidor SQL |
| `DB_ENCRYPT` | `1` |

El puerto del contenedor es el **80**.

## 3. Probar

Abre el enlace público y entra con `ana.lopez@demo.com` (tutor) o `sofia.gomez@demo.com` (estudiante), contraseña `Prueba123*`.
