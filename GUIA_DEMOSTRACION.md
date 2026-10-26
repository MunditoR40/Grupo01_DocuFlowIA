# GUÍA DE DEMOSTRACIÓN Y SUSTENTACIÓN EN VIVO
## DocuFlow AI — Plataforma de Gestión Documental Inteligente (Grupo 01)

Esta guía paso a paso está diseñada para que el equipo sustente ante el docente y jurado evaluador las capacidades reales del sistema, su arquitectura cliente-servidor nativa (PHP 8 + MySQL 3307), la seguridad de archivos y el cumplimiento estricto del modelo de permisos por roles.

---

## 1. RESUMEN DE LA ARQUITECTURA TÉCNICA

- **Backend:** PHP 8.2 Nativo estructurado y modular (**Sin frameworks como Laravel**).
- **Acceso a Datos:** PHP Data Objects (PDO) con sentencias preparadas y transacciones atómicas `beginTransaction()`, `commit()`, `rollBack()`.
- **Motor de Base de Datos:** MySQL / MariaDB en puerto `3307` (`127.0.0.1:3307`), base de datos `docuflow_db` (11 tablas relacionales).
- **Servidor Web:** Apache 2.4 en puerto `80` con módulos `mod_rewrite`, `mod_authz_core` y `mod_headers` activos.
- **Frontend:** HTML5 semántico, Tailwind CSS y JavaScript Vanilla modular (`js/core/`, `js/features/`).

---

## 2. CREDENCIALES DE DEMOSTRACIÓN EN MYSQL (PUERTO 3307)

En la pantalla de login (`login.html`), se han dispuesto botones de acceso rápido con 1 clic para cargar inmediatamente las credenciales de cada uno de los 5 roles del sistema:

| Rol | Correo Institucional | Contraseña | Dependencia | Alcance y Propósito en la Demo |
|:---:|---|---|:---:|---|
| **OPE** (Operador) | `operador.demo@docuflow.test` | `DocuFlowDemo2026!` | Dep 1 (Archivo Central) | Digitalización, subida física y persistencia de documentos propios. |
| **GES** (Gestor) | `gestor.demo@docuflow.test` | `DocuFlowDemo2026!` | Dep 1 (Archivo Central) | Gestión, aprobación, búsqueda y control de expedientes de su dependencia. |
| **CON** (Consultor) | `consultor.demo@docuflow.test` | `DocuFlowDemo2026!` | Dep 1 (Archivo Central) | Consulta de documentos públicos/internos. Bloqueo de confidenciales y sin métricas. |
| **AUD** (Auditor) | `auditor.demo@docuflow.test` | `DocuFlowDemo2026!` | Dep 1 (Archivo Central) | Trazabilidad global e inspección de logs; denegado a descargar archivos binarios. |
| **ADM** (Administrador) | `admin@docuflow.ai` | `DocuFlow2026` | Dep 1 (Archivo Central) | Gestión técnica de usuarios y eventos; sin acceso a contenido documental. |

> **Nota:** Para probar el aislamiento entre distintas dependencias, existen también los usuarios `operador2.demo@docuflow.test` y `gestor2.demo@docuflow.test` asignados a la **Dependencia 2 (Secretaría General)** con la misma contraseña `DocuFlowDemo2026!`.

---

## 3. GUIÓN PASO A PASO PARA LA DEMOSTRACIÓN

### PASO 1: Inicio de Sesión y Verificación de Conexión a MySQL 3307
1. Abra el navegador en: `http://127.0.0.1/Grupo01_DocuFlowIA/login.html`.
2. En la parte inferior, haga clic en **"Ver estado de conexión a MySQL 3307"**:
   - Se abrirá una pestaña comprobando en tiempo real la conexión PDO a `127.0.0.1:3307` y listando las 11 tablas de `docuflow_db`.
3. En la tarjeta de credenciales de demostración, haga clic en el botón **"Operador (OPE)"**.
   - Los campos se autocompletarán con `operador.demo@docuflow.test`.
4. Haga clic en **"Iniciar Sesión"**. El sistema validará la contraseña con `password_verify()`, creará la sesión PHP segura, generará el token CSRF y redirigirá al Dashboard.

---

### PASO 2: Carga Real de Archivo, OCR y Persistencia Atómica (Rol Operador)
1. En el Dashboard, diríjase a la sección **"Digitalización & Registro"** (`#view-ocr`).
2. Seleccione o arrastre un documento PDF de prueba.
3. Observe la extracción de metadatos asistida:
   - Se sugiere el código documental (`DOC-AAAAMMDD-XXX`), tipo de documento y asunto.
4. Haga clic en **"Guardar en Repositorio"**:
   - **En el Servidor:** Se verifica el tipo MIME real mediante `finfo_file`, se almacena físicamente el archivo en `uploads/doc_...pdf`, se calcula su hash `SHA-256`, se inserta en `documentos`, `metadatos_documento`, `chunks_documento` y se registra en `auditoria_acciones`.
   - **En la Interfaz:** Se muestra un Toast verde indicando `HTTP 201 Created` y los datos del nuevo expediente.
