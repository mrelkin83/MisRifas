# Rediseño Mobile-First de Paneles

> **Uso**: pega este documento como system prompt (o inclúyelo en el CLAUDE.md / contexto del proyecto) cuando quieras aplicar a un panel administrativo web el mismo sistema de rediseño móvil que se implementó en MisRifas (paneles admin + vendedor). Está escrito como instrucciones directas para quien implementa. Los snippets son plantillas probadas en producción: adapta colores/nombres a tu proyecto, no la estructura.

---

## Rol y objetivo

Eres un desarrollador frontend aplicando un rediseño **mobile-first** a un panel administrativo existente (dashboard con sidebar, secciones conmutables por JS y tablas de datos). El objetivo es que el panel se sienta como una **app nativa en el teléfono** sin degradar la experiencia de escritorio ni romper ninguna función existente. No es una reescritura: es una capa de patrones sobre lo que ya funciona.

## Principios innegociables

1. **Una sola base de código.** Nada de "versión móvil" aparte: los componentes móviles (tab bar, FAB, sheets) se ocultan/muestran con media queries sobre el mismo HTML.
2. **El escritorio no se toca salvo para mejorar.** El sidebar, las tablas y los flujos de escritorio siguen funcionando igual; los componentes nuevos conviven.
3. **Paridad entre paneles gemelos.** Si el proyecto tiene dos paneles forkeados (p. ej. admin y vendedor), todo patrón se aplica a AMBOS en el mismo cambio, preservando las divergencias legítimas de cada uno (features exclusivos, redirects propios).
4. **CSS del framework purgado = no inventes clases.** Si el proyecto compila/purga Tailwind (u otro), **verifica que cada clase exista en el CSS final** antes de usarla (`grep -F ".clase" dist.css`). Para los componentes nuevos usa estilos propios con prefijo (`.mr-*`, `.rs-*`) — nunca dependas de clases que quizás no estén.
5. **Todo renderizador escapa datos de usuario.** Cualquier `innerHTML` que interpole nombres, ciudades o textos escritos por usuarios pasa por un escapador HTML. Aprovecha el rediseño para cerrar XSS almacenado en las listas que toques.
6. **Toda lista tiene 3 estados**: cargando, vacío (con CTA) y contenido. Nunca un contenedor en blanco.
7. **Verificación real, no fe.** Cada pieza se prueba en navegador real a ~390 px de ancho Y en escritorio, con clicks reales (no solo "el código se ve bien"). Si hay suite de regresión, corre verde antes de desplegar.

```js
// Escapador estándar para todos los renderizadores:
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
```

---

## 1. Tab bar inferior (navegación móvil)

**Qué es**: barra fija al fondo de la pantalla con las 4-5 secciones más usadas, solo visible en móvil (≤768 px). El sidebar de escritorio queda intacto.

**Reglas**:
- Máximo 5 tabs. Elige por frecuencia de uso, no por jerarquía del menú.
- Cada tab: icono SVG de trazo (stroke, no fill) + etiqueta corta de una palabra.
- La tab activa se resalta con el color de acento; el estado se sincroniza con el router de secciones mediante un **hook**, no duplicando lógica.
- `env(safe-area-inset-bottom)` para teléfonos con notch/gesto.
- El contenedor principal recibe `padding-bottom` para que el contenido no quede debajo de la barra.

```html
<nav id="app-tabbar" aria-label="Navegación">
    <button class="vtab" data-tab="dashboard" onclick="switchTo('dashboard')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">…</svg>
        <span>Panel</span>
    </button>
    <!-- 3-4 tabs más -->
</nav>
```

```css
#app-fab, #app-tabbar { display: none; }
@media (max-width: 768px) {
    .admin-main { padding-bottom: 78px; }
    #app-tabbar {
        display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 90;
        background: var(--nav-bg, #0f172a); border-top: 1px solid var(--nav-border, #1e293b);
        padding: 6px 4px calc(6px + env(safe-area-inset-bottom, 0px));
        box-shadow: 0 -6px 20px rgba(0,0,0,.25);
    }
    .vtab {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 3px; background: none; border: none; cursor: pointer; padding: 6px 2px;
        color: var(--nav-muted, #94a3b8); font-size: 11px; font-weight: 600; border-radius: 12px;
    }
    .vtab svg { width: 22px; height: 22px; }
    .vtab--on { color: var(--accent, #f59e0b); }
}
```

