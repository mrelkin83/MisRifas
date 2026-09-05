<?php
require_once __DIR__ . '/_layout.php';
waHeader('Conexión', 'conexion', 'Vincular el número de WhatsApp del negocio');
?>
<div class="grid lg:grid-cols-2 gap-4">

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">1 · Servidor de WhatsApp</h3>
    <p id="gestionado_aviso" class="text-xs hidden" style="color:var(--success-color,#34d399)">
      ✅ El servidor de WhatsApp lo administra la plataforma. Solo pon el nombre de tu
      negocio y tu número — nosotros nos encargamos del resto.
    </p>
    <p id="manual_aviso" class="text-xs text-[var(--text-muted)] hidden">
      El motor se conecta a WhatsApp a través de Evolution API. Estos datos los da quien
      instaló ese servicio.
    </p>
    <div id="campo_url"><label class="text-xs text-[var(--text-muted)] block mb-1">URL de Evolution API</label>
      <input id="evolution_url" class="neon-input w-full" placeholder="http://localhost:8080">
      <span class="text-xs text-[var(--text-muted)]">
        Si Evolution API corre <b>fuera</b> de Docker (Laragon, XAMPP): <code>http://localhost:8080</code>.
        Solo si corre <b>dentro</b> de Docker y comparte red con Evolution: <code>http://evolution:8080</code>.
      </span>
      <button type="button" onclick="probarUrl()" class="neon-btn text-xs mt-1">Probar esta URL</button>
      <span id="url_estado" class="text-xs ml-2"></span></div>
    <div><label id="lbl_instancia" class="text-xs text-[var(--text-muted)] block mb-1">Nombre de la instancia</label>
      <input id="evolution_instancia" class="neon-input w-full" placeholder="mi-negocio">
      <span id="instancia_ayuda" class="text-xs text-[var(--text-muted)] hidden">
        Un identificador corto y único para tu conexión (por ejemplo, el nombre de tu negocio sin espacios).
      </span></div>
    <div id="campo_apikey"><label class="text-xs text-[var(--text-muted)] block mb-1">API Key de Evolution</label>
      <input id="evolution_apikey" type="password" class="neon-input w-full" placeholder="••••••••••••">
      <span id="apikey_estado" class="text-xs text-[var(--text-muted)]"></span></div>
    <div><label class="text-xs text-[var(--text-muted)] block mb-1">Número de WhatsApp</label>
      <input id="numero_whatsapp" class="neon-input w-full" placeholder="573001112233"></div>
    <button onclick="guardar()" class="neon-btn-success">Guardar</button>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">2 · Dirección del webhook</h3>
    <p class="text-xs text-[var(--text-muted)]">
      Es la dirección a la que Evolution manda los mensajes. Lleva un token secreto:
      <b>se muestra una sola vez</b>. Si lo pierdes, genera otro y vuelve a registrarlo.
    </p>
    <div class="flex gap-2">
      <input id="webhook_url" class="neon-input w-full" readonly placeholder="Genera la dirección…">
      <button onclick="copiar()" class="neon-btn">Copiar</button>
    </div>
    <div class="flex gap-2 flex-wrap">
      <button onclick="generar()" class="neon-btn">Generar dirección</button>
      <button onclick="registrar()" class="neon-btn-success">Registrarla en Evolution</button>
    </div>
    <p id="webhook_pago" class="text-xs text-[var(--text-muted)]"></p>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">3 · Instancias y vinculación (hasta 5 números)</h3>
    <p class="text-xs text-[var(--text-muted)]">
      Hasta 5 números vinculados a la vez. Con la <b>rotación</b> encendida, el
      emisor va TURNÁNDOSE entre los números conectados en cada tanda de envíos
      — no siempre sale del mismo número (mitiga el riesgo de baneo de
      WhatsApp). Apagada, envía siempre la instancia marcada como activa. Los
      mensajes entrantes se leen desde todas (el webhook se hereda solo).
    </p>
    <label class="flex items-center gap-2 text-sm" style="cursor:pointer;">
      <input type="checkbox" id="inst_rotacion" onchange="instRotacion(this.checked)">
      🔄 Rotar el n&uacute;mero emisor entre las instancias conectadas (anti-baneo)
    </label>
    <div id="inst_lista" class="text-sm">Cargando instancias…</div>
    <button type="button" id="inst_nueva" onclick="instCrear()" class="neon-btn text-xs">＋ Nueva instancia</button>
    <div id="estado_conexion" class="text-sm">Consultando…</div>
    <div id="qr_zona" class="text-center"></div>
    <div class="flex gap-2 flex-wrap">
      <button onclick="pedirQr()" class="neon-btn">Mostrar código QR</button>
      <button onclick="verEstado()" class="neon-btn">Revisar estado</button>
      <button onclick="desconectar()" class="neon-btn-danger">Desvincular</button>
    </div>
  </div>

  <div class="neon-card p-4 space-y-3">
    <h3 class="neon-text">4 · Respuestas automáticas (el bot)</h3>
    <p class="text-xs text-[var(--text-muted)]">
      Apagado: NADIE recibe respuestas automáticas de la IA — pero los códigos de
      verificación (OTP), los comandos SI/NO de pagos y las notificaciones salientes
      SIGUEN funcionando con normalidad. Enciéndelo solo cuando el proveedor de IA
      esté configurado y probado.
    </p>
    <label class="flex items-center gap-2 text-sm" style="cursor:pointer;">
      <input type="checkbox" id="activo"> 🤖 El bot responde automáticamente los mensajes de WhatsApp
    </label>
    <div id="requisitos" class="text-xs space-y-1"></div>
    <button onclick="guardar()" class="neon-btn-success">Guardar</button>
  </div>

  <div class="neon-card p-4 space-y-3 lg:col-span-2">
    <h3 class="neon-text">5 · Proteger la cuota</h3>
    <p class="text-xs text-[var(--text-muted)]">
      Cada mensaje que atiende el motor es una llamada al proveedor de IA, y se paga.
      Quien escriba cien veces seguidas gastaría cien llamadas. Este techo corta la ráfaga:
      el cliente recibe <b>un</b> aviso y, pasada la ventana, se le vuelve a atender solo.
    </p>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">Mensajes permitidos</label>
        <input id="limite_mensajes" type="number" min="0" max="500" class="neon-input w-full">
        <span class="text-xs text-[var(--text-muted)]">0 = sin límite (no recomendado)</span></div>
      <div><label class="text-xs text-[var(--text-muted)] block mb-1">En cuántos minutos</label>
        <input id="limite_ventana_minutos" type="number" min="1" max="1440" class="neon-input w-full"></div>
    </div>
    <p id="limite_resumen" class="text-xs"></p>
    <div id="cupo_plan" class="text-xs"></div>
    <button onclick="guardarLimites()" class="neon-btn-success">Guardar</button>
  </div>
