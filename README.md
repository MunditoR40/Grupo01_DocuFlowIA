# DocuFlow AI — Plataforma de Gestión Documental Inteligente (Grupo 01)
**Curso:** Aplicaciones Distribuidas I  
**Universidad:** Universidad Nacional del Santa (UNS)  
**Semana:** 6 (Primer Avance Funcional — PHP Nativo + PDO + MySQL 3307)

---

## 1. Descripción del Proyecto

**DocuFlow AI** es una plataforma web para la gestión, organización, digitalización y consulta inteligente de documentos administrativos de una institución (oficios, memorandos, informes técnicos, etc.).

En este primer hito funcional, el sistema implementa un flujo de persistencia real de extremo a extremo sin frameworks pesados ni Laravel:
```text
HTML5 + CSS3 + Vanilla JavaScript
             ↓ (Fetch API con FormData y Credenciales)
        PHP 8 Nativo
             ↓ (Sesiones seguras, password_verify, CSRF)
     PHP Data Objects (PDO)
             ↓ (Prepared Statements, Transacciones y Rollback)
 MySQL 8 / MariaDB (Puerto TCP/IP 3307, docuflow_db, 11 tablas)
```

---

## 2. Requisitos del Entorno

- **Servidor Web:** Apache (vía XAMPP u homólogo).
- **PHP:** Versión 8.0 o superior (con extensiones `pdo`, `pdo_mysql`, `fileinfo`, `session` activas).
- **Base de Datos:** MySQL / MariaDB escuchando en el **puerto 3307** (configuración local especificada para el Grupo 01).
- **Navegador Web:** Chrome, Edge, Firefox con soporte para JavaScript moderno.

---

## 3. Configuración de Base de Datos (MySQL Puerto 3307)

La base de datos se denomina `docuflow_db` y cuenta con 11 tablas normalizadas:
1. `roles` (5 roles del sistema)
2. `permisos` (19 permisos de la matriz)
3. `rol_permiso` (46 asignaciones de privilegios)
4. `dependencias` (catálogo institucional)
5. `usuarios` (usuarios registrados)
6. `tipos_documento` (catálogo de tipos documentales)
7. `documentos` (expedientes registrados)
8. `metadatos_documento` (asunto, fechas, remitente, destinatario)
9. `chunks_documento` (fragmentos textuales para búsqueda RAG)
10. `evaluaciones_ia_log` (trazas de métricas de investigación)
11. `auditoria_acciones` (bitácora inmutable de eventos)