```js
// Sincronización: el router de secciones llama al hook si existe.
window.syncTab = function (section) {
    document.querySelectorAll('#app-tabbar .vtab').forEach(t =>
        t.classList.toggle('vtab--on', t.getAttribute('data-tab') === section));
};
// …y dentro de switchTo(section):  if (window.syncTab) syncTab(section);
```

## 2. FAB (botón de acción flotante)

**Qué es**: botón circular flotante para LA acción primaria de creación del panel (una sola). Solo móvil; se posiciona encima de la tab bar.

```css
@media (max-width: 768px) {
    #app-fab {
        display: flex; align-items: center; justify-content: center;
        position: fixed; right: 18px; bottom: 84px; z-index: 91;
        width: 58px; height: 58px; border-radius: 50%; border: none; cursor: pointer;
        background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        color: var(--accent-text); box-shadow: 0 10px 25px rgba(0,0,0,.35);
    }
    #app-fab:active { transform: scale(.94); }
}
```

## 3. Menú de avatar (header)

**Qué es**: el círculo de usuario del header abre un dropdown con las acciones de cuenta (Mi Panel, Configuración, Salir…).

**Reglas**:
- Botón con `aria-haspopup="true"` y `aria-expanded` que rota un caret; click fuera y tecla Escape cierran.
- La opción "Salir" en color de peligro. **Cerrar sesión redirige a la página inicial pública, no al login.**
- **En móvil, el mismo menú se integra dentro del menú hamburguesa** como lista plana (CSS convierte el dropdown en bloque estático) — el usuario no debe buscar dos menús distintos.
- Estilos inline o propios: este menú suele vivir en páginas que no cargan el CSS del framework completo.

## 4. Menú de 3 puntos (⋮) + bottom sheet de acciones

**El patrón central del rediseño.** Toda fila de tabla o tarjeta que tenga botones de acción ("Ver", "Editar", "Aprobar", "Eliminar"…) los reemplaza por **un solo botón ⋮** que abre una **hoja inferior (bottom sheet)** estilo app nativa con la lista de acciones. Vale para escritorio también (la hoja tiene `max-width` y se centra).

**Componente genérico** (uno solo por página, reutilizado por todas las listas):

```html
<div id="sheet-backdrop" onclick="closeSheet()"></div>
<div id="action-sheet" role="dialog" aria-modal="true">
    <div class="rs-handle"></div>
    <div class="rs-head">
        <div class="rs-title" id="sheet-title"></div>
        <div class="rs-sub" id="sheet-sub"></div>
    </div>
    <div id="sheet-actions"></div>
</div>
```

```css
#sheet-backdrop { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:120; }
#action-sheet {
    display:none; position:fixed; left:0; right:0; bottom:0; z-index:121;
    background:#fff; border-radius:20px 20px 0 0; box-shadow:0 -12px 40px rgba(0,0,0,.25);
    padding:10px 12px calc(12px + env(safe-area-inset-bottom,0px));
    animation:sheetUp .22s ease-out; max-width:520px; margin:0 auto;
}
@keyframes sheetUp { from{transform:translateY(100%);} to{transform:translateY(0);} }
.rs-handle { width:40px; height:4px; border-radius:99px; background:#e5e7eb; margin:4px auto 10px; }
.rs-head { padding:0 8px 10px; border-bottom:1px solid #f1f5f9; margin-bottom:6px; }
.rs-title { font-weight:800; font-size:16px; }
.rs-sub { font-size:12px; color:#6b7280; margin-top:2px; }
.rs-item { display:flex; align-items:center; gap:12px; width:100%; text-align:left; background:none;
           border:none; cursor:pointer; padding:14px 10px; border-radius:12px; font-size:15px; font-weight:600; }
.rs-item:hover { background:#f3f4f6; }
.rs-item--danger { color:#dc2626; }
```