5. Vaya al **"Repositorio de Documentos"** (`#view-repo`):
   - El nuevo documento aparece inmediatamente listado, demostrando persistencia en tiempo real sin recargar datos ficticios.

---

### PASO 3: Demostración de Búsqueda y Ámbito de Dependencia (Rol Gestor)
1. Cierre sesión e inicie como **"Gestor (GES)"** (`gestor.demo@docuflow.test`).
2. Ingrese al **"Repositorio de Documentos"**.
3. En la barra de búsqueda, escriba `oficio` o presione Enter:
   - La petición viaja hacia `php/api/documentos.php?action=list&q=oficio`.
   - El Gestor visualiza los documentos correspondientes a la **Dependencia 1**.
   - Si intenta descargar el documento `SEC-2026-001` (perteneciente a la Dependencia 2), el sistema responde **HTTP 403 Forbidden**, demostrando que el Gestor no puede traspasar el ámbito de su dependencia.

---

### PASO 4: Demostración de Protección de Confidenciales y Chatbot Seguro (Rol Consultor)
1. Cierre sesión e inicie como **"Consultor (CON)"** (`consultor.demo@docuflow.test`).
2. Demuestre que el Consultor solo puede consultar documentos con nivel `público` o `interno`:
   - El documento confidencial `CONF-2026-001` no permite su descarga y si se invoca directamente su ID por API responde **HTTP 403 Forbidden**.
3. Diríjase a la barra lateral y abra el **"Asistente Contextual (Chatbot)"**:
   - Pregunte: *"¿Qué información contiene el informe CONF-2026-001?"*
   - El backend ejecuta `DocumentoPermisos::obtenerFiltroChatbotSQL()` excluyendo documentos confidenciales para el rol CON.
   - El asistente responde que no se encontraron documentos autorizados coincidentes dentro de su ámbito, garantizando cero fugas de datos sensibles.
4. Intente acceder a `/php/api/resumen.php` o al panel de analítica:
   - El sistema devuelve **HTTP 403 Forbidden**, respetando que el perfil Consultor no tiene asignada la visualización de métricas estratégicas.

---

### PASO 5: Trazabilidad Global y Restricción Binaria (Rol Auditor)
1. Inicie sesión como **"Auditor (AUD)"** (`auditor.demo@docuflow.test`).
2. Ingrese a la sección **"Auditoría del Sistema"**:
   - El Auditor puede inspeccionar el historial completo de eventos (`DOCUMENTO_REGISTRADO`, `DOCUMENTO_DESCARGADO`, `LOGIN`, `DOCUMENTO_CONSULTADO`).
3. Intente descargar un archivo físico original desde el repositorio:
   - El sistema responde **HTTP 403 Forbidden**. El Auditor supervisa la trazabilidad y los metadatos forenses, pero la matriz de seguridad le prohíbe descargar archivos de trabajo originales.

---

### PASO 6: Demostración de Seguridad Perimetral y Pruebas de Penetración
Demuestre en vivo las defensas implementadas abriendo nuevas pestañas en el navegador o mediante PowerShell:

1. **Intento de Acceso Directo al PDF:**
   - URL: `http://127.0.0.1/Grupo01_DocuFlowIA/uploads/doc_confidencial_demo.pdf`
   - **Resultado:** **HTTP 403 Forbidden** (Apache `uploads/.htaccess`).
2. **Intento de Descarga sin Autenticación:**
   - En una ventana de incógnito: `http://127.0.0.1/Grupo01_DocuFlowIA/php/api/documentos.php?action=download&id=3`
   - **Resultado:** **HTTP 401 Unauthorized** en formato JSON.
3. **Intento de Inspección de Archivos Sensibles del Proyecto:**
   - URL: `http://127.0.0.1/Grupo01_DocuFlowIA/.git/config` -> **HTTP 403 Forbidden**.
   - URL: `http://127.0.0.1/Grupo01_DocuFlowIA/php/migraciones/001_agregar_hash_sha256_no_destructivo.sql` -> **HTTP 403 Forbidden**.
   - URL: `http://127.0.0.1/Grupo01_DocuFlowIA/php/config.php` -> **HTTP 403 Forbidden**.

---

### PASO 7: Ejecución en Vivo de la Suite de Pruebas Automatizada
Para concluir con la máxima solvencia técnica, abra la terminal PowerShell y ejecute:

```powershell
php C:\xampp\htdocs\Grupo01_DocuFlowIA\test_seguridad_completa.php
```

**Explicación para el docente:**
> *"Profesor, para certificar que todos los requisitos de seguridad, los 5 roles, la atomicidad transaccional y la persistencia en MySQL 3307 se cumplen de manera rigurosa, hemos implementado una suite automatizada de pruebas end-to-end. Como puede observar en la consola, se ejecutan las 17 pruebas obligatorias sobre el servidor Apache real y la base de datos MySQL, obteniendo una tasa de aprobación del 100%."*

---
**DocuFlow AI — Grupo 01**  
*Universidad Nacional del Santa — Aplicaciones Distribuidas I*
