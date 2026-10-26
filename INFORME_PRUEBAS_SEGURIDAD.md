# INFORME DE PRUEBAS DE SEGURIDAD, PERMISOS Y PERSISTENCIA
## DocuFlow AI — Grupo 01 (Curso: Aplicaciones Distribuidas I, UNS)

- **Fecha de Auditoría:** 26 de Octubre de 2026
- **Entorno de Ejecución:** Servidor Web Apache 2.4 / PHP 8.2 Nativo (Sin frameworks)
- **Base de Datos:** MySQL / MariaDB (Puerto: `3307`, Base: `docuflow_db`, 11 tablas)
- **Resultado Global:** **17 de 17 Pruebas Aprobadas (100.0%)**

---

## 1. RESUMEN EJECUTIVO

Se ejecutó una auditoría y corrección integral sobre el sistema **DocuFlow AI**, resolviendo vulnerabilidades críticas de acceso directo a archivos, centralizando la lógica de autorización por roles y dependencias, eliminando simulaciones de persistencia en frontend y asegurando atomicidad transaccional completa en MySQL 3307 mediante PHP PDO.

### Principales Logros de Seguridad y Arquitectura
1. **Protección Total del Almacenamiento Privado (`uploads/`):** Configuración de Apache con `Require all denied` y `Options -Indexes`. Todo acceso HTTP directo a documentos (PDF, DOCX, imágenes) es rechazado con **HTTP 403 Forbidden**.
2. **Descarga Controlada e Inmune a Path Traversal:** Implementación de endpoint seguro `php/api/documentos.php?action=download&id=...` con verificación estricta de ruta canónica (`realpath`), cabeceras binarias seguras, RFC 5987 / RFC 6266 y registro obligatorio de evento `DOCUMENTO_DESCARGADO` en auditoría.
3. **Centralización de Permisos (`DocumentoPermisos`):** Unificación de reglas de negocio para los 5 roles (`ADM`, `GES`, `OPE`, `CON`, `AUD`), garantizando cumplimiento estricto del principio de mínimo privilegio y aislamiento por dependencias.
4. **Chatbot Aislado y Seguro (`php/api/chat.php`):** Consultas contextuales filtradas en backend por rol y nivel de confidencialidad, previniendo fuga de información confidencial hacia perfiles no autorizados.
5. **Persistencia Real y Atomicidad Transaccional:** Carga física de archivos binarios, cálculo de integridad SHA-256 (`hash_sha256`), inserción sincronizada en `documentos`, `metadatos_documento`, `chunks_documento` y `auditoria_acciones`. Ante cualquier excepción, se ejecuta `rollBack()` y se elimina el archivo en disco, impidiendo registros y archivos huérfanos.
6. **Hardening de Servidor Apache:** Bloqueo perimetral a carpetas de control de versiones (`.git/`), archivos `.sql`, `.bak`, `.log` y librerías internas PHP en subdirectorios.

---

## 2. MATRIZ DE ROLES Y PRIVILEGIOS DEL SISTEMA

| Rol | Código | Alcance Autorizado | Acceso Documental | Descarga de Archivos |
|---|:---:|---|:---:|:---:|
| **Administrador** | `ADM` | Gestión técnica de usuarios, roles, configuraciones y eventos administrativos. | Sin acceso documental operativo por defecto. | **DENEGADO (403)** |
| **Gestor** | `GES` | Gestión y aprobación documental exclusivamente dentro de su dependencia autorizada. | Limitado a su `dependencia_id`. | **AUTORIZADO** (su dependencia) |
| **Operador** | `OPE` | Digitalización, OCR asistido y registro de documentos propios. | Exclusivamente documentos donde `usuario_id = auth_id`. | **AUTORIZADO** (solo propios) |
| **Consultor** | `CON` | Consulta y lectura de documentos públicos e internos de su dependencia. | `dependencia_id` y confidencialidad `publico` o `interno`. | **DENEGADO** para `confidencial` (403) |
| **Auditor** | `AUD` | Trazabilidad global, auditoría forense, historial y metadatos documentales. | Lectura de metadatos globales de auditoría. | **DENEGADO** a binario original (403) |