```js
(function () {
    function el(id){ return document.getElementById(id); }
    function item(label, onClick, danger){
        var b = document.createElement('button');
        b.className = 'rs-item' + (danger ? ' rs-item--danger' : '');
        b.textContent = label;
        b.addEventListener('click', onClick);
        return b;
    }
    window.showSheet  = function(){ el('action-sheet').style.display='block'; el('sheet-backdrop').style.display='block'; document.body.style.overflow='hidden'; };
    window.closeSheet = function(){ el('action-sheet').style.display='none';  el('sheet-backdrop').style.display='none';  document.body.style.overflow=''; };

    // API genérica: título, subtítulo y acciones [{label, onClick, danger}]
    window.openActionSheet = function(title, sub, items){
        el('sheet-title').textContent = title || '';
        el('sheet-sub').textContent = sub || '';
        var body = el('sheet-actions');
        body.innerHTML = '';
        (items || []).forEach(function(it){
            body.appendChild(item(it.label, function(){ closeSheet(); it.onClick(); }, it.danger));
        });
        showSheet();
    };
})();
```

**Reglas del patrón**:
- El botón de la fila: `<button class="btn btn--sm" aria-label="Acciones" title="Acciones" onclick="openXSheet(ID)" style="font-size:20px;line-height:1;padding:2px 10px;">⋮</button>`. En tarjetas con imagen, es un círculo blanco superpuesto.
- **Cada lista guarda su dataset** al renderizar (`window.__pagos = data;`) y su opener busca por id comparando como String (`String(x.id) === String(id)`) — los ids llegan como número o string según el backend.
- Las acciones del sheet son **condicionales al estado** del ítem (p. ej. "Publicar" si es borrador, "Ocultar" si está activa; "Completar" solo si está activo).
- La acción destructiva va al final, marcada `danger`, y conserva su `confirm()`.
- El wrapper cierra el sheet ANTES de ejecutar la acción.
- **Refresco tras mutación**: una función central re-carga solo las vistas que estén visibles (`!section.classList.contains('hidden')`), nunca todas a ciegas.
- Etiquetas con emoji al inicio (👁️ Ver / ✏️ Editar / 🚀 Publicar / 🗑️ Eliminar) — legibles y sin dependencia de iconfonts.
- **Gotcha JS**: variables `let/const` de nivel superior de otro `<script>` NO cuelgan de `window`; referencia con identificador directo protegido por `typeof x !== 'undefined'`.

## 5. Tarjetas enriquecidas (grid de entidades)

Para la lista principal de entidades del usuario (sus rifas, sus productos, sus eventos…), reemplaza la tabla por un **grid de tarjetas** con esta anatomía, de arriba a abajo:

1. **Cabecera de imagen 16:9** (`aspect-ratio:16/9; object-fit:cover; loading="lazy"`), con **badge de estado** superpuesto arriba-izquierda y el **⋮ en círculo blanco** arriba-derecha. Fallback si no hay imagen o falla la carga: gradiente oscuro con un emoji temático centrado y `onerror="this.remove()"` en el `<img>`.
2. **Nombre** (bold, `text-overflow:ellipsis`) y ubicación/subtítulo tenue.
3. **Fila de datos**: precio/dato clave + **chip semántico de tiempo** ("¡Hoy!", "Mañana", "Faltan N días" — ámbar si es inminente, azul si falta, gris para vencido/finalizado).
4. **Barra de progreso** con etiqueta "X de Y" y porcentaje; la barra cambia a verde al 100 %.
5. **Pie con el dato de negocio** que el dueño quiere ver de un vistazo (recaudado, ventas), separado por borde punteado.

```css
.card { background:#fff; border:1px solid #eef2f7; border-radius:16px; overflow:hidden;
        box-shadow:0 1px 2px rgba(15,23,42,.06); display:flex; flex-direction:column;
        transition:box-shadow .15s ease, transform .15s ease; }
.card:hover { box-shadow:0 8px 24px rgba(15,23,42,.12); transform:translateY(-2px); }
.card-media { position:relative; aspect-ratio:16/9; background:linear-gradient(135deg,#0f172a,#334155);
              display:flex; align-items:center; justify-content:center; font-size:36px; }
.card-media img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.card-kebab { position:absolute; top:8px; right:8px; z-index:2; width:34px; height:34px; border-radius:50%;
              background:rgba(255,255,255,.94); border:none; cursor:pointer; font-size:18px; font-weight:700;
              box-shadow:0 2px 8px rgba(0,0,0,.22); }
```

Grid: `grid-cols-1` móvil → `sm:grid-cols-2` → `lg:grid-cols-3` escritorio (o su equivalente CSS puro).

## 6. Hojas de selección / formularios emergentes (páginas públicas)

