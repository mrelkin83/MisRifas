<?php
require_once __DIR__ . '/_layout.php';

use ElkinLinan\WhatsappAiEngine\Media\SttManager;
use ElkinLinan\WhatsappAiEngine\Providers\LlmProviderManager;
waHeader('Voz e imágenes', 'voz', 'Notas de voz, respuestas habladas y lectura de fotos');

/* Los tres módulos son MIXTOS: cada uno acepta proveedor de pago o servidor
   propio open source. El de pago pide API Key; el propio, URL. La pantalla
   enseña solo el campo que aplica. */
$provStt = SttManager::PROVEEDORES;
// Visión: cualquiera del catálogo del motor sirve (los compatibles con OpenAI
// aceptan imágenes) más el servidor propio. Se listan todos para no tener que
// tocar esta pantalla cuando el motor gane un proveedor.
$provVision = LlmProviderManager::PROVEEDORES;
?>
<div class="grid lg:grid-cols-3 gap-4">

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">🎧 Escuchar notas de voz</h3>
    <p id="stt_aviso_gestionado" class="text-xs hidden" style="color:var(--success-color,#34d399)">
      ✅ Servidor propio (open source) de la plataforma — gratis y ya listo. El bot entiende
      las notas de voz sin que configures nada. Si prefieres, elige otro proveedor abajo.
    </p>
    <div id="stt_manual" class="space-y-3">
      <p class="text-xs text-[var(--text-muted)]">
        Sin esto configurado, a quien mande un audio se le pide amablemente que lo escriba.
      </p>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Proveedor</label>
        <select id="stt_proveedor" class="neon-input w-full" onchange="pintarStt(); cargarModelos('stt')">
          <option value="">— desactivado —</option>
          <?php foreach ($provStt as $k => $v): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div id="caja_stt_key"><label class="text-xs text-[var(--text-muted)] block mb-1">API Key</label>
        <input id="stt_api_key" type="password" class="neon-input w-full" placeholder="••••••••••••">
        <span id="stt_estado" class="text-xs text-[var(--text-muted)]"></span></div>
      <div id="caja_stt_url" style="display:none">
        <label class="text-xs text-[var(--text-muted)] block mb-1">URL del servidor</label>
        <input id="stt_url" class="neon-input w-full" placeholder="http://localhost:8000">
        <p class="text-xs text-[var(--text-muted)] mt-1">
          Un servidor compatible con OpenAI: <code>speaches</code> (antes faster-whisper-server)
          o similar. Gratis y en tu propio servidor.</p>
      </div>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Modelo</label>
        <select id="stt_modelo_sel" class="neon-input w-full" onchange="stt_modelo.value=this.value"></select>
        <input id="stt_modelo" class="neon-input w-full mt-1" placeholder="whisper-large-v3"></div>
      <div class="flex gap-2">
        <button onclick="cargarModelos('stt', true)" class="neon-btn">Buscar modelos</button>
        <button onclick="probarStt()" class="neon-btn">Probar</button>
      </div>
    </div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">🗣️ Responder con voz</h3>
    <p id="tts_aviso_gestionado" class="text-xs hidden" style="color:var(--success-color,#34d399)">
      ✅ Servidor propio (open source) de la plataforma — gratis y ya listo. El bot puede
      contestar con voz; arriba defines cuándo. Si prefieres, elige otro proveedor abajo.
    </p>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Cuándo hablar</label>
      <select id="tts_modo" class="neon-input w-full">
        <option value="espejo">Como el cliente (audio si él manda audio) — recomendado</option>
        <option value="nunca">Nunca: siempre texto</option>
        <option value="siempre">Siempre con voz</option>
        <option value="texto_y_audio">Texto y voz a la vez</option>
      </select></div>
    <div id="tts_manual" class="space-y-3">
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Proveedor</label>
        <select id="tts_proveedor" class="neon-input w-full" onchange="pintarTts(); cargarVoces()">
          <option value="">— desactivado —</option>
          <option value="elevenlabs">ElevenLabs (de pago)</option>
          <option value="openai">OpenAI (de pago)</option>
          <option value="piper">Piper — open source, servidor propio</option>
        </select></div>
      <div id="caja_tts_key"><label class="text-xs text-[var(--text-muted)] block mb-1">API Key</label>
        <input id="tts_api_key" type="password" class="neon-input w-full" placeholder="••••••••••••">
        <span id="tts_estado" class="text-xs text-[var(--text-muted)]"></span></div>
      <div id="caja_tts_url" style="display:none">
        <label class="text-xs text-[var(--text-muted)] block mb-1">URL del servidor de voz</label>
        <input id="tts_url" class="neon-input w-full" placeholder="http://localhost:5005">
        <p class="text-xs text-[var(--text-muted)] mt-1">
          Un contenedor de <code>piper1-gpl</code> con una voz en español
          (p. ej. <code>es_MX-claude-high</code>). Sin API key: gratis y en tu propio servidor.</p>
      </div>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Voz</label>
        <select id="tts_voice_sel" class="neon-input w-full" onchange="tts_voice_id.value=this.value"></select>
        <input id="tts_voice_id" class="neon-input w-full mt-1" placeholder="Identificador de la voz"></div>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Modelo</label>
        <input id="tts_modelo" class="neon-input w-full" placeholder="eleven_multilingual_v2"></div>
      <div class="flex gap-2">
        <button onclick="cargarVoces()" class="neon-btn">Buscar voces</button>
        <button onclick="probarTts()" class="neon-btn">Probar</button>
      </div>
    </div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">👁️ Ver imágenes</h3>
    <p id="vision_aviso_gestionado" class="text-xs hidden" style="color:var(--success-color,#34d399)">
      ✅ Servidor propio (open source) de la plataforma — gratis y ya listo. El bot entiende
      las fotos y lee los comprobantes sin que configures nada. Si prefieres, elige otro proveedor abajo.
    </p>
    <div id="vision_manual" class="space-y-3">
      <p class="text-xs text-[var(--text-muted)]">
        Sirve para entender fotos y para leer los comprobantes de pago. Si lo dejas vacío se usa
        el proveedor de IA principal, siempre que sepa ver imágenes.
      </p>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Proveedor</label>
        <select id="vision_proveedor" class="neon-input w-full" onchange="pintarVision(); cargarModelos('vision')">
          <option value="">— usar el proveedor principal —</option>
          <?php foreach ($provVision as $k => $v): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
          <?php endforeach; ?>
          <option value="local">Servidor propio (open source)</option>
        </select></div>
      <div id="caja_vision_key"><label class="text-xs text-[var(--text-muted)] block mb-1">API Key</label>
        <input id="vision_api_key" type="password" class="neon-input w-full" placeholder="Vacío = la del proveedor principal"></div>
      <div id="caja_vision_url" style="display:none">
        <label class="text-xs text-[var(--text-muted)] block mb-1">URL del servidor</label>
        <input id="vision_url" class="neon-input w-full" placeholder="http://localhost:11434">
        <p class="text-xs text-[var(--text-muted)] mt-1">
          <code>Ollama</code>, LM Studio o vLLM. En CPU el único que rinde es
          <code>moondream</code>; con GPU, <code>qwen2.5vl</code> o <code>llava</code>.</p>
      </div>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Modelo</label>
        <select id="vision_modelo_sel" class="neon-input w-full" onchange="vision_modelo.value=this.value"></select>
        <input id="vision_modelo" class="neon-input w-full mt-1" placeholder="Vacío = el modelo principal"></div>
      <div class="flex gap-2">
        <button onclick="cargarModelos('vision', true)" class="neon-btn">Buscar modelos</button>
        <button onclick="probarVision()" class="neon-btn">Probar</button>
      </div>
    </div>
    <div class="pt-2 border-t border-[var(--border-color)]">
      <label class="text-xs text-[var(--text-muted)] block mb-1">Días que se guardan los audios y fotos</label>
      <input id="retencion_media_dias" type="number" min="1" max="365" class="neon-input w-full">
      <p class="text-xs text-[var(--text-muted)] mt-1">
        Cuenta para el cupo de almacenamiento del plan. Los comprobantes de pago se guardan
        el doble de tiempo porque son la prueba de una transacción.</p>
    </div>
  </div>
