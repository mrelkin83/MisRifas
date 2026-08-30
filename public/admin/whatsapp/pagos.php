<?php
require_once __DIR__ . '/_layout.php';
waHeader('Pagos y entrega', 'pagos', 'Cómo cobra y cómo entrega el negocio');
?>
<div class="grid lg:grid-cols-2 gap-4">

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Cómo se cobra</h3>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Modo de cobro</label>
      <select id="pago_modo" class="neon-input w-full" onchange="pintarModo()">
        <option value="contra_entrega">Contra entrega (se paga al recibir)</option>
        <option value="wompi">En línea con Wompi</option>
        <option value="manual">Transferencia con comprobante</option>
        <option value="mixto">En línea o transferencia</option>
        <option value="todos">Todas las anteriores (el cliente elige)</option>
      </select></div>
    <div id="explica_modo" class="text-xs p-3 rounded border border-[var(--border-color)]"></div>
    <div id="caja_transferencia">
      <label class="text-xs text-[var(--text-muted)] block mb-1">Datos para transferir (Nequi, Daviplata, Bre-B, banco)</label>
      <textarea id="pago_datos_transferencia" rows="3" maxlength="400" class="neon-input w-full"
                placeholder="Nequi 300 123 4567 · Llave Bre-B @minegocio · Bancolombia ahorros 123-456789-00 · A nombre de Bar Matilde Lina"></textarea>
      <p class="text-xs text-[var(--text-muted)] mt-1">
        Es lo que el agente le dicta al cliente <b>tal cual</b>, al cerrar el pedido. Si lo dejas vacío,
        la IA pide la transferencia pero no puede decir a dónde: revísalo antes de guardar.
      </p>
    </div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Minutos para pagar antes de cancelar</label>
      <input id="pago_expira_minutos" type="number" min="5" max="480" class="neon-input w-full">
      <p class="text-xs text-[var(--text-muted)] mt-1">
        Pasado ese tiempo, un pedido sin pagar se cancela solo y <b>se devuelve el stock</b>.
        Sin esto, un carrito abandonado retendría las últimas unidades para siempre.</p></div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Wompi</h3>
    <div class="p-3 rounded border border-amber-500/40 text-xs text-amber-300">
      Las llaves de <b>pruebas</b> y las de <b>producción</b> no se mezclan nunca. Verifica el
      ambiente antes de guardar: cobrar de verdad con llaves de prueba —o al revés— es el error
      más caro de esta pantalla.
    </div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Ambiente</label>
      <select id="wompi_ambiente" class="neon-input w-full">
        <option value="sandbox">Pruebas (sandbox)</option>
        <option value="produccion">Producción — cobra dinero real</option>
      </select></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Llave pública</label>
      <input id="wompi_public_key" class="neon-input w-full" placeholder="pub_test_…"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Llave privada</label>
      <input id="wompi_private_key" type="password" class="neon-input w-full" placeholder="••••••••••••">
      <span id="est_priv" class="text-xs text-[var(--text-muted)]"></span></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Secreto de eventos (firma del webhook)</label>
      <input id="wompi_events_secret" type="password" class="neon-input w-full" placeholder="••••••••••••">
      <span id="est_ev" class="text-xs text-[var(--text-muted)]"></span></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Secreto de integridad</label>
      <input id="wompi_integrity_secret" type="password" class="neon-input w-full" placeholder="••••••••••••">
      <span id="est_int" class="text-xs text-[var(--text-muted)]"></span></div>
    <p class="text-xs text-[var(--text-muted)]">
      En Wompi hay que registrar la dirección de eventos que aparece en la pantalla de Conexión
      (la que termina en <code>/pago/webhook/…</code>).
    </p>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Entrega</h3>
    <div class="flex gap-4 text-sm">
      <label class="flex items-center gap-2"><input type="checkbox" id="e_domicilio"> Domicilio</label>
      <label class="flex items-center gap-2"><input type="checkbox" id="e_recoger"> Recoger en el local</label>
    </div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Costo del domicilio</label>
      <input id="costo_domicilio" type="number" min="0" step="100" class="neon-input w-full"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Pedido mínimo (sin contar el domicilio)</label>
      <input id="pedido_minimo" type="number" min="0" step="100" class="neon-input w-full"></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Número que recibe los avisos de atención</label>
      <input id="handoff_numero" class="neon-input w-full" placeholder="573001112233">
      <p class="text-xs text-[var(--text-muted)] mt-1">
        Ahí llega el aviso cuando un cliente necesita hablar con una persona.</p></div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">Horario de atención</h3>
    <p class="text-xs text-[var(--text-muted)]">
      Fuera de este horario el agente informa y <b>no toma pedidos</b>. Un día sin horas es un
      día cerrado. Déjalo todo vacío para atender siempre.
    </p>
    <div id="horario" class="space-y-1"></div>
  </div>
</div>

<div class="flex gap-2 mt-3"><button onclick="guardar()" class="neon-btn-success">Guardar</button></div>

<script>
const DIAS = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