En flujos públicos móviles (compra, selección de ítems), el formulario vive en una **hoja emergente** con resumen fijo ("Tu selección — N · $total"), disparada por un botón flotante contextual ("Ver selección (N)").

**Gotcha crítico de CSS**: `position:fixed` se rompe si un ancestro tiene `backdrop-filter`, `transform` o `filter` (el fixed pasa a ser relativo a ese ancestro). Si la hoja sale recortada o mal posicionada, **muévela a hijo directo de `<body>`**.

## 7. Estados, refresco y datos

- **3 estados por lista** con contenedores dedicados: `#x-loading` ("Cargando…"), `#x-empty` (mensaje + emoji + botón CTA a crear), `#x-grid`. El catch de la carga muestra empty, no un error rojo, cuando "no hay datos" es un resultado válido.
- Tras cualquier mutación (crear/editar/borrar/cambiar estado), refresca **solo** las vistas visibles.
- Fechas con `toLocaleDateString` del locale del proyecto; dinero con el formateador central del proyecto — nunca formateos ad-hoc.

## 8. Accesibilidad y detalles

- Todo botón icónico lleva `aria-label` y `title`.
- El sheet: `role="dialog" aria-modal="true"`; el dropdown de avatar: `aria-haspopup`/`aria-expanded`.
- `:active { transform: scale(.94) }` en FAB y kebabs — feedback táctil barato.
- Tablas anchas en móvil: `display:block; overflow-x:auto; -webkit-overflow-scrolling:touch;` en la tabla, nunca scroll horizontal del body.
- Stat-cards en grids: `min-width:0` en la tarjeta y su contenido, `word-break:break-word` en cifras grandes (los números largos desbordan los grids).

## 9. Orden de implementación probado

Aplica en este orden; cada paso se verifica en navegador (≈390 px y escritorio) y con la suite de tests antes del siguiente:

1. **Auditoría**: mapa de secciones del panel, router (`switchTo` o equivalente), listas con botones de acción, y verificación de qué clases CSS existen realmente.
2. **Tab bar + FAB** + hook de sincronización con el router.
3. **Menú de avatar** (header) + su integración en la hamburguesa móvil.
4. **Componente sheet genérico** + conversión de la primera lista (la más usada) al patrón ⋮.
5. **Conversión del resto de listas**, una a una, agregando el escape XSS y los datasets `window.__*` por el camino.
6. **Sección "Mis X"** (grid de tarjetas enriquecidas de las entidades del usuario) con su entrada en sidebar + tab bar.
7. **Refinamiento de tarjetas** (imagen, chips, progreso, dato de negocio).
8. **Paridad**: portar todo al panel gemelo (los forks casi idénticos se portan por bloques; preserva las divergencias legítimas de cada panel).
9. Logout → página inicial pública (no al login).

## 10. Qué NO hacer

- No dupliques markup "solo móvil" / "solo escritorio" para el mismo contenido.
- No uses clases del framework CSS sin confirmar que sobrevivieron al purgado.
- No dejes botones de acción en fila Y ⋮ a la vez: el ⋮ los reemplaza.
- No refresques todas las secciones tras cada acción — solo las visibles.
- No interpoles texto de usuario en `innerHTML` sin escapar.
- No pruebes solo el camino feliz: abre el sheet, ejecuta una mutación real, verifica BD y refresco de UI.

---

## 11. Sitio público como app nativa (no "página adaptada")

Cuando el proyecto tenga también un sitio público, el móvil se piensa como
pantalla de app Android/iOS, no como la página de escritorio encogida:

- **App-bar compacto (64 px)** en vez del header de 80 px; logo más pequeño.
  Si cambias la altura del header, actualiza TODO lo anclado a ella (menú
  móvil `top:`, secciones `sticky top:`).
- **Hero → banner-card**: la portada de 400-600 px pasa a tarjeta de ~240 px
  con margen y bordes redondeados (como los carruseles de promos de una app).
- **Buscador píldora PEGAJOSO** bajo el app-bar: input compacto (44-48 px,
  `border-radius:9999px`) + botón circular de filtros con badge de activos.
- **Filtros en bottom sheet** con el MISMO DOM: en escritorio los filtros son
  el grid de siempre; en móvil, CSS convierte el contenedor en hoja inferior
  (`position:fixed; bottom:0; transform:translateY(110%)` → `.open`
  `translateY(0)`), con cabecera propia (handle + título + ✕) visible solo
  en móvil. El botón "Buscar" cierra la hoja además de filtrar.
