<?php
require_once __DIR__ . '/_layout.php';
waHeader('Panel', 'dashboard', 'Cómo va el canal de WhatsApp');
?>
<div class="flex items-end gap-2 flex-wrap mb-2">
    <div><label class="block text-xs text-[var(--text-muted)] mb-1">Desde</label>
        <input type="date" id="desde" class="neon-input" value="<?= date('Y-m-01') ?>"></div>
    <div><label class="block text-xs text-[var(--text-muted)] mb-1">Hasta</label>
        <input type="date" id="hasta" class="neon-input" value="<?= date('Y-m-d') ?>"></div>
    <button onclick="cargar()" class="neon-btn">Actualizar</button>
</div>

<div id="avisos" class="space-y-2"></div>

<div id="tarjetas" class="grid grid-cols-2 md:grid-cols-4 gap-3"></div>

<div class="neon-card p-4">
    <h3 class="neon-text mb-3">Uso de modelos</h3>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-[var(--text-muted)] text-left border-b border-[var(--border-color)]">
            <tr><th class="py-2 pr-3">Proveedor</th><th class="pr-3">Modelo</th>
                <th class="pr-3 text-right">Mensajes</th><th class="pr-3 text-right">Tokens entrada</th>
                <th class="pr-3 text-right">Tokens salida</th><th class="text-right">Latencia media</th></tr>
        </thead>
        <tbody id="modelos"><tr><td colspan="6" class="py-6 text-center text-[var(--text-muted)]">Cargando…</td></tr></tbody>
    </table>
    </div>
    <p class="text-xs text-[var(--text-muted)] mt-3">
        Se muestra el consumo en tokens, no un costo en pesos: cada proveedor tiene su propia tarifa
        y una cifra inventada sería peor que ninguna.
    </p>
</div>

<script>
const TARJETAS = [
  ['conversaciones','Conversaciones',''], ['mensajes','Mensajes',''],
  ['pedidos','Pedidos',''], ['ventas','Ventas','dinero'],
  ['pagos_ok','Pagos confirmados','ok'], ['pagos_pendientes','Pagos pendientes','warn'],
  ['pagos_revision','Pagos por revisar','warn'], ['pagos_rechazados','Pagos rechazados','bad'],
  ['transferencias','Pasadas a una persona',''], ['errores','Errores','bad'],
  ['esperando_humano','Esperando atención','warn'],
];

async function cargar(){
  const d = await WA.get('dashboard', {desde: desde.value, hasta: hasta.value});
  if (!d.success) { WA.aviso(d.error || 'No se pudo cargar', false); return; }
  const m = d.metricas;

  tarjetas.innerHTML = TARJETAS.map(([k,label,tipo]) => {
    const v = tipo === 'dinero' ? WA.dinero(m[k]) : (m[k] ?? 0);
    let color = 'text-white';
    if (tipo === 'ok'   && m[k] > 0) color = 'text-emerald-400';
    if (tipo === 'warn' && m[k] > 0) color = 'text-amber-400';
    if (tipo === 'bad'  && m[k] > 0) color = 'text-rose-400';
    return `<div class="neon-card p-4">
      <div class="text-xs text-[var(--text-muted)]">${label}</div>
      <div class="text-2xl font-bold ${color}">${v}</div></div>`;
  }).join('');

  modelos.innerHTML = (d.uso_modelos || []).length
    ? d.uso_modelos.map(r => `<tr class="border-b border-[var(--border-color)]">
        <td class="py-2 pr-3">${WA.esc(r.proveedor)}</td><td class="pr-3">${WA.esc(r.modelo)}</td>
        <td class="pr-3 text-right">${r.mensajes}</td>
        <td class="pr-3 text-right">${Number(r.tokens_in).toLocaleString('es-CO')}</td>
        <td class="pr-3 text-right">${Number(r.tokens_out).toLocaleString('es-CO')}</td>
        <td class="text-right">${r.latencia_media ? r.latencia_media + ' ms' : '—'}</td></tr>`).join('')
    : '<tr><td colspan="6" class="py-6 text-center text-[var(--text-muted)]">Sin actividad todavía</td></tr>';

  const av = [];
  if (m.pagos_revision > 0) av.push(['amber', `Hay ${m.pagos_revision} pago(s) esperando que alguien los revise.`,
                                     '<?= WA_BASE ?>/public/admin/whatsapp/pagos.php']);
  if (m.esperando_humano > 0) av.push(['amber', `${m.esperando_humano} conversación(es) esperan a una persona.`,
                                       '<?= WA_BASE ?>/public/admin/whatsapp/conversaciones.php']);
  if (m.errores > 0) av.push(['rose', `Se registraron ${m.errores} errores en el periodo.`,
                              '<?= WA_BASE ?>/public/admin/whatsapp/logs.php']);
  avisos.innerHTML = av.map(([c,t,u]) =>
    `<a href="${u}" class="block neon-card p-3 border-l-4 border-${c}-500 text-sm">${t} →</a>`).join('');
}

async function estado(){
  const e = await WA.get('conexion-estado');
  if (e.estado !== 'conectado') {
    avisos.insertAdjacentHTML('afterbegin',
      `<a href="<?= WA_BASE ?>/public/admin/whatsapp/conexion.php" class="block neon-card p-3 border-l-4 border-rose-500 text-sm">
         WhatsApp no está conectado. El motor no puede recibir ni enviar mensajes. →</a>`);
  }
}

cargar().then(estado);
</script>
<?php waFooter(); ?>
