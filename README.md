# DocuFlow AI — Landing Page (Grupo 01)

Antes todo el proyecto vivía en un único HTML. Ahora está separado por responsabilidades:

```
Grupo01_DocuFlowIA/
├── index.html                  ← Punto de entrada (solo estructura HTML)
├── css/
│   └── estilos.css             ← Estilos propios + keyframes
├── js/
│   ├── tailwind-config.js      ← Configuración de Tailwind (brand, animaciones)
│   ├── main.js                 ← Arranque: restaura ruta y pestaña guardadas
│   ├── core/
│   │   ├── theme-init.js       ← Evita el parpadeo de tema al recargar (F5)
│   │   ├── config.js           ← Credenciales demo, claves de LocalStorage, timeouts
│   │   ├── toast.js            ← Notificaciones flotantes + escapeHTML()
│   │   ├── api.js              ← fakeFetch: simulación HTTP (login, OCR, RAG)
│   │   ├── router.js           ← Router SPA (vistas, pestañas, History API)
│   │   ├── theme.js            ← Modo claro / oscuro
│   │   └── idle.js             ← Cierre de sesión por inactividad (15 min)
│   └── features/
│       ├── auth.js             ← Login, validación de correo, logout
│       ├── ocr.js              ← Carga de archivo, escaneo OCR y guardado
│       ├── repository.js       ← Filtro en vivo del repositorio RAG
│       ├── chatbot.js          ← Chatbot RAG contextual
│       └── landing.js          ← Búsqueda rápida del hero y menú móvil
├── php/
│   ├── config.php              ← Parámetros de conexión (Host 127.0.0.1, Puerto 3307, BD, usuario)
│   ├── conexion.php            ← Conexión PDO robusta y singleton Database::conectar()
│   ├── test_conexion.php       ← Verificador visual y de consola para probar MySQL
│   ├── crear_base_datos.php    ← Asistente para inicializar docuflow_db automáticamente
│   └── schema.sql              ← Tablas iniciales (usuarios, documentos, chat_historial)
├── FAVICON.png
├── Logotipo Horizontal.png
└── Logotipo Horizontal Dark.png
```

> El antiguo archivo único (`Landing Page - Grupo02.html`) se eliminó porque su contenido
> ya está repartido entre `index.html`, `css/estilos.css` y los archivos de `js/`.

## Cómo abrirlo

- **Doble clic en `index.html`** (funciona con `file://`).
- O con un servidor local, por ejemplo: `npx serve .` o la extensión *Live Server* de VS Code.

## Notas técnicas

- **Orden de carga de scripts:** importa. `index.html` los carga al final del `<body>` en el
  orden correcto. `theme-init.js` va en el `<head>` y `tailwind-config.js` siempre **después**
  del CDN de Tailwind.
- **Scripts clásicos (no ES modules):** así la página sigue funcionando abierta como archivo
  local (`file://`), donde los `import` fallarían por CORS. Por eso las funciones de cada
  archivo quedan en el ámbito global y los `onclick="..."` del HTML las encuentran.
- Los `@keyframes` (`scanner`, `shimmer`, etc.) se declararon también en `estilos.css` porque
  `.scanner-line` y `.skeleton` los usan directamente desde CSS; sin ellos, el láser del OCR
  no se animaba.
- Los logos apuntaban a `LOGO HORIZONTAL.png` (archivo inexistente) y dependían de un
  `onerror` como respaldo. Ahora apuntan directo a los nombres reales.

## Base de Datos (MySQL - Puerto 3307)

La conexión a la base de datos se encuentra organizada en la carpeta `php/`:

1. **Configuración (`php/config.php`):**
   - Host: `127.0.0.1` (fuerza la conexión TCP/IP para respetar puertos personalizados como el `3307`).
   - Puerto: `3307`.
   - Base de datos: `docuflow_db`.
   - Usuario: `root`.
2. **Conexión (`php/conexion.php`):** Implementada con **PDO** con modo de error por excepción y emulación de prepares desactivada para máxima seguridad contra inyecciones SQL.
3. **Comprobación:**
   - En consola: `php php/test_conexion.php`
   - En navegador: `http://localhost/Grupo01_DocuFlowIA/php/test_conexion.php`
4. **Inicialización de Tablas:**
   - Ejecuta `php php/crear_base_datos.php` para crear la base de datos y sus tablas automáticamente.