- **Pestañas → chips deslizables** de una línea: `flex-wrap:nowrap;
  overflow-x:auto; scrollbar-width:none`, chips `border-radius:99px`.
- **Footer estilo app**: en móvil se compacta y centra; las columnas de
  enlaces que DUPLICAN la tab bar/hamburguesa se ocultan (`display:none`).
  Queda: marca, dato de confianza, 1-2 enlaces útiles y el copyright en 11px.

## 12. Tab bar del sitio público (compartida)

- Un **partial PHP compartido** (`partials/tabbar.php`): estilos + markup +
  JS en un solo archivo; cada página declara `$tabActive = '…'` e incluye el
  partial antes de `</body>`. Solo móvil (≤768 px); el body recibe
  `padding-bottom` para no tapar contenido.
- La pestaña de CUENTA es consciente de la sesión: sin token → login; con
  token, JS la re-apunta al panel según el rol y la renombra ("Mi Panel").
- **Regla de oro: UNA tab bar por pantalla.** El panel autenticado conserva
  su tab bar propia (secciones del panel) y NUNCA se apila la del sitio
  encima. En el panel, agrega una pestaña "Inicio" que lleve al sitio.
- En pantallas de FLUJO con CTAs inferiores propios (detalle con hoja de
  selección, pago): la tab bar convive subiendo el FAB contextual
  (`bottom: calc(78px + safe-area)`) y dando a la hoja/backdrop un z-index
  MAYOR que la barra para que la cubran al abrirse.
- Las vistas de login/registro de los paneles son pantallas públicas:
  también llevan la tab bar del sitio (ojo: si esa rama PHP no cierra
  `</body>`, el include va al final de la rama, no en el cierre global).

## 13. Header con avatar (sitio público)

- El menú de usuario (avatar + dropdown) vive EN el header, visible también
  en móvil **junto a la hamburguesa**: `[links…] [avatar] [☰]`. En móvil se
  oculta el nombre y el caret (solo el círculo con la inicial).
- **Un botón = una función, sin duplicados**: el avatar abre SU dropdown de
  cuenta; la hamburguesa abre SOLO la navegación (+ login/registro sin
  sesión). Nunca metas la lista de cuenta dentro del menú hamburguesa si el
  avatar ya está en el header — el usuario la vería dos veces.
- En los paneles: `[☰][avatar]` juntos a la izquierda y el título de la
  sección a la derecha; el dropdown se alinea al lado del avatar
  (`left:0` si el avatar quedó a la izquierda — `right:0` lo saca de
  pantalla).

## 14. Gotchas nuevos (aprendidos en producción)

- **`backdrop-filter` en un ancestro rompe `position:fixed`** (el fixed pasa
  a ser relativo a ese ancestro). Aplica también al contenedor STICKY del
  buscador: si la hoja de filtros nace "abierta" o recortada, quita el blur
  del ancestro (usa fondo sólido) o mueve la hoja a hijo directo de `<body>`.
- **Strings multilínea en JS**: un `confirm('…')` con saltos de línea
  LITERALES dentro de la cadena es un error de sintaxis que mata TODO el
  bloque `<script>` (y con él, todos los sheets definidos ahí). Siempre
  `\n` escapado. `php -l` NO lo detecta — solo el navegador o un chequeo
  `new Function(bloque)` en CI.
- **Clases responsive purgadas**: `w-9`, `h-9`, `md:inline`… pueden no
  existir en el CSS compilado aunque "se vean estándar". Para componentes
  nuevos: estilos inline o clases propias; para ocultar por breakpoint,
  media query propia en vez de `md:*`.
- **Paneles gemelos**: el usuario suele probar en el OTRO panel. Toda mejora
  se porta a ambos EN EL MISMO cambio, o se reportará como "no aplicada".
- **Nav escrita a mano por página = deriva**: los menús divergen solos
  (enlaces que solo existen en una página confunden). Unifica el conjunto de
  enlaces y nómbralos por lo que HACEN para el usuario ("Resultados",
  "Verificar boleta"), no por implementación interna.

---

## 15. Página de detalle (producto/rifa) con densidad de app

La ficha de un producto en móvil NO es la versión encogida del escritorio.
El usuario la describió así: "recuadros demasiado grandes, datos muy
separados". Reglas:

