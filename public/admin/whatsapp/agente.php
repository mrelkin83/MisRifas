<?php
require_once __DIR__ . '/_layout.php';
waHeader('Agente', 'agente', 'Cómo se presenta y qué puede hacer');
?>
<div class="grid lg:grid-cols-2 gap-4">
  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Quién atiende</h3>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Nombre</label>
      <input id="nombre" class="neon-input w-full" maxlength="60" placeholder="Ej: Sofi"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Rol</label>
      <input id="rol" class="neon-input w-full" maxlength="200" placeholder="Asesora del restaurante"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Objetivo</label>
      <textarea id="objetivo" rows="2" class="neon-input w-full" placeholder="Atender clientes, mostrar la carta y tomar pedidos"></textarea></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Forma de ser</label>
      <input id="personalidad" class="neon-input w-full" maxlength="200" placeholder="Amable, cercana y directa"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Idioma</label>
      <select id="idioma" class="neon-input w-full"><option value="es">Español</option><option value="en">Inglés</option></select></div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Indicaciones del negocio</h3>
    <textarea id="instrucciones" rows="8" class="neon-input w-full"
      placeholder="Ej: si preguntan por sedes, decimos que solo tenemos la del centro. Los domingos no hay parrilla."></textarea>
    <p class="text-xs text-[var(--text-muted)]">
      Lo que escribas aquí se suma a las reglas del sistema, <b>no las reemplaza</b>. El agente
      seguirá sin poder inventar precios, vender sin existencias ni dar un pago por confirmado,
      aunque se lo pidas aquí.
    </p>
    <h3 class="neon-text pt-2">Mensajes fijos</h3>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Cuando algo falla</label>
      <input id="mensaje_error" class="neon-input w-full" placeholder="(por defecto) En este momento no puedo completar la operación…"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Fuera de horario</label>
      <input id="mensaje_fuera_horario" class="neon-input w-full" placeholder="(por defecto) En este momento estamos cerrados…"></div>
  </div>

  <div class="neon-card p-4 lg:col-span-2">
    <h3 class="neon-text mb-2">Qué puede hacer</h3>
    <p class="text-xs text-[var(--text-muted)] mb-3">
      Sin nada marcado, el agente usa todas las que su plan permita. Las marcadas como
      <i>siempre</i> no se pueden apagar.
    </p>
    <div id="herramientas" class="grid md:grid-cols-2 gap-2"></div>
  </div>
</div>

<div class="flex gap-2 mt-3">
  <button onclick="guardar()" class="neon-btn-success">Guardar agente</button>
</div>

<script>
let TOOLS = [];

async function cargar(){
  const d = await WA.get('agente-get');
  const a = d.agente || {};
  ['nombre','rol','objetivo','personalidad','idioma','instrucciones','mensaje_error','mensaje_fuera_horario']
    .forEach(k => { const el = document.getElementById(k); if (el) el.value = a[k] || ''; });

  TOOLS = d.herramientas_disponibles || [];
  let marcadas = null;
  try { marcadas = a.herramientas ? JSON.parse(a.herramientas) : null; } catch(e) { marcadas = null; }

  herramientas.innerHTML = TOOLS.map(t => {
    const chk = (marcadas === null || marcadas.includes(t.nombre) || t.siempre) ? 'checked' : '';
    const dis = t.siempre ? 'disabled' : '';
    return `<label class="flex items-start gap-2 text-sm p-2 rounded border border-[var(--border-color)]">
      <input type="checkbox" class="mt-1 tool" value="${t.nombre}" ${chk} ${dis}>
      <span><b>${WA.esc(t.nombre)}</b>${t.siempre ? ' <span class="text-xs text-emerald-400">(siempre)</span>' : ''}
      <br><span class="text-xs text-[var(--text-muted)]">${WA.esc(t.descripcion)}</span></span></label>`;
  }).join('');
}

async function guardar(){
  const seleccionadas = Array.from(document.querySelectorAll('.tool'))
    .filter(c => c.checked || c.disabled).map(c => c.value);
  const todas = seleccionadas.length === TOOLS.length;
  const d = await WA.post('agente-save', {
    nombre: nombre.value.trim(), rol: rol.value.trim(), objetivo: objetivo.value.trim(),
    personalidad: personalidad.value.trim(), idioma: idioma.value,
    instrucciones: instrucciones.value.trim(),
    mensaje_error: mensaje_error.value.trim(),
    mensaje_fuera_horario: mensaje_fuera_horario.value.trim(),
    // null = todas: así, si mañana se añade una herramienta nueva, el negocio
    // la hereda en vez de quedarse con la lista congelada de hoy.
    herramientas: todas ? null : seleccionadas,
  });
  WA.aviso(d.success ? 'Agente guardado' : (d.error || 'No se pudo guardar'), !!d.success);
}

cargar();
</script>
<?php waFooter(); ?>