### Parámetros de Conexión (`php/config.php`):
```php
define('DB_HOST', '127.0.0.1'); // Fuerza conexión TCP/IP
define('DB_PORT', 3307);        // Puerto requerido: 3307
define('DB_NAME', 'docuflow_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

> **IMPORTANTE:** No se debe ejecutar `DROP DATABASE` ni `TRUNCATE`. Los registros iniciales se preservan íntegramente.

---

## 4. Credenciales de Demostración y Roles

| Rol | Código | Correo de Acceso | Contraseña | Alcance y Permisos |
| :--- | :---: | :--- | :--- | :--- |
| **Operador** | `OPE` | `operador.demo@docuflow.test` | `DocuFlowDemo2026!` | Registra documentos, sube archivos físicos, valida OCR y consulta expedientes de su dependencia. |
| **Administrador** | `ADM` | `admin@docuflow.ai` | `DocuFlow2026` | Gestión técnica, catálogo y auditoría. **No registra documentos por defecto** (recibe HTTP 403 si lo intenta). |
| **Gestor Documental** | `GES` | Configurable | BCRYPT | Registra, clasifica, edita y anula documentos de su ámbito. |
| **Consultor** | `CON` | Configurable | BCRYPT | Consulta y busca documentos autorizados sin modificarlos. |
| **Auditor** | `AUD` | Configurable | BCRYPT | Inspecciona bitácora de auditoría y reportes. |

---

## 5. Instrucciones de Instalación y Ejecución

1. **Ubicar el proyecto en XAMPP:**
   Asegúrate de que la carpeta esté ubicada en:
   ```text
   C:\xampp\htdocs\Grupo01_DocuFlowIA
   ```

2. **Verificar servicios en XAMPP Control Panel:**
   - Inicia **Apache** (puerto estándar 80).
   - Inicia **MySQL** en el **puerto 3307**.

3. **Ejecutar diagnóstico de conexión:**
   - **Por consola:**
     ```powershell
     cd C:\xampp\htdocs\Grupo01_DocuFlowIA
     php php/test_conexion.php
     ```
   - **Por navegador:**
     Visita: [http://localhost/Grupo01_DocuFlowIA/php/test_conexion.php](http://localhost/Grupo01_DocuFlowIA/php/test_conexion.php)

4. **Acceder a la Aplicación:**
   - **Portal Principal / Landing:** [http://localhost/Grupo01_DocuFlowIA/](http://localhost/Grupo01_DocuFlowIA/)
   - **Acceso a Intranet / Login:** [http://localhost/Grupo01_DocuFlowIA/login.html](http://localhost/Grupo01_DocuFlowIA/login.html)
   - **Panel de Control / Dashboard:** [http://localhost/Grupo01_DocuFlowIA/dashboard.html](http://localhost/Grupo01_DocuFlowIA/dashboard.html)

---

## 6. Demostración del Flujo Operativo Completo

1. **Iniciar Sesión:**
   Ingresa a `login.html` y pulsa sobre el botón rápido **"Operador (OPE)"** o escribe `operador.demo@docuflow.test` con su contraseña. La verificación es 100% auténtica mediante `password_verify()` en MySQL y genera una sesión PHP segura (`PHPSESSID` HttpOnly).

2. **Ingresar al Centro OCR & Carga:**
   - Arrastra o selecciona un archivo (PDF, JPG, PNG o DOCX, máximo 20 MB).
   - Pulsa **"Procesar Documento con IA"** para ejecutar el escaneo asistido demostrativo.
   - Revisa o edita los campos del formulario: Código Documental, Tipo de Documento, Fecha de Emisión, Confidencialidad y Asunto.

3. **Guardar en MySQL:**
   - Pulsa **"Confirmar y Guardar en MySQL"**.
   - JavaScript enviará el archivo físico y los campos estructurados mediante `FormData` al endpoint `php/api/documentos.php`.
   - El backend validará el tamaño, MIME real (`finfo`), extensión permitida y calculará el hash SHA-256.
   - Mediante una **transacción PDO atómica**, se registra:
     1. Fila en `documentos` con su ruta física en `uploads/`.
     2. Fila en `metadatos_documento`.
     3. Fila en `chunks_documento` para búsquedas.
     4. Fila en `auditoria_acciones` (`DOCUMENTO_REGISTRADO`).
   - El archivo físico queda guardado con un nombre seguro en `uploads/`.

4. **Consultar en el Repositorio y Descargar:**
   - Dirígete a la pestaña **"Repositorio RAG"**.
   - El documento recién guardado aparece de inmediato en la tabla con sus datos: ID, Código, Archivo, Tipo, Registrador, Dependencia, Estados y Fecha.
   - Pulsa **"Descargar"** para obtener el archivo físico a través del endpoint protegido `php/api/documentos.php?action=download&id=...`.
   - Recarga la página (F5): el documento continúa visible y persistente.

5. **Verificar Trazabilidad en Auditoría:**
   - En la pestaña **"Auditoría & Logs"**, comprueba que eventos como `LOGIN`, `DOCUMENTO_REGISTRADO` y `DOCUMENTO_DESCARGADO` quedan registrados de forma inmutable con la IP y datos del usuario.

---

## 7. Ejecución de la Suite de Pruebas Automatizadas

El proyecto incluye un script de pruebas automatizadas que valida de manera desatendida las 14 comprobaciones obligatorias:
```powershell
php test_verificaciones.php
```

### Matriz de Pruebas:
- **Prueba 01:** Conexión PDO a MySQL en puerto 3307.
- **Prueba 02:** Autenticación válida con sesión PHP.
- **Prueba 03:** Rechazo de contraseñas inválidas (HTTP 401).
- **Prueba 04:** Bloqueo de usuarios con `activo = 0` (HTTP 403).
- **Prueba 05:** Registro de documento real con `FormData` y `$_FILES` (HTTP 201).
- **Prueba 06:** Almacenamiento físico del archivo en `uploads/`.
- **Prueba 07:** Persistencia en tabla `documentos`.
- **Prueba 08:** Persistencia en `metadatos_documento` y `auditoria_acciones`.
- **Prueba 09:** Consulta con JOINs en el repositorio.
- **Prueba 10:** Persistencia verificada tras recarga.
- **Prueba 11:** Rechazo de usuario sin privilegios (ADM recibe HTTP 403 al intentar registrar).
- **Prueba 12:** Peticiones sin autenticación rechazadas sin respuestas ficticias (HTTP 401).
- **Prueba 13:** Búsqueda SQL con parámetros independientes (`:q1`, `:q2`, `:q3`, `:q4`) sin error HY093.
- **Prueba 14:** Conservación de datos y roles preexistentes.

---

## 8. Seguridad Implementada

- **Prevención de Inyecciones SQL:** PDO con `PDO::ATTR_EMULATE_PREPARES => false` y consultas preparadas con parámetros separados.
- **Validación Físico-Binaria de Archivos:** Inspección de tipo MIME real con `finfo` de PHP, evitando confiar en la cabecera enviada por el navegador.
- **Directorio de Almacenamiento Protegido:** Archivo `.htaccess` en `uploads/` que deniega la ejecución de scripts PHP/CGI y deshabilita el listado de directorios.
- **Protección de Sesión y CSRF:** Cookies con `HttpOnly` y `SameSite=Lax`, regeneración de ID de sesión (`session_regenerate_id(true)`), expiración por inactividad a los 15 minutos y tokens CSRF validados en métodos mutables.
- **Mitigación XSS:** Escape estricto de entidades HTML en el frontend antes de la renderización en el DOM.
- **Aislamiento de Errores Internos:** Los mensajes crudos de MySQL (`SQLSTATE[...]`) nunca se exponen al cliente; se registran en el log del servidor y se devuelven respuestas limpias con códigos HTTP estándar (200, 201, 400, 401, 403, 404, 409, 500).
- **Scripts de Migración No Destructivos:** Ubicados en `php/migraciones/001_agregar_hash_sha256_no_destructivo.sql`.