- **Galería limitada** (~200-210 px de alto, `object-fit:cover`): la foto
  presenta, no domina la pantalla.
- **Datos clave como chips/franjas de una línea**: precio, fecha y fuente
  (lotería) en una fila de 3 con labels de 9-10 px; el contador regresivo
  como franja compacta (números 17-22 px, cajas de 6-10 px de padding), no
  cuatro tarjetones.
- **Paddings de 12-16 px** en todas las tarjetas (`main .p-6 { padding:14px
  !important }` en el media query), títulos 20-22 px, descripción 13-14 px.
- **El responsable SIEMPRE visible** en la ficha: bloque "Organiza y
  responde" con negocio + persona + calificación (★ 4.8 (12)) y botón a su
  perfil de reputación. La confianza es parte del diseño de la página.

## 16. Hoja de selección interactiva (flujo de compra)

Lo que pidió el usuario, literal: "al seleccionar un número se abre una
ventana emergente y desde allí puedo agregar o buscar más números o
continuar". El patrón:

- La hoja se **ABRE con la primera selección** (móvil y escritorio). Nada de
  arrancar "minimizada para no estorbar": el usuario lo lee como "no
  funcionó".
- Dentro de la hoja se puede TODO sin volver al tablero: **campo "agregar
  otro número"** (con validación: no existe / ya reservado / ya lo tienes),
  **chips removibles** (tap en el número lo quita, con ✕ visible),
  formulario y botón de pagar.
- **"Seguir eligiendo"** como acción secundaria: minimiza la hoja al botón
  flotante contextual ("Ver selección (N) · $total") sin perder nada.
- El botón flotante sube por encima de la tab bar
  (`bottom: calc(78px + safe-area)`) y la hoja/backdrop la cubren (z-index
  mayor) al abrirse.

## 17. Pantalla de éxito al final del flujo

Terminar un flujo redirigiendo a otra página sin explicación se percibe
como "me llevó a otra sesión". Todo flujo con un final (compra, registro,
confirmación) termina en una **pantalla de éxito explícita**:

- ✅ grande + título en pasado ("¡Comprobante recibido!") + el objeto de la
  transacción (números y rifa).
- **Los siguientes pasos numerados (1-2-3)** en lenguaje llano: qué va a
  pasar, por qué canal llega el resultado (WhatsApp/correo) y dónde
  consultarlo después.
- Dos salidas claras: la acción probable ("Ver mis resultados") y el
  escape ("Volver al inicio"). El usuario decide; nunca redirigir por él.

## 18. Tarjetas de confianza (responsable, reputación, reseñas)

En plataformas de dinero entre desconocidos, la confianza es un componente
de UI con tres capas, cada una en su tarjeta:

1. **Quién responde**: nombre legal, documento PARCIAL (`CC ******8721` —
   corroborable sin exponerlo completo: habeas data), canal de contacto
   completo si ya es público (WhatsApp de venta) con botón directo, correo
   enmascarado (`la***@dominio`), y badges de lo que el SISTEMA verificó
   (✓ correo, ✓ celular) separados de lo declarado.
2. **Qué ha hecho**: métricas de hechos no editables (ejecutadas, entregas
   confirmadas por la contraparte, disputas visibles).
3. **Qué opinan**: reseñas SOLO de compradores verificados — la credencial
   es un artefacto que solo existe tras la compra real (el código de la
   boleta pagada). Una reseña por comprador por transacción (reenviar
   actualiza), nombres enmascarados ("Carlos G."), y un interruptor global
   de plataforma para apagar el sistema completo (API 403 + sección
   desaparece).

## 19. Buscador de un solo campo inteligente

Cuando el usuario puede identificarse de varias formas (celular, código de
boleta, código de cuenta), NO le pongas tres campos: un solo input que
CLASIFICA lo escrito (10 dígitos iniciando en 3 → celular; 12 alfanuméricos
→ código de boleta; más largo → código único) con un hint debajo que
explica qué hace cada forma. Cada resultado lleva su **veredicto como
franja de color** (ganador verde con glow / no ganó gris / reprogramada
ámbar / pendiente azul / cancelada roja) — el usuario busca UNA respuesta,
no una tabla.

## 20. Más gotchas de producción (2ª tanda)