</div>

<div class="flex gap-2 mt-3"><button onclick="guardar()" class="neon-btn-success">Guardar</button></div>

<script>
async function cargar(){
  const d = await WA.get('config-get');
  const c = d.config || {};
  ['stt_proveedor','stt_modelo','stt_url','tts_proveedor','tts_voice_id','tts_modelo','tts_modo','tts_url',
   'vision_proveedor','vision_modelo','vision_url','retencion_media_dias'].forEach(k => {
    const el = document.getElementById(k); if (el) el.value = c[k] || '';
  });
  if (!retencion_media_dias.value) retencion_media_dias.value = 7;
  if (!tts_modo.value) tts_modo.value = 'espejo';
  stt_estado.textContent = c.stt_api_key_configurado ? 'Clave guardada.' : 'Sin clave.';
  tts_estado.textContent = c.tts_api_key_configurado ? 'Clave guardada.' : 'Sin clave.';

  // ¿La plataforma ofrece un servidor propio (open source) para cada uno? Se
  // guarda para que pintar*() muestre el aviso y oculte la URL de infraestructura.
  window.WA_GESTION = { stt: !!c.stt_gestionado, tts: !!c.tts_gestionado, vision: !!c.vision_gestionado };

  // Opción PREDETERMINADA = servidor propio (open source), cuando la plataforma lo
  // ofrece y el negocio no eligió otro proveedor. Queda seleccionado por defecto,
  // pero es un selector normal: el cliente puede cambiar a un proveedor de pago.
  if (!c.stt_proveedor    && c.stt_gestionado)    stt_proveedor.value    = 'local';
  if (!c.tts_proveedor    && c.tts_gestionado)    tts_proveedor.value    = 'piper';
  if (!c.vision_proveedor && c.vision_gestionado) vision_proveedor.value = 'local';

  pintarStt(); pintarTts(); pintarVision();
}