---

## 3. MATRIZ DETALLADA DE LAS 17 PRUEBAS DE SEGURIDAD

A continuación se detalla la ejecución real de la batería de pruebas ejecutada contra el servidor Apache en `http://127.0.0.1/Grupo01_DocuFlowIA` y MySQL 3307:

| # | Escenario de Prueba | Entrada / Petición HTTP | Comportamiento Esperado | Resultado Real Obtenido | Estado |
|:---:|---|---|---|---|:---:|
| **01** | Acceso directo por HTTP a archivo en `uploads/` | `GET /uploads/doc_confidencial_demo.pdf` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (Apache `Require all denied`) | **APROBADO** |
| **02** | Descarga vía endpoint sin sesión autenticada | `GET /php/api/documentos.php?action=download&id=3` | HTTP 401 Unauthorized | **HTTP 401 Unauthorized** (JSON `Auth::requerirAutenticacion`) | **APROBADO** |
| **03** | Operador (OPE 2) intenta descargar documento de otro operador (OPE 1) | `GET /php/api/documentos.php?action=download&id=3` con sesión `operador2.demo@docuflow.test` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (Mensaje: Acceso denegado a archivo ajeno) | **APROBADO** |
| **04** | Gestor (GES 1) intenta descargar documento de Dependencia 2 | `GET /php/api/documentos.php?action=download&id=6` con sesión `gestor.demo@docuflow.test` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (Ámbito de dependencia no coincidente) | **APROBADO** |
| **05** | Consultor (CON 1) intenta descargar documento confidencial | `GET /php/api/documentos.php?action=download&id=7` con sesión `consultor.demo@docuflow.test` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (Bloqueo por nivel de confidencialidad) | **APROBADO** |
| **06** | Consultor (CON) consulta al Chatbot sobre documento confidencial | `POST /php/api/chat.php` body: `{"query": "informe CONF-2026-001 financiero"}` | HTTP 200 con 0 fuentes confidenciales | **HTTP 200 OK** (0 fragmentos confidenciales devueltos, sin fuga) | **APROBADO** |
| **07** | Administrador (ADM) intenta descargar contenido documental | `GET /php/api/documentos.php?action=download&id=3` con sesión `admin@docuflow.ai` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (ADM no accede a contenido operativo) | **APROBADO** |
| **08** | Auditor (AUD) intenta descargar archivo original | `GET /php/api/documentos.php?action=download&id=3` con sesión `auditor.demo@docuflow.test` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (AUD audita trazas, sin descarga física) | **APROBADO** |
| **09** | Gestor (GES 1) consulta registros en `/php/api/auditoria.php` | `GET /php/api/auditoria.php` con sesión `gestor.demo@docuflow.test` | HTTP 200 acotado a Dep 1 | **HTTP 200 OK** (46 registros recibidos, 0 pertenecientes a Dep 2) | **APROBADO** |
| **10** | Consultor (CON) consulta endpoint `/php/api/resumen.php` | `GET /php/api/resumen.php` con sesión `consultor.demo@docuflow.test` | HTTP 403 Forbidden | **HTTP 403 Forbidden** (CON no autorizado para métricas) | **APROBADO** |
| **11** | Búsqueda en repositorio documental mediante `?q=oficio` | `GET /php/api/documentos.php?action=list&q=oficio` | HTTP 200 con array JSON | **HTTP 200 OK** (Coincidencias encontradas y devueltas desde MySQL) | **APROBADO** |
| **12** | Subida autorizada de documento y metadatos por Operador (OPE) | `POST /php/api/documentos.php` (Multipart + PDF válido + metadatos) | HTTP 201 Created | **HTTP 201 Created** (Doc ID persistido, archivo físico guardado, SHA-256) | **APROBADO** |
| **13** | Recarga del repositorio tras el registro | `GET /php/api/documentos.php?action=list` con sesión Operador | HTTP 200 y presencia del nuevo código | **HTTP 200 OK** (Documento nuevo listado con datos reales de MySQL) | **APROBADO** |
| **14** | Intento de subida de archivo superior a 20 MB | `POST /php/api/documentos.php` con archivo simulado de 21 MB | HTTP 400 Bad Request o 413 | **HTTP 400 Bad Request** (Validación de tamaño máximo superado) | **APROBADO** |
| **15** | Intento de subida con tipo MIME o extensión no permitida (`.exe`) | `POST /php/api/documentos.php` con archivo binario ejecutable | HTTP 400 Bad Request | **HTTP 400 Bad Request** (Validación de extensión y tipo MIME) | **APROBADO** |
| **16** | Atomicidad transaccional ante error forzado | Inserción en `documentos` seguida de excepción simulada | Rollback en DB y eliminación en disco | **Rollback exitoso** (0 registros huérfanos en DB, archivo físico eliminado) | **APROBADO** |
| **17** | Acceso directo por navegador a `.git/config` y archivos `.sql` | `GET /.git/config` y `GET /php/migraciones/...sql` | HTTP 403 Forbidden en ambos | **HTTP 403 Forbidden** (Bloqueado por Apache mod_rewrite y FilesMatch) | **APROBADO** |