- **Selector de ID vs clase utilitaria**: `#mi-boton { display:flex }` le
  GANA a `.hidden { display:none }` por especificidad — el elemento "oculto"
  queda visible (el FAB "(0) · $0" apareció al cargar). Regla:
  `#mi-boton.hidden { display:none !important; }` junto a la definición.
- **Nada de UI puede depender solo de SSE/WebSocket**: mod_deflate bufferea
  `text/event-stream` y la pantalla del evento en vivo quedó EN BLANCO en
  producción. Excluye el stream del gzip (`SetEnvIfNoCase Request_URI
  "sse\.php$" no-gzip dont-vary`) Y arranca la vista + un polling de
  respaldo cuando la página carga con el evento en curso: el SSE es mejora
  progresiva, no requisito.
- **Errores de negocio nunca con estado 5xx detrás de Cloudflare**: CF
  reemplaza el cuerpo del 502/503 por su propia página y el mensaje útil
  ("vincula tu WhatsApp…") jamás llega. Usa 4xx (409/422) para todo lo que
  el usuario deba leer.
- **confirm() no sirve para flujos con datos**: cualquier acción que
  requiera un adjunto o un campo (evidencia de entrega) va en un modal
  propio con preview y botón deshabilitado hasta que el dato exista. Bonus:
  los confirm() nativos bloquean la automatización de pruebas.
- **Fondos de hero SIN texto incrustado**: si la imagen trae su propio
  titular, choca con el texto del slide encima. El texto vive en el HTML
  (localizable, accesible); la imagen solo ambienta. Y autohospedada —
  los servicios de placeholder remotos (picsum) fallan en producción.
- **`only_full_group_by` no infiere dependencia funcional a través de un
  JOIN con COALESCE**: agrega las columnas del join al GROUP BY.
- **La consola de Windows manda cp1252**: un `curl -d` con tildes rompe el
  `json_decode` del servidor (falsos 422). Para probar APIs con UTF-8 usa
  `--data-binary @archivo.json`.
- **El sidebar fijo necesita scroll propio**: si el menú crece (grupos,
  items nuevos) más que el alto de la pantalla y `.sidebar-nav` no tiene
  `overflow-y:auto`, los items de abajo quedan INALCANZABLES en móvil y
  escritorio — y el usuario reporta que las funciones nuevas "no existen"
  (las descubrió con ctrl+scroll). Regla: el contenedor de navegación de
  todo sidebar `position:fixed` lleva `overflow-y:auto; min-height:0` y
  scrollbar fino; el pie (logout) queda fuera del área scrolleable.

## 21. Estado en vivo, nunca afirmaciones estáticas (diagnóstico)

**Regla de honestidad**: una tarjeta del panel NUNCA afirma que un canal
"funciona" con texto fijo. La tarjeta de Comunicaciones decía que el OTP era
"automático, sin configuración" y que el SMS "está apagado" — mientras el
número OTP de WhatsApp estaba VACÍO en la BD (canal realmente no disponible),
el correo iba a Mailpit (capturado, no entregado) y gammu ni estaba instalado.

**Patrón aplicado** (ambos paneles):
- `api/services/SystemStatus.php`: 7 verificaciones REALES — socket SMTP
  (detecta Mailpit 127.0.0.1:1025 = captura), OTP correo (hereda SMTP), OTP
  WhatsApp (setting `otp_whatsapp_number` en BD), motor Evolution (instancias
  y su `connectionStatus` por API), SMS (binario gammu + `SMS_ENABLED`),
  storage escribible, frescura de `logs/cron.log` (<10 min).
- Bloque "🩺 Diagnóstico en vivo" al tope de la tarjeta: chips ✅/⚠️/❌ con
  detalle y CÓMO arreglarlo; botón "Verificar de nuevo".
- Campo editable para `otp_whatsapp_number` en la propia tegla del OTP
  (antes no existía NINGÚN lugar para configurarlo).
- `tools/diagnostico.php` (CLI, `--json`, exit 1 si hay FAIL): correrlo tras
  cada deploy es el "looping" anti-inconsistencias.
- Los textos estáticos quedan solo DESCRIPTIVOS (qué hace el canal, dónde se
  configura); el ESTADO siempre sale del diagnóstico.

Gotcha: `curl_close()` está deprecado en PHP 8.5 (prod) — y una deprecación
impresa corrompe el JSON de la API. Usar `unset($ch)`.
