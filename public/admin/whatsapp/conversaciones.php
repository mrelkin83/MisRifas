<?php
require_once __DIR__ . '/_layout.php';
waHeader('Conversaciones', 'conversaciones', 'Bandeja de chats y atención humana');
?>
<div class="grid lg:grid-cols-3 gap-4">

  <div class="neon-card p-4 lg:col-span-1">
    <div class="flex items-center gap-2 mb-3">
      <select id="filtro" class="neon-input w-full" onchange="cargar()">
        <option value="">Todas</option>
        <option value="HUMANO_ATENDIENDO">Esperando a una persona</option>
        <option value="IA_ACTIVA">Atendidas por la IA</option>
        <option value="IA_PAUSADA">Con la IA pausada</option>
        <option value="CERRADA">Cerradas</option>
      </select>
      <button onclick="cargar()" class="neon-btn">↻</button>
    </div>
    <div id="lista" class="space-y-1 max-h-[600px] overflow-y-auto">
      <div class="text-[var(--text-muted)] text-sm">Cargando…</div>
    </div>
  </div>

  <div class="neon-card p-4 lg:col-span-2 flex flex-col" style="min-height:620px">
    <div id="cabecera" class="pb-3 mb-3 border-b border-[var(--border-color)] text-sm text-[var(--text-muted)]">
      Elige una conversación de la lista.
    </div>
    <div id="hilo" class="flex-1 overflow-y-auto space-y-2 pr-1"></div>
    <div id="responder" class="pt-3 mt-3 border-t border-[var(--border-color)]" style="display:none;">
      <div class="flex gap-2">
        <input id="texto" class="neon-input w-full" placeholder="Escribe tu respuesta…"
               onkeydown="if(event.key==='Enter')enviar()">
        <button onclick="enviar()" class="neon-btn-success">Enviar</button>
      </div>
      <p class="text-xs text-[var(--text-muted)] mt-2">
        Al responder tú, la IA deja de contestar en este chat automáticamente: dos voces a la vez
        confunden al cliente. Pulsa «Devolver a la IA» cuando termines.
      </p>
    </div>
  </div>
</div>

<script>
let ACTUAL = null;

async function cargar(){
  const d = await WA.get('conversaciones', filtro.value ? {estado: filtro.value} : {});
  const cs = d.conversaciones || [];
  const icono = {IA_ACTIVA:'🤖', HUMANO_ATENDIENDO:'🙋', IA_PAUSADA:'⏸️', CERRADA:'✔️'};
  lista.innerHTML = cs.length ? cs.map(c => `
    <button onclick="abrir(${c.id})" class="w-full text-left p-2 rounded border neon-transition
      ${ACTUAL===c.id ? 'border-[var(--primary-color)]' : 'border-[var(--border-color)]'}">
      <div class="flex justify-between text-sm">
        <span>${icono[c.estado]||''} ${WA.esc(c.nombre_contacto || c.telefono)}</span>
        <span class="text-xs text-[var(--text-muted)]">${c.mensajes}</span>
      </div>
      <div class="text-xs text-[var(--text-muted)] truncate">${WA.esc((c.ultimo||'').substr(0,70))}</div>
    </button>`).join('')
    : '<div class="text-[var(--text-muted)] text-sm py-6 text-center">Sin conversaciones</div>';
}

async function abrir(id){
  ACTUAL = id;
  const d = await WA.get('conversacion-detalle', {id});
  const c = d.conversacion || {};
  const enHumano = c.estado === 'HUMANO_ATENDIENDO';

  cabecera.innerHTML = `<div class="flex justify-between items-center flex-wrap gap-2">
      <div><b class="text-white">${WA.esc(c.nombre_contacto || c.telefono)}</b>
        <span class="text-xs">· ${WA.esc(c.telefono)} · ${WA.esc(c.estado)}</span></div>
      <div class="flex gap-2">
        ${enHumano
          ? `<button onclick="liberar()" class="neon-btn text-xs">Devolver a la IA</button>`
          : `<button onclick="tomar()" class="neon-btn text-xs">Atender yo</button>
             <button onclick="pausar()" class="neon-btn text-xs">Pausar la IA</button>`}
      </div></div>`;

  hilo.innerHTML = (d.mensajes || []).map(m => {
    const mio = m.direccion === 'saliente';
    const txt = m.transcripcion || m.contenido || (m.media_ruta ? '[archivo adjunto]' : '');
    const meta = [m.tipo !== 'texto' ? m.tipo : null, m.modelo || null,
                  (m.tokens_entrada||m.tokens_salida) ? `${m.tokens_entrada}/${m.tokens_salida} tk` : null]
                 .filter(Boolean).join(' · ');
    return `<div class="flex ${mio ? 'justify-end' : 'justify-start'}">
      <div class="max-w-[80%] p-2 rounded-lg text-sm ${mio ? 'bg-[var(--primary-color)]/15' : 'bg-white/5'}">
        <div>${WA.esc(txt).replace(/\n/g,'<br>')}</div>
        <div class="text-[10px] text-[var(--text-muted)] mt-1">${m.created_at}${meta ? ' · ' + WA.esc(meta) : ''}</div>
      </div></div>`;
  }).join('');
  hilo.scrollTop = hilo.scrollHeight;
  responder.style.display = (c.estado === 'CERRADA') ? 'none' : 'block';
  cargar();
}

async function tomar(){ await WA.post('conversacion-tomar', {id: ACTUAL}); abrir(ACTUAL); }
async function liberar(){ await WA.post('conversacion-liberar', {id: ACTUAL}); abrir(ACTUAL); }
async function pausar(){ await WA.post('conversacion-pausar', {id: ACTUAL}); abrir(ACTUAL); }

async function enviar(){
  const t = texto.value.trim();
  if (!t || !ACTUAL) return;
  texto.value = '';
  const d = await WA.post('conversacion-responder', {id: ACTUAL, texto: t});
  if (!d.success) WA.aviso(d.error || 'No se pudo enviar', false);
  abrir(ACTUAL);
}

cargar();
setInterval(() => { if (!document.hidden) cargar(); }, 20000);
</script>
<?php waFooter(); ?>