</div>

<script>
let CFG = {};

async function cargar(){
  const d = await WA.get('config-get');
  CFG = d.config || {};
  ['evolution_url','evolution_instancia','numero_whatsapp'].forEach(k => {
    const el = document.getElementById(k); if (el) el.value = CFG[k] || '';
  });
  activo.checked = String(CFG.activo) === '1';
  apikey_estado.textContent = CFG.evolution_apikey_configurado
    ? 'Ya hay una clave guardada. Déjalo vacío para conservarla.' : 'Sin clave guardada.';

  // Modo gestionado (SaaS con Evolution de plataforma): el negocio no configura
  // URL ni API Key. Se ocultan esos campos y se simplifica el resto.
  const gestionado = !!CFG.evolution_gestionado;
  document.getElementById('gestionado_aviso').classList.toggle('hidden', !gestionado);
  document.getElementById('manual_aviso').classList.toggle('hidden', gestionado);
  document.getElementById('campo_url').classList.toggle('hidden', gestionado);
  document.getElementById('campo_apikey').classList.toggle('hidden', gestionado);
  document.getElementById('instancia_ayuda').classList.toggle('hidden', !gestionado);
  document.getElementById('lbl_instancia').textContent = gestionado ? 'Nombre de tu negocio' : 'Nombre de la instancia';

  const faltan = [];
  if (!CFG.llm_proveedor || !CFG.llm_modelo) faltan.push('Falta elegir el proveedor de IA y su modelo.');
  if (!CFG.llm_api_key_configurado)          faltan.push('Falta la API Key del proveedor de IA.');
  if (!CFG.mesa_ancla_ok)                    faltan.push('No hay mesa-ancla de mostrador: los pedidos no se podrán crear.');
  requisitos.innerHTML = faltan.length
    ? faltan.map(f => `<div class="text-amber-400">⚠️ ${f}</div>`).join('')
    : '<div class="text-emerald-400">✅ Todo lo necesario está configurado.</div>';

  cargarInstancias();
  limite_mensajes.value = CFG.limite_mensajes ?? 15;
  limite_ventana_minutos.value = CFG.limite_ventana_minutos || 5;
  resumenLimite();
  cupo();
  verEstado();
}