function pintarHorario(h){
  horario.innerHTML = DIAS.map((d,i) => `
    <div class="flex items-center gap-2 text-sm">
      <span class="w-24 text-[var(--text-muted)]">${d}</span>
      <input type="time" class="neon-input hd" data-d="${i}" value="${(h[i]&&h[i].desde)||''}">
      <span class="text-[var(--text-muted)]">a</span>
      <input type="time" class="neon-input hh" data-d="${i}" value="${(h[i]&&h[i].hasta)||''}">
    </div>`).join('');
}

function pintarModo(){
  const t = {
    contra_entrega: 'El pedido entra a cocina apenas se confirma y se cobra al entregarlo. No hace falta configurar nada más.',
    wompi: 'Se genera un enlace de pago. El pedido entra a cocina <b>solo cuando el pago queda verificado</b> contra Wompi.',
    manual: 'El cliente transfiere y manda la captura. La IA la lee, pero <b>quien aprueba es una persona</b>: una captura por sí sola nunca confirma un pago.',
    mixto: 'Se ofrece el enlace de Wompi y también se acepta comprobante. Lo verificado automáticamente entra solo; lo demás pasa a revisión.',
    todos: 'La IA le pregunta al cliente si prefiere <b>enlace en línea, transferencia o pagar al recibir</b>, y actúa según lo que elija. Contra entrega el pedido entra a cocina de una; con enlace, cuando el pago quede verificado; con transferencia, cuando una persona apruebe el comprobante.'
  };
  explica_modo.innerHTML = t[pago_modo.value] || '';
  // Los datos de la cuenta solo estorban en los modos donde nadie transfiere.
  caja_transferencia.style.display =
    ['manual','mixto','todos'].includes(pago_modo.value) ? '' : 'none';
}

async function cargar(){
  const d = await WA.get('config-get');
  const c = d.config || {};
  ['pago_modo','wompi_ambiente','wompi_public_key','costo_domicilio','pedido_minimo',
   'handoff_numero','pago_expira_minutos','pago_datos_transferencia'].forEach(k => {
    const el = document.getElementById(k); if (el) el.value = c[k] || '';
  });
  if (!pago_expira_minutos.value) pago_expira_minutos.value = 30;
  const modos = (c.entrega_modos || 'domicilio,recoger').split(',').map(s => s.trim());
  e_domicilio.checked = modos.includes('domicilio');
  e_recoger.checked   = modos.includes('recoger');
  est_priv.textContent = c.wompi_private_key_configurado ? 'Guardada.' : 'Sin guardar.';
  est_ev.textContent   = c.wompi_events_secret_configurado ? 'Guardado.' : 'Sin guardar.';
  est_int.textContent  = c.wompi_integrity_secret_configurado ? 'Guardado.' : 'Sin guardar.';
  let h = {}; try { h = c.horario_atencion ? JSON.parse(c.horario_atencion) : {}; } catch(e) { h = {}; }
  pintarHorario(h);
  pintarModo();
}

async function guardar(){
  const h = {};
  document.querySelectorAll('.hd').forEach(el => {
    const i = el.dataset.d;
    const hasta = document.querySelector(`.hh[data-d="${i}"]`).value;
    if (el.value && hasta) h[i] = {desde: el.value, hasta: hasta};
  });
  const modos = [];
  if (e_domicilio.checked) modos.push('domicilio');
  if (e_recoger.checked)   modos.push('recoger');
  if (!modos.length) { WA.aviso('Elige al menos una forma de entrega', false); return; }

  if (wompi_ambiente.value === 'produccion' &&
      !confirm('Vas a dejar Wompi en PRODUCCIÓN: los cobros serán reales. ¿Continuar?')) return;

  if (['manual','mixto','todos'].includes(pago_modo.value) && !pago_datos_transferencia.value.trim() &&
      !confirm('Vas a aceptar transferencias sin dejar los datos de la cuenta. La IA no va a poder decirle al cliente a dónde transferir. ¿Guardar así?')) return;

  const body = {
    pago_modo: pago_modo.value,
    pago_datos_transferencia: pago_datos_transferencia.value.trim(),
    pago_expira_minutos: parseInt(pago_expira_minutos.value, 10) || 30,
    wompi_ambiente: wompi_ambiente.value,
    wompi_public_key: wompi_public_key.value.trim(),
    entrega_modos: modos.join(','),
    costo_domicilio: parseFloat(costo_domicilio.value) || 0,
    pedido_minimo: parseFloat(pedido_minimo.value) || 0,
    handoff_numero: handoff_numero.value.trim(),
    horario_atencion: h,
  };
  ['wompi_private_key','wompi_events_secret','wompi_integrity_secret'].forEach(k => {
    const v = document.getElementById(k).value.trim();
    if (v !== '') body[k] = v;
  });
  const d = await WA.post('config-save', body);
  WA.aviso(d.success ? 'Guardado' : (d.error || 'No se pudo guardar'), !!d.success);
  if (d.success) {
    ['wompi_private_key','wompi_events_secret','wompi_integrity_secret'].forEach(k => document.getElementById(k).value = '');
    cargar();
  }
}

cargar();
</script>
<?php waFooter(); ?>
