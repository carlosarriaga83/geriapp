// cd-logs.js — Activity logs
// Extracted from cuidados.php (lines 14111)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// LOGS (Registro de actividad)
// ═══════════════════════════════════════════════
const LOGS_API = BASE + '/api/logs.php';
let _logsPage = 1;

async function loadLogs(page) {
    if (page) _logsPage = page;
    const params = new URLSearchParams({ page: _logsPage, limit: 50 });
    const modulo = $('#cfgLogModulo')?.value;
    const estado = $('#cfgLogEstado')?.value;
    const busqueda = $('#cfgLogSearch')?.value;
    initAppDateTextInput($('#cfgLogDesde'));
    initAppDateTextInput($('#cfgLogHasta'));
    const desde = appDateInputIso($('#cfgLogDesde'), { message:t('config_logs_from') + ': ' + appDatePlaceholder() });
    const hasta = appDateInputIso($('#cfgLogHasta'), { message:t('config_logs_to') + ': ' + appDatePlaceholder() });
    if (desde === null || hasta === null) return;
    if (modulo) params.set('modulo', modulo);
    if (estado) params.set('estado', estado);
    if (busqueda) params.set('busqueda', busqueda);
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);
    const list = $('#cfgLogsList');
    if (list) list.innerHTML = skeleton(3);
    try {
        const data = await api(LOGS_API + '?' + params);
        const logs = data.logs || [];
        renderLogsList(logs);
        renderLogsPagination(data.page || 1, data.pages || 1);
    } catch(e) {
        if (list) list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">' + t('error_load_logs') + '</p>';
    }
}

