<?php
require_once __DIR__ . '/_layout.php';
waHeader('Proveedor de IA', 'llm', 'Qué modelo piensa por el agente');
?>
<div class="grid lg:grid-cols-2 gap-4">

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Proveedor principal</h3>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Proveedor</label>
      <select id="llm_proveedor" class="neon-input w-full" onchange="cambiarProveedor()"></select></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">API Key</label>
      <input id="llm_api_key" type="password" class="neon-input w-full" placeholder="••••••••••••">
      <span id="key_estado" class="text-xs text-[var(--text-muted)]"></span></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Modelo</label>
      <?php /* DOS controles a propósito. El desplegable enseña lo descubierto —
               un campo de texto con `datalist` parece vacío aunque haya 36
               modelos detrás, y no hay forma de saber que cargaron. El texto
               libre queda porque los modelos se piden a la API del proveedor: si
               ese catálogo falla, con un desplegable cerrado te quedarías sin
               poder elegir nada. */ ?>
      <select id="modelo_sel" class="neon-input w-full mb-1"
              onchange="if(this.value) llm_modelo.value = this.value;">
        <option value="">— pulsa «Buscar modelos» —</option>
      </select>
      <input id="llm_modelo" class="neon-input w-full"
             placeholder="Identificador del modelo" autocomplete="off">
      <div class="text-xs text-[var(--text-muted)] mt-1">
        Los modelos se piden al proveedor, así que siempre salen los vigentes.
        Si su catálogo no responde, escribe el identificador exacto
        (ej. <code>grok-4</code>, <code>kimi-k2-0905-preview</code>, <code>glm-4.6</code>).</div></div>
    <div class="flex gap-2 flex-wrap">
      <button onclick="probar()" class="neon-btn">Probar conexión</button>
      <button onclick="sincronizar()" class="neon-btn">Buscar modelos</button>
    </div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Respaldo (opcional)</h3>
    <p class="text-xs text-[var(--text-muted)]">
      Si el proveedor principal falla, se usa este <b>solo para ese mensaje</b>. Tu configuración
      no cambia, y en la bitácora queda anotado que se usó el respaldo.
    </p>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Proveedor</label>
      <select id="llm_fallback_proveedor" class="neon-input w-full"></select></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Modelo</label>
      <input id="llm_fallback_modelo" class="neon-input w-full" placeholder="Identificador del modelo"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">API Key del respaldo</label>
      <input id="llm_fallback_api_key" type="password" class="neon-input w-full" placeholder="Vacío = usa la principal">
    </div>

    <h3 class="neon-text pt-2">Ajustes</h3>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Tokens máximos por respuesta</label>
      <input id="llm_max_tokens" type="number" min="256" max="8192" class="neon-input w-full">
      <div class="text-xs text-[var(--text-muted)] mt-1">
        En los modelos que razonan, este tope cubre razonamiento y respuesta juntos.
        Por debajo de 1500 la respuesta puede cortarse a media frase.</div></div>
  </div>

  <div class="neon-card p-4 lg:col-span-2">
    <div class="flex items-center justify-between mb-2">
      <h3 class="neon-text">Modelos descubiertos</h3>
      <button onclick="revisados()" class="neon-btn text-xs">Marcar los nuevos como vistos</button>
    </div>
    <p class="text-xs text-[var(--text-muted)] mb-3">
      Que aparezca un modelo nuevo <b>no cambia nada</b>: tu negocio sigue con el que elegiste.
      Cambiarlo es una decisión tuya, no automática.
    </p>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-[var(--text-muted)] text-left border-b border-[var(--border-color)]">
        <tr><th class="py-2 pr-3">Proveedor</th><th class="pr-3">Modelo</th><th class="pr-3">Contexto</th>
            <th class="pr-3">Visión</th><th>Estado</th></tr>
      </thead>
      <tbody id="tabla_modelos"><tr><td colspan="5" class="py-6 text-center text-[var(--text-muted)]">Cargando…</td></tr></tbody>
    </table>
    </div>
  </div>
</div>

<div class="flex gap-2 mt-3"><button onclick="guardar()" class="neon-btn-success">Guardar</button></div>

<script>
let CFG = {};

async function cargar(){
  const d = await WA.get('config-get');
  CFG = d.config || {};
  const provs = d.config.proveedores || {};
  const opts = Object.entries(provs).map(([k,v]) => `<option value="${k}">${WA.esc(v)}</option>`).join('');
  llm_proveedor.innerHTML = opts;
  llm_fallback_proveedor.innerHTML = '<option value="">— sin respaldo —</option>' + opts;
  llm_proveedor.value = CFG.llm_proveedor || 'anthropic';
  llm_fallback_proveedor.value = CFG.llm_fallback_proveedor || '';
  llm_fallback_modelo.value = CFG.llm_fallback_modelo || '';
  llm_max_tokens.value = CFG.llm_max_tokens || 2048;
  key_estado.textContent = CFG.llm_api_key_configurado
    ? 'Ya hay una clave guardada. Déjalo vacío para conservarla.' : 'Sin clave guardada.';
  await cargarModelos();
  await tabla();
}

/**
 * Cambio de proveedor: se limpia el modelo si venía de otro.
 * Un identificador de OpenAI guardado con Gemini seleccionado se acepta al
 * guardar y solo falla después, al primer mensaje de un cliente.
 */
async function cambiarProveedor(){
  if (llm_proveedor.value !== CFG.llm_proveedor) llm_modelo.value = '';
  await cargarModelos();
}