/** Traduce los dos números a una frase, que es como se piensa el ajuste. */
function resumenLimite(){
  const n = parseInt(limite_mensajes.value, 10) || 0;
  const m = parseInt(limite_ventana_minutos.value, 10) || 5;
  limite_resumen.innerHTML = n <= 0
    ? '<span class="text-amber-400">⚠️ Sin techo: un solo número puede agotar tu cuota del proveedor de IA.</span>'
    : `<span class="text-[var(--text-muted)]">A partir del mensaje <b>${n + 1}</b> en <b>${m}</b> minuto${m === 1 ? '' : 's'}, `
      + 'ese teléfono deja de consultar a la IA hasta que pase la ventana.</span>';
}
limite_mensajes.addEventListener('input', resumenLimite);
limite_ventana_minutos.addEventListener('input', resumenLimite);

/** Cupo de conversaciones del plan. Informativo aquí; el corte está en el motor. */
async function cupo(){
  const d = await WA.get('limites-estado');
  if (!d.success || !d.techo) {
    cupo_plan.innerHTML = '<span class="text-[var(--text-muted)]">Tu plan no limita el número de conversaciones.</span>';
    return;
  }
  const pct = Math.min(100, Math.round(d.usadas * 100 / d.techo));
  const color = pct >= 100 ? 'rose-400' : (pct >= 80 ? 'amber-400' : 'emerald-400');
  cupo_plan.innerHTML = `<span class="text-${color}">Conversaciones de este mes: <b>${d.usadas}</b> de ${d.techo}</span>`
    + (pct >= 100 ? ' — <b>agotado</b>: los números nuevos reciben un aviso y no se les atiende. Las conversaciones ya abiertas siguen.'
                  : (pct >= 80 ? ' — te estás acercando al tope de tu plan.' : ''));
}

async function guardarLimites(){
  const d = await WA.post('config-save', {
    limite_mensajes: Math.max(0, parseInt(limite_mensajes.value, 10) || 0),
    limite_ventana_minutos: Math.max(1, parseInt(limite_ventana_minutos.value, 10) || 5),
  });
  WA.aviso(d.success ? 'Guardado' : (d.error || 'No se pudo guardar'), !!d.success);
}

async function guardar(){
  const body = {
    activo: activo.checked ? 1 : 0,
    evolution_url: evolution_url.value.trim(),
    evolution_instancia: evolution_instancia.value.trim(),
    numero_whatsapp: numero_whatsapp.value.trim(),
  };
  if (evolution_apikey.value.trim() !== '') body.evolution_apikey = evolution_apikey.value.trim();
  const d = await WA.post('config-save', body);
  WA.aviso(d.success ? 'Guardado' : (d.error || 'No se pudo guardar'), !!d.success);
  if (d.success) { evolution_apikey.value = ''; cargar(); }
}

async function generar(){
  if (!confirm('Se generará una dirección nueva y la anterior dejará de funcionar. ¿Continuar?')) return;
  const d = await WA.post('webhook-url');
  if (!d.success) { WA.aviso(d.error || 'No se pudo generar', false); return; }
  webhook_url.value = d.url;
  webhook_pago.textContent = 'Dirección para los avisos de pago: ' + d.url_pago;
  WA.aviso(d.aviso, true);
}

