<section id="viewConfig" class="cd-view">
<div class="cd-config-page">
    <div class="cd-config-head">
        <h1><?= t('config_title') ?></h1>
        <button type="button" class="cd-btn-add cd-view-refresh-btn" id="cdConfigRefreshBtn" data-view-refresh="viewConfig" title="<?= t('tl_refresh') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            <span><?= t('tl_refresh') ?></span>
        </button>
    </div>

    <div class="cd-config-shell">
    <aside class="cd-config-nav" aria-label="Secciones de configuración">
    <div class="cd-config-nav-title">Secciones</div>

    <!-- Config section tabs -->
    <div class="cd-cfg-tabs" id="cdCfgTabs" role="tablist" aria-label="Secciones de configuración">
        <span class="cd-cfg-tabs-pill" aria-hidden="true"></span>
        <button class="cd-cfg-tab active" data-cfg="general" data-cfg-nav="general" role="tab" aria-selected="true" aria-controls="cfgPanelGeneral">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            <span class="cd-cfg-tab-text"><span><?= t('config_general') ?></span><small>Preferencias, seguridad y documentos</small></span>
        </button>
        <button class="cd-cfg-tab" data-cfg="equipo" data-cfg-nav="equipo" role="tab" aria-selected="false" aria-controls="cfgPanelEquipo">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="cd-cfg-tab-text"><span><?= t('config_team') ?></span><small>Usuarios, roles y permisos</small></span>
        </button>
        <?php if (in_array($userRole, ['admin','superadmin'], true)): ?>
        <button class="cd-cfg-tab" data-cfg="instituciones" data-cfg-nav="instituciones" role="tab" aria-selected="false" aria-controls="cfgPanelInstituciones">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/></svg>
            <span class="cd-cfg-tab-text"><span>Mis instituciones</span><small>Centros, cupos y datos fiscales</small></span>
        </button>
        <?php endif; ?>
        <button class="cd-cfg-tab" data-cfg="notificaciones_admin" data-cfg-nav="notificaciones_admin" role="tab" aria-selected="false" aria-controls="cfgPanelNotificacionesAdmin">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span class="cd-cfg-tab-text"><span><?= t('config_notifications') ?></span><small>Avisos administrativos</small></span>
        </button>
        <button class="cd-cfg-tab" data-cfg="logs" data-cfg-nav="logs" role="tab" aria-selected="false" aria-controls="cfgPanelLogs">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
            <span class="cd-cfg-tab-text"><span><?= t('config_logs_title') ?></span><small>Auditoría, eventos y errores</small></span>
        </button>
        <button class="cd-cfg-tab" data-cfg="integraciones" data-cfg-nav="integraciones" role="tab" aria-selected="false" aria-controls="cfgPanelIntegraciones">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 2v6"/><path d="M15 2v6"/><path d="M6 8h12v3a6 6 0 0 1-6 6 6 6 0 0 1-6-6V8z"/><path d="M12 17v5"/></svg>
            <span class="cd-cfg-tab-text"><span><?= t('config_integrations') ?></span><small>Correo, WhatsApp y servicios</small></span>
        </button>
        <?php if ($userRole === 'superadmin'): ?>
        <button class="cd-cfg-tab" data-cfg="permisos_ui" data-cfg-nav="permisos_ui" role="tab" aria-selected="false" aria-controls="cfgPanelPermisosUi">
            <span class="material-symbols-outlined" style="font-size:18px;line-height:1" aria-hidden="true">tune</span>
            <span class="cd-cfg-tab-text"><span>Permisos de elementos</span><small>Acceso granular por perm-id</small></span>
        </button>
        <button class="cd-cfg-tab" data-cfg="db" data-cfg-nav="db" role="tab" aria-selected="false" aria-controls="cfgPanelDb">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg>
            <span class="cd-cfg-tab-text"><span><?= t('config_db_title') ?></span><small>Respaldos, sesiones y base de datos</small></span>
        </button>
        <?php endif; ?>
    </div>
    </aside>

    <main class="cd-config-content" id="cdConfigContent">

    <!-- General Panel (con sub-tabs) -->
    <div class="cd-cfg-panel active" id="cfgPanelGeneral" data-cfg-panel="general">
        <div class="cd-cfg-subtabs"><span class="cd-cfg-subtabs-pill" aria-hidden="true"></span>
            <button class="cd-cfg-subtab active" data-subtab="generales"><?= t('config_general') ?></button>
            <button class="cd-cfg-subtab" data-subtab="seguridad"><?= t('config_sec_title') ?></button>
            <button class="cd-cfg-subtab" data-subtab="terminos"><?= t('config_tc_title') ?></button>
            <button class="cd-cfg-subtab" data-subtab="privacidad"><?= t('config_privacy_title') ?></button>
        </div>

        <!-- Sub-panel: Generales -->
        <div class="cd-cfg-subpanel active" data-subpanel="generales">
        <h2><?= t('config_general') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_general_desc') ?></p>
        <div class="cd-cfg-form">
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_inst_name') ?></label><input class="cd-input" id="cfgInstNombre" placeholder="Residencia Geriátrica"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_app_url') ?></label><input class="cd-input" id="cfgAppUrl" placeholder="https://miapp.com/v5"></div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_timezone') ?></label>
                    <select class="cd-input cd-select-native" id="cfgTimezone">
                        <optgroup label="GMT">
                            <option value="Etc/GMT+12">GMT-12:00</option>
                            <option value="Etc/GMT+11">GMT-11:00</option>
                            <option value="Etc/GMT+10">GMT-10:00</option>
                            <option value="Etc/GMT+9">GMT-09:00</option>
                            <option value="Etc/GMT+8">GMT-08:00</option>
                            <option value="Etc/GMT+7">GMT-07:00</option>
                            <option value="Etc/GMT+6">GMT-06:00</option>
                            <option value="Etc/GMT+5">GMT-05:00</option>
                            <option value="Etc/GMT+4">GMT-04:00</option>
                            <option value="Etc/GMT+3">GMT-03:00</option>
                            <option value="Etc/GMT+2">GMT-02:00</option>
                            <option value="Etc/GMT+1">GMT-01:00</option>
                            <option value="Etc/GMT">GMT+00:00</option>
                            <option value="Etc/GMT-1">GMT+01:00</option>
                            <option value="Etc/GMT-2">GMT+02:00</option>
                            <option value="Etc/GMT-3">GMT+03:00</option>
                            <option value="Etc/GMT-4">GMT+04:00</option>
                            <option value="Etc/GMT-5">GMT+05:00</option>
                            <option value="Etc/GMT-5:30" disabled>GMT+05:30</option>
                            <option value="Etc/GMT-6">GMT+06:00</option>
                            <option value="Etc/GMT-7">GMT+07:00</option>
                            <option value="Etc/GMT-8">GMT+08:00</option>
                            <option value="Etc/GMT-9">GMT+09:00</option>
                            <option value="Etc/GMT-10">GMT+10:00</option>
                            <option value="Etc/GMT-11">GMT+11:00</option>
                            <option value="Etc/GMT-12">GMT+12:00</option>
                            <option value="Etc/GMT-13">GMT+13:00</option>
                            <option value="Etc/GMT-14">GMT+14:00</option>
                        </optgroup>
                        <optgroup label="Ciudades">
                            <option value="America/Mexico_City">América/Ciudad de México</option>
                            <option value="America/Santiago">América/Santiago</option>
                            <option value="America/Bogota">América/Bogotá</option>
                            <option value="America/Lima">América/Lima</option>
                            <option value="America/Argentina/Buenos_Aires">América/Buenos Aires</option>
                            <option value="America/Caracas">América/Caracas</option>
                            <option value="Europe/Madrid">Europa/Madrid</option>
                            <option value="America/New_York">América/Nueva York</option>
                        </optgroup>
                    </select>
                </div>
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_language') ?></label>
                    <select class="cd-input cd-select-native" id="cfgIdioma">
                        <option value="es">Español</option>
                        <option value="en">English</option>
                        <option value="pt">Português</option>
                    </select>
                </div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_date_format') ?></label>
                    <select class="cd-input cd-select-native" id="cfgFechaFormato">
                        <option value="d/m/Y">DD/MM/AAAA</option>
                        <option value="d-m-Y">DD-MM-AAAA</option>
                        <option value="m/d/Y">MM/DD/AAAA</option>
                        <option value="Y-m-d">AAAA-MM-DD</option>
                    </select>
                </div>
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_currency') ?></label>
                    <select class="cd-input cd-select-native" id="cfgMoneda">
                        <option value="MXN">MXN — Peso mexicano</option>
                        <option value="CLP">CLP — Peso chileno</option>
                        <option value="COP">COP — Peso colombiano</option>
                        <option value="ARS">ARS — Peso argentino</option>
                        <option value="USD">USD — Dólar</option>
                        <option value="EUR">EUR — Euro</option>
                    </select>
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_logo') ?></label>
                <div style="display:flex;align-items:center;gap:12px">
                    <img id="cfgLogoPreview" src="" alt="" style="width:48px;height:48px;border-radius:var(--cd-radius);border:1px solid var(--cd-border);object-fit:cover;display:none">
                    <input type="file" class="cd-input" id="cfgLogoFile" accept="image/*" style="flex:1">
                </div>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_legal_cc_email') ?></label>
                <input class="cd-input" id="cfgLegalCcEmail" type="email" placeholder="legal@miinstitucion.com" maxlength="200">
                <p class="cd-text-muted cd-text-xs" style="margin:4px 0 0"><?= t('config_legal_cc_email_hint') ?></p>
            </div>
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_support_phone') ?></label>
                <input class="cd-input" id="cfgSupportPhone" type="tel" placeholder="+52 5512345678" maxlength="30" autocomplete="tel">
                <p class="cd-text-muted cd-text-xs" style="margin:4px 0 0"><?= t('config_support_phone_hint') ?></p>
                <p class="cd-text-muted cd-text-xs" id="cfgSupportPhoneAdminHint" style="margin:4px 0 0;display:none"></p>
            </div>
            <div class="cd-cfg-actions">
                <button class="cd-btn-submit" id="cfgGeneralSave" data-perm-id="cfg_save_general_btn"><?= t('config_save') ?></button>
            </div>
        </div>
        </div><!-- /generales -->

        <!-- Sub-panel: Seguridad -->
        <div class="cd-cfg-subpanel" data-subpanel="seguridad">
        <h2><?= t('config_sec_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_sec_desc') ?></p>
        <div class="cd-cfg-form">
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_sec_min_len') ?></label><input class="cd-input" id="cfgSegPassLen" type="number" min="4" max="32" value="8"></div>
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_sec_pass_expire') ?></label><input class="cd-input" id="cfgSegPassExpire" type="number" min="0" value="0" placeholder="<?= t('config_sec_pass_expire_ph') ?>"></div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_sec_timeout') ?></label><input class="cd-input" id="cfgSegTimeout" type="number" min="5" max="1440" value="60"></div>
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_sec_max_attempts') ?></label><input class="cd-input" id="cfgSegMaxAttempts" type="number" min="0" value="5" placeholder="<?= t('config_sec_max_att_ph') ?>"></div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_sec_block_min') ?></label><input class="cd-input" id="cfgSegBlockMin" type="number" min="1" value="15"></div>
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_sec_log_access') ?></label>
                <label class="cd-toggle"><input type="checkbox" id="cfgSegLog" checked><span class="cd-toggle-track"><span class="cd-toggle-knob"></span></span></label>
            </div>
            <div class="cd-cfg-actions"><button class="cd-btn-submit" id="cfgSegSave" data-perm-id="cfg_save_security_btn"><?= t('config_save_security') ?></button></div>
        </div>
        </div><!-- /seguridad -->

        <!-- Sub-panel: Términos y Condiciones -->
        <div class="cd-cfg-subpanel" data-subpanel="terminos">
        <h2><?= t('config_tc_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_tc_desc') ?></p>
        <div class="cd-cfg-form">
            <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
                <button class="cd-btn-submit cd-btn-sm" id="cfgTcNew" data-perm-id="cfg_new_tc_doc_btn"><?= t('config_legal_new') ?></button>
            </div>
            <div id="cfgTcList" class="cd-legal-docs-list">
                <p class="cd-text-muted cd-text-sm"><?= t('config_legal_loading') ?></p>
            </div>
        </div>
        </div><!-- /terminos -->

        <!-- Sub-panel: Aviso de Privacidad -->
        <div class="cd-cfg-subpanel" data-subpanel="privacidad">
        <h2><?= t('config_privacy_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_privacy_desc') ?></p>
        <div class="cd-cfg-form">
            <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
                <button class="cd-btn-submit cd-btn-sm" id="cfgPrivNew" data-perm-id="cfg_new_privacy_doc_btn"><?= t('config_legal_new') ?></button>
            </div>
            <div id="cfgPrivList" class="cd-legal-docs-list">
                <p class="cd-text-muted cd-text-sm"><?= t('config_legal_loading') ?></p>
            </div>
        </div>
        </div><!-- /privacidad -->

    </div>

    <!-- Integraciones Panel (SMTP + WhatsApp + IA) -->
    <div class="cd-cfg-panel" id="cfgPanelIntegraciones" data-cfg-panel="integraciones">
        <div class="cd-cfg-subtabs"><span class="cd-cfg-subtabs-pill" aria-hidden="true"></span>
            <button class="cd-cfg-subtab active" data-subtab="smtp"><?= t('config_smtp_title') ?></button>
            <button class="cd-cfg-subtab" data-subtab="whatsapp"><?= t('config_wa_title') ?></button>
            <button class="cd-cfg-subtab" data-subtab="ia"><?= t('config_ia_title') ?></button>
        </div>

        <!-- Sub-panel: SMTP -->
        <div class="cd-cfg-subpanel active" data-subpanel="smtp">
        <h2><?= t('config_smtp_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_smtp_desc') ?></p>
        <form class="cd-cfg-form" onsubmit="return false" autocomplete="off">
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_host') ?></label><input class="cd-input" id="cfgSmtpHost" placeholder="smtp.gmail.com" autocomplete="off"></div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_port') ?></label><input class="cd-input" id="cfgSmtpPort" type="number" placeholder="587"></div>
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_enc') ?></label>
                    <select class="cd-input cd-select-native" id="cfgSmtpEnc"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="ninguna"><?= t('config_smtp_enc_none') ?></option></select>
                </div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_user') ?></label><input class="cd-input" id="cfgSmtpUser" placeholder="usuario@dominio.com" autocomplete="off" data-lpignore="true" data-form-type="other"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_pass') ?></label><div class="cd-input-password-wrap"><input class="cd-input cd-masked" id="cfgSmtpPass" type="text" placeholder="••••••••" autocomplete="off" data-lpignore="true" data-form-type="other"><button type="button" class="cd-pass-toggle" tabindex="-1" title="<?= t('pw_toggle') ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
            <div class="cd-form-row">
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_from_email') ?></label><input class="cd-input" id="cfgSmtpFromEmail" placeholder="noreply@dominio.com"></div>
                <div class="cd-form-group"><label class="cd-form-label"><?= t('config_smtp_from_name') ?></label><input class="cd-input" id="cfgSmtpFromName" placeholder="GeriApp"></div>
            </div>
            <div class="cd-cfg-actions">
                <button class="cd-btn-submit" id="cfgSmtpSave" data-perm-id="cfg_save_smtp_btn"><?= t('config_save_smtp') ?></button>
                <button class="cd-btn-submit cd-btn-secondary" id="cfgSmtpTest" data-perm-id="cfg_test_smtp_btn"><?= t('config_test_conn') ?></button>
            </div>
        </form>
        </div>

        <!-- Sub-panel: WhatsApp -->
        <div class="cd-cfg-subpanel" data-subpanel="whatsapp">
        <h2><?= t('config_wa_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_wa_desc') ?></p>
        <form class="cd-cfg-form" onsubmit="return false" autocomplete="off">
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_wa_provider') ?></label>
                <select class="cd-input cd-select-native" id="cfgWaProvider"><option value="wasender">WaSender</option><option value="waapi">WaAPI.app</option></select>
            </div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_wa_api_key') ?></label><div class="cd-input-password-wrap"><input class="cd-input cd-masked" id="cfgWaKey" type="text" placeholder="••••••••" autocomplete="off" data-lpignore="true" data-form-type="other"><button type="button" class="cd-pass-toggle" tabindex="-1" title="<?= t('pw_toggle') ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
            <div class="cd-form-group" id="cfgWaInstIdGroup"><label class="cd-form-label"><?= t('config_wa_inst_id') ?></label><input class="cd-input" id="cfgWaInstId" placeholder="<?= t('config_wa_inst_id_ph') ?>"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_wa_phone') ?></label><input class="cd-input" id="cfgWaPhone" placeholder="+52 1234567890"></div>
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_wa_active') ?></label>
                <label class="cd-toggle"><input type="checkbox" id="cfgWaActive"><span class="cd-toggle-track"><span class="cd-toggle-knob"></span></span></label>
            </div>
            <div class="cd-cfg-actions">
                <button class="cd-btn-submit" id="cfgWaSave" data-perm-id="cfg_save_wa_btn"><?= t('config_save_wa') ?></button>
                <button class="cd-btn-submit cd-btn-secondary" id="cfgWaTest" data-perm-id="cfg_test_wa_btn"><?= t('config_test_conn') ?></button>
            </div>
        </form>
        </div>

        <!-- Sub-panel: IA -->
        <div class="cd-cfg-subpanel" data-subpanel="ia">
        <h2><?= t('config_ia_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_ia_desc') ?></p>
        <form class="cd-cfg-form" onsubmit="return false">
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_ia_provider') ?></label>
                <div class="cd-ia-providers" id="cfgIaProviders">
                    <input type="hidden" id="cfgIaProvider" value="openai">
                    <button type="button" class="cd-ia-provider active" data-provider="openai">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M22.28 9.37a5.98 5.98 0 00-.52-4.9A6.05 6.05 0 0015.27 1.5a5.98 5.98 0 00-4.53 2.06 5.98 5.98 0 00-4.48-.56A6.05 6.05 0 002.3 6.5a5.98 5.98 0 00.74 7.03 5.98 5.98 0 00.52 4.9 6.05 6.05 0 006.49 2.97 5.98 5.98 0 004.53-2.06 5.98 5.98 0 004.48.56 6.05 6.05 0 003.96-3.5 5.98 5.98 0 00-.74-7.03z"/></svg>
                        <span class="cd-ia-provider-name">ChatGPT</span>
                        <span class="cd-ia-provider-co">OpenAI</span>
                        <span class="cd-ia-provider-badge"><?= t('config_ia_active_badge') ?></span>
                    </button>
                    <button type="button" class="cd-ia-provider" data-provider="gemini">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2L2 19.5h20L12 2zm0 4l6.9 11.5H5.1L12 6z"/></svg>
                        <span class="cd-ia-provider-name">Gemini</span>
                        <span class="cd-ia-provider-co">Google</span>
                        <span class="cd-ia-provider-badge"><?= t('config_ia_active_badge') ?></span>
                    </button>
                    <button type="button" class="cd-ia-provider" data-provider="deepseek">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15l-5-5 1.41-1.41L11 14.17l7.59-7.59L20 8l-9 9z"/></svg>
                        <span class="cd-ia-provider-name">DeepSeek</span>
                        <span class="cd-ia-provider-co">DeepSeek AI</span>
                        <span class="cd-ia-provider-badge"><?= t('config_ia_active_badge') ?></span>
                    </button>
                </div>
                <p class="cd-cfg-desc" style="margin-top:8px"><?= t('config_ia_provider_hint') ?></p>
            </div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_ia_api_key') ?></label><div class="cd-input-password-wrap"><input class="cd-input cd-masked" id="cfgIaKey" type="text" placeholder="sk-..." autocomplete="off" data-lpignore="true" data-form-type="other"><button type="button" class="cd-pass-toggle" tabindex="-1" title="<?= t('pw_toggle') ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div><p class="cd-cfg-desc" style="margin:4px 0 0" id="cfgIaKeyHint"><?= t('config_ia_key_hint_openai') ?> → API Keys</p></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_ia_model') ?></label><input class="cd-input" id="cfgIaModel" placeholder="gpt-4o"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_ia_prompt') ?> <span style="font-weight:400;color:var(--cd-text-muted)">(<?= t('config_ia_prompt_opt') ?>)</span></label><textarea class="cd-textarea" id="cfgIaPrompt" rows="3" placeholder="Eres el asistente administrativo de una residencia geriátrica. Responde siempre en español, de forma formal y concisa."></textarea><p class="cd-cfg-desc" style="margin:4px 0 0"><?= t('config_ia_prompt_desc') ?></p></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_ia_max_words') ?></label><input class="cd-input" id="cfgIaMaxPalabras" type="number" min="50" max="2000" step="50" placeholder="400"><p class="cd-cfg-desc" style="margin:4px 0 0"><?= t('config_ia_max_words_desc') ?></p></div>
            <div class="cd-cfg-actions">
                <button class="cd-btn-submit" id="cfgIaSave" data-perm-id="cfg_save_ia_btn"><?= t('config_save_ia') ?></button>
                <button class="cd-btn-submit cd-btn-secondary" id="cfgIaTest" data-perm-id="cfg_test_ia_btn"><?= t('config_test_conn') ?></button>
            </div>
        </form>
        </div>
    </div>

    <!-- Equipo Panel (Personal + Roles + Sesiones) -->
    <div class="cd-cfg-panel" id="cfgPanelEquipo" data-cfg-panel="equipo">
        <div class="cd-cfg-subtabs"><span class="cd-cfg-subtabs-pill" aria-hidden="true"></span>
            <button class="cd-cfg-subtab active" data-subtab="personal"><?= t('config_personal_title') ?></button>
            <button class="cd-cfg-subtab" data-subtab="roles"><?= t('config_roles_title') ?></button>
            <button class="cd-cfg-subtab" data-subtab="sesiones"><?= t('config_sessions_tab') ?></button>
        </div>

        <!-- Sub-panel: Roles -->
        <div class="cd-cfg-subpanel" data-subpanel="roles">
        <h2><?= t('config_roles_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_roles_desc') ?></p>
        <div class="cd-cfg-form">
            <div class="cd-cfg-perms-wrap">
            <table class="cd-cfg-perms-table" id="cfgPermsTable">
                <thead>
                    <tr><th><?= t('config_roles_permission') ?></th><th><span><?= t('role_admin') ?></span></th><th><span><?= t('role_enfermero') ?></span></th><th><span><?= t('role_medico') ?></span></th><th><span><?= t('role_familiar') ?></span></th></tr>
                </thead>
                <tbody></tbody>
            </table>
            </div>
            <div class="cd-cfg-actions" style="gap:8px">
                <button class="cd-btn-submit" id="cfgRolesSave" data-perm-id="cfg_save_roles_btn"><?= t('config_roles_save') ?></button>
                <button class="cd-btn-submit cd-btn-secondary" id="cfgRolesDefaults" data-perm-id="cfg_reset_roles_defaults_btn"><?= t('config_roles_defaults') ?></button>
            </div>
        </div>
        </div>

        <!-- Sub-panel: Personal -->
        <div class="cd-cfg-subpanel active" data-subpanel="personal">
        <h2><?= t('config_personal_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_personal_desc', [':inst' => htmlspecialchars($_SESSION['user_institucion_nombre'] ?? 'esta institución')]) ?></p>
        <div class="cd-cfg-form">
            <div class="cd-cfg-users-toolbar">
                <button class="cd-btn-submit" id="cfgInviteBtn" data-perm-id="cfg_invite_user_btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= t('config_personal_invite') ?>
                </button>
                <button class="cd-btn-submit cd-btn-secondary" id="cfgInviteQRBtn" data-perm-id="cfg_invite_qr_btn" title="<?= t('config_personal_invite_qr') ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <path d="M14 14h3v3h-3zM20 14h1v1h-1zM14 20h1v1h-1zM18 18h3v3h-3z"/>
                    </svg>
                    <?= t('config_personal_invite_qr') ?>
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
                <input class="cd-input" id="cfgUserSearch" placeholder="<?= t('config_personal_search') ?>" style="flex:1">
                <button type="button" class="cd-btn-icon" id="cfgUsersRefresh" title="<?= t('btn_refresh') ?>" style="flex-shrink:0;padding:6px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-surface);cursor:pointer;color:var(--cd-text-muted);display:inline-flex;align-items:center">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </button>
            </div>
            <div id="cfgUsersFilter" style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap">
                <button type="button" class="cd-chip-filter active" data-filter="todos">Todos</button>
                <button type="button" class="cd-chip-filter" data-filter="activo">Activos</button>
                <button type="button" class="cd-chip-filter" data-filter="inactivo">Inactivos</button>
                <button type="button" class="cd-chip-filter" data-filter="pendientes">Pendientes</button>
            </div>
            <div id="cfgUsersList" class="cd-cfg-users-list"></div>
            <div id="cfgInvitesSection">
            <div style="display:flex;align-items:center;gap:6px;margin-top:20px">
                <h3 style="font-size:0.9375rem;font-weight:600;margin:0;flex:1"><?= t('config_personal_pending') ?></h3>
                <button type="button" class="cd-btn-icon" id="cfgInvitesRefresh" title="<?= t('btn_refresh') ?>" style="flex-shrink:0;padding:6px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-surface);cursor:pointer;color:var(--cd-text-muted);display:inline-flex;align-items:center">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                </button>
            </div>
            <div id="cfgInvitesFilter" style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap">
                <button type="button" class="cd-chip-filter active" data-filter="pendiente">Pendientes</button>
                <button type="button" class="cd-chip-filter" data-filter="todos">Todas</button>
                <button type="button" class="cd-chip-filter" data-filter="aceptada">Aceptadas</button>
                <button type="button" class="cd-chip-filter" data-filter="revocada">Revocadas</button>
            </div>
            <div id="cfgInvitesList" class="cd-cfg-users-list"></div>
            </div>
        </div>
        </div>

        <!-- Sub-panel: Sesiones -->
        <div class="cd-cfg-subpanel" data-subpanel="sesiones">
        <h2><?= t('config_sessions_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_sessions_desc') ?></p>
        <div class="cd-sess-filters" id="cfgSessFilters">
            <button type="button" class="cd-chip-filter active" data-sf="all">Todas</button>
            <button type="button" class="cd-chip-filter" data-sf="current">Mi sesión</button>
            <button type="button" class="cd-chip-filter" data-sf="others">Otras</button>
        </div>
        <div class="cd-sess-summary" id="cfgSessSummary"></div>
        <div id="cfgSessionsList" class="cd-cfg-users-list"></div>
        <div style="margin-top:28px;padding-top:20px;border-top:1px solid var(--cd-border)">
            <h3 style="font-size:0.9375rem;font-weight:600;margin:0 0 8px">Push Notifications</h3>
            <div id="cfgPushDiag" style="font-size:0.8125rem;color:var(--cd-text-muted)">Cargando...</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
                <button type="button" class="cd-btn-submit" id="cfgPushTest" data-perm-id="cfg_push_test_btn" style="font-size:0.75rem;padding:5px 14px">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Enviar push de prueba
                </button>
                <button type="button" class="cd-btn-submit cd-btn-secondary" id="cfgPushRefresh" style="font-size:0.75rem;padding:5px 14px">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    Actualizar diagnóstico
                </button>
                <button type="button" class="cd-btn-ghost-sm" id="cfgPushClear" data-perm-id="cfg_push_clear_cache_btn" style="font-size:0.7rem;padding:4px 10px">Limpiar caché</button>
            </div>
            <div id="cfgPushTestResult" style="display:none;margin-top:10px;padding:8px 12px;border-radius:var(--cd-radius);font-size:0.8125rem;border:1px solid var(--cd-border)"></div>
        </div>
        </div>
    </div>
    <!-- Permisos UI Panel (solo superadmin) -->
    <?php if ($userRole === 'superadmin'): ?>
    <div class="cd-cfg-panel" id="cfgPanelPermisosUi" data-cfg-panel="permisos_ui">
        <div class="cd-pe-header">
            <div class="cd-pe-header-info">
                <h2>Permisos de elementos</h2>
                <p class="cd-cfg-desc">Control granular de acceso por elemento de interfaz. Cada elemento tiene un <code>perm-id</code> único. Configura qué roles pueden verlo o usarlo.</p>
            </div>
            <div class="cd-pe-header-actions">
                <button class="cd-btn cd-btn-secondary" id="cdPeAddBtn" data-perm-id="cfg_permui_add_element_btn" type="button">
                    <span class="material-symbols-outlined" aria-hidden="true">add</span>
                    Agregar elemento
                </button>
                <button class="cd-btn cd-btn-primary" id="cfgPeSave" data-perm-id="cfg_permui_save_btn" type="button">
                    <span class="material-symbols-outlined" aria-hidden="true">save</span>
                    Guardar cambios
                </button>
            </div>
        </div>
        <!-- Grid de perm-ids registrados -->
        <div class="cd-pe-grid" id="cdPermElementsGrid">
    </div>
    <?php endif; ?>

    <!-- DB Panel (solo superadmin) -->
    <?php if ($userRole === 'superadmin'): ?>
    <div class="cd-cfg-panel" id="cfgPanelDb" data-cfg-panel="db">
        <h2><?= t('config_db_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_db_desc') ?></p>

        <!-- Sub-tabs -->
        <div class="cd-cfg-subtabs" id="cfgDbSubTabs"><span class="cd-cfg-subtabs-pill" aria-hidden="true"></span>
            <button class="cd-cfg-subtab active" data-subtab="conexion">Conexión</button>
            <button class="cd-cfg-subtab" data-subtab="backups">Respaldos</button>
        </div>

        <!-- Sub-panel: Conexión (existing content) -->
        <div class="cd-cfg-subpanel active" data-subpanel="conexion">

        <!-- DB Status Indicator -->
        <div id="cfgDbStatus" class="cd-db-status" style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--cd-radius);background:var(--cd-bg-card);border:1px solid var(--cd-border);margin-bottom:16px;cursor:pointer" title="Clic para ver detalles">
            <span id="cfgDbStatusDot" style="width:12px;height:12px;border-radius:50%;background:#ccc;flex-shrink:0;transition:background .3s"></span>
            <span id="cfgDbStatusText" style="font-size:0.8125rem;color:var(--cd-text-muted)"><?= t('config_db_checking') ?></span>
        </div>

        <form class="cd-cfg-form" onsubmit="return false">
            <div class="cd-form-group">
                <label class="cd-form-label"><?= t('config_db_profile') ?></label>
                <select class="cd-input" id="cfgDbProfile"><option value="_active"><?= t('config_db_active_conn') ?></option></select>
                <div style="display:flex;gap:8px;margin-top:6px">
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgDbProfileSaveAs" data-perm-id="cfg_db_profile_save_as_btn" style="padding:6px 12px;font-size:0.8125rem" title="<?= t('config_db_save_as') ?>"><?= t('config_db_save_as') ?></button>
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgDbProfileRename" data-perm-id="cfg_db_profile_rename_btn" style="padding:6px 12px;font-size:0.8125rem;display:none" title="<?= t('config_db_rename') ?>"><?= t('config_db_rename') ?></button>
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgDbProfileDelete" data-perm-id="cfg_db_profile_delete_btn" style="padding:6px 12px;font-size:0.8125rem;display:none;color:var(--cd-danger,#e74c3c)" title="<?= t('config_db_delete') ?>"><?= t('config_db_delete') ?></button>
                </div>
            </div>
            <div class="cd-form-row">
                <div class="cd-form-group" style="flex:2"><label class="cd-form-label"><?= t('config_db_host') ?></label><input class="cd-input" id="cfgDbHost" placeholder="localhost"></div>
                <div class="cd-form-group" style="flex:1"><label class="cd-form-label"><?= t('config_smtp_port') ?></label><input class="cd-input" id="cfgDbPort" type="number" placeholder="3306"></div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_db_name') ?></label><input class="cd-input" id="cfgDbName" placeholder="geriapp"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_db_user') ?></label><input class="cd-input" id="cfgDbUser" placeholder="root" autocomplete="off" data-lpignore="true" data-form-type="other"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_db_password') ?></label>
                <div class="cd-input-password-wrap"><input class="cd-input cd-masked" id="cfgDbPass" type="text" placeholder="••••••••" autocomplete="off" data-lpignore="true" data-form-type="other"><button type="button" class="cd-pass-toggle" tabindex="-1" title="<?= t('pw_toggle') ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
            </div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_db_charset') ?></label><input class="cd-input" id="cfgDbCharset" placeholder="utf8mb4"></div>
            <div class="cd-form-group"><label class="cd-form-label"><?= t('config_db_tenant') ?></label><input class="cd-input" id="cfgDbTenantPrefix" placeholder="geriapp_i"></div>
            <div id="cfgDbTestResult" style="display:none;margin-top:8px;padding:10px 12px;border-radius:var(--cd-radius);font-size:0.8125rem"></div>
            <div class="cd-cfg-actions" style="gap:8px">
                <button class="cd-btn-submit cd-btn-secondary" id="cfgDbTest" data-perm-id="cfg_db_test_btn"><?= t('config_db_test') ?></button>
                <button class="cd-btn-submit" id="cfgDbSave" data-perm-id="cfg_db_apply_btn"><?= t('config_db_apply') ?></button>
            </div>
            <p class="cd-cfg-desc" style="margin-top:4px"><?= t('config_db_apply_hint') ?></p>
        </form>

        </div><!-- /conexion -->

        <!-- Sub-panel: Respaldos (Â§8) -->
        <div class="cd-cfg-subpanel" data-subpanel="backups">
            <div class="cd-cfg-form">
                <!-- Programación -->
                <h3 style="font-size:0.9375rem;font-weight:600;margin:0 0 12px">Programación de respaldos</h3>
                <div class="cd-form-row" style="gap:12px;flex-wrap:wrap">
                    <div class="cd-form-group" style="flex:1;min-width:140px">
                        <label class="cd-form-label">Frecuencia</label>
                        <select class="cd-input cd-select-native" id="cfgBackupFrec">
                            <option value="desactivado">Desactivado</option>
                            <option value="diario">Diario</option>
                            <option value="semanal">Semanal</option>
                            <option value="mensual">Mensual</option>
                        </select>
                    </div>
                    <div class="cd-form-group" style="flex:1;min-width:100px">
                        <label class="cd-form-label">Hora programada</label>
                        <input class="cd-input" id="cfgBackupHora" type="time" value="03:00">
                    </div>
                </div>
                <div class="cd-cfg-actions" style="margin-top:8px">
                    <button class="cd-btn-submit" id="cfgBackupSaveSchedule" data-perm-id="cfg_backup_save_schedule_btn">Guardar programación</button>
                </div>

                <hr style="border:none;border-top:1px solid var(--cd-border);margin:20px 0">

                <!-- Acciones manuales -->
                <h3 style="font-size:0.9375rem;font-weight:600;margin:0 0 12px">Acciones</h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="cd-btn-submit" id="cfgBackupNow" data-perm-id="cfg_backup_generate_btn">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Generar respaldo ahora
                    </button>
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgBackupDownloadDirect" data-perm-id="cfg_backup_download_sql_btn" title="Descarga directa del SQL (sin almacenar en servidor)">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Descarga directa SQL
                    </button>
                </div>

                <hr style="border:none;border-top:1px solid var(--cd-border);margin:20px 0">

                <!-- Historial de backups -->
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                    <h3 style="font-size:0.9375rem;font-weight:600;margin:0;flex:1">Historial de respaldos</h3>
                    <button type="button" class="cd-btn-icon" id="cfgBackupRefresh" title="Actualizar" style="flex-shrink:0;padding:6px;border:1px solid var(--cd-border);border-radius:var(--cd-radius);background:var(--cd-surface);cursor:pointer;color:var(--cd-text-muted);display:inline-flex;align-items:center">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                    </button>
                </div>
                <div id="cfgBackupsList" class="cd-cfg-users-list"></div>

                <hr style="border:none;border-top:1px solid var(--cd-border);margin:20px 0">

                <!-- Plan de recuperación (Â§8.3) -->
                <details style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius);padding:12px">
                    <summary style="cursor:pointer;font-weight:600;font-size:0.875rem">Plan de recuperación (PHIPA Â§8.3)</summary>
                    <div style="margin-top:10px;font-size:0.8125rem;color:var(--cd-text-muted);line-height:1.6">
                        <p><strong>1. Restaurar desde respaldo SQL:</strong></p>
                        <code style="display:block;background:var(--cd-bg);padding:8px;border-radius:4px;margin:4px 0 8px;font-size:0.75rem">mysql -u usuario -p &lt; geriapp_backup_instX_YYYYMMDD_HHMMSS.sql</code>
                        <p><strong>2. Respaldo cifrado (.enc):</strong></p>
                        <code style="display:block;background:var(--cd-bg);padding:8px;border-radius:4px;margin:4px 0 8px;font-size:0.75rem">openssl enc -aes-256-cbc -d -in backup.sql.enc -out backup.sql -pass pass:CLAVE</code>
                        <p><strong>3. Respaldo comprimido (.gz):</strong></p>
                        <code style="display:block;background:var(--cd-bg);padding:8px;border-radius:4px;margin:4px 0 8px;font-size:0.75rem">gunzip geriapp_backup_instX.sql.gz<br>mysql -u usuario -p &lt; geriapp_backup_instX.sql</code>
                        <p><strong>4. Verificación post-restauración:</strong></p>
                        <ul style="margin:4px 0;padding-left:20px">
                            <li>Verificar conteo de registros en tablas principales</li>
                            <li>Ejecutar <code>run_migrations.php</code> para aplicar migraciones pendientes</li>
                            <li>Validar acceso con una cuenta de prueba</li>
                            <li>Revisar logs_sistema para confirmar integridad</li>
                        </ul>
                        <p><strong>5. Contacto de emergencia:</strong> Administrador del sistema o proveedor de hosting.</p>
                        <p><strong>Retención:</strong> Los respaldos automáticos se conservan 30 días.</p>
                    </div>
                </details>
            </div>
        </div><!-- /backups -->
    </div>
    <?php endif; ?>

    <!-- Logs Panel -->
    <div class="cd-cfg-panel" id="cfgPanelLogs" data-cfg-panel="logs">
        <h2><?= t('config_logs_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_logs_desc') ?></p>

        <!-- Sub-tabs -->
        <div class="cd-cfg-subtabs" id="cfgLogsSubTabs"><span class="cd-cfg-subtabs-pill" aria-hidden="true"></span>
            <button class="cd-cfg-subtab active" data-subtab="auditoria">Auditoría</button>
            <button class="cd-cfg-subtab" data-subtab="catalogo">Catálogo</button>
            <?php if (in_array($userRole, ['admin','superadmin'])): ?>
            <button class="cd-cfg-subtab" data-subtab="errores">Errores PHP</button>
            <?php endif; ?>
        </div>

        <!-- Sub-panel: Auditoría -->
        <div class="cd-cfg-subpanel active" data-subpanel="auditoria">
            <div class="cd-cfg-form">
                <div class="cd-form-row" style="gap:8px;flex-wrap:wrap">
                    <input class="cd-input" id="cfgLogSearch" placeholder="<?= t('config_logs_search') ?>" style="flex:1;min-width:140px">
                    <select class="cd-input cd-select-native" id="cfgLogModulo" style="width:auto">
                        <option value=""><?= t('config_logs_all_modules') ?></option>
                        <option value="auth">Auth</option>
                        <option value="residentes"><?= t('nav_residentes') ?></option>
                        <option value="bitacora"><?= t('nav_bitacora') ?></option>
                        <option value="historial"><?= t('nav_historial') ?></option>
                        <option value="configuracion"><?= t('nav_configuracion') ?></option>
                        <option value="personal"><?= t('nav_personal') ?></option>
                        <option value="prescripciones"><?= t('nav_prescripciones') ?></option>
                        <option value="reportes"><?= t('nav_reportes') ?></option>
                        <option value="inventario"><?= t('nav_inventario') ?></option>
                        <option value="backup">Backup</option>
                    </select>
                    <select class="cd-input cd-select-native" id="cfgLogEstado" style="width:auto">
                        <option value="">Todos los estados</option>
                        <option value="ok">OK</option>
                        <option value="warn">Advertencia</option>
                        <option value="error">Error</option>
                        <option value="info">Info</option>
                    </select>
                </div>
                <div class="cd-form-row" style="gap:8px;flex-wrap:wrap;margin-top:6px">
                    <input class="cd-input cd-app-date-input" id="cfgLogDesde" type="text" inputmode="numeric" style="width:auto" title="<?= t('config_logs_from') ?>">
                    <input class="cd-input cd-app-date-input" id="cfgLogHasta" type="text" inputmode="numeric" style="width:auto" title="<?= t('config_logs_to') ?>">
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgLogFilter" style="white-space:nowrap"><?= t('btn_filter') ?></button>
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgLogExport" data-perm-id="cfg_logs_export_csv_btn" style="white-space:nowrap" title="Exportar CSV para auditoría PHIPA/NOM">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Exportar CSV
                    </button>
                </div>
                <div id="cfgLogsList" class="cd-cfg-users-list" style="margin-top:12px"></div>
                <div id="cfgLogsPag" style="display:flex;justify-content:center;gap:8px;margin-top:12px"></div>
            </div>
        </div>

        <!-- Sub-panel: Catálogo de tags -->
        <div class="cd-cfg-subpanel" data-subpanel="catalogo">
            <div class="cd-cfg-form">
                <p class="cd-cfg-desc" style="margin-top:0">
                    Diccionario de acciones registradas en la auditoría. Selecciona una para ver qué la dispara y su impacto.
                </p>
                <div class="cd-form-row" style="gap:8px;flex-wrap:wrap;margin-bottom:8px">
                    <input class="cd-input" id="cfgTagSearch" placeholder="Buscar tag o módulo…" style="flex:1;min-width:140px">
                </div>
                <div id="cfgTagsCatalog" class="cd-cfg-users-list"></div>
            </div>
        </div>

        <!-- Sub-panel: Errores PHP (admin only) -->
        <?php if (in_array($userRole, ['admin','superadmin'])): ?>
        <div class="cd-cfg-subpanel" data-subpanel="errores">
            <div class="cd-cfg-form">
                <!-- display_errors toggle -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:var(--cd-radius);background:var(--cd-surface);border:1px solid var(--cd-border);margin-bottom:12px">
                    <div>
                        <strong style="font-size:0.875rem">display_errors</strong>
                        <p style="font-size:0.75rem;color:var(--cd-text-muted);margin:2px 0 0">Mostrar errores PHP en pantalla.<br>&#x26A0;&#xFE0F; Solo activar temporalmente para depuración.</p>
                    </div>
                    <label class="cd-toggle" style="flex-shrink:0;margin-left:12px">
                        <input type="checkbox" id="cfgDisplayErrors">
                        <span class="cd-toggle-track"><span class="cd-toggle-knob"></span></span>
                    </label>
                </div>

                <!-- Error log info -->
                <div id="cfgErrorLogInfo" style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--cd-radius);background:var(--cd-surface);border:1px solid var(--cd-border);margin-bottom:12px">
                    <span style="font-size:0.8125rem;color:var(--cd-text-muted)">Cargando información del log¦</span>
                </div>

                <!-- Action buttons -->
                <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgErrorLogRefresh" style="font-size:0.8125rem;padding:6px 14px">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                        Actualizar
                    </button>
                    <button class="cd-btn-submit cd-btn-secondary" id="cfgErrorLogClear" data-perm-id="cfg_error_log_clear_btn" style="font-size:0.8125rem;padding:6px 14px;color:var(--cd-danger,#e74c3c)">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        Limpiar log
                    </button>
                    <select class="cd-input cd-select-native" id="cfgErrorLogLines" style="width:auto;font-size:0.8125rem;padding:4px 8px">
                        <option value="100">Últimas 100 líneas</option>
                        <option value="200" selected>Últimas 200 líneas</option>
                        <option value="500">Últimas 500 líneas</option>
                        <option value="1000">Últimas 1000 líneas</option>
                    </select>
                </div>

                <!-- Error log output -->
                <div id="cfgErrorLogOutput" style="background:var(--cd-bg);border:1px solid var(--cd-border);border-radius:var(--cd-radius);padding:12px;font-family:'Fira Code','Consolas','Monaco',monospace;font-size:0.75rem;line-height:1.5;max-height:500px;overflow:auto;white-space:pre-wrap;word-break:break-all;color:var(--cd-text)">
                    Cargando¦
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Notificaciones Admin Panel -->
    <div class="cd-cfg-panel" id="cfgPanelNotificacionesAdmin" data-cfg-panel="notificaciones_admin">
        <h2><?= t('config_notif_title') ?></h2>
        <p class="cd-cfg-desc"><?= t('config_notif_desc') ?></p>
        <div class="cd-cfg-form">
            <div style="margin-bottom:12px">
                <button type="button" class="cd-btn-submit" id="cdNotifNewBtn" data-perm-id="cfg_new_admin_notif_btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?= t('config_notif_new') ?>
                </button>
            </div>
            <h3 style="margin-top:20px;font-size:0.9375rem;font-weight:600"><?= t('config_notif_existing') ?></h3>
            <div id="cfgNotifList" class="cd-notif-list"></div>
        </div>
    </div>

    <?php if (in_array($userRole, ['admin','superadmin'], true)): ?>
    <!-- Mis instituciones (admin/superadmin) -->
    <div class="cd-cfg-panel" id="cfgPanelInstituciones" data-cfg-panel="instituciones">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:8px">
            <div>
                <h2 style="margin:0">Mis instituciones</h2>
                <p class="cd-cfg-desc" style="margin:4px 0 0">Crea, edita y archiva las instituciones que administras. El número de instituciones activas depende de tu plan.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <span id="instQuotaBadge" class="cd-badge" style="background:var(--cd-surface);border:1px solid var(--cd-border);padding:4px 10px;border-radius:999px;font-size:0.75rem;color:var(--cd-text-muted)">—</span>
                <button class="cd-btn-submit" id="btnInstNew" data-perm-id="cfg_new_institution_btn" type="button">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nueva institución
                </button>
            </div>
        </div>
        <div id="instList" class="inst-list" style="display:flex;flex-direction:column;gap:10px;margin-top:12px"></div>
    </div>

    <!-- Modal: crear/editar institución -->
    <div class="cd-modal-overlay" id="instModalOverlay">
        <div class="cd-modal" role="dialog" aria-modal="true" aria-labelledby="instModalTitle">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px">
                <h2 id="instModalTitle" style="margin:0">Nueva institución</h2>
                <button type="button" id="instModalClose" aria-label="Cerrar" style="background:none;border:0;font-size:1.5rem;line-height:1;color:var(--cd-text-muted);cursor:pointer;padding:4px 8px">&times;</button>
            </div>
            <form class="cd-cfg-form" id="instForm" autocomplete="off">
                <input type="hidden" id="instId">
                <div class="cd-form-group">
                    <label class="cd-form-label" for="instNombre">Nombre *</label>
                    <input class="cd-input" id="instNombre" name="nombre" required maxlength="150" placeholder="Residencia Geriátrica">
                </div>
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label" for="instEmail">Email administrador *</label>
                        <input class="cd-input" id="instEmail" name="email_admin" type="email" required maxlength="150">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label" for="instTel">Teléfono</label>
                        <input class="cd-input" id="instTel" name="telefono" maxlength="30">
                    </div>
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label" for="instDir">Dirección</label>
                    <input class="cd-input" id="instDir" name="direccion" maxlength="255">
                </div>
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label" for="instCiudad">Ciudad</label>
                        <input class="cd-input" id="instCiudad" name="ciudad" maxlength="100">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label" for="instEstadoGeo">Estado / Provincia</label>
                        <input class="cd-input" id="instEstadoGeo" name="estado_geo" maxlength="60">
                    </div>
                </div>
                <div class="cd-form-row">
                    <div class="cd-form-group">
                        <label class="cd-form-label" for="instTz">Zona horaria *</label>
                        <select class="cd-input cd-select-native" id="instTz" name="timezone" required>
                            <option value="America/Mexico_City">América/Ciudad de México</option>
                            <option value="America/Santiago">América/Santiago</option>
                            <option value="America/Bogota">América/Bogotá</option>
                            <option value="America/Lima">América/Lima</option>
                            <option value="America/Argentina/Buenos_Aires">América/Buenos Aires</option>
                            <option value="America/Caracas">América/Caracas</option>
                            <option value="Europe/Madrid">Europa/Madrid</option>
                            <option value="America/New_York">América/Nueva York</option>
                        </select>
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-form-label" for="instCamas">Camas / Cupos</label>
                        <input class="cd-input" id="instCamas" name="num_camas" type="number" min="0" max="9999">
                    </div>
                </div>
                <div class="cd-form-group">
                    <label class="cd-form-label" for="instRfc">RFC / Identificación fiscal</label>
                    <input class="cd-input" id="instRfc" name="rfc" maxlength="20">
                </div>
                <div id="instFormErr" class="cd-form-error" style="display:none;color:var(--cd-danger,#e74c3c);font-size:0.8125rem;margin-top:8px"></div>
                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                    <button type="button" class="cd-btn-submit cd-btn-secondary" id="instCancelBtn">Cancelar</button>
                    <button type="submit" class="cd-btn-submit" id="instSaveBtn" data-perm-id="cfg_save_institution_btn">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</main>
</div>
</div>
</section>