function renderLogsList(logs) {
    const list = $('#cfgLogsList');
    if (!list) return;
    if (!logs.length) { list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">' + t('logs_empty') + '</p>'; return; }
    list.innerHTML = logs.map((l, i) => {
        const dtFmt = l.creado_at ? fmtDateTime(l.creado_at) : null;
        const dtStr = dtFmt ? dtFmt.date + ' ' + dtFmt.time : '';
        const statusCls = l.estado === 'ok' ? 'active' : '';
        return `<div class="cd-cfg-user-card" data-log-idx="${i}" style="cursor:pointer">
            <div class="cd-cfg-user-info" style="min-width:0">
                <strong style="font-size:0.8125rem">${esc(l.accion || '—')}</strong>
                <span style="font-size:0.75rem">${esc(l.usuario_nombre || l.usuario_email || '—')} · ${esc(l.modulo || '')} · ${esc(dtStr)}</span>
            </div>
            <span class="cd-cfg-user-status ${statusCls}">${esc(l.estado || '—')}</span>
        </div>`;
    }).join('');
    list._logs = logs;
}

function renderLogsPagination(current, total) {
    const pag = $('#cfgLogsPag');
    if (!pag) return;
    if (total <= 1) { pag.innerHTML = ''; return; }
    let html = '';
    if (current > 1) html += `<button class="cd-btn-submit cd-btn-secondary cd-log-pag" data-page="${current-1}" style="padding:4px 12px;font-size:0.8125rem">← Anterior</button>`;
    html += `<span style="font-size:0.8125rem;color:var(--cd-text-muted);align-self:center;white-space:nowrap;padding:0 4px">Página ${current} de ${total}</span>`;
    if (current < total) html += `<button class="cd-btn-submit cd-btn-secondary cd-log-pag" data-page="${current+1}" style="padding:4px 12px;font-size:0.8125rem">Siguiente →</button>`;
    pag.innerHTML = html;
}

$('#cfgLogsPag')?.addEventListener('click', e => {
    const btn = e.target.closest('.cd-log-pag');
    if (btn) loadLogs(parseInt(btn.dataset.page));
});

$('#cfgLogFilter')?.addEventListener('click', () => loadLogs(1));

$('#cfgLogsList')?.addEventListener('click', e => {
    const card = e.target.closest('.cd-cfg-user-card');
    if (!card) return;
    const idx = parseInt(card.dataset.logIdx);
    const logs = $('#cfgLogsList')?._logs || [];
    const log = logs[idx];
    if (log) openLogDetailSidebar(log);
});

function openLogDetailSidebar(l) {
    const dtFmt = l.creado_at ? fmtDateTime(l.creado_at) : null;
    const dtStr = dtFmt ? dtFmt.date + ' ' + dtFmt.time : '—';

    // ── Parse detalle: JSON (new) vs pipe-delimited (legacy) ──
    let det = null;
    let detRaw = l.detalle || '';
    try { det = JSON.parse(detRaw); } catch(_) { det = null; }

    // ── Action badge color ──
    const accionColor = (l.accion || '').includes('eliminar') ? 'var(--cd-danger)'
        : (l.accion || '').includes('crear') ? 'var(--cd-success)' : 'var(--cd-warning)';
    const accionLabel = {
        'cuidado_crear':   t('log_act_create'),
        'cuidado_editar':  t('log_act_edit'),
        'cuidado_eliminar':t('log_act_delete')
    }[l.accion] || l.accion || '—';

    // ── Section 1: Detalle del registro ──
    let body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('log_record_detail')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>${t('log_date')}</th><td>${esc(dtStr)}</td></tr>
            <tr><th>${t('log_action')}</th><td><span style="color:${accionColor};font-weight:600">${esc(accionLabel)}</span></td></tr>
            <tr><th>${t('log_module')}</th><td>${esc(l.modulo || '—')}</td></tr>
            <tr><th>${t('log_status')}</th><td><span style="color:${l.estado==='ok'?'var(--cd-success)':'var(--cd-danger)'};font-weight:600">${esc(l.estado || '—')}</span></td></tr>
            <tr><th>${t('log_user')}</th><td>${esc(l.usuario_nombre || '—')}</td></tr>
            <tr><th>Email</th><td>${esc(l.usuario_email || '—')}</td></tr>
            <tr><th>${t('log_institution')}</th><td>${esc(l.institucion_nombre || '—')}</td></tr>
        </tbody></table>
    </div>`;

    // ── Section 2: Detalle del cuidado (if JSON) ──
    if (det && typeof det === 'object') {
        const catLabel = CAT_LABELS[det.categoria] || det.categoria || '—';
        body += `<div class="cd-sidebar-section">
            <div class="cd-sidebar-section-title">${t('log_care_detail')}</div>
            <table class="cd-sb-vitals-table"><tbody>
                <tr><th>${t('log_category')}</th><td><span class="cd-log-cat-badge">${esc(catLabel)}</span></td></tr>
                <tr><th>${t('log_resident')}</th><td>${esc(det.residente_nombre || ('ID ' + det.residente_id))} <span style="color:var(--cd-text-muted);font-size:0.75rem">(ID: ${det.residente_id})</span></td></tr>`;
        if (det.fecha) body += `<tr><th>${t('log_care_date')}</th><td>${esc(fmtDate(det.fecha))}${det.hora ? ' ' + esc(det.hora) : ''}</td></tr>`;
        if (det.registro_id) body += `<tr><th>${t('log_record_id')}</th><td>${det.registro_id}</td></tr>`;
        if (det.observaciones) body += `<tr><th>${t('log_observations')}</th><td style="word-break:break-word;white-space:pre-wrap">${esc(det.observaciones)}</td></tr>`;
        body += `</tbody></table>`;

        // Datos registrados
        if (det.datos && typeof det.datos === 'object' && Object.keys(det.datos).length) {
            body += `<div style="margin-top:10px">
                <div style="font-size:0.75rem;font-weight:600;color:var(--cd-text-muted);text-transform:uppercase;margin-bottom:4px">${t('log_recorded_data')}</div>
                <table class="cd-sb-vitals-table"><tbody>`;
            for (const [k, v] of Object.entries(det.datos)) {
                const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                body += `<tr><th style="font-size:0.75rem">${esc(label)}</th><td style="font-size:0.8125rem">${esc(String(v))}</td></tr>`;
            }
            body += `</tbody></table></div>`;
        }

        // Cambios (for edits)
        if (det.cambios && typeof det.cambios === 'object') {
            body += `<div style="margin-top:10px">
                <div style="font-size:0.75rem;font-weight:600;color:var(--cd-text-muted);text-transform:uppercase;margin-bottom:4px">${t('log_changes')}</div>
                <table class="cd-sb-vitals-table"><tbody>`;
            for (const [k, v] of Object.entries(det.cambios)) {
                const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                body += `<tr><th style="font-size:0.75rem">${esc(label)}</th><td style="font-size:0.8125rem">${esc(String(v))}</td></tr>`;
            }
            body += `</tbody></table></div>`;
        }
        body += `</div>`;
    }

    // ── Section 3: Detalles técnicos ──
    let detDisplay = detRaw;
    if (det) {
        // Fallback readable summary for the raw field
        detDisplay = `${det.categoria || ''} | ${det.residente_nombre || ''} | Reg: ${det.registro_id || '—'}`;
    } else {
        // Legacy pipe-delimited: enrich resident name
        detDisplay = detDisplay.replace(/Residente ID:\s*(\d+)/gi, (m, id) => {
            const res = RESIDENTES.find(r => r.id === parseInt(id));
            return res ? `Residente: ${res.nombre} (ID: ${id})` : m;
        });
    }
    body += `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">${t('log_technical')}</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>IP</th><td>${esc(l.ip || '—')}</td></tr>
            <tr><th>User Agent</th><td style="word-break:break-all;font-size:0.75rem">${esc(l.user_agent || '—')}</td></tr>
            <tr><th>${t('log_detail')}</th><td style="word-break:break-all;font-size:0.75rem">${esc(detDisplay)}</td></tr>
            <tr><th>ID</th><td>${l.id || '—'}</td></tr>
        </tbody></table>
    </div>`;

    const actions = `<button class="cd-btn-close-sidebar" id="cdLogClose">${t('btn_close')}</button>`;
    openSidebar(t('sidebar_log') + ' #' + (l.id || ''), body, actions);
    $('#cdLogClose')?.addEventListener('click', closeSidebar);
}

// ── Export CSV auditoría ───────────────────────────────────
$('#cfgLogExport')?.addEventListener('click', () => {
    const params = new URLSearchParams({ action: 'export' });
    const modulo = $('#cfgLogModulo')?.value;
    const estado = $('#cfgLogEstado')?.value;
    const busqueda = $('#cfgLogSearch')?.value;
    const desde = appDateInputIso($('#cfgLogDesde'), { message:t('config_logs_from') + ': ' + appDatePlaceholder() });
    const hasta = appDateInputIso($('#cfgLogHasta'), { message:t('config_logs_to') + ': ' + appDatePlaceholder() });
    if (desde === null || hasta === null) return;
    if (modulo) params.set('modulo', modulo);
    if (estado) params.set('estado', estado);
    if (busqueda) params.set('busqueda', busqueda);
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);
    window.location.href = LOGS_API + '?' + params;
});

// ═══════════════════════════════════════════════
// CATÁLOGO DE TAGS (acciones registradas en auditoría)
// Convención de nombres: objeto_accion[_detalle]
//   objeto  = entidad afectada (sesion, usuario, residente, cuidado, …)
//   accion  = verbo en infinitivo (crear, actualizar, eliminar, probar, …)
//   detalle = (opcional) sub-tipo o cualificador (manual, automatico, …)
// ═══════════════════════════════════════════════
const LOG_TAGS_CATALOG = [
    // ── Auth / Sesión / Password ──────────────────────────────────────
    { mod: 'Auth', tag: 'sesion_iniciar', sev: 'ok',
      desc: 'Inicio de sesión exitoso de un usuario.',
      trigger: 'index.php registra el evento al validar credenciales y activar la sesión.' },
    { mod: 'Auth', tag: 'sesion_cerrar', sev: 'ok',
      desc: 'Cierre de sesión del usuario.',
      trigger: 'auth/logout.php (botón "Salir" o expiración manual de sesión).' },
    { mod: 'Auth', tag: 'password_restablecer', sev: 'ok',
      desc: 'Restablecimiento de contraseña vía enlace por email.',
      trigger: 'reset-password.php cuando el usuario completa el flujo con un token válido.' },
    { mod: 'Auth', tag: 'password_cambiar', sev: 'warn',
      desc: 'Cambio de contraseña por el propio usuario o por un administrador.',
      trigger: 'POST api/change_password.php o api/personal.php?action=cambiar_password.' },

    // ── Usuarios / Personal ───────────────────────────────────────────
    { mod: 'Personal', tag: 'usuario_crear', sev: 'ok',
      desc: 'Alta de un nuevo usuario en la institución.',
      trigger: 'POST api/personal.php?action=crear_usuario o aceptación de invitación en register.php.' },
    { mod: 'Personal', tag: 'usuario_actualizar', sev: 'warn',
      desc: 'Edición de datos de perfil de un usuario (nombre, email, teléfono).',
      trigger: 'PUT api/personal.php?id=N desde la sidebar de edición.' },
    { mod: 'Personal', tag: 'usuario_eliminar', sev: 'error',
      desc: 'Baja definitiva de un usuario (no soft-delete).',
      trigger: 'DELETE api/personal.php?id=N por un administrador.' },
    { mod: 'Personal', tag: 'usuario_cambiar_rol', sev: 'warn',
      desc: 'Cambio de rol (admin / cuidador / médico / familiar).',
      trigger: 'POST api/personal.php?action=cambiar_rol.' },
    { mod: 'Personal', tag: 'usuario_toggle_estado', sev: 'warn',
      desc: 'Activar o desactivar la cuenta de un usuario.',
      trigger: 'POST api/personal.php?action=toggle_estado.' },
    { mod: 'Personal', tag: 'usuario_sync_residentes', sev: 'ok',
      desc: 'Actualización del set de residentes vinculados a un usuario.',
      trigger: 'POST api/personal.php?action=sync_residentes desde la tarjeta expandida del usuario.' },
    { mod: 'Personal', tag: 'usuario_actualizar_perfil', sev: 'ok',
      desc: 'El propio usuario actualiza datos básicos de su perfil.',
      trigger: 'POST api/personal.php?action=update_profile desde Configuración → Mi Cuenta.' },

    // ── ARCO (LFPDPPP) ────────────────────────────────────────────────
    { mod: 'ARCO', tag: 'arco_solicitar_acceso', sev: 'info',
      desc: 'Solicitud ARCO: acceso a datos personales (LFPDPPP).',
      trigger: 'Usuario solicita una copia de sus datos desde Configuración → Privacidad.' },
    { mod: 'ARCO', tag: 'arco_solicitar_rectificacion', sev: 'info',
      desc: 'Solicitud ARCO: rectificación de datos personales.',
      trigger: 'POST api/personal.php?action=arco con tipo=rectificacion.' },
    { mod: 'ARCO', tag: 'arco_solicitar_cancelacion', sev: 'warn',
      desc: 'Solicitud ARCO: cancelación (eliminación) de datos personales.',
      trigger: 'POST api/personal.php?action=arco con tipo=cancelacion.' },
    { mod: 'ARCO', tag: 'arco_solicitar_oposicion', sev: 'info',
      desc: 'Solicitud ARCO: oposición al tratamiento de datos.',
      trigger: 'POST api/personal.php?action=arco con tipo=oposicion.' },

    // ── Residentes ────────────────────────────────────────────────────
    { mod: 'Residentes', tag: 'residente_crear', sev: 'ok',
      desc: 'Alta de un nuevo residente.',
      trigger: 'POST api/residentes.php desde el formulario "Nuevo residente".' },
    { mod: 'Residentes', tag: 'residente_actualizar', sev: 'warn',
      desc: 'Edición de datos de identificación o ficha del residente.',
      trigger: 'PUT api/residentes.php?id=N.' },
    { mod: 'Residentes', tag: 'residente_eliminar', sev: 'error',
      desc: 'Eliminación de un residente y sus registros asociados.',
      trigger: 'DELETE api/residentes.php?id=N por un administrador.' },
    { mod: 'Residentes', tag: 'residente_subir_foto', sev: 'ok',
      desc: 'Carga o reemplazo de la foto de perfil del residente.',
      trigger: 'POST multipart api/residentes.php?action=upload_foto.' },

    // ── Cuidados ──────────────────────────────────────────────────────
    { mod: 'Cuidados', tag: 'cuidado_crear', sev: 'ok',
      desc: 'Registro de un evento de cuidado (sueño, alimentación, medicación, etc.).',
      trigger: 'POST api/cuidados.php desde cualquier formulario de la cuadrícula de categorías.' },
    { mod: 'Cuidados', tag: 'cuidado_editar', sev: 'warn',
      desc: 'Modificación de un registro de cuidado existente.',
      trigger: 'PUT api/cuidados.php?id=N desde la sidebar de detalle del registro.' },
    { mod: 'Cuidados', tag: 'cuidado_eliminar', sev: 'error',
      desc: 'Eliminación de un registro de cuidado.',
      trigger: 'DELETE api/cuidados.php?id=N (requiere confirmación del usuario).' },

    // ── Notas de turno ────────────────────────────────────────────────
    { mod: 'Cuidados', tag: 'nota_turno_crear', sev: 'ok',
      desc: 'Creación de una nota de turno (cuidador) sobre un residente.',
      trigger: 'POST api/cuidados.php?action=crear_nota desde la sección Notas.' },
    { mod: 'Cuidados', tag: 'nota_turno_actualizar', sev: 'warn',
      desc: 'Edición de una nota de turno (sólo el autor).',
      trigger: 'POST api/cuidados.php?action=actualizar_nota.' },

    // ── Notas médicas ─────────────────────────────────────────────────
    { mod: 'Notas Médicas', tag: 'nota_medica_crear', sev: 'ok',
      desc: 'Alta de una nota médica (SOAP) por un médico/superadmin, con recetas y adjuntos opcionales. Genera registro en expediente si se solicita.',
      trigger: 'POST api/cuidados.php?action=crear_nota_medico desde Notas Médicas → Nueva nota.' },
    { mod: 'Notas Médicas', tag: 'nota_medica_actualizar', sev: 'warn',
      desc: 'Edición del contenido, recetas o adjuntos de la nota médica vigente (sólo el autor).',
      trigger: 'POST api/cuidados.php?action=actualizar_nota_medico.' },
    { mod: 'Notas Médicas', tag: 'nota_medica_archivar', sev: 'warn',
      desc: 'Archivado de la nota médica vigente (queda fuera del flujo activo, conservada para auditoría).',
      trigger: 'POST api/cuidados.php?action=archivar_nota_medico.' },
    { mod: 'Notas Médicas', tag: 'nota_medica_eliminar', sev: 'error',
      desc: 'Eliminación permanente de una nota médica y los documentos del expediente vinculados (cascada). Operación restringida a superadmin.',
      trigger: 'POST api/cuidados.php?action=eliminar_nota_medico (sólo superadmin).' },

    // ── Reportes ──────────────────────────────────────────────────────
    { mod: 'Reportes', tag: 'reporte_generar', sev: 'info',
      desc: 'Generación de un reporte agregado de cuidados.',
      trigger: 'GET api/cuidados.php?action=reporte desde la sección Reportes.' },

    // ── Prescripciones ────────────────────────────────────────────────
    { mod: 'Prescripciones', tag: 'prescripcion_crear', sev: 'ok',
      desc: 'Alta de una nueva prescripción médica para un residente.',
      trigger: 'POST api/prescripciones.php desde el módulo de Médico o Notas Médicas.' },
    { mod: 'Prescripciones', tag: 'prescripcion_crear_lote', sev: 'ok',
      desc: 'Alta simultánea de varias prescripciones (por receta médica completa).',
      trigger: 'POST api/prescripciones.php?action=crear_lote enviando un arreglo de medicamentos.' },
    { mod: 'Prescripciones', tag: 'prescripcion_editar', sev: 'warn',
      desc: 'Modificación de dosis, frecuencia o vigencia de una prescripción.',
      trigger: 'PUT api/prescripciones.php?id=N.' },
    { mod: 'Prescripciones', tag: 'prescripcion_eliminar', sev: 'error',
      desc: 'Eliminación de una prescripción (idealmente reservada a errores de captura).',
      trigger: 'DELETE api/prescripciones.php?id=N.' },

    // ── Notificaciones ────────────────────────────────────────────────
    { mod: 'Notificaciones', tag: 'notificacion_crear', sev: 'ok',
      desc: 'Creación de una notificación programada/inmediata para un residente o usuario.',
      trigger: 'POST api/notificaciones.php desde Configuración → Notificaciones o desde un evento de cuidado.' },
    { mod: 'Notificaciones', tag: 'notificacion_actualizar', sev: 'warn',
      desc: 'Edición del contenido o programación de una notificación.',
      trigger: 'PUT api/notificaciones.php?id=N.' },
    { mod: 'Notificaciones', tag: 'notificacion_eliminar', sev: 'error',
      desc: 'Borrado de una notificación.',
      trigger: 'DELETE api/notificaciones.php?id=N.' },

    // ── Backup ────────────────────────────────────────────────────────
    { mod: 'Backup', tag: 'backup_crear_manual', sev: 'ok',
      desc: 'Backup ejecutado manualmente por un administrador.',
      trigger: 'POST api/backup.php?action=create desde Configuración → Backups.' },
    { mod: 'Backup', tag: 'backup_crear_automatico', sev: 'ok',
      desc: 'Backup ejecutado por el cron programado.',
      trigger: 'cron/backup.php (tarea programada del servidor).' },
    { mod: 'Backup', tag: 'backup_descargar', sev: 'info',
      desc: 'Descarga de un archivo de backup desde el panel.',
      trigger: 'GET api/backup.php?action=download&file=…' },
    { mod: 'Backup', tag: 'backup_descargar_sql', sev: 'info',
      desc: 'Descarga del backup SQL completo (DDL + datos) generado on-the-fly.',
      trigger: 'GET api/configuracion.php?action=backup_sql.' },
    { mod: 'Backup', tag: 'backup_eliminar', sev: 'warn',
      desc: 'Borrado manual de un archivo de backup.',
      trigger: 'POST api/backup.php?action=delete con file=…' },
    { mod: 'Backup', tag: 'backup_fallar', sev: 'error',
      desc: 'Falla al ejecutar un backup automático.',
      trigger: 'cron/backup.php captura una excepción durante la ejecución.' },

    // ── Configuración ────────────────────────────────────────────────
    { mod: 'Configuración', tag: 'configuracion_actualizar_general', sev: 'warn',
      desc: 'Cambio en la configuración general de la institución.',
      trigger: 'POST api/configuracion.php desde Configuración → General.' },
    { mod: 'Configuración', tag: 'perfil_actualizar', sev: 'ok',
      desc: 'Actualización del perfil del usuario actual.',
      trigger: 'POST api/configuracion.php?section=perfil.' },
    { mod: 'Configuración', tag: 'institucion_actualizar', sev: 'warn',
      desc: 'Edición de los datos legales/operativos de la institución.',
      trigger: 'POST api/configuracion.php?section=institucion (solo admin).' },
    { mod: 'Configuración', tag: 'smtp_probar', sev: 'info',
      desc: 'Prueba de envío de email vía SMTP configurado.',
      trigger: 'Botón "Probar SMTP" en Configuración → Integraciones.' },
    { mod: 'Configuración', tag: 'whatsapp_probar', sev: 'info',
      desc: 'Prueba de envío de mensaje WhatsApp por la API configurada (WaSender / WaAPI).',
      trigger: 'Botón "Probar WhatsApp" en Configuración → Integraciones.' },
    { mod: 'Configuración', tag: 'db_actualizar_config', sev: 'warn',
      desc: 'Cambio en parámetros de conexión a base de datos.',
      trigger: 'POST api/db_config.php (superadmin).' },
    { mod: 'Configuración', tag: 'db_probar_conexion', sev: 'info',
      desc: 'Prueba de conectividad a la base de datos configurada.',
      trigger: 'POST api/db_config.php?action=test.' },
    { mod: 'Configuración', tag: 'datos_reset', sev: 'error',
      desc: 'Reinicio de datos transaccionales (residentes, cuidados, etc.) preservando configuración. Operación destructiva.',
      trigger: 'POST api/reset_datos.php (solo superadmin con confirmación).' },
    { mod: 'Configuración', tag: 'errores_php_activar', sev: 'warn',
      desc: 'Activación temporal de display_errors PHP (modo depuración).',
      trigger: 'Toggle "display_errors" en Configuración → Logs → Errores PHP.' },
    { mod: 'Configuración', tag: 'errores_php_desactivar', sev: 'ok',
      desc: 'Desactivación de display_errors PHP.',
      trigger: 'Toggle "display_errors" en Configuración → Logs → Errores PHP.' },
    { mod: 'Configuración', tag: 'errores_php_limpiar', sev: 'warn',
      desc: 'Vaciado del archivo de log de errores PHP.',
      trigger: 'Botón "Limpiar log" en Configuración → Logs → Errores PHP.' },
    { mod: 'Configuración', tag: 'auditoria_exportar', sev: 'info',
      desc: 'Exportación CSV de la auditoría (cumple LFPDPPP / NOM-024).',
      trigger: 'Botón "Exportar CSV" en Configuración → Logs → Auditoría.' },

    // ── Datos / Importación ───────────────────────────────────────────
    { mod: 'Datos', tag: 'datos_importar', sev: 'warn',
      desc: 'Importación masiva de residentes/usuarios desde archivo.',
      trigger: 'POST api/import.php con un archivo CSV/Excel cargado.' },

    // ── Sesiones ──────────────────────────────────────────────────────
    { mod: 'Sesiones', tag: 'sesion_cerrar_propia', sev: 'ok',
      desc: 'Cierre de una sesión activa específica del propio usuario.',
      trigger: 'POST api/sesiones.php?action=cerrar desde Configuración → Sesiones.' },
    { mod: 'Sesiones', tag: 'sesion_cerrar_usuario', sev: 'warn',
      desc: 'Un administrador cierra todas las sesiones de otro usuario.',
      trigger: 'POST api/sesiones.php?action=cerrar_usuario&usuario_id=N.' },
];

const LOG_SEV_META = {
    ok:    { color: 'var(--cd-success)',  label: 'OK',    desc: 'Operación exitosa rutinaria.' },
    info:  { color: 'var(--cd-primary)',  label: 'Info',  desc: 'Evento informativo sin alteración de datos.' },
    warn:  { color: 'var(--cd-warning, #b45309)', label: 'Warn', desc: 'Operación que modifica datos sensibles o configuración.' },
    error: { color: 'var(--cd-danger)',   label: 'Error', desc: 'Operación destructiva o fallida.' },
};

function renderTagsCatalog() {
    const list = $('#cfgTagsCatalog');
    if (!list) return;
    const q = ($('#cfgTagSearch')?.value || '').toLowerCase().trim();
    const items = LOG_TAGS_CATALOG.filter(it =>
        !q || it.tag.toLowerCase().includes(q) || it.mod.toLowerCase().includes(q) || (it.desc || '').toLowerCase().includes(q)
    );
    if (!items.length) {
        list.innerHTML = '<p style="color:var(--cd-text-muted);font-size:0.8125rem;padding:12px">Sin resultados.</p>';
        return;
    }
    // Group by module preserving order
    const groups = {};
    const order = [];
    items.forEach(it => { if (!groups[it.mod]) { groups[it.mod] = []; order.push(it.mod); } groups[it.mod].push(it); });
    list.innerHTML = order.map(mod => {
        const rows = groups[mod].map((it, i) => {
            const sev = LOG_SEV_META[it.sev] || LOG_SEV_META.info;
            return `<div class="cd-cfg-user-card" data-tag="${esc(it.tag)}" style="cursor:pointer">
                <div class="cd-cfg-user-info" style="min-width:0">
                    <strong style="font-size:0.8125rem;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">${esc(it.tag)}</strong>
                    <span style="font-size:0.75rem;color:var(--cd-text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(it.desc)}</span>
                </div>
                <span class="cd-cfg-user-status" style="background:color-mix(in srgb, ${sev.color} 18%, transparent);color:${sev.color};border:1px solid color-mix(in srgb, ${sev.color} 35%, transparent)">${sev.label}</span>
            </div>`;
        }).join('');
        return `<div style="margin-top:14px">
            <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--cd-text-muted);margin:0 0 6px 4px">${esc(mod)}</div>
            ${rows}
        </div>`;
    }).join('');
}

$('#cfgTagSearch')?.addEventListener('input', () => renderTagsCatalog());

$('#cfgTagsCatalog')?.addEventListener('click', e => {
    const card = e.target.closest('.cd-cfg-user-card[data-tag]');
    if (!card) return;
    const tag = card.dataset.tag;
    const it = LOG_TAGS_CATALOG.find(x => x.tag === tag);
    if (it) openTagDetailSidebar(it);
});

function openTagDetailSidebar(it) {
    const sev = LOG_SEV_META[it.sev] || LOG_SEV_META.info;
    const body = `<div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Identificación</div>
        <table class="cd-sb-vitals-table"><tbody>
            <tr><th>Tag</th><td><code style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:0.85rem">${esc(it.tag)}</code></td></tr>
            <tr><th>Módulo</th><td>${esc(it.mod)}</td></tr>
            <tr><th>Severidad</th><td><span style="display:inline-block;padding:2px 8px;border-radius:9px;background:color-mix(in srgb, ${sev.color} 18%, transparent);color:${sev.color};font-weight:600;font-size:0.75rem">${sev.label}</span> <span style="font-size:0.75rem;color:var(--cd-text-muted)">— ${esc(sev.desc)}</span></td></tr>
        </tbody></table>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Descripción</div>
        <p style="margin:0;font-size:0.875rem;line-height:1.45">${esc(it.desc)}</p>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Cuándo se dispara</div>
        <p style="margin:0;font-size:0.875rem;line-height:1.45">${esc(it.trigger)}</p>
    </div>
    <div class="cd-sidebar-section">
        <div class="cd-sidebar-section-title">Buscar registros con este tag</div>
        <button class="cd-btn-submit" id="cdTagFilterBtn" style="width:100%">
            Ir a Auditoría filtrada por <code style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace">${esc(it.tag)}</code>
        </button>
    </div>`;
    const actions = `<button class="cd-btn-close-sidebar" id="cdTagClose">Cerrar</button>`;
    openSidebar('Tag: ' + it.tag, body, actions);
    $('#cdTagClose')?.addEventListener('click', closeSidebar);
    $('#cdTagFilterBtn')?.addEventListener('click', () => {
        const search = $('#cfgLogSearch'); if (search) search.value = it.tag;
        // Switch to Auditoría sub-tab
        const subtabs = document.getElementById('cfgLogsSubTabs');
        const aud = subtabs?.querySelector('[data-subtab="auditoria"]');
        if (aud) aud.click();
        closeSidebar();
        loadLogs(1);
    });
}