function copiar(){
  if (!webhook_url.value) return;
  navigator.clipboard.writeText(webhook_url.value);
  WA.aviso('Dirección copiada', true);
}

async function registrar(){
  if (!webhook_url.value) { WA.aviso('Genera primero la dirección', false); return; }
  const d = await WA.post('conexion-webhook-registrar', {url: webhook_url.value});
  WA.aviso(d.success ? 'Registrada en Evolution' : (d.error || 'No se pudo registrar'), !!d.success);
}

async function probarUrl(){
  url_estado.textContent = 'probando…';
  url_estado.className = 'text-xs ml-2 text-[var(--text-muted)]';
  const d = await WA.post('conexion-probar-url', {url: evolution_url.value.trim()});
  url_estado.textContent = d.ok ? ('✅ responde — Evolution ' + (d.version || '')) : ('❌ ' + (d.error || 'no responde'));
  url_estado.className = 'text-xs ml-2 ' + (d.ok ? 'text-emerald-400' : 'text-rose-400');
}

async function pedirQr(){
  qr_zona.innerHTML = '<span class="text-[var(--text-muted)] text-sm">Pidiendo el código… '
                    + '(la primera vez tarda unos segundos)</span>';
  const d = await WA.post('conexion-qr');
  if (!d.success || !d.qr) { qr_zona.innerHTML = ''; WA.aviso(d.error || 'No se pudo obtener el QR', false); return; }
  const src = d.qr.startsWith('data:') ? d.qr : ('data:image/png;base64,' + d.qr);
  qr_zona.innerHTML = `<img src="${src}" alt="Código QR" class="mx-auto max-w-[260px]">
    <p class="text-xs text-[var(--text-muted)] mt-2">Escanéalo desde WhatsApp → Dispositivos vinculados.</p>`;
  setTimeout(verEstado, 12000);
}

async function verEstado(){
  const d = await WA.get('conexion-estado');
  const map = {conectado:['✅','emerald-400','Conectado'], qr:['⏳','amber-400','Esperando el escaneo del QR'],
               desconectado:['⚪','text-[var(--text-muted)]','Desconectado'], error:['❌','rose-400','Error de conexión']};
  const [ico, color, txt] = map[d.estado] || map.desconectado;
  estado_conexion.innerHTML = `<span class="text-${color}">${ico} ${txt}</span>`
    + (d.numero ? ` <span class="text-[var(--text-muted)]">— ${WA.esc(d.numero)}</span>` : '')
    + (d.mensaje && d.estado === 'error' ? `<div class="text-xs text-[var(--text-muted)]">${WA.esc(d.mensaje)}</div>` : '');
  if (d.estado === 'conectado') qr_zona.innerHTML = '';
}

async function cargarInstancias(){
  const d = await WA.get('instancias');
  const box = document.getElementById('inst_lista');
  if (!d.success) { box.innerHTML = '<span class="text-xs text-rose-400">' + WA.esc(d.error || 'No se pudo listar') + '</span>'; return; }
  window.__instMax = d.max;
  const rotChk = document.getElementById('inst_rotacion');
  if (rotChk) rotChk.checked = !!d.rotacion;
  box.innerHTML = (d.instancias || []).map(i => {
    const chip = i.estado === 'open' ? '✅ conectada' : (i.estado === 'connecting' ? '⏳ esperando QR' : '⚪ desconectada');
    const esActiva = i.name === d.activa;
    return '<div class="flex items-center gap-2" style="flex-wrap:wrap;padding:6px 0;border-bottom:1px solid var(--border-color);">'
      + '<b>' + WA.esc(i.name) + '</b>'
      + '<span class="text-xs">' + chip + '</span>'
      + (i.numero ? '<span class="text-xs text-[var(--text-muted)]">+' + WA.esc(i.numero) + '</span>' : '')
      + (i.webhook ? '' : '<span class="text-xs text-amber-400">⚠ sin webhook</span>')
      + (esActiva
          ? '<span class="text-xs text-emerald-400 font-bold">★ ACTIVA</span>'
          : '<button type="button" class="neon-btn text-xs" onclick="instUsar(\'' + i.name + '\')">Usar</button>'
            + '<button type="button" class="neon-btn-danger text-xs" onclick="instEliminar(\'' + i.name + '\')">🗑</button>')
      + '<button type="button" class="neon-btn text-xs" onclick="instQr(\'' + i.name + '\')">Mostrar QR</button>'
      + (i.estado === 'open'
          ? '<button type="button" class="neon-btn-danger text-xs" onclick="instDesvincular(\'' + i.name + '\')">Desvincular</button>'
          : '')
      + '</div>';
  }).join('') || '<span class="text-xs text-[var(--text-muted)]">Sin instancias: crea la primera.</span>';
  document.getElementById('inst_nueva').style.display = (d.instancias || []).length >= d.max ? 'none' : '';
}