/** ¿Tenemos con qué preguntarle a ESTE proveedor? */
function claveDisponible(){
  if (llm_api_key.value.trim() !== '') return true;
  // La clave guardada solo sirve para el proveedor al que pertenece.
  return !!CFG.llm_api_key_configurado && CFG.llm_proveedor === llm_proveedor.value;
}

function pintarModelos(ms, mensaje){
  if (mensaje) { modelo_sel.innerHTML = `<option value="">${mensaje}</option>`; return; }
  modelo_sel.innerHTML = ms.length
    ? `<option value="">— ${ms.length} modelos disponibles —</option>` + ms.map(m =>
        `<option value="${WA.esc(m.modelo_id)}"${m.modelo_id === llm_modelo.value ? ' selected' : ''}>`
        + `${WA.esc(m.nombre)}${m.estado === 'nuevo' ? ' ✨ nuevo' : ''}</option>`).join('')
    : '<option value="">— sin modelos: escribe el identificador a mano —</option>';
}

/**
 * Carga los modelos del proveedor elegido.
 *
 * Si ese proveedor todavía no tiene catálogo y hay una clave con la que
 * preguntar, se pide SOLO. Antes había que acordarse de pulsar «Buscar
 * modelos», y quien elegía un proveedor nuevo veía una lista vacía sin saber
 * por qué: elegir proveedor y ver sus modelos debería ser un solo gesto.
 */
async function cargarModelos(autoSincronizar = true){
  pintarModelos([], 'consultando…');
  let d = await WA.get('llm-modelos', {proveedor: llm_proveedor.value});
  let ms = d.modelos || [];

  if (!ms.length && autoSincronizar && claveDisponible()) {
    pintarModelos([], 'consultando al proveedor…');
    const s = await WA.post('llm-sincronizar-modelos', cuerpoSync());
    if (s.ok) {
      d = await WA.get('llm-modelos', {proveedor: llm_proveedor.value});
      ms = d.modelos || [];
    } else if (s.error) {
      WA.aviso(s.error, false);
    }
  }
  pintarModelos(ms);

  if (CFG.llm_modelo && !llm_modelo.value) llm_modelo.value = CFG.llm_modelo;
  if (!ms.length && !claveDisponible()) {
    pintarModelos([], '— pega la API Key de este proveedor —');
  }
}

function cuerpoSync(){
  const body = {proveedor: llm_proveedor.value};
  if (llm_api_key.value.trim() !== '') body.api_key = llm_api_key.value.trim();
  return body;
}

async function tabla(){
  const d = await WA.get('llm-modelos');
  tabla_modelos.innerHTML = (d.modelos || []).length
    ? d.modelos.map(m => `<tr class="border-b border-[var(--border-color)]">
        <td class="py-2 pr-3">${WA.esc(m.proveedor)}</td><td class="pr-3">${WA.esc(m.modelo_id)}</td>
        <td class="pr-3">${m.contexto_max ? Number(m.contexto_max).toLocaleString('es-CO') : '—'}</td>
        <td class="pr-3">${Number(m.soporta_vision) ? '✅' : '—'}</td>
        <td>${m.estado === 'nuevo' ? '<span class="text-amber-400">✨ nuevo</span>' : 'disponible'}</td></tr>`).join('')
    : '<tr><td colspan="5" class="py-6 text-center text-[var(--text-muted)]">Todavía no se han buscado modelos</td></tr>';
}

async function guardar(){
  const body = {
    llm_proveedor: llm_proveedor.value,
    llm_modelo: llm_modelo.value,
    llm_fallback_proveedor: llm_fallback_proveedor.value,
    llm_fallback_modelo: llm_fallback_modelo.value.trim(),
    llm_max_tokens: parseInt(llm_max_tokens.value, 10) || 2048,
  };
  if (llm_api_key.value.trim() !== '')          body.llm_api_key = llm_api_key.value.trim();
  if (llm_fallback_api_key.value.trim() !== '') body.llm_fallback_api_key = llm_fallback_api_key.value.trim();
  const d = await WA.post('config-save', body);
  WA.aviso(d.success ? 'Guardado' : (d.error || 'No se pudo guardar'), !!d.success);
  if (d.success) { llm_api_key.value = ''; llm_fallback_api_key.value = ''; cargar(); }
}

async function probar(){
  const body = {proveedor: llm_proveedor.value, modelo: llm_modelo.value};
  if (llm_api_key.value.trim() !== '') body.api_key = llm_api_key.value.trim();
  WA.aviso('Probando…', true);
  const d = await WA.post('llm-probar', body);
  WA.aviso(d.ok ? (d.modelo_ok ? `Correcto: ${d.proveedor} responde con «${llm_modelo.value}»`
                               : `Clave correcta en ${d.proveedor} (${d.modelos} modelos). Elige un modelo y vuelve a probar.`)
                : (d.error || 'No se pudo conectar'), !!d.ok);
}

async function sincronizar(){
  pintarModelos([], 'consultando al proveedor…');
  // Se manda la clave recién escrita si la hay: así el botón funciona sin
  // tener que guardar primero, que era el orden que nadie adivinaba.
  const d = await WA.post('llm-sincronizar-modelos', cuerpoSync());
  WA.aviso(d.ok ? `${d.total} modelos encontrados (${d.nuevos} nuevos)`
                : (d.error || 'No se pudo consultar. Puedes escribir el modelo a mano.'), !!d.ok);
  await cargarModelos(false);   // ya se acaba de sincronizar: no repetir
  if (d.ok) await tabla();
}

async function revisados(){
  await WA.post('llm-modelos-revisados');
  tabla();
}

cargar();
</script>
<?php waFooter(); ?>
