<?php
require_once __DIR__ . '/_layout.php';
waHeader('Bitácora', 'logs', 'Todo lo que hizo el motor');
?>
<div class="neon-card p-4">
  <div class="flex items-center gap-2 mb-3 flex-wrap">
    <select id="tipo" class="neon-input" onchange="cargar()">
      <option value="">Todos los tipos</option>
      <option value="error">Errores</option>
      <option value="tool">Herramientas</option>
      <option value="llm">Proveedor de IA</option>
      <option value="pago">Pagos</option>
      <option value="handoff">Transferencias</option>
      <option value="mensaje">Mensajes</option>
      <option value="webhook">Webhooks</option>
      <option value="config">Configuración</option>
    </select>
    <button onclick="cargar()" class="neon-btn">Actualizar</button>
    <span class="text-xs text-[var(--text-muted)] ml-auto">
      Las claves y credenciales nunca se guardan aquí: se sustituyen por <code>[oculto]</code> antes de escribir.
    </span>
  </div>
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-[var(--text-muted)] text-left border-b border-[var(--border-color)]">
      <tr><th class="py-2 pr-3">Fecha</th><th class="pr-3">Tipo</th><th class="pr-3">Descripción</th>
          <th class="pr-3">Conversación</th><th></th></tr>
    </thead>
    <tbody id="tabla"><tr><td colspan="5" class="py-6 text-center text-[var(--text-muted)]">Cargando…</td></tr></tbody>
  </table>
  </div>
</div>

<script>
const COLOR = {error:'text-rose-400', pago:'text-emerald-400', handoff:'text-amber-400',
               tool:'text-sky-400', llm:'text-violet-400'};

async function cargar(){
  const d = await WA.get('eventos', tipo.value ? {tipo: tipo.value} : {});
  const es = d.eventos || [];
  tabla.innerHTML = es.length ? es.map(e => `
    <tr class="border-b border-[var(--border-color)]">
      <td class="py-2 pr-3 text-xs whitespace-nowrap">${e.created_at}</td>
      <td class="pr-3"><span class="${COLOR[e.tipo]||''}">${WA.esc(e.tipo)}</span></td>
      <td class="pr-3">${WA.esc(e.descripcion)}</td>
      <td class="pr-3">${e.conversacion_id
        ? `<a class="text-[var(--primary-color)]" href="<?= WA_BASE ?>/public/admin/whatsapp/conversaciones.php">#${e.conversacion_id}</a>`
        : '—'}</td>
      <td class="text-right">${e.payload
        ? `<button class="neon-btn text-xs" onclick="ver(${e.id})">Detalle</button>` : ''}</td>
    </tr>
    <tr id="p${e.id}" style="display:none"><td colspan="5" class="pb-3">
      <pre class="text-xs p-2 rounded bg-black/30 overflow-x-auto">${WA.esc(
        (() => { try { return JSON.stringify(JSON.parse(e.payload), null, 2); } catch(x) { return e.payload; } })()
      )}</pre></td></tr>`).join('')
    : '<tr><td colspan="5" class="py-6 text-center text-[var(--text-muted)]">Sin eventos</td></tr>';
}

function ver(id){
  const f = document.getElementById('p' + id);
  f.style.display = f.style.display === 'none' ? 'table-row' : 'none';
}

cargar();
</script>
<?php waFooter(); ?>