---

## 4. EVIDENCIA TÉCNICA DE EJECUCIÓN

La suite automatizada `test_seguridad_completa.php` ejecutó todas las pruebas vía HTTP contra el puerto `80` (Apache) y verificó la consistencia en el puerto `3307` (MySQL).

Salida de la consola de ejecución:

```text
========================================================================================
 DOCUFLOW AI — EJECUTANDO BATERÍA COMPLETA DE 17 PRUEBAS DE SEGURIDAD (GRUPO 01)
========================================================================================

[01] Acceso directo por HTTP a archivo en uploads/ sin pasar por PHP   [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : Apache 2.4 deniega el acceso directo vía uploads/.htaccess (Require all denied).

[02] Descarga de documento vía endpoint sin sesión autenticada         [APROBADO]
     Esperado: HTTP 401 Unauthorized
     Obtenido: HTTP 401
     Detalle : El endpoint valida Auth::requerirAutenticacion() antes de servir cualquier flujo binario.

[03] Operador (OPE 2) intenta descargar documento de otro operador (OPE 1) [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : DocumentoPermisos::puedeDescargar() impide que OPE descargue archivos de otros autores.

[04] Gestor (GES 1) intenta descargar documento de otra dependencia (Dep 2) [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : DocumentoPermisos::puedeDescargar() restringe al Gestor estrictamente a su ámbito de dependencia.

[05] Consultor (CON 1) intenta descargar documento confidencial        [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : DocumentoPermisos::puedeDescargar() bloquea descarga de confidenciales para rol CON.

[06] Chatbot: Consultor (CON) consulta contenido de documento confidencial [APROBADO]
     Esperado: HTTP 200 con 0 fragmentos de documentos confidenciales
     Obtenido: HTTP 200 (0 fuentes devueltas, contiene confidencial: NO)
     Detalle : DocumentoPermisos::obtenerFiltroChatbotSQL() excluye confidenciales para CON; no hay fuga de contexto.

[07] Administrador (ADM) intenta descargar contenido de documento operativo [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : Matriz de seguridad: ADM administra usuarios y eventos técnicos, sin acceso a contenido documental.

[08] Auditor (AUD) intenta descargar archivo físico de documento       [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : Matriz de seguridad: AUD audita metadatos, historial y eventos, sin descarga de archivos originales.

[09] Auditoría: Gestor (GES 1) consulta registros de eventos           [APROBADO]
     Esperado: HTTP 200 con registros limitados estrictamente a su dependencia (Dep 1)
     Obtenido: HTTP 200 (46 registros, dependencias ajenas: NO)
     Detalle : DocumentoPermisos::obtenerFiltroAuditoriaSQL() restringe a GES a los logs de su dependencia.

[10] Estadísticas: Consultor (CON) consulta endpoint /api/resumen      [APROBADO]
     Esperado: HTTP 403 Forbidden
     Obtenido: HTTP 403
     Detalle : DocumentoPermisos::puedeConsultarEstadisticas() bloquea a CON; rol destinado a consulta documental.

[11] Búsqueda en repositorio mediante parámetro ?q=oficio hacia MySQL  [APROBADO]
     Esperado: HTTP 200 con lista de documentos filtrados por término
     Obtenido: HTTP 200 (Total devueltos: 4)
     Detalle : Consulta parametrizada PDO ejecutada exitosamente con búsqueda de coincidencias.

[12] Subida autorizada de documento y metadatos por Operador (OPE 1)    [APROBADO]
     Esperado: HTTP 201 Created con ID autoincremental y hash SHA-256 generado
     Obtenido: HTTP 201 (Doc ID: 11, SHA256: 16006f351cfd55ad...)
     Detalle : Documento persistido en MySQL, archivo binario almacenado y auditoría generada en transacción atómica.

[13] Recarga del repositorio documental para verificar persistencia real [APROBADO]
     Esperado: HTTP 200 y presencia del documento OF-AUT-20261026-3914 en la lista
     Obtenido: HTTP 200 (Encontrado en listado: SI)
     Detalle : El listado consulta en tiempo real MySQL; el nuevo registro se visualiza inmediatamente.

[14] Validación de tamaño máximo de archivo (> 20 MB)                [APROBADO]
     Esperado: HTTP 400 Bad Request o 413 Payload Too Large
     Obtenido: HTTP 400 (El archivo supera el tamaño máximo permitido de 20 MB o se encuentra vacío.)
     Detalle : El servidor valida el tamaño del archivo físico rechazando cargas excesivas.

[15] Validación de tipo MIME y extensión permitida (.exe / no documental) [APROBADO]
     Esperado: HTTP 400 Bad Request
     Obtenido: HTTP 400 (Extensión no permitida. Solo se aceptan archivos PDF, JPG, JPEG, PNG y DOCX.)
     Detalle : El endpoint valida extensión y finfo_file MIME; rechaza ejecutables y archivos peligrosos.

[16] Atomicidad transaccional (Rollback ante error y eliminación de archivo físico) [APROBADO]
     Esperado: Rollback ejecutado, registro ausente en MySQL y archivo físico eliminado
     Obtenido: Rollback: SI, En DB: NO, Archivo en disco: ELIMINADO
     Detalle : PDO Transaction garantiza que no quedan registros parciales ni archivos huérfanos ante excepciones.

[17] Acceso directo por navegador a .git/config y archivos .sql de migraciones [APROBADO]
     Esperado: HTTP 403 Forbidden en ambas rutas
     Obtenido: .git/config: HTTP 403 | .sql: HTTP 403
     Detalle : Reglas mod_rewrite y FilesMatch en root .htaccess bloquean metadatos de Git y código SQL interno.

========================================================================================
 RESUMEN FINAL: 17 / 17 PRUEBAS APROBADAS (100.0%)
========================================================================================
>>> TODAS LAS PRUEBAS DE SEGURIDAD Y ESTÁNDARES FUERON SATISFECHAS EXITOSAMENTE. <<<
```

---

## 5. COMANDOS PARA REPRODUCIR LAS PRUEBAS

Para volver a ejecutar y certificar todas las pruebas en cualquier momento, abrir PowerShell o la consola del sistema y ejecutar:

```powershell
php C:\xampp\htdocs\Grupo01_DocuFlowIA\test_seguridad_completa.php
```

Para verificar puntualmente la protección de Apache mediante cURL:

```powershell
# 1. Acceso directo a PDF (debe responder HTTP 403)
curl -I http://127.0.0.1/Grupo01_DocuFlowIA/uploads/doc_confidencial_demo.pdf

# 2. Acceso a metadatos Git (debe responder HTTP 403)
curl -I http://127.0.0.1/Grupo01_DocuFlowIA/.git/config

# 3. Acceso a archivo SQL de migración (debe responder HTTP 403)
curl -I http://127.0.0.1/Grupo01_DocuFlowIA/php/migraciones/001_agregar_hash_sha256_no_destructivo.sql
```

---
**Elaborado por:** Equipo de Desarrollo DocuFlow AI — Grupo 01  
**Asignatura:** Aplicaciones Distribuidas I — Universidad Nacional del Santa