// Los proveedores autoalojados llevan URL en vez de API key: se enseña solo el
// campo que aplica, para que nadie pegue una clave donde va una dirección.
// Con el servidor propio de la PLATAFORMA, la URL es infraestructura del operador:
// se oculta el campo y se muestra un aviso; el cliente no configura nada, pero
// puede elegir otro proveedor arriba. Con servidor propio pero SIN plataforma
// (instalación de un solo negocio), sí se pide la URL.
function pintarStt(){
  const propio = stt_proveedor.value === 'local';
  const gestion = !!(window.WA_GESTION && window.WA_GESTION.stt);
  caja_stt_key.style.display = propio ? 'none' : '';
  caja_stt_url.style.display = (propio && !gestion) ? '' : 'none';
  document.getElementById('stt_aviso_gestionado').classList.toggle('hidden', !(propio && gestion));
}
function pintarTts(){
  const propio = tts_proveedor.value === 'piper';
  const gestion = !!(window.WA_GESTION && window.WA_GESTION.tts);
  caja_tts_key.style.display = propio ? 'none' : '';
  caja_tts_url.style.display = (propio && !gestion) ? '' : 'none';
  document.getElementById('tts_aviso_gestionado').classList.toggle('hidden', !(propio && gestion));
}
function pintarVision(){
  const propio = vision_proveedor.value === 'local';
  const gestion = !!(window.WA_GESTION && window.WA_GESTION.vision);
  caja_vision_key.style.display = propio ? 'none' : '';
  caja_vision_url.style.display = (propio && !gestion) ? '' : 'none';
  document.getElementById('vision_aviso_gestionado').classList.toggle('hidden', !(propio && gestion));
}