function instPintarQr(qr){
  if (!qr) { WA.aviso('La instancia no entregó QR (¿ya está conectada?)', false); return; }
  const src = String(qr).startsWith('data:') ? qr : ('data:image/png;base64,' + qr);
  qr_zona.innerHTML = '<img src="' + src + '" alt="Código QR" class="mx-auto max-w-[260px]">'
    + '<p class="text-xs text-[var(--text-muted)] mt-2">Escanéalo desde WhatsApp → Dispositivos vinculados.</p>';
  setTimeout(cargarInstancias, 12000);
}

async function instRotacion(on){
  const d = await WA.post('instancias-rotacion', { enabled: on ? 1 : 0 });
  WA.aviso(d.success ? (d.rotacion ? 'Rotación ACTIVADA: cada tanda sale por un número distinto' : 'Rotación desactivada')
                     : (d.error || 'No se pudo guardar'), !!d.success);
  if (!d.success) document.getElementById('inst_rotacion').checked = !on;
}

async function instCrear(){
  const d = await WA.post('instancia-crear');
  if (!d.success) { WA.aviso(d.error || 'No se pudo crear', false); return; }
  WA.aviso('Instancia ' + d.name + ' creada', true);
  if (d.qr) instPintarQr(d.qr);
  cargarInstancias();
}

async function instQr(name){
  qr_zona.innerHTML = '<span class="text-[var(--text-muted)] text-sm">Pidiendo el código…</span>';
  const d = await WA.post('instancia-qr', { name });
  if (!d.success) { qr_zona.innerHTML = ''; WA.aviso(d.error || 'Sin QR', false); return; }
  instPintarQr(d.qr);
}

async function instUsar(name){
  const d = await WA.post('instancia-usar', { name });
  WA.aviso(d.success ? ('Instancia activa: ' + name) : (d.error || 'No se pudo activar'), !!d.success);
  cargarInstancias(); verEstado();
}

async function instDesvincular(name){
  if (!confirm('Se desvinculará el teléfono de ' + name + '. La instancia queda creada y puedes volver a vincularla con el QR. ¿Continuar?')) return;
  const d = await WA.post('instancia-desvincular', { name });
  WA.aviso(d.success ? ('Número desvinculado de ' + name) : (d.error || 'No se pudo desvincular'), !!d.success);
  cargarInstancias(); verEstado();
}

async function instEliminar(name){
  if (!confirm('¿Eliminar la instancia ' + name + '? El número quedará desvinculado de la plataforma.')) return;
  const d = await WA.post('instancia-eliminar', { name });
  WA.aviso(d.success ? 'Instancia eliminada' : (d.error || 'No se pudo eliminar'), !!d.success);
  cargarInstancias();
}

async function desconectar(){
  if (!confirm('Se desvinculará el teléfono de WhatsApp. ¿Continuar?')) return;
  const d = await WA.post('conexion-desconectar');
  WA.aviso(d.success ? 'Desvinculado' : 'No se pudo desvincular', !!d.success);
  verEstado();
}

cargar();
</script>
<?php waFooter(); ?>
