// cd-api.js — API helper
// Extracted from cuidados.php (lines 4615)
// ────────────────────────────────────────────────────────────

// ═══════════════════════════════════════════════
// API
// ═══════════════════════════════════════════════
// Global 401 interceptor: redirect to login on any unauthenticated response
{
    const _origFetch = window.fetch;
    window.fetch = async function(...args) {
        const res = await _origFetch.apply(this, args);
        if (res.status === 401) {
            // Clone to read body without consuming
            try {
                const clone = res.clone();
                const json = await clone.json();
                if ((json.message || '').toLowerCase().includes('no autenticado') || !json.success) {
                    window.location.href = BASE + '/index.php';
                    return res;
                }
            } catch(e) { /* not JSON, still redirect on 401 */ 
                window.location.href = BASE + '/index.php';
                return res;
            }
        }
        return res;
    };
}

async function api(url, opts) {
    try {
        const res = await fetch(url, opts);
        const json = await res.json();
        if (!json.success) {
            if (res.status === 401 || (json.message || '').toLowerCase().includes('no autenticado')) {
                window.location.href = BASE + '/index.php';
                return;
            }
            // F6: límite de asientos → diálogo con CTA a billing en vez de toast genérico.
            if (json.errors && json.errors.seat_limit) {
                showSeatLimitDialog(json.message || 'Límite alcanzado', json.errors);
                throw new Error(json.message || 'Límite alcanzado');
            }
            throw new Error(json.message || 'Error');
        }
        return json.data;
    } catch(e) { showToast(e.message, 'error'); throw e; }
}

// F6 — Diálogo amistoso cuando el server devuelve seat_limit.
// Se inyecta una sola vez para evitar duplicados.
// F9 — En Capacitor (app nativa) NO mostramos el CTA de compra para cumplir
// con Apple §3.1.1 / Google Play. En su lugar redirigimos al usuario a la
// versión web.
function showSeatLimitDialog(message, info) {
    const isNative = !!(
        (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) ||
        document.documentElement.dataset.native === '1' ||
        localStorage.getItem('geriappNativeApp') === '1'
    );
    let bg = document.getElementById('cdSeatLimitBg');
    if (!bg) {
        bg = document.createElement('div');
        bg.id = 'cdSeatLimitBg';
        bg.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:99999;padding:16px';
        bg.innerHTML = `
            <div style="background:var(--cd-bg,#fff);color:var(--cd-text,#1c1c1e);border:1px solid var(--cd-border,#d1d5db);border-radius:12px;padding:22px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4)">
                <h3 style="margin:0 0 10px;font-size:18px;font-weight:700">⚠️ Límite alcanzado</h3>
                <p id="cdSeatLimitMsg" style="margin:0 0 12px;font-size:14px;color:var(--cd-text-muted,#636366);line-height:1.45"></p>
                <p id="cdSeatLimitNativeNote" style="display:none;margin:0 0 16px;padding:10px;background:var(--cd-card-hover,#f3f4f6);border-radius:8px;font-size:13px;color:var(--cd-text-muted,#636366);line-height:1.45"></p>
                <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap">
                    <button id="cdSeatLimitClose" style="padding:9px 16px;border:1px solid var(--cd-border,#d1d5db);background:transparent;color:var(--cd-text,#1c1c1e);border-radius:8px;font-weight:600;cursor:pointer">Cerrar</button>
                    <a id="cdSeatLimitCta" href="#" style="padding:9px 16px;background:#635bff;color:#fff;border-radius:8px;font-weight:600;text-decoration:none">Comprar asiento extra</a>
                </div>
            </div>`;
        document.body.appendChild(bg);
        bg.addEventListener('click', e => { if (e.target === bg) bg.style.display = 'none'; });
        document.getElementById('cdSeatLimitClose').addEventListener('click', () => { bg.style.display = 'none'; });
    }
    document.getElementById('cdSeatLimitMsg').textContent = message;
    const cta = document.getElementById('cdSeatLimitCta');
    const note = document.getElementById('cdSeatLimitNativeNote');
    if (isNative) {
        cta.style.display = 'none';
        note.style.display = 'block';
        note.textContent = 'Para ampliar tu plan, contacta al administrador de tu institución.';
    } else {
        cta.style.display = '';
        note.style.display = 'none';
        cta.href = info.billing_url || (BASE + '/billing.php#addons');
    }
    bg.style.display = 'flex';
}