async function guardar(){
  const body = {
    stt_proveedor: stt_proveedor.value, stt_modelo: stt_modelo.value.trim(), stt_url: stt_url.value.trim(),
    tts_proveedor: tts_proveedor.value, tts_voice_id: tts_voice_id.value.trim(),
    tts_modelo: tts_modelo.value.trim(), tts_modo: tts_modo.value, tts_url: tts_url.value.trim(),
    vision_proveedor: vision_proveedor.value, vision_modelo: vision_modelo.value.trim(),
    vision_url: vision_url.value.trim(),
    retencion_media_dias: parseInt(retencion_media_dias.value, 10) || 7,
  };
  ['stt_api_key','tts_api_key','vision_api_key'].forEach(k => {
    const v = document.getElementById(k).value.trim();
    if (v !== '') body[k] = v;
  });
  const d = await WA.post('config-save', body);
  WA.aviso(d.success ? 'Guardado' : (d.error || 'No se pudo guardar'), !!d.success);
  if (d.success) { ['stt_api_key','tts_api_key','vision_api_key'].forEach(k => document.getElementById(k).value = ''); cargar(); }
}

/* Modelos de transcripción y visión. Se piden con lo que hay EN PANTALLA
   (proveedor, clave y URL recién escritos): obligar a guardar antes de poder
   mirar los modelos es la trampa que ya costó tiempo en el buscador del agente. */
async function cargarModelos(cual, avisar){
  const prov = document.getElementById(cual + '_proveedor').value;
  const sel  = document.getElementById(cual + '_modelo_sel');
  if (!prov) { sel.innerHTML = '<option value="">— escribe el modelo —</option>'; return; }
  if (avisar) sel.innerHTML = '<option value="">Buscando…</option>';
  const body = {
    proveedor: prov,
    api_key: (document.getElementById(cual + '_api_key') || {}).value || '',
    url: (document.getElementById(cual + '_url') || {}).value || '',
  };
  const d = await WA.post(cual + '-modelos', body);
  const lista = (d && d.modelos) || [];
  sel.innerHTML = '<option value="">— escribe el modelo —</option>'
    + lista.map(m => `<option value="${WA.esc(m)}">${WA.esc(m)}</option>`).join('');
  const actual = document.getElementById(cual + '_modelo').value;
  if (actual) sel.value = actual;
  if (avisar) {
    WA.aviso(lista.length ? (lista.length + ' modelos encontrados')
                          : ((d && d.nota) || 'No se pudieron listar: escribe el modelo a mano'),
             lista.length > 0);
  }
}

async function cargarVoces(){
  const d = await WA.get('tts-voces');
  tts_voice_sel.innerHTML = '<option value="">— escribe el identificador —</option>'
    + (d.voces || []).map(v => `<option value="${WA.esc(v.id)}">${WA.esc(v.nombre)}</option>`).join('');
  if (tts_voice_id.value) tts_voice_sel.value = tts_voice_id.value;
}

async function probarStt(){
  const d = await WA.post('stt-probar');
  WA.aviso(d.ok ? 'La transcripción responde correctamente' : (d.error || 'No responde'), !!d.ok);
}

async function probarTts(){
  const d = await WA.post('tts-probar');
  WA.aviso(d.ok ? `Voz generada (${d.bytes} bytes)` : (d.error || 'No se pudo generar'), !!d.ok);
}

async function probarVision(){
  const d = await WA.post('vision-probar');
  WA.aviso(d.ok ? 'El modelo vio la imagen de prueba' : (d.error || 'No se pudo analizar'), !!d.ok);
}

cargar().then(() => { cargarVoces(); cargarModelos('stt'); cargarModelos('vision'); });
</script>
<?php waFooter(); ?>
