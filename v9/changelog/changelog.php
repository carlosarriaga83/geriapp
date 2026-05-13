<?php
/**
 * GeriApp — Changelog
 *
 * Cada entrada es un array con:
 *   'version' => string,
 *   'date'    => string (YYYY-MM-DD),
 *   'changes' => [ ['tag' => '...', 'text' => '...'], ... ]
 *
 * El tooltip cd-changelog-tip y cualquier vista de changelog
 * se alimentan de este archivo.
 *
 * Convención de tags: General, Cuidados, Medicación, Notificaciones,
 * Inventario, Reportes, UI, Seguridad, Config, i18n, API, Fix
 */

return [

    [
        'version' => '1.53.26',
        'date'    => '2026-04-29',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Inicio: todos los badges de cd-cat-grid (contador y alertas: heces, sueno, medicacion, signos, notas medico vigente, notificaciones) se reubicaron a la segunda linea de la card para liberar el ancho del titulo y evitar que los nombres se recorten en pantallas pequenas.'],
            ['tag' => 'UI', 'text' => 'Layout actualizado a grid 2x4: icono span 2 filas + titulo a todo el ancho de la fila 1 + auto-flow de badges en la fila 2.'],
        ],
    ],

    [
        'version' => '1.53.25',
        'date'    => '2026-04-29',
        'changes' => [
            ['tag' => 'Doc', 'text' => 'Nomenclatura: la seccion principal del usuario se denomina "Inicio" (i18n nav_home). El id tecnico interno sigue siendo viewDashboard pero las notas de release usan "Inicio".'],
        ],
    ],

    [
        'version' => '1.53.24',
        'date'    => '2026-04-29',
        'changes' => [
            ['tag' => 'UI', 'text' => 'cd-cat-grid: Material Symbols a 26px peso 500, SVG con padding 9px y stroke 2, PNGs con filtro neutro oscuro (opacidad 0.82 en claro) y blanco (opacidad 0.95 en oscuro).'],
            ['tag' => 'UI', 'text' => 'Variantes (notas, notas-medico, ficha, incidente, notificaciones): glifo en tono oscuro (700/800) en tema claro y tono claro (300/400) sobre tile con tint mas denso (30-32%) en tema oscuro para garantizar contraste WCAG.'],
            ['tag' => 'UI', 'text' => 'Mobile (≤480px): tile 40px, padding 8px, glifo 24px.'],
        ],
    ],

    [
        'version' => '1.53.23',
        'date'    => '2026-04-29',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Header: la tarjeta del residente activo solo funciona como selector desplegable en la seccion Inicio (viewDashboard); en el resto de las secciones se presenta como card informativa de solo lectura.'],
            ['tag' => 'UI', 'text' => 'En estado bloqueado se oculta el caret de cambio, se desactiva el hover de acento y el select queda deshabilitado y fuera del flujo de teclado.'],
        ],
    ],

    [
        'version' => '1.53.22',
        'date'    => '2026-04-29',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Inicio: cards de cd-cat-grid replican el estilo de los accesos rapidos (cd-qs-btn) con bordes 14px, padding 14x16, fondo de superficie y hover elevado.'],
            ['tag' => 'UI', 'text' => 'Iconos de categoria presentados como tile cuadrado de 44px con fondo tintado al color de acento por variante (notas, notas medico, ficha, incidente, notificaciones).'],
            ['tag' => 'UI', 'text' => 'Badges reorganizados: cd-cat-badge azul (contador) como subtitulo bajo el rotulo y badges de alerta (heces, sueno, medicacion, signos, notas vigente, notificaciones) al extremo derecho.'],
            ['tag' => 'UX', 'text' => 'Tipografia del rotulo a 0.9375rem peso 600 y contraste verificado en tema claro y oscuro.'],
        ],
    ],

    [
        'version' => '1.53.21',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Inicio: cd-cat-grid rediseniado a 2 columnas con cards de altura uniforme y layout horizontal (icono + texto + badge de alerta a la derecha).'],
            ['tag' => 'UI', 'text' => 'Cards de cuidados: el cd-cat-badge azul informativo (contador) ahora se muestra como subtitulo en una segunda linea bajo el rotulo principal.'],
            ['tag' => 'UX', 'text' => 'Ajustes de iconos, padding y tipografia para mantener un estilo simple, organico y coherente entre temas y breakpoints.'],
        ],
    ],

    [
        'version' => '1.53.20',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Bottom bar: se retiro el acceso de Registros para simplificar la navegacion inferior; Registros permanece disponible desde los accesos existentes fuera del Bottom bar.'],
            ['tag' => 'UI', 'text' => 'Header: eliminada la linea vertical/acento lateral del control de residente activo para dejar cdPatientName mas limpio y minimalista.'],
            ['tag' => 'Fix', 'text' => 'cdPatientName: las opciones del selector ahora definen color y fondo explicitos para tema claro y oscuro, manteniendo contraste legible al abrir el desplegable.'],
        ],
    ],

    [
        'version' => '1.53.19',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Expediente: cd-exp-filters-row2 integra selector de fecha con icono de calendario en los filtros Desde y Hasta, manteniendo tambien la entrada manual.'],
            ['tag' => 'Fix', 'text' => 'El campo visible conserva el formato definido por cfgFechaFormato y el selector nativo se sincroniza con valores ISO internamente para preservar el filtrado de la API sin mostrar placeholders del sistema operativo.'],
        ],
    ],

    [
        'version' => '1.53.18',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Header: redisenado cdHeaderResidentCard con una tarjeta minimalista, mas compacta y visible para el residente activo, con avatar pequeno, nombre claro, acento lateral y control discreto de cambio.'],
            ['tag' => 'UX', 'text' => 'cdPatientName queda oculto visualmente para reducir ruido en el header, pero permanece como control funcional sobre la tarjeta para mantener el cambio de residente.'],
        ],
    ],

    [
        'version' => '1.53.17',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Bottom bar: el icono de Medicacion ahora usa el asset oficial pill.png en lugar del simbolo Material.'],
            ['tag' => 'UX', 'text' => 'Se ajusto el tamano del PNG para equilibrar el grosor visual de linea con el resto de iconos del Bottom bar en estados activo, inactivo, claro y oscuro.'],
        ],
    ],

    [
        'version' => '1.53.16',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Bottom bar: revertido el anillo de contraste invertido del boton Inicio elevado. El estado no activo vuelve a usar borde var(--cd-surface), evitando que parezca el boton seleccionado.'],
            ['tag' => 'UI', 'text' => 'Inicio no activo ahora se distingue con una pequena marca superior tipo anchor en color acento tenue, manteniendo su elevacion y posicion central sin competir con el contraste del estado active.'],
        ],
    ],

    [
        'version' => '1.53.15',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Expediente: los filtros compactos Desde/Hasta ya no usan input type=date nativo, porque el placeholder lo controla el sistema operativo y podia mostrar mm/dd/yyyy. Ahora usan input text numerico con placeholder derivado de cfgFechaFormato (DD-MM-AAAA, DD/MM/AAAA, MM/DD/AAAA o AAAA-MM-DD) y convierten internamente a ISO (YYYY-MM-DD) para la API.'],
            ['tag' => 'UX', 'text' => 'Al salir del campo, el valor ingresado se normaliza al formato configurado mediante fmtDate(); si el formato no es valido, se muestra una ayuda compacta bajo el input.'],
        ],
    ],

    [
        'version' => '1.53.14',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Header: integrado un card compacto del residente seleccionado arriba de cdPatientName, reutilizando el lenguaje visual de cd-res-full-card / cd-res-avatar-card. Muestra mini-foto (o placeholder) y nombre completo para identificar claramente al residente activo en todo momento.'],
            ['tag' => 'UX', 'text' => 'El card compacto se sincroniza con el selector de residente, con la carga de Ficha y con cambios/eliminacion de foto del residente. RESIDENTES bootstrap ahora incluye foto_path.'],
            ['tag' => 'Layout', 'text' => '--cd-header-h actualizado para reflejar el header mas alto y mantener alineados elementos fijos dependientes del header.'],
        ],
    ],

    [
        'version' => '1.53.13',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Expediente: cd-exp-filters-row2 ahora respeta el formato de fecha configurado en cfgFechaFormato (APP_DATE_FMT). Los filtros Desde/Hasta dejaron de excluir el helper de fecha y ahora muestran el hint formateado bajo cada input.'],
            ['tag' => 'Fix', 'text' => 'Expediente: eliminado formato fijo interno "DD Mes YYYY" en fmtDateShort(); separadores de timeline, detalle de documento y selector de enlaces ahora usan fmtDate(), respetando d/m/Y, d-m-Y, m/d/Y o Y-m-d.'],
            ['tag' => 'Config', 'text' => 'Al guardar Configuracion > General > Formato de fecha, se refrescan los hints de inputs date ya renderizados sin recargar la pagina.'],
        ],
    ],

    [
        'version' => '1.53.12',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Bottom bar: el anillo del boton Inicio elevado ahora usa contraste invertido segun tema: negro (#111) en tema claro y blanco (#fff) en tema oscuro. El fondo del icono se mantiene solido con var(--cd-surface).'],
        ],
    ],

    [
        'version' => '1.53.11',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Bottom bar: el boton Inicio elevado, cuando no esta activo, deja de usar fondo transparente y ahora usa var(--cd-surface) en tema claro y oscuro. Mantiene el realce posicional sin adoptar el contraste del boton seleccionado.'],
        ],
    ],

    [
        'version' => '1.53.10',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'Capacitor', 'text' => 'Correccion definitiva del contraste de la StatusBar: ajustado el mapeo real de @capacitor/status-bar (LIGHT = texto/iconos oscuros para fondos claros; DARK = texto/iconos claros para fondos oscuros). applyTheme() ahora usa LIGHT en tema claro y DARK en tema oscuro. La configuracion inicial nativa arranca con LIGHT + backgroundColor #ffffff para evitar reloj/notificaciones/bateria blancos sobre barra clara antes de cargar JS.'],
        ],
    ],

    [
        'version' => '1.53.9',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Bottom bar: el boton Inicio (viewResidentes) queda siempre elevado como referencia posicional central aunque no este seleccionado. En estado no activo mantiene fondo transparente, borde del surface y sombra suave; el boton seleccionado conserva el contraste de color normal del estado active.'],
        ],
    ],

    [
        'version' => '1.53.8',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'Capacitor', 'text' => 'Barra superior del sistema corregida para tema claro/oscuro: agregado @capacitor/status-bar y sincronizacion runtime desde applyTheme(). En tema claro se fuerza StatusBar style DARK (texto/iconos oscuros sobre fondo blanco); en tema oscuro se fuerza style LIGHT (texto/iconos claros sobre fondo oscuro). Tambien se define backgroundColor y overlaysWebView=false para evitar que reloj/senal/bateria queden invisibles.'],
        ],
    ],

    [
        'version' => '1.53.7',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Registros: agregado boton "Volver" en el encabezado de la seccion, con flecha izquierda y estilo cd-form-back. El boton regresa al nuevo Inicio (viewResidentes cuando esta disponible) y conserva fallback a Cuidados para roles sin acceso a Residentes.'],
        ],
    ],

    [
        'version' => '1.53.6',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Navegacion principal reorganizada: Residentes pasa a ser el nuevo Inicio; el Inicio anterior (dashboard de registro) ahora se llama Cuidados; en el bottom bar, Inicio queda en posicion central para el layout de 6 botones (Cuidados, Registros, Medicacion, Inicio, Ficha, Expediente), dejando 3 botones a la izquierda y 2 a la derecha. El estado inicial usa viewResidentes cuando el rol tiene permiso, con fallback a Cuidados u otra seccion disponible.'],
        ],
    ],

    [
        'version' => '1.53.5',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Contraste corregido en dashboard para tema oscuro: los iconos PNG de .cd-cat-icon-img ahora usan brightness(0) invert(1) opacity(.9), evitando que food.png, terapia.png, moon-zzz.png, pill.png y handwash.png se vean negros sobre tarjetas oscuras. En tema claro se mantiene el tint negro suave existente.'],
        ],
    ],

    [
        'version' => '1.53.4',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Contraste de iconos PNG en tema claro y oscuro: timeline (.cd-tl-icon img) ahora tintea cada PNG al color de su categoria (alimentacion ambar, higiene cian, terapia rosa, eliminacion rojo) igual que ya lo hacian sueno/medicacion/movilidad/comportamiento. Sidebar de residentes (.cd-sb-vitals-table th img) usa brightness(0) opacity(.65) en claro y brightness(0) invert(1) opacity(.75) en oscuro. Hint de sueno (.cd-sleep-hint-row img) invertido a blanco en oscuro.'],
        ],
    ],

    [
        'version' => '1.53.3',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Iconos PNG oficiales aplicados a mas categorias para uniformar estilo grafico: moon-zzz.png para Sueño (dashboard, timeline tlIcon -antes night.png-, sidebar fila Sueño pendiente y counts, hint sleep_crosses_midnight en cd-forms); pill.png para Medicacion (dashboard, sidebar Medicaciones pendientes y counts); handwash.png para Higiene (dashboard, timeline tlIcon, sidebar counts -antes shower.png-). Sin impacto en datos clinicos/PHI.'],
        ],
    ],

    [
        'version' => '1.53.2',
        'date'    => '2026-04-28',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Iconos PNG oficiales food.png y terapia.png aplicados a las categorias Alimentacion y Terapia en: dashboard (cd-cat-btn), timeline (tlIcon en cd-core.js.php) y sidebar de residentes (tabla de counts). Reemplazan SVG inline y material-symbols previos para uniformar estilo con el resto de iconos PNG (walk, toilet, head-ia).'],
        ],
    ],

    [
        'version' => '1.53.1',
        'date'    => '2026-04-27',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Referencias de Signos Vitales reestructuradas para PRIORIZAR Normas Oficiales Mexicanas (NOM): bloque destacado "Marco normativo aplicable" con NOM-031-SSA3-2012 (asistencia social adultos mayores) y NOM-004-SSA3-2012 (expediente clinico). Cada signo vital cita su NOM: Temp/FR -> NOM-004 + GPC CENETEC; FC/PA -> NOM-030-SSA2-2009; SpO2 -> GPC CENETEC EPOC/IRA; Glucosa -> NOM-015-SSA2-2010; Peso/IMC -> NOM-008-SSA3-2017 + NOM-043-SSA2-2012. Enlaces a gob.mx/salud y cenetec-difusion.com/CMGPC.'],
            ['tag' => 'i18n', 'text' => 'Nueva clave vitals_refs_framework. vitals_refs_title y vitals_refs_intro actualizadas para mencionar NOM y CENETEC (es/en).'],
            ['tag' => 'UI', 'text' => 'Item .cd-vref-framework destacado con fondo azul tenue y borde acentuado para sobresalir del resto de la lista de referencias.'],
        ],
    ],

    [
        'version' => '1.53.0',
        'date'    => '2026-04-27',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Cumplimiento Apple App Store Guideline 1.4.1 (Safety/Physical Harm): Signos Vitales ahora incluye citas clinicas. Asterisco (*) en el hint abre seccion colapsable cd-vitals-refs (id cdVitalsRefs) con rangos normales y fuentes oficiales: Mayo Clinic (temperatura), Cleveland Clinic (FR), AHA (FC), ACC/AHA 2017 + NOM-030-SSA2 (PA), WHO Pulse Oximetry (SpO2), ADA Standards 2024 + NOM-015-SSA2 (glucosa), WHO BMI + NOM-008-SSA3 (peso/IMC). Cada referencia tiene enlace al sitio oficial.'],
            ['tag' => 'i18n', 'text' => 'Nuevas claves vitals_refs_title, vitals_refs_intro, vitals_refs_source, vitals_refs_disclaimer en es/en.'],
            ['tag' => 'UI', 'text' => 'cd-vitals-refs: details/summary con badge "*" en pill azul, lista de rangos en monoespaciada, enlaces subrayados, disclaimer destacado, soporte dark mode.'],
        ],
    ],

    [
        'version' => '1.52.41',
        'date'    => '2026-04-27',
        'changes' => [
            ['tag' => 'UI', 'text' => 'cd-res-med-name vuelve a color neutral (var(--cd-text-primary)) en todos los estados. El color de estado se aplica solo al icono y al status de la fila para mantener legibilidad del nombre y no competir con los chips de horario.'],
        ],
    ],

    [
        'version' => '1.52.40',
        'date'    => '2026-04-27',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Texto "Día anterior" reemplazado por "Ayer" en cd-event-date-chip (incluido viewFormSueno), en el toggle cdSleepStartDayToggle y en el hint cdSleepDateHint ("Inicio marcado como ayer").'],
        ],
    ],

    [
        'version' => '1.52.39',
        'date'    => '2026-04-27',
        'changes' => [
            ['tag' => 'UI', 'text' => 'cd-res-med-name se colorea segun el estado de la fila alineado con cd-res-med-chip: overdue/soon/expired AMBAR (#78350f / #fde68a dark), done/ok verde (#14532d / #bbf7d0), partial azul (#1e3a8a / #bfdbfe), off gris muted. Icono y status de fila overdue tambien pasan a ambar (antes rojo).'],
        ],
    ],

    [
        'version' => '1.52.38',
        'date'    => '2026-04-27',
        'changes' => [
            ['tag' => 'UI', 'text' => 'cd-event-date-chip: cuando la fecha es el día en curso, muestra el nombre del día capitalizado (ej. "Lunes · 27-04-2026") en vez de "Hoy · ...". Solo informativo del día actual.'],
        ],
    ],

    [
        'version' => '1.52.37',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Familiares: cd-res-fam-rel convertido en badge pill al extremo derecho (color primario, fondo translucido, borde suave); se quito el "·". cd-res-fam-head usa space-between.'],
            ['tag' => 'UI', 'text' => 'Familiares: icono oficial de WhatsApp junto al teléfono cuando el numero tiene 10+ dígitos. Click abre wa.me/<num> con autoprefijo 52 para celulares MX.'],
            ['tag' => 'UI', 'text' => 'cd-res-mgmt-subcard fondo blanco (#fff) en tema claro para mayor contraste; en dark se mantiene --cd-bg.'],
            ['tag' => 'UI', 'text' => 'Animación de expansión del subcard más lenta: max-height 0.9s -> 1.4s, opacity 0.65s -> 1.0s, margin/border 0.55s -> 0.85s.'],
            ['tag' => 'UI', 'text' => 'cd-res-med-chip recoloreado: admin verde, overdue ÁMBAR (warning, antes rojo), pending NEUTRO con fondo --cd-bg + fuente negra. Bordes añadidos para mejor definicion.'],
        ],
    ],

    [
        'version' => '1.52.36',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Botón cd-res-med-goto adopta .cd-btn-add (primario) y usa el SVG estándar de medicación (píldora) del dashboard. Icono a la izquierda del texto.'],
            ['tag' => 'UI', 'text' => 'cd-res-subtabs: fondo del contenedor más contrastado (color-mix --cd-text 6%) + sombra inset; pill activa con sombra más pronunciada (3 capas) para look flotante claro.'],
        ],
    ],

    [
        'version' => '1.52.35',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: cd-res-subtabs ahora usa el estilo pill de cd-cfg-tabs (border-radius 999px, pill animada con cubic-bezier .34,1.56,.64,1, color primario en activo, count badge relleno). JS posiciona el pill con offsetLeft + offsetWidth al render y al click.'],
        ],
    ],

    [
        'version' => '1.52.34',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Stickybar compacta: aplicada la fuente del sitio (var(--cd-font) = DM Sans/Inter). Botón Cerrar rehecho con el estilo .cd-btn-add (primario): fondo --cd-primary, padding 8px 16px, border-radius suave, hover oscurecido, active scale. Icono 14x14.'],
        ],
    ],

    [
        'version' => '1.52.33',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar aparecía desfasada y se movía con el hover de la card. Causa: .cd-res-mgmt-card usa transform en hover/active, lo que crea un containing block para position:fixed según el spec CSS. Solución: appendear la barra al <body> con data-card-id; ahora fixed usa el viewport real.'],
        ],
    ],

    [
        'version' => '1.52.32',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar no aparecía al scrollear: ahora detectamos el scroller real subiendo por ancestros (overflow-y auto/scroll/overlay + scrollHeight > clientHeight) y atamos el listener en document con capture:true para atrapar scroll de cualquier ancestro. Además 3 checks diferidos tras expandir.'],
        ],
    ],

    [
        'version' => '1.52.31',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar dejaba hueco vacío dentro del subcard (position:sticky sigue en flujo). Cambiada a position:fixed con top/left/width calculados por JS desde card.getBoundingClientRect(). Sin .is-visible no ocupa espacio. También se oculta cuando la card sale por abajo del viewport.'],
        ],
    ],

    [
        'version' => '1.52.30',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar quedaba flotando muy abajo. .cd-main es el scroller y empieza bajo el header, por lo que el top de position:sticky es relativo al inicio de .cd-main. Cambiado de top: calc(--cd-header-h + 6px) a top: 6px. JS calcula el umbral con scroller.getBoundingClientRect().top.'],
        ],
    ],

    [
        'version' => '1.52.29',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Stickybar compacta se desprende cuando .cd-res-mgmt-head (avatar + nombre + datos principales) sale del viewport por arriba, no sólo cuando la foto principal queda oculta. Transición más natural.'],
        ],
    ],

    [
        'version' => '1.52.28',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Stickybar compacta ahora flota sobre toda la card (cd-res-mgmt-card), no sólo sobre el subcard: negativos margin -11px -15px = padding card + borde subcard, width calc(100% + 30px), border-radius grande, sombra más pronunciada.'],
        ],
    ],

    [
        'version' => '1.52.27',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar compacta: el scroll real ocurre en .cd-main (overflow-y:auto), no en window. El scroll listener estaba en window y nunca disparaba. Ahora se ata al contenedor real y la barra aparece/desaparece correctamente según la posición de la foto principal.'],
            ['tag' => 'UI',  'text' => 'Removida la leyenda de chips (cd-res-legend) de la cabecera de Residentes — cada card ya muestra sus propios indicadores. CSS y HTML asociado también eliminado.'],
        ],
    ],

    [
        'version' => '1.52.26',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar compacta no aparecía al scrollear: el IntersectionObserver con rootMargin negativo no disparaba fiable. Reemplazado por scroll listener con requestAnimationFrame throttle que verifica directamente getBoundingClientRect().bottom del avatar contra la altura del header.'],
            ['tag' => 'UI',  'text' => 'Listener se remueve limpiamente al colapsar la card. Estado inicial calculado al instalar.'],
        ],
    ],

    [
        'version' => '1.52.25',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Stickybar compacta del residente solo flota cuando el avatar principal sale del viewport (queda oculto bajo el header). Al scrollear de regreso y volver a ver la foto principal, la stickybar desaparece con fade + translate para no duplicar información.'],
            ['tag' => 'UI', 'text' => 'Implementado con IntersectionObserver sobre .cd-res-mgmt-avatar (rootMargin negativo = altura del header). Estado controlado con clase .is-visible. Observer se desconecta al colapsar la card.'],
        ],
    ],

    [
        'version' => '1.52.24',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Stickybar de residente no se veía. Tres causas: (1) overflow:hidden de la subcard confinaba el position:sticky → ahora overflow:visible al expandir; (2) IntersectionObserver sobre sentinel nunca disparaba en muchos casos → eliminado, la barra siempre visible al expandir con animación cdStickybarIn; (3) _resLoadExpand sobrescribía innerHTML borrando la barra → ahora se instala después del then() de la carga.'],
            ['tag' => 'UI',  'text' => 'Stickybar se elimina al colapsar y se recrea al re-expandir (datos frescos garantizados).'],
        ],
    ],

    [
        'version' => '1.52.23',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Auto-scroll al expandir card de residente: al pulsar Ver más, scroll suave a top de la card (- header_h - 8px) para enfocar la información desplegada.'],
            ['tag' => 'UI', 'text' => 'Nueva stickybar (reemplaza sticky de avatar+head): se inyecta al expandir con mini-avatar 38x38 rounded-12, nombre con ellipsis, y botón Cerrar que colapsa + scrollea de vuelta. Aparece con fade-in solo al scrollear más allá del header (IntersectionObserver sobre sentinel) y se oculta cuando la card sale del viewport. Variantes light/dark.'],
            ['tag' => 'UI', 'text' => 'El avatar grande 168x168 y el nombre original ya NO se vuelven sticky (eliminada obstrucción por la foto grande); la stickybar compacta es lo único fijo.'],
        ],
    ],

    [
        'version' => '1.52.22',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Sub-card Ver más: transición notablemente más lenta y cinematográfica (max-height 0.9s, opacity 0.65s, margin/border 0.55s, chevron 0.7s).'],
            ['tag' => 'UI',  'text' => 'Sticky header en card de residente expandida: foto (avatar 168x168) y nombre quedan fijos al tope (top: header_h + 8px) mientras se scrollea por la información expandida (familiares / medicaciones / nota médica). Nombre con fondo cd-surface + blur 4px.'],
            ['tag' => 'Fix', 'text' => 'Botón Ir a Medicación (cd-res-med-goto) no funcionaba: orden incorrecto (change → showView) hacía break en reloadCurrentView. Ahora showView va primero, luego change/loadDashboard, y polling 80ms x 20 intentos para clickear .cd-cat-btn[data-cat=medicacion] cuando esté disponible.'],
            ['tag' => 'UI',  'text' => 'Contraste cd-nm-card.cd-res-nm-card: background var(--cd-bg-card) era variable inexistente (transparente). Ahora var(--cd-surface) en light y #1f1f1f en dark con borde + sombra sutil que destaca la nota sobre el panel cd-bg.'],
        ],
    ],

    [
        'version' => '1.52.21',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Sub-card Familiares: cd-res-fam-meta con íconos (correo/teléfono) en grid uniforme de 2 filas. Placeholders "Sin correo"/"Sin teléfono" mantienen altura idéntica entre residentes.'],
            ['tag' => 'UI',  'text' => 'Sub-card Familiares: cada cd-res-fam-item muestra parentesco junto al nombre (· Hija, · Hijo, etc.).'],
            ['tag' => 'API', 'text' => 'cuidados.php ?dashboard=1: residente.familiares_usuarios ahora incluye parentesco, matcheado contra contactos_json (por email/teléfono/nombre).'],
            ['tag' => 'UI',  'text' => 'Vista Residentes: título cambia de "Estado de Cuidados de Residentes" a "Residentes".'],
            ['tag' => 'UI',  'text' => 'Sub-card Medicaciones: se eliminan las 3 secciones; ahora una sola lista ordenada por prioridad (vencidas → pendientes → completadas → inactivas).'],
            ['tag' => 'UI',  'text' => 'Sub-card Medicaciones: cd-res-med-tbl-status muestra cada horario como chip coloreado (admin/overdue/pending/skip/off).'],
            ['tag' => 'UI',  'text' => 'Sub-card Medicaciones: nuevo header con título y botón "Ir a Medicación →" que abre la categoría Medicación del residente actual.'],
            ['tag' => 'UI',  'text' => 'Nota médica del residente: la sección de Signos Vitales se mueve al final del cd-nm-card.cd-res-nm-card (después de SOAP y diagnósticos).'],
        ],
    ],

    [
        'version' => '1.52.20',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Sub-card Ver más: animación de expand fluida (transición max-height + opacity + margin + border, 550ms cubic-bezier) en vez del fade rígido. Chevron suaviza rotación a 420ms.'],
            ['tag' => 'UI', 'text' => 'Sub-card Medicaciones: lista simplificada como tabla compacta estilo cd-nm-sv-table, agrupada en Pendientes hoy / Administradas hoy / Sin pendientes hoy. Cada fila: ícono estado + nombre/dosis + n/Total + sub-texto. Tooltip con horarios e indicaciones.'],
            ['tag' => 'UI', 'text' => 'Transiciones más visibles: slides 280→420ms, fade-up 220→380ms (translateY 10px), fade-in 180→300ms.'],
        ],
    ],

    [
        'version' => '1.52.19',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Sistema global de transiciones: nuevas animaciones cd-fade-up-in (220ms) y cd-fade-in (180ms) reutilizables.'],
            ['tag' => 'UI', 'text' => 'showView aplica fade-up por defecto en cualquier cambio de vista (Inicio, Registros, Residentes, Reportes, Config) además de los slides existentes en formularios.'],
            ['tag' => 'UI', 'text' => 'Pestañas de Ficha (Info/Familia/Notificaciones): el panel hace fade-up al cambiar de tab.'],
            ['tag' => 'UI', 'text' => 'Sub-card Ver más en Residentes: cada tab (Familiares/Medicaciones/Nota) hace fade-up al cambiar.'],
            ['tag' => 'UI', 'text' => 'Respeto a prefers-reduced-motion: animaciones desactivadas automáticamente para usuarios que lo solicitan.'],
        ],
    ],

    [
        'version' => '1.52.18',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes · Sub-card Nota médica: ahora reutiliza el render SOAP completo de cd-nm.js (Signos Vitales, Subjetivo, Objetivo, Análisis, Plan, Dx CIE-10) idéntico al Expediente.'],
            ['tag' => 'UI', 'text' => 'Residentes · Sub-card Medicaciones: lista simplificada con chips de dosis del día — administradas (✓ verde), pendientes vencidas (⚠ rojo), programadas (ámbar), omitidas (tachadas). Badge por medicamento: Completada hoy / X de Y hoy / X pendientes / Inactiva / Vencida.'],
            ['tag' => 'UI', 'text' => 'Residentes · Sub-card Familiares: nuevo botón de campana por familiar que abre Ficha → Notificaciones con el panel del contacto correspondiente desplegado automáticamente.'],
            ['tag' => 'API', 'text' => '_resLoadExpand lee registros de medicación del día (horarios_cubiertos, medicamentos_no_administrados) para calcular el estado de cada dosis en tiempo real.'],
        ],
    ],

    [
        'version' => '1.52.17',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Residentes · Ver más: lazy-load corregido. Llamaba a /api/cuidados.php?id=X pero el endpoint requiere ?dashboard=1&residente_id=X&fecha=Y → mostraba "No se pudo cargar la información".'],
        ],
    ],

    [
        'version' => '1.52.16',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes (cards): padding derecho de 18px en la lista de info para que los valores no toquen el borde (matching imagen de referencia).'],
            ['tag' => 'UI', 'text' => 'Residentes (cards): row-gap reducido (8px → 4px) y padding-right de 36px en meta para acercar el nombre con su meta-info.'],
            ['tag' => 'UI', 'text' => 'Residentes: nuevo botón "Ver más / Ver menos" con chevron animado que despliega una sub-card con tab-bar.'],
            ['tag' => 'UI', 'text' => 'Sub-card · Familiares: lista con avatar de iniciales, email/teléfono como links y botones de acción (llamar/correo).'],
            ['tag' => 'UI', 'text' => 'Sub-card · Medicaciones: cada medicamento coloreado por estado (Activa, Programada/Por terminar, Inactiva, Vencida) con tira lateral + pill de status + detalle de horario/periodo/indicaciones.'],
            ['tag' => 'UI', 'text' => 'Sub-card · Nota médica: nota vigente con badge "Vigente" y parsing automático de SOAP (S/O/A/P) si el cuerpo es JSON.'],
            ['tag' => 'API', 'text' => 'Lazy-load de la sub-card: datos pedidos solo al abrir y cacheados por residente (_resExpandCache). Endpoints: /api/cuidados.php y /api/prescripciones.php.'],
        ],
    ],

    [
        'version' => '1.52.15',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes (info): layout justificado con label a la izquierda (64px) y valor alineado a la derecha.'],
            ['tag' => 'UI', 'text' => 'Residentes (info): múltiples Dx separados por coma se muestran en líneas propias; etiqueta "Dx:" solo en la primera.'],
            ['tag' => 'UI', 'text' => 'Residentes (info): orden cambiado a Médico, Dx, Alergias.'],
            ['tag' => 'UI', 'text' => 'Residentes (info): "negadas" reconocido como sin alergias (verde). Valores de alergias en negrita y color destacado.'],
        ],
    ],

    [
        'version' => '1.52.14',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: badge de estado movido al renglón de los stats (row 4) y columna de la foto (col 1), centrado.'],
        ],
    ],

    [
        'version' => '1.52.13',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: badge de estado movido al renglón debajo de la foto, centrado en un wrapper .cd-res-mgmt-avatar-wrap.'],
            ['tag' => 'UI', 'text' => 'Residentes: removidos los iconos SVG de la lista de info (alergias / médico / diagnóstico). Ahora cada item es "Label: valor" en texto plano.'],
        ],
    ],

    [
        'version' => '1.52.12',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: alergias / médico / diagnóstico ahora como pequeña lista vertical (icono + label en negrita + valor), sin badges. Alergias en rojo (verde si sin alergias) solo en texto, sin fondo.'],
        ],
    ],

    [
        'version' => '1.52.11',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: avatar duplicado a 168px (rounded square 24px), móvil 110px. Ocupa 3 filas del grid alineado al inicio.'],
            ['tag' => 'UI', 'text' => 'Residentes: nueva fila de info chips entre meta y stats con alergias (rojo o verde si no conocidas), médico responsable y diagnóstico (truncado con tooltip).'],
            ['tag' => 'UI', 'text' => 'Residentes: icono de heces corregido. Cambiado de <img> con mask a <span> con mask-image + background-color: currentColor para adoptar el color de la fuente del badge.'],
            ['tag' => 'API', 'text' => 'api/residentes.php (?cuidados_estado): añade alergias, diagnostico, medico_nombre al payload.'],
            ['tag' => 'Seguridad', 'text' => 'PHIPA: alergias y diagnostico (PHI) limitados a roles clínicos ya autorizados (superadmin/admin/medico/enfermero), mínimo necesario.'],
        ],
    ],

    [
        'version' => '1.52.10',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: avatar agrandado de 48px circular a 84px rounded-square (18px radius) según la referencia visual. En móvil 64px.'],
            ['tag' => 'UI', 'text' => 'Residentes: icono de heces ahora usa CSS mask-image con currentColor, asi adopta el mismo color que la fuente del badge en claro y oscuro.'],
        ],
    ],

    [
        'version' => '1.52.9',
        'date'    => '2026-04-25',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes (cards): sombra suave en cada card con realce al hover y dark mode más profundo.'],
            ['tag' => 'UI', 'text' => 'Residentes (badges): fondo semi-transparente + borde tintado + texto coloreado en lugar del fondo sólido anterior — mejor legibilidad.'],
            ['tag' => 'UI', 'text' => 'Residentes (stats): movidos a una línea independiente debajo del nombre y meta, con separador punteado superior. Layout: izquierda cuidados+stock, spacer, derecha medicación+alertas+signos+sueño+evacuación.'],
        ],
    ],

    [
        'version' => '1.52.8',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: revertido el rediseño tipo panel (v1.52.0–v1.52.7). Vuelve al layout horizontal compacto previo: avatar 48px a la izquierda, nombre + badge de estado + subtítulo + pills de stats, kebab a la derecha. Se restaura la leyenda de chips (cd-res-legend) en la cabecera de la vista.'],
        ],
    ],

    [
        'version' => '1.52.7',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: el kebab ahora se ancla a la esquina superior derecha de la tarjeta (10px desde top/right) en vez de la esquina del hero. La card es position:relative para servir de contexto al overlay. Hero perdio el margen-top vacio.'],
        ],
    ],

    [
        'version' => '1.52.6',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: removido cd-res-panel-top. El kebab ahora se posiciona en absoluto sobre el hero (esquina superior derecha del avatar) con fondo cd-surface y borde para contraste. Reduce ~36px de espacio vertical vacio en cada tarjeta.'],
        ],
    ],

    [
        'version' => '1.52.5',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Bottom bar: removido el boton "Config" del nav inferior. Reubicado al header como cd-icon-btn (icono settings de Material Symbols) a la derecha del toggle de tema y antes del logout. Respeta $canSecConfiguracion.'],
            ['tag' => 'UI', 'text' => 'El boton del header se marca activo (cd-icon-btn--active, fondo color de acento) cuando la vista actual es viewConfig, sincronizado por showView(). Click handler replica las mismas confirmaciones de salida (medicacion, notas medico, care form dirty) que los botones del nav inferior.'],
        ],
    ],

    [
        'version' => '1.52.4',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: simplificado el header de la tarjeta (cd-res-panel-top) — ahora solo contiene el boton kebab alineado a la derecha. Removidos el avatar mini y el nombre duplicado (el nombre ya aparece grande debajo del hero).'],
        ],
    ],

    [
        'version' => '1.52.3',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: las filas de la tarjeta (cd-res-panel-row) ahora son una lista plana separada por hairlines (border-bottom 1px) en vez de cajas con borde individual. Reduce ~40% el alto vertical de cada tarjeta sin perder claridad. El color de severidad se mueve al icono (rojo/ambar/verde).'],
            ['tag' => 'UI', 'text' => 'Mobile (<=480px): grid a 1 columna, hero 96px, titulo 1.0625rem, filas 32px de alto minimo, padding lateral reducido y boton Ver full-width.'],
        ],
    ],

    [
        'version' => '1.52.2',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Residentes: las tarjetas nuevas se renderizaban desfazadas (avatar grande flotando fuera, boton Ver suelto a la derecha) porque las reglas viejas de .cd-res-mgmt-card (display:grid 3 columnas) y .cd-res-mgmt-menu-wrap (grid-column:3) ganaban por orden en el archivo. Fix: reglas con doble especificidad .cd-res-mgmt-card.cd-res-panel que fuerzan display:flex column y resetean el grid; override del menu-wrap dentro de .cd-res-panel-top con grid-column/row:auto + margin-left:auto.'],
        ],
    ],

    [
        'version' => '1.52.1',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: tarjetas mas parecidas al referente Gemini. Titulo del nombre en mayusculas + bold (1.25rem). Subtitulo (chip de estado + edad/sexo/habitacion) solo se muestra si el residente no esta activo o si hay datos. Filas (cd-res-panel-row) mas compactas: padding 7px 12px, gap 6px, min-height 40px, etiqueta+detalle en linea, iconos 17px, indicador 20px.'],
        ],
    ],

    [
        'version' => '1.52.0',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: rediseno de tarjetas estilo "panel" (inspirado en el modal de apps conectadas de Gemini). Cada residente se muestra en una tarjeta vertical con header (avatar mini + nombre + kebab), avatar circular grande, titulo + chip de estado + edad/sexo/habitacion, filas con badges integrados (Medicacion, Alertas medicas, Signos vitales, Sueno, Eliminacion, Inventario, Cuidados hoy) cada una con icono + etiqueta + detalle + indicador OK/Atencion/Critico, y un boton "Ver" full-width que navega al Inicio con el residente seleccionado.'],
            ['tag' => 'UI', 'text' => 'Removida la leyenda de chips superior (cd-res-legend) — cada fila explica su semantica con su propio indicador. Grid responsivo (auto-fill minmax(320px,1fr); 1 columna en <=480px).'],
        ],
    ],

    [
        'version' => '1.51.61',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'Config', 'text' => 'Configuración → Equipo → Personal: el "último acceso" en las tarjetas .cd-cfg-user-info ahora se formatea con el timezone configurado de la institución (cfgTimezone / APP_TZ) y el formato de fecha (APP_DATE_FMT) vía el helper fmtDateTime(). Antes se mostraba el timestamp crudo del servidor (UTC sin formato).'],
            ['tag' => 'Config', 'text' => 'Auditoría TZ similar: corregidas las tarjetas de Invitaciones (fecha de expiración en lista + sidebar de detalle con fmtTs ahora pasa timeZone: APP_TZ a Intl), Notificaciones (lista de notificaciones y tooltip fechaFull, más el fallback de _timeAgo a >7 días) e Historial ARCO (fechas de solicitudes en ambos paneles). Todas explicitan APP_TZ ahora.'],
            ['tag' => 'Note',  'text' => 'Logs (cd-logs.js) y Sesiones (cd-sessions.js) ya usaban fmtDateTime desde versiones anteriores — sin cambios.'],
        ],
    ],

    [
        'version' => '1.51.60',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Matriz de Roles: los cambios ahora se reflejan INMEDIATAMENTE en las sesiones de otros usuarios. La caché de configuración (5 min por sesión) hacía que enfermeros/médicos/familiares con sesión abierta siguieran viendo el JSON viejo después de que el admin guardara la matriz — por eso parecía que habilitar "ver_seccion_medicacion" no surtía efecto. Ahora `Configuracion::clearCache()` toca el mtime de `conf/.cfg_v_<inst>` y `getCached()` lo compara contra el timestamp cacheado para invalidar entre sesiones sin queries extra.'],
            ['tag' => 'Fix', 'text' => 'Matriz de Roles: cerrado bypass en Inicio. Los atajos rápidos "Ver registros" y "Medicación del día", y las categorías "Ficha" y "Notificaciones" del dashboard ahora respetan `ver_seccion_registros`, `ver_seccion_medicacion` y `ver_seccion_ficha` respectivamente. Antes un rol con `ver_cuidados_grid` o `hacer_registros` podía entrar a esas vistas desde el Inicio aunque la sección estuviera apagada en la matriz.'],
        ],
    ],

    [
        'version' => '1.51.59',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'Cifrado', 'text' => 'inventario_items.notas marcada como PHI (Protected Health Information / Información de Salud Protegida) en EncryptionMap. Modelo Inventario.php usa el valor cifrado en INSERT/UPDATE. La columna no se usa en WHERE/LIKE/ORDER BY — cifrado seguro. Activar por entorno desde Superadmin → Auditoría BD → Cifrado: marcar el campo y correr las 4 fases.'],
        ],
    ],

    [
        'version' => '1.51.58',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'Cifrado', 'text' => 'Superadmin → Auditoría BD → Cifrado: ahora se puede REPARAR drift entre encryption_state.json y la BD real (caso típico: JSON dice consolidated pero las columnas _enc siguen existiendo porque la migración DDL no corrió en todos los tenants). Nuevo flag `drift` por campo en encryption_status, insignia ⚠️ en la UI, toggle "Modo forzar" que habilita los 4 botones (Preparar/Migrar/Activar/Consolidar) sobre cualquier fase y envía force=true al backend, selector inline "Fijar fase…" para editar manualmente la fase guardada (nueva acción encryption_set_phase). El guard de no-pérdida de PHI se mantiene incluso en Modo forzar: el DROP de la columna original se rechaza si hay filas con texto plano y _enc en NULL.'],
        ],
    ],

    [
        'version' => '1.51.57',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'IA', 'text' => 'Reporte del día — prompt enriquecido con (1) la NOTA MÉDICA VIGENTE del residente (notas_medico estado=vigente) para que la IA contraste lo registrado contra el plan del médico, y (2) ALERTAS DE ELIMINACIÓN con horas desde el último registro de Heces y Orina (heces ≥12h monitoreo / ≥24h crítica; orina ≥8h monitoreo / ≥12h crítica). Las críticas se inyectan obligatoriamente en la sección 🔴 ALERTAS PRIORITARIAS. Aplica a cron/notificaciones.php (envío automático) y api/reportes.php (botón manual). Aviso PHIPA/NOM: la nota médica es PHI y viaja al proveedor IA configurado por la institución; transferencia ya cubierta por el aviso de privacidad LFPDPPP §3.'],
        ],
    ],

    [
        'version' => '1.51.56',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes — reorganización de los cards: fila 1 avatar | nombre | kebab; fila 2 nueva .cd-res-mgmt-meta con badge de estado + edad·sexo·habitación a la izquierda y los contadores (medicación, signos fuera de rango, sueño pendiente, heces, inventario, actividad de hoy) alineados a la derecha en la misma línea. Reduce altura del card y mejora el balance visual.'],
        ],
    ],

    [
        'version' => '1.51.55',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Cuidados — Formularios de registro: el bloque .cd-form-group.cd-event-time-group (alimentación, higiene, eliminación, comportamiento, movilidad, signos vitales, sueño, medicación, terapia, incidente) ahora muestra un chip con el día del registro formateado según Configuración > General > Formato de fecha. Estados: "Hoy · DD/MM/YYYY" cuando _fecha=hoy, "Ayer · …" cuando es ayer, solo la fecha en otros días. Inyectado dinámicamente al abrir el form y actualizado al cambiar la fecha en la barra global.'],
            ['tag' => 'Sueño', 'text' => 'viewFormSueno: el chip de fecha respeta el toggle cdSleepStartDayToggle (Hoy / Día anterior) usando _sleepFecha(), porque el registro de sueño se ancla al día en que comenzó. Con "Día anterior" el chip cambia a estilo ámbar.'],
        ],
    ],

    [
        'version' => '1.51.54',
        'date'    => '2026-04-24',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Cipher::decrypt() inundaba php_errors.log en producción con "decrypt failed — clave incorrecta o datos corruptos (len=…)" cuando un valor PARECÍA base64 válido pero era texto plano sin migrar (transición gradual a AES-256-GCM). La app ya retornaba el valor original sin romperse; solo era ruido. Ahora el log está silenciado por defecto y se activa solo con CIPHER_DEBUG=1 en conf/.env (con dedupe + caller).'],
            ['tag' => 'Fix', 'text' => 'api/personal.php fallaba con "Table arco_solicitudes doesn\'t exist" al consultar historial ARCO. La tabla solo se creaba con db/migrate_arco.php (standalone) y no estaba en db/expected_schema.php, así que la Auditoría BD del superadmin no la creaba ni reparaba. Ahora forma parte del esquema esperado (scope=master) y run_full_migration la crea automáticamente. ACCIÓN REQUERIDA en producción: Superadmin → Auditoría BD → Migración total.'],
        ],
    ],

    [
        'version' => '1.51.53',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Residentes → click en card: el dashboard cambiaba de vista correctamente (v1.51.52) pero los badges (Medicación pendiente, Heces, Signos, Sueño, NM, contadores por categoría) seguían mostrando los valores del residente ANTERIOR durante 1-2s mientras llegaba la respuesta del backend. Ahora loadDashboard(silent=false) limpia _counts/_registros/_bitacoraEvents/__cdServerBadges y resetea visualmente todos los badges al iniciar, en línea con los esqueletos del timeline y RxTracker.'],
        ],
    ],

    [
        'version' => '1.51.52',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Residentes → click en card iba al dashboard pero no actualizaba la información. El change del select se disparaba con _currentView=viewResidentes (case break en reloadCurrentView), y el showView posterior no llamaba loadDashboard. Ahora showView va antes del change y, si el id es el mismo, se invoca loadDashboard manualmente.'],
        ],
    ],

    [
        'version' => '1.51.51',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Sesión cerrándose antes del timeout configurado en Configuración > Seguridad. Multi-inst: api/switch_institucion.php no refrescaba _cfg_session_timeout al cambiar de institución (usaba el valor de la institución primaria fijado en login). Y al guardar la sección seguridad en api/configuracion.php, el nuevo seg_timeout_sesion no se aplicaba hasta re-login. Ambos endpoints ahora recargan el timeout desde la config y resetean _last_activity.'],
        ],
    ],

    [
        'version' => '1.51.50',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix',      'text' => 'Badges del dashboard (medicación pendiente, horas desde última heces) podían diferir hasta en 1h del badge de la lista de Residentes cuando el timezone de la institución (ej. Etc/GMT+5) no coincidía con date_default_timezone_set del servidor (America/Mexico_City, UTC-6 todo el año desde 2022). El cliente clasificaba 22:00 como overdue mientras el servidor lo clasificaba como upcoming.'],
            ['tag' => 'Refactor', 'text' => 'Helpers _signos_out_count, _med_overdue_count, _heces_hours_since y _rx_parse_horarios extraídos a api/_badge_helpers.php (single source of truth, usado por api/residentes.php y api/cuidados.php).'],
            ['tag' => 'API',      'text' => 'GET /api/cuidados.php?dashboard=1 ahora devuelve badges:{medicacion_pendiente, signos_fuera_rango, heces_horas} calculados en TZ del servidor.'],
            ['tag' => 'JS',       'text' => 'cd-dashboard.js loadDashboard guarda data.badges en window.__cdServerBadges. renderHecesBadge prefiere heces_horas del servidor; renderRxTracker prefiere medicacion_pendiente del servidor. Resultado: dashboard y lista de Residentes muestran exactamente los mismos números, sin importar la TZ del navegador.'],
        ],
    ],

    [
        'version' => '1.51.49',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Residentes: medicacion_pendiente ahora coincide con cdMedPendingBadge del dashboard. _med_overdue_count() en api/residentes.php ahora (1) salta Rx con inicio > today, (2) cuenta Rx sin horarios como 1 pendiente si hoy no hay administración/skip.'],
        ],
    ],

    [
        'version' => '1.51.48',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Residentes: badges de las cards alineados con los iconos del dashboard (alertas médico = clipboard-cross, signos = heart-pulse e650, heces = poop.png con tono danger ≥24h). Medicación/sueño ya coincidían.'],
        ],
    ],

    [
        'version' => '1.51.47',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Tarjetas de categoría con efecto "breathing" (glow pulsante): azul en .cd-cat-btn--notas-medico cuando hay nota médica vigente; rojo en el botón de Eliminación cuando el badge de heces está en danger. Respeta prefers-reduced-motion.'],
        ],
    ],

    [
        'version' => '1.51.46',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Botón .cd-cat-btn--notificaciones del dashboard ahora muestra el logo de WhatsApp (SVG inline) con el mismo tamaño que el resto de iconos de la rejilla.'],
        ],
    ],

    [
        'version' => '1.51.45',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Botones de ayuda de notificaciones (.cd-notif-help-btn) ahora 28×28px (icono 22px) y usan el glifo Material Icons "info" filled (e88e) reproducido como SVG inline. Color primario para mayor visibilidad.'],
        ],
    ],

    [
        'version' => '1.51.44',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Visor de imagen de receta: nueva barra de herramientas con botones de rotar 90° izquierda/derecha y restablecer. La imagen se ajusta al viewport incluso cuando queda de lado (intercambia max-w/max-h en 90°/270°).'],
        ],
    ],

    [
        'version' => '1.51.43',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Fechas: todos los <input type="date"> ahora muestran debajo el valor formateado según cfgFechaFormato (APP_DATE_FMT). Implementado vía helper global _attachDateFmtHint + MutationObserver en cd-date-nav.js — cubre formularios estáticos y dinámicos (sidebar, modales). Inputs con data-no-fmt-hint="1" quedan exentos (filtros compactos).'],
        ],
    ],

    [
        'version' => '1.51.42',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Inventario: campo unidad de medida ahora es un dropdown con opciones predefinidas (Unidades, Piezas, Tabletas, Cápsulas, ml, mg, g, Frascos, Ampolletas, Sobres, Parches, Cajas) + opción "Otra…" para texto personalizado. En edición preserva valores que no estén en la lista. Nuevas lang keys: unit_*, error_unit_required.'],
        ],
    ],

    [
        'version' => '1.51.41',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'i18n', 'text' => 'Inventario: label `inv_unit` ahora es "Unidad de medida" / "Unit of measure" (antes "Unidad"/"Unit", ambiguo).'],
        ],
    ],

    [
        'version' => '1.51.40',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => '.cd-med-notes (informativo) cambiado de ámbar a azul discreto (--cd-info), para no confundirse con estados de alerta.'],
        ],
    ],

    [
        'version' => '1.51.39',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => '.cd-med-detail ahora usa var(--cd-text-muted) en lugar de #8a7656/#b89a62 (beige hardcoded), integrándose con el sistema de texto secundario del tema.'],
        ],
    ],

    [
        'version' => '1.51.38',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Registro de Medicación: píldoras de horario (.cd-med-time) ahora con colores semánticos correctos. Upcoming = NEUTRO (no warning), Overdue = ÁMBAR con bg+borde, Administered = VERDE, No-administrado = ROJO, Active = AZUL. Eliminados colores beige hardcoded #f0e6d2/#d4c4a0/#6b5530 que dejaban todo en falso warning.'],
            ['tag' => 'UX', 'text' => 'Registro de Medicación: la lista de archivados preserva su estado expandido tras actualizar/archivar/eliminar desde el sidebar. renderMedList() ahora detecta si #cdMedArchivedList estaba abierto y lo restaura + re-renderiza. También se llama renderRxTracker() en las rutas reactivar/eliminar archivados y openRxDetailSidebar.'],
        ],
    ],

    [
        'version' => '1.51.37',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Registro de Medicación: .cd-med-item ya no son amarillentos por defecto. Bg/borde cambiados de #faf4ea/#e0d1b8 (#2c2317/#5c4a28 en dark) a var(--cd-surface)/var(--cd-border). El color queda reservado para estados reales: verde administrado, ámbar overdue/upcoming, rojo skipped.'],
        ],
    ],

    [
        'version' => '1.51.36',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'renderMedList crash en producción: "Cannot read properties of null (reading toLowerCase)" cuando _rxPendingSelect.nombre era null al click en píldora del RxTracker. Cambiado sel.nombre.toLowerCase() a String(sel.nombre || \'\').toLowerCase() en el callback formMedicacion auto-select.'],
        ],
    ],

    [
        'version' => '1.51.35',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Sueño: icono .cd-sleep-hint-icon (luna) aumentado de 14px a 20px (18px en mobile ≤480px). Dark mode con mejor contraste: bg rgba(129,140,248,.22), texto #e0e7ff, <strong> #fff, filtro del icono invert(1) brightness(2).'],
        ],
    ],

    [
        'version' => '1.51.34',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Tema oscuro: botón Guardar registro cambiado de #60a5fa (contraste 2.2:1, falla WCAG AA) a #2563eb con hover #1d4ed8 (contraste 4.7:1, AA OK).'],
            ['tag' => 'UI', 'text' => 'Sueño: hint de fecha (cdSleepDateHint) en mobile ya no se ve amontonado. Cada mensaje (cruza medianoche / día anterior / fecha ajustada) es un .cd-sleep-hint-row apilado en columna con padding y line-height mejorados. Soporte dark mode con texto #c7d2fe sobre rgba(129,140,248,.16).'],
        ],
    ],

    [
        'version' => '1.51.33',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Sueño: registros que cruzan medianoche (p.ej. 21:20 → 13:20) se guardaban con la fecha del despertar y aparecían en el día equivocado de la línea de tiempo. Ahora _updateSleepHint auto-marca "Día anterior" cuando hora_fin ≤ hora_inicio (cruce de medianoche), de modo que el registro queda anclado al día en que el sueño INICIÓ. Se respeta la elección manual del cuidador si toca el toggle.'],
        ],
    ],

    [
        'version' => '1.51.32',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Roles y Permisos en mobile: headers rotados ya no se desfasan ni se cortan. Cambiado a writing-mode: vertical-rl + rotate(180deg) sobre el span interno; th con altura 96px y ancho fijo 38px.'],
        ],
    ],

    [
        'version' => '1.51.31',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Lista de medicamentos: botones .cd-med-info-btn (editar) y .cd-med-img-btn (foto de receta) más grandes (SVG 22px, padding 8px) para mejor target táctil.'],
        ],
    ],

    [
        'version' => '1.51.30',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'renderMedList / renderRxTracker (pillHtml) crasheaban con "Cannot read properties of null (reading toLowerCase)" cuando una prescripción tenía nombre null. Todas las llamadas a rx.nombre.toLowerCase() / it.nombre.toLowerCase() ahora usan String(...||\'\').toLowerCase().'],
        ],
    ],

    [
        'version' => '1.51.29',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Inicio · Notificaciones: el botón abre Ficha directo en la pestaña Notificaciones (antes abría Familia).'],
            ['tag' => 'UI', 'text' => 'Ficha · Notificaciones: el dropdown de familiar se reemplazó por una lista de cards expandibles (uno por contacto) con avatar, nombre, parentesco y canales activos.'],
            ['tag' => 'UI', 'text' => 'Configuración · Roles y Permisos: headers de columna rotados -90° en mobile (<768px) para ahorrar espacio horizontal.'],
        ],
    ],

    [
        'version' => '1.51.28',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Guardar medicamento con foto: catch genérico ya no sobrescribe el mensaje real del servidor; subida de imagen es no-fatal (la prescripción ya guardada se preserva); compressImage siempre produce nombre .jpg aunque el archivo original venga sin extensión.'],
        ],
    ],

    [
        'version' => '1.51.27',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Registros: línea de tiempo envuelta en card (.cd-records-card) con fondo, borde y sombra.'],
        ],
    ],

    [
        'version' => '1.51.26',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Badge de Incidente (y notas/notas_medico) en Inicio: renderCategoryBadges ahora itera sobre [data-cat-count] del DOM, no sobre CAT_LABELS.'],
        ],
    ],

    [
        'version' => '1.51.25',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Sesión cerrándose antes del timeout configurado: PHP gc_maxlifetime subido a 24h, columna seg_timeout_sesion migrada de TINYINT(1) a SMALLINT UNSIGNED, default unificado a 60 min, input con max=1440.'],
        ],
    ],

    [
        'version' => '1.51.24',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Login tras timeout de sesión: conserva el email y muestra aviso amable; CSRF-expired re-renderiza en sitio en vez de redirigir y limpiar el formulario.'],
        ],
    ],

    [
        'version' => '1.51.23',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Migration', 'text' => 'Backfill expediente_docs.descripcion ← JSON SOAP v:2 de notas_medico (db/migrate_backfill_expdoc_soap.php, multi-tenant, idempotente).'],
        ],
    ],

    [
        'version' => '1.51.22',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Configuración: iconos SVG a la izquierda en cada tab de #cdCfgTabs.'],
        ],
    ],

    [
        'version' => '1.51.21',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Configuración: pestaña "Equipo" renombrada a "Usuarios" (es/en).'],
        ],
    ],

    [
        'version' => '1.51.20',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Línea de tiempo: icono de incidente ahora en rojo (cd-tl-icon--incidente) con fondo tenue.'],
        ],
    ],

    [
        'version' => '1.51.19',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Config', 'text' => 'Matriz de roles: nuevo permiso "Editar familiares/contactos" (`editar_familia`) en Ficha del residente. Defaults: admin✓, enfermero/médico/familiar✗. Fallback a `editar_residentes` para instituciones existentes.'],
        ],
    ],

    [
        'version' => '1.51.18',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI',      'text' => 'Expediente sidebar: notas médicas ahora se muestran con el layout completo SOAP (Signos vitales, Subjetivo, Objetivo, Análisis, Plan, Dx CIE-10) igual que en el módulo de cuidados.'],
            ['tag' => 'Backend', 'text' => '_nmCrearExpedienteDoc guarda el JSON SOAP completo en expediente_docs.descripcion; timeline y sidebar detectan el formato v:2 y caen a resumen de texto plano para notas antiguas.'],
        ],
    ],

    [
        'version' => '1.51.17',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UX',  'text' => 'Formularios de registro: el botón "Guardar registro" ahora siempre está visible (sticky al fondo) mientras se hace scroll dentro del formulario.'],
            ['tag' => 'Fix', 'text' => '.cd-form-view y form usan ahora `overflow-x: clip` en lugar de `hidden` para no romper la posición sticky del botón de guardar.'],
        ],
    ],

    [
        'version' => '1.51.16',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Security', 'text' => 'Configuración: panel "Base de Datos" (conexión y respaldos) ahora visible y accesible solo para superadmin (UI + API).'],
            ['tag' => 'UI',       'text' => 'Configuración: tab bar reordenado a General → Equipo → Notificaciones → Logs → Integraciones (→ Base de Datos para superadmin).'],
        ],
    ],

    [
        'version' => '1.51.15',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Notas médicas: superadmin puede eliminar permanentemente una nota (vigente o archivada); cascada borra documentos del expediente vinculados (mismo residente + autor + tipo nota_medico/receta + fecha del creado_at). Nuevo endpoint eliminar_nota_medico.'],
            ['tag' => 'UI',      'text' => 'Inicio: sección "Accesos rápidos" reubicada debajo del grid de categorías de cuidados.'],
        ],
    ],

    [
        'version' => '1.51.14',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Nota médica: el médico ahora puede establecer/editar la fecha y hora de la nota (input datetime-local en el formulario; backend acepta creado_at en crear/actualizar)'],
        ],
    ],

    [
        'version' => '1.51.13',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Línea de tiempo: agregado icono dedicado para incidentes (mismo triángulo de alerta del card de Inicio); antes caían en el fallback genérico'],
        ],
    ],

    [
        'version' => '1.51.12',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Form Incidente: input de foto ahora aparece después de Severidad; título renombrado a "Registrar incidente" (es/en)'],
        ],
    ],

    [
        'version' => '1.51.11',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Matriz de permisos: el thead sticky no funcionaba (overflow-x:auto + overflow-y:visible se promovía a auto vacío). Ahora el wrap usa overflow:auto + max-height:65vh para que el sticky del header funcione dentro del propio contenedor'],
        ],
    ],

    [
        'version' => '1.51.10',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Registrar incidente / caída: 422 "Categoría inválida". "incidente" no estaba en Cuidado::CATEGORIAS; ahora sí se permite y se guarda'],
            ['tag' => 'UI',  'text' => 'Formulario de incidente: agregado botón para adjuntar foto (mismo estilo del registro de alimentación)'],
        ],
    ],

    [
        'version' => '1.51.9',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Registro de Medicación: badge de stock ahora reconoce items tipo "suplemento" del inventario (antes mostraba "Sin inventario" porque la caché filtraba sólo medicamento)'],
        ],
    ],

    [
        'version' => '1.51.8',
        'date'    => '2026-04-23',
        'changes' => [
            ['tag' => 'UI',     'text' => 'Matriz de permisos: cabecera (thead) ahora sticky al hacer scroll'],
            ['tag' => 'Config', 'text' => 'Matriz de permisos refactorizada: ~25 perms obsoletos eliminados, agregada sección "Visibilidad de secciones" con 7 perms ver_seccion_* (ahora aplicados en sidebar y bottom-nav)'],
            ['tag' => 'UI',     'text' => 'Familiar: ahora ve los cards de cuidados en Inicio en modo solo lectura (sin clic)'],
        ],
    ],

    [
        'version' => '1.51.2',
        'date'    => '2026-04-20',
        'changes' => [
            ['tag' => 'UI',        'text' => 'Detección offline: banner + toast al perder/recuperar conexión'],
            ['tag' => 'Config',    'text' => 'APP_ADDRESS actualizado con domicilio fiscal real'],
            ['tag' => 'Seguridad', 'text' => 'sync_profiles.json movido a secretos/'],
        ],
    ],

    [
        'version' => '1.51.1',
        'date'    => '2026-04-20',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Eliminados 15 archivos debug/test/backup del servidor'],
            ['tag' => 'Seguridad', 'text' => '.htaccess: bloqueo de archivos _diag_*, _test_*, *.bak'],
            ['tag' => 'iOS',       'text' => 'Info.plist: ITSAppUsesNonExemptEncryption = false'],
            ['tag' => 'Config',    'text' => 'package.json: agregada dependencia native-biometric'],
        ],
    ],

    [
        'version' => '1.51.0',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Alertas médico: detalles del registro visibles inline en la tarjeta'],
            ['tag' => 'UI',  'text' => 'Alertas médico: eliminado nombre del autor de la vista compacta'],
            ['tag' => 'API', 'text' => 'alertas_medico: incluye datos, hora y obs del registro asociado'],
        ],
    ],

    [
        'version' => '1.50.9',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Dark mode: botón submit usa accent azul en vez de blanco'],
            ['tag' => 'Fix', 'text' => 'Dark mode: botón "< Volver" con borde y texto accent'],
        ],
    ],

    [
        'version' => '1.50.8',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Timeline signos vitales: celdas fuera de rango con borde, fondo tintado y efecto breathing'],
            ['tag' => 'UI', 'text' => 'Warn: borde ámbar; Danger: borde rojo + breathing animado en toda la celda'],
        ],
    ],

    [
        'version' => '1.50.7',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Timeline: iconos PNG teñidos con color de categoría y reducidos a 75% para uniformidad'],
        ],
    ],

    [
        'version' => '1.50.6',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Timeline encerrado en contenedor con borde y esquinas redondeadas'],
        ],
    ],

    [
        'version' => '1.50.5',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Timeline: signos vitales en grid de 3 columnas con etiqueta, valor y unidad'],
            ['tag' => 'UI', 'text' => 'Celdas vitales con indicador de rango (warn/danger) y soporte dark mode'],
        ],
    ],

    [
        'version' => '1.50.4',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Sidebar y panel de notificaciones: respetan safe-area en iOS/Android (Capacitor)'],
            ['tag' => 'UI', 'text' => 'Sidebar actions: padding inferior con safe-area-inset-bottom para gestos'],
        ],
    ],

    [
        'version' => '1.50.3',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Error 500 al registrar cuidados — CHECK constraint json_valid(datos) impedía insertar datos cifrados'],
            ['tag' => 'Superadmin', 'text' => 'Migración: paso automático JSON→TEXT para columnas cifradas (drop CHECK + MODIFY)'],
            ['tag' => 'Superadmin', 'text' => 'Auditoría BD: detecta LONGTEXT con CHECK JSON en campos cifrados'],
        ],
    ],

    [
        'version' => '1.50.2',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Medicamentos: paleta cálida ámbar/crema para ambos temas (fondo, bordes, texto)'],
            ['tag' => 'UI', 'text' => 'Time badges y detalles de dosis con tonos dorados armónicos'],
        ],
    ],

    [
        'version' => '1.50.1',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'Superadmin', 'text' => 'Migración completa reescrita: schema-driven usando expected_schema.php como fuente de verdad'],
            ['tag' => 'Superadmin', 'text' => 'Eliminado gap entre auditoría BD y "Migrar todo" — ambos usan el mismo esquema'],
            ['tag' => 'Fix', 'text' => 'Tablas y ~30 columnas que la migración anterior omitía ahora se crean automáticamente'],
        ],
    ],

    [
        'version' => '1.50.0',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Contraste mejorado en ambos temas (claro y oscuro) — WCAG AA en todo el sitio'],
            ['tag' => 'UI', 'text' => 'Medicamentos del día: eliminada opacidad, fondos/textos con ratio ≥4.5:1 en dark mode'],
            ['tag' => 'UI', 'text' => 'Dark mode: variables semánticas revisadas + overrides para fondos sólidos (badges, toasts, botones)'],
            ['tag' => 'UI', 'text' => 'Limpieza de colores hardcodeados (#333, #444, #555) reemplazados por variables CSS'],
        ],
    ],

    [
        'version' => '1.49.9',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Tema claro: todos los colores semánticos cumplen WCAG AA (4.5:1 mínimo contra blanco)'],
            ['tag' => 'UI', 'text' => 'Nuevo --cd-info para notas médico; badges de warning con texto blanco; bordes más definidos'],
        ],
    ],

    [
        'version' => '1.49.8',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Se elimina encriptación de la tabla configuracion: smtp_password, smtp_usuario, smtp_from_email, wa_api_key, wa_instance_id, ia_api_key ya se almacenan en texto plano'],
            ['tag' => 'Fix', 'text' => 'Migración [5b] descifra valores cifrados existentes en la BD automáticamente'],
        ],
    ],

    [
        'version' => '1.49.7',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Cipher::decrypt() ya no lanza excepción fatal si un campo no se puede descifrar — loguea y devuelve el valor original'],
            ['tag' => 'Fix', 'text' => 'decryptRow() con try/catch por campo: un campo corrupto no tumba toda la fila ni la página'],
        ],
    ],

    [
        'version' => '1.49.6',
        'date'    => '2026-04-19',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Detecta campos sensibles corruptos (máscara guardada en BD) y los devuelve vacíos para forzar re-ingreso'],
            ['tag' => 'UX', 'text' => 'Alerta toast + borde naranja en inputs de contraseñas/keys corruptas al abrir Configuración'],
        ],
    ],

    [
        'version' => '1.49.5',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => '$instId indefinido en alertas al médico (crear/enterado) — reemplazado por api_inst_id()'],
        ],
    ],

    [
        'version' => '1.49.4',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Contraseñas/keys cortas se guardaban enmascaradas: check de •••• fallaba con <4 bullets, ahora detecta cualquier •'],
            ['tag' => 'Fix', 'text' => 'SMTP password, WA key e IA key: el valor enmascarado ya no sobreescribe el real en BD'],
            ['tag' => 'Fix', 'text' => 'Misma corrección en test_smtp, test_wa y test_ia (detectar mask con un solo bullet)'],
            ['tag' => 'Fix', 'text' => 'Cache busting ETag incluye mtime de todos los JS/CSS/views incluidos, no solo cuidados.php'],
        ],
    ],

    [
        'version' => '1.49.3',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Capacitor iOS login: detección nativa via header X-Native-App (funciona con server.url remota)'],
            ['tag' => 'Fix', 'text' => 'WKWebView iOS: exención CSRF por X-Native-App resuelve desincronización de cookies en primer page load'],
            ['tag' => 'Fix', 'text' => 'CORS: X-Native-App incluido en Access-Control-Allow-Headers'],
        ],
    ],

    [
        'version' => '1.49.2',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Guardar config de una sección (SMTP/WA/IA/Seg/Roles) ya no sobreescribe cambios no guardados de otras secciones'],
            ['tag' => 'Refactor', 'text' => 'populateConfigForms dividido en funciones por sección; _reloadSection solo repopula la sección guardada'],
        ],
    ],

    [
        'version' => '1.49.1',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Cache busting: ETag + no-cache en cuidados.php para forzar descarga de última versión'],
            ['tag' => 'Fix', 'text' => 'Config autofill: campos sensibles usan CSS masking (cd-masked) en vez de type=password para eliminar detección de Chrome'],
            ['tag' => 'Fix', 'text' => 'Re-populate con delay 350ms como safety net contra autofill de Chrome'],
            ['tag' => 'Fix', 'text' => 'Capacitor iOS CSRF: login exime de CSRF a clientes con Origin capacitor:// (header infalsificable)'],
        ],
    ],

    [
        'version' => '1.49.0',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Login en Capacitor iOS: SameSite=None;Secure para cookies de sesión en origen capacitor://'],
            ['tag' => 'Fix', 'text' => 'Auto-retry CSRF en login nativo: refresca token y reintenta si falla por token inválido'],
            ['tag' => 'Fix', 'text' => 'Variation selectors mojibake corregidos en notificaciones y sesiones (5 ocurrencias)'],
        ],
    ],

    [
        'version' => '1.48.9',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Guardar config de una sección ya no impacta valores de otras secciones'],
            ['tag' => 'Fix', 'text' => 'Recarga automática de config desde BD tras cada guardado para reflejar estado real'],
        ],
    ],

    [
        'version' => '1.48.8',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Prevención de autofill de Chrome en campos de configuración (SMTP, WhatsApp, IA, BD)'],
        ],
    ],

    [
        'version' => '1.48.7',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Auditoría de caracteres: corregidos 11 emojis mojibake en cd-ai, cd-init, cd-notes, cd-notif-admin, cd-sessions'],
            ['tag' => 'Fix', 'text' => 'Símbolo ¢ reemplazado por • (bullet) en regex de AI y lista de errores de sesiones'],
            ['tag' => 'Fix', 'text' => 'BOM (Byte Order Mark) eliminado de app.js, cuidados.php, cuidados_new.php'],
        ],
    ],

    [
        'version' => '1.48.6',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => "ENUM wa_proveedor no incluía 'wasender': valor se perdía al guardar"],
            ['tag' => 'Fix', 'text' => 'Mismatch may/min entre ENUM de BD y valores HTML en smtp_encriptacion y wa_proveedor'],
            ['tag' => 'Fix', 'text' => 'Placeholders ¢ corregidos a • en campos de contraseña de integraciones'],
            ['tag' => 'Fix', 'text' => 'JS populate normaliza valores ENUM con toLowerCase()'],
        ],
    ],

    [
        'version' => '1.48.5',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Emojis corruptos (mojibake) en panel de sesiones corregidos'],
            ['tag' => 'Fix', 'text' => 'Símbolo ¢ reemplazado por 💊 en detalle de medicación del sidebar'],
            ['tag' => 'UI', 'text' => 'Historial de notificaciones muestra nombre del contacto junto al teléfono/email'],
        ],
    ],

    [
        'version' => '1.48.4',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Fotos Antes/Después en sidebar se muestran lado a lado con etiqueta'],
            ['tag' => 'UI', 'text' => 'Botón de rotar imagen (90°) en el visor de imágenes'],
        ],
    ],

    [
        'version' => '1.48.3',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Cron de notificaciones: throttle de 7s entre envíos de WhatsApp para evitar rate-limit'],
            ['tag' => 'Fix', 'text' => 'Emojis corruptos (mojibake) en historial de notificaciones y plantillas de mensajes'],
        ],
    ],

    [
        'version' => '1.48.2',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Formulario de sueño: resetCareForm restaura visibilidad de hora despertar tras pending'],
            ['tag' => 'Fix', 'text' => 'Timeline de sueño <1hr muestra minutos en vez de "?"'],
            ['tag' => 'Fix', 'text' => 'Scroll de rx-pill enfoca correctamente el medicamento correspondiente'],
        ],
    ],

    [
        'version' => '1.48.1',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Botón Agregar Documento con altura consistente al de Notas del Médico en expediente'],
        ],
    ],

    [
        'version' => '1.48.0',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Diálogo de cambios sin guardar cubre todos los puntos de navegación'],
            ['tag' => 'UX', 'text' => 'Cambio de residente y clic en avatar verifican formularios con cambios pendientes'],
            ['tag' => 'UX', 'text' => 'Formularios se limpian automáticamente al confirmar salida sin guardar'],
        ],
    ],

    [
        'version' => '1.47.9',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Click en fotos del sidebar (signos vitales, antes/después) abre visor con zoom'],
            ['tag' => 'Fix', 'text' => 'Doble ícono de foto en timeline de signos vitales con una sola foto'],
            ['tag' => 'Fix', 'text' => 'Formularios de cuidados se limpian al confirmar salida con cambios sin guardar'],
        ],
    ],

    [
        'version' => '1.47.8',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Botón Volver en formularios de cuidados no mostraba diálogo de cambios sin guardar'],
        ],
    ],

    [
        'version' => '1.47.7',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Sidebar de signos vitales mostraba solo texto resumido (sin tabla ni fotos) cuando no había PA registrada'],
        ],
    ],

    [
        'version' => '1.47.6',
        'date'    => '2026-04-18',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Fotos de signos vitales ahora se limpian correctamente al crear un nuevo registro'],
            ['tag' => 'Fix', 'text' => 'La hora de registro ya no se pierde al adjuntar fotos de signos vitales'],
        ],
    ],

    [
        'version' => '1.47.5',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Fotos de signos vitales ya no se pierden al editar un registro (datos preservados en PUT)'],
            ['tag' => 'Fix', 'text' => 'Upload de fotos vitales ahora funciona tanto al crear como al editar registros'],
            ['tag' => 'Fix', 'text' => 'Merge de fotos al subir nuevas — ya no sobrescribe fotos de otros vitales'],
            ['tag' => 'UI', 'text' => 'Thumbnails de fotos existentes se muestran en el formulario al editar signos vitales'],
        ],
    ],

    [
        'version' => '1.47.4',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Indicador de cámara inline junto a cada lectura de signos vitales con foto en la timeline'],
            ['tag' => 'UI', 'text' => 'Badge de foto en timeline también detecta foto_antes y foto_despues'],
        ],
    ],

    [
        'version' => '1.47.3',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Botón Imprimir Reporte con mismo estilo destacado que Actualizar (contraste en ambos temas)'],
        ],
    ],

    [
        'version' => '1.47.2',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Error 500 al presionar Enterado en alertas médicas ($instId no definido en bloque POST)'],
            ['tag' => 'Fix', 'text' => 'Rol admin ahora puede marcar alertas médicas como vistas'],
            ['tag' => 'UI',  'text' => 'Botón Notas en mobile ya no ocupa ancho completo del grid'],
            ['tag' => 'UI',  'text' => 'Botón Notas del Médico en expediente: altura consistente con Agregar documento'],
            ['tag' => 'UI',  'text' => 'Barra de acciones de notas médicas (Nueva valoración) alineada a la derecha'],
        ],
    ],

    [
        'version' => '1.47.1',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Icono walk.png para categoría Movilidad (timeline y grid dashboard)'],
            ['tag' => 'UI', 'text' => 'Icono head-ia.png para categoría Comportamiento (timeline y grid dashboard)'],
        ],
    ],

    [
        'version' => '1.47.0',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Superadmin', 'text' => 'Sistema de seeding demo con toggle on/off en panel superadmin'],
            ['tag' => 'Superadmin', 'text' => 'Crea institución, 4 usuarios (admin/médico/enfermero/familiar), 10 residentes, registros de cuidados, notas médicas SOAP y prescripciones'],
            ['tag' => 'Fix',        'text' => 'Nota médica no se mostraba en cd-form-view tras guardar o editar'],
            ['tag' => 'UI',         'text' => 'Borde azul (--cd-accent) en tarjeta de nota médica vigente (cdNmCurrent)'],
        ],
    ],

    [
        'version' => '1.46.0',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Editor de texto enriquecido en Plan/Indicaciones de notas médicas (negrita, cursiva, listas)'],
            ['tag' => 'Fix',      'text' => 'Warning user_rol indefinido en middleware de autenticación'],
            ['tag' => 'Fix',      'text' => 'Emoji corrupto por doble encoding UTF-8 en configuración de errores'],
            ['tag' => 'UI',       'text' => 'Chips CIE-10 alineados al extremo derecho en notas médicas'],
        ],
    ],

    [
        'version' => '1.45.5',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Cards del grid de categorías con altura uniforme mediante min-height'],
            ['tag' => 'UI', 'text' => 'Rediseño de nota médica vigente: secciones con icono lateral, separadores y adjuntos a ancho completo'],
        ],
    ],

    [
        'version' => '1.45.4',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Expediente médico: solo el autor puede editar sus documentos (admin/superadmin siempre pueden)'],
            ['tag' => 'Config',    'text' => 'Nuevo permiso "editar_expediente" en matriz de roles para permitir edición de documentos ajenos'],
            ['tag' => 'API',       'text' => 'Verificación de autoría en endpoint actualizar del expediente'],
        ],
    ],

    [
        'version' => '1.45.3',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Diálogo "cambios sin guardar" ya no aparece al abrir formularios de cuidado sin haber editado nada'],
        ],
    ],

    [
        'version' => '1.45.2',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Badge de Notas del Médico unificado con el estilo de badges de categorías (tamaño, posición, border-radius)'],
        ],
    ],

    [
        'version' => '1.45.1',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Fix',      'text' => 'Signos vitales sin PA ahora se muestran correctamente en timeline'],
            ['tag' => 'UX',       'text' => 'Scroll automático al activar toggle "Notificar al médico" para mostrar el campo de mensaje'],
            ['tag' => 'Cuidados', 'text' => 'Indicador de borrador en botón de categoría al cerrar formulario sin guardar'],
            ['tag' => 'UX',       'text' => 'Icono de información en toggle de alerta con tooltip explicativo'],
            ['tag' => 'UI',       'text' => 'Tarjetas de alerta al médico compactas con layout horizontal'],
        ],
    ],

    [
        'version' => '1.45.0',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Alertas al Médico: toggle para notificar al médico al guardar registro de cuidado'],
            ['tag' => 'Cuidados', 'text' => 'Médicos ven alertas pendientes en NM con botón Enterado'],
            ['tag' => 'Cuidados', 'text' => 'Historial de alertas con auditoría de quién acusó recibo'],
            ['tag' => 'UI',       'text' => 'Badge NM muestra conteo de alertas pendientes'],
            ['tag' => 'API',      'text' => 'Endpoints crear_alerta_medico y enterado_alerta'],
        ],
    ],

    [
        'version' => '1.44.7',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Texto SOAP alineado a la derecha en tarjeta NM'],
            ['tag' => 'UI',  'text' => 'Sidebar reporte NM con toggle de previsualización propio'],
            ['tag' => 'UI',  'text' => 'Botón Notas Médicas en Expediente: icono inline con texto'],
            ['tag' => 'UX',  'text' => 'Displays de vitales pre-llenados con valores normales'],
            ['tag' => 'UI',  'text' => 'Sliders TA sys/dia más separados en formulario NM'],
        ],
    ],

    [
        'version' => '1.44.6',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UX',  'text' => 'Vitales NM pre-llenados con valores normales al crear nota'],
            ['tag' => 'UI',  'text' => 'Botón Imprimir Reporte en header de Notas del Médico'],
        ],
    ],

    [
        'version' => '1.44.5',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Dx chips CIE-10 alineados a la derecha en tarjeta NM'],
            ['tag' => 'UI',  'text' => 'Adjuntos NM con vista miniatura (thumbnails)'],
            ['tag' => 'UI',  'text' => 'Botón acceso directo a Notas Médicas en header Expediente'],
            ['tag' => 'UX',  'text' => 'Al editar nota, se ocultan notas existentes + link Ver historial'],
            ['tag' => 'Fix', 'text' => 'text-align:right erróneo en dark mode para secciones SOAP'],
        ],
    ],

    [
        'version' => '1.44.4',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI',  'text' => 'cdNmCurrent: vitales en tabla con iconos, color solo fuera de rango'],
            ['tag' => 'UI',  'text' => 'cdNmCurrent: header plano sin avatar ni gradiente'],
            ['tag' => 'UI',  'text' => 'cdNmCurrent: secciones SOAP sin color-coding'],
            ['tag' => 'UI',  'text' => 'Dark theme actualizado para nuevo diseño NM'],
        ],
    ],

    [
        'version' => '1.44.3',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Rediseño tarjeta nota médica vigente — header, avatar, fecha formateada'],
            ['tag' => 'UI',  'text' => 'Signos vitales como mini-cards en grid con valor prominente'],
            ['tag' => 'UI',  'text' => 'Secciones SOAP color-coded: S=verde, O=azul, A=naranja, P=púrpura'],
            ['tag' => 'UI',  'text' => 'Dark theme: contraste completo para tarjeta NM y secciones SOAP'],
        ],
    ],

    [
        'version' => '1.44.2',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Botón "Archivar nota" — texto corregido en nota médica vigente'],
            ['tag' => 'UI',  'text' => 'cdNmSaveBtn texto negro en dark theme para mejor contraste'],
            ['tag' => 'UI',  'text' => 'Área de recetas (Rx) rediseñada — borde azul, gradiente, símbolo ℞'],
        ],
    ],

    [
        'version' => '1.44.1',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Adjuntos de notas médicas en Expediente: links rotos por campo url vs path'],
            ['tag' => 'Fix', 'text' => 'Adjuntos NM: visor inline (lightbox/PDF) en vez de pestaña externa'],
            ['tag' => 'Fix', 'text' => 'Login: auto-recarga silenciosa en token CSRF expirado'],
            ['tag' => 'UI',  'text' => 'Contraste dark theme en botones formulario NM (CSS vars)'],
        ],
    ],

    [
        'version' => '1.44.0',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'General', 'text' => 'Refactor: cuidados.php modularizado — 20 vistas HTML + 35 módulos JS extraídos'],
            ['tag' => 'General', 'text' => 'Shell cuidados.php reducido de 16,958 a ~430 líneas'],
            ['tag' => 'General', 'text' => 'Migraciones: schema changes via expected_schema.php + Auditoría BD (sin migrate_*.php)'],
        ],
    ],

    [
        'version' => '1.43.3',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Área separada de adjuntos para recetas médicas en NM (layout 2 columnas)'],
            ['tag' => 'Cuidados', 'text' => 'Badge TA evalúa sistólica y diastólica — muestra el peor estado'],
            ['tag' => 'UX',       'text' => 'Slider deshabilitado cuando toggle de vital card está apagado'],
            ['tag' => 'UI',       'text' => 'Valor numérico (cd-vital-value) reposicionado; botón foto alineado a la derecha'],
            ['tag' => 'UX',       'text' => 'Barra de acciones NM y botón guardar de formularios de cuidado ahora son sticky'],
            ['tag' => 'UX',       'text' => 'Dirty tracking + alerta de cambios sin guardar en formularios de cuidado'],
            ['tag' => 'UX',       'text' => 'Diálogo NM con 3 opciones: Salir / Guardar borrador / Seguir editando'],
            ['tag' => 'Cuidados', 'text' => 'Borrador NM ahora incluye adjuntos y recetas pendientes'],
        ],
    ],

    [
        'version' => '1.43.2',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Cards NM: solo el toggle switch activa la tarjeta, sliders no auto-activan'],
            ['tag' => 'UI', 'text' => 'Inputs numéricos ocultos en cards NM; TA con dos sliders (sys/dia)'],
            ['tag' => 'UI', 'text' => 'Botones NM rediseñados (Cancelar, Borrador, Guardar) estilo Volver'],
            ['tag' => 'UX', 'text' => 'Alerta de cambios sin guardar al navegar fuera del formulario NM'],
            ['tag' => 'UX', 'text' => 'beforeunload al cerrar/recargar con cambios pendientes'],
            ['tag' => 'UX', 'text' => 'Guardado provisional (borrador) en sessionStorage con restauración automática'],
            ['tag' => 'i18n', 'text' => 'Nuevas claves: confirm_unsaved_nm, nm_draft, nm_draft_saved, nm_draft_restored'],
        ],
    ],

    [
        'version' => '1.43.1',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Cards de signos vitales NM con sliders (range bars) idénticos al grid principal, sincronización bidireccional slider↔number, tamaño homogéneo'],
            ['tag' => 'UX', 'text' => 'Scroll guard móvil mejorado: detecta desplazamiento horizontal y vertical (>6px), aplica también a cards NM'],
            ['tag' => 'UX', 'text' => 'Sangría derecha en grids de signos vitales en móvil táctil para zona de scroll libre'],
        ],
    ],

    [
        'version' => '1.43.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Auto-refresh en formularios al expirar token CSRF: recarga automática con mensaje informativo en vez de error'],
            ['tag' => 'UX', 'text' => 'Guard anti-scroll en tarjetas de signos vitales móvil: revierte toggles activados accidentalmente al desplazar'],
            ['tag' => 'UI', 'text' => 'Cards de signos vitales NM rediseñadas al estilo principal: layout 2-columnas, badge de estado con rangos, grid homogéneo'],
        ],
    ],

    [
        'version' => '1.42.1',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Corrección masiva de 5,500+ caracteres mojibake (doble-codificación UTF-8 vía CP-1252): em-dashes, bullets, elipsis, flechas, emojis, acentos CIE-10'],
            ['tag' => 'Fix', 'text' => 'Variable JS const pañales → panales: elimina riesgo de error de sintaxis por identificador no-ASCII'],
            ['tag' => 'Fix', 'text' => 'Regex cleanFileName corregida: caracteres acentuados correctos en vez de bytes corruptos'],
        ],
    ],

    [
        'version' => '1.42.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Card de Peso en grid principal de signos vitales con slider y evaluación de rango'],
            ['tag' => 'Feature', 'text' => 'Foto-evidencia en cada tarjeta de signo vital: cámara del teléfono o archivo, con preview y eliminación'],
            ['tag' => 'UX',      'text' => 'Tarjetas de signos vitales full-width horizontal: título izquierda, valor grande derecha'],
            ['tag' => 'UX',      'text' => 'Textareas SOAP en Notas del Médico con auto-grow (sin scroll interno)'],
            ['tag' => 'UX',      'text' => 'Grid NM vitals distribuido 4+3 con inputs spinner nativos (flechas de scroll)'],
            ['tag' => 'i18n',    'text' => 'Nuevas claves: vital_weight, vital_photo, vital_photo_hint'],
        ],
    ],

    [
        'version' => '1.41.0',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'UX',      'text' => 'Panel de notificaciones rediseñado: sidebar más ancho, pestañas por canal, tarjetas expandibles con detalle, badges de estado, iconos y tiempo relativo'],
            ['tag' => 'UX',      'text' => 'Botones de acciones en nota médica rediseñados: icono + texto, estilo editar/archivar consistente con el sitio'],
            ['tag' => 'UX',      'text' => 'Formulario de nota médica reubicado sobre la nota vigente para flujo más intuitivo'],
            ['tag' => 'UX',      'text' => 'Signos vitales en Notas del Médico estandarizados con patrón cd-vitals-grid: toggles, auto-enable, card de Peso añadido'],
            ['tag' => 'i18n',    'text' => 'Nuevas claves de traducción para panel de notificaciones (subtítulo, pestañas, estados, detalle)'],
        ],
    ],

    [
        'version' => '1.40.0',
        'date'    => '2026-04-17',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Catálogo CIE-10 en Notas del Médico: autocomplete de diagnósticos ICD-10 (~170 códigos geriátricos) con tags removibles'],
            ['tag' => 'Feature', 'text' => 'Integración Expediente: crear/actualizar nota médica genera automáticamente un registro en expediente_docs con resumen SOAP y diagnósticos'],
            ['tag' => 'UX',      'text' => 'Área de adjuntos rediseñada con drag & drop (zona punteada, icono, botón) para Notas del Médico'],
            ['tag' => 'Config',  'text' => 'Nuevo valor nota_medico en ENUM expediente_docs.tipo'],
        ],
    ],

    [
        'version' => '1.39.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Notas del Médico: formato SOAP (NOM-004-SSA3-2012) con signos vitales, subjetivo, objetivo, análisis y plan. Retrocompatible con notas existentes'],
            ['tag' => 'Feature', 'text' => 'Adjuntos múltiples en Notas del Médico: imágenes y PDF (máx. 10 MB, hasta 10 archivos) con compresión server-side'],
            ['tag' => 'UX',      'text' => 'Vista lectura por defecto en Notas del Médico; formulario bajo demanda con botón Nueva/Editar y Guardar acorde al sitio'],
            ['tag' => 'Config',  'text' => 'Columna adjuntos (TEXT) agregada a tabla notas_medico'],
        ],
    ],

    [
        'version' => '1.38.1',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Icono shelf (bottom bar): contraste mejorado en tema claro y filtro corregido en tema oscuro'],
            ['tag' => 'Fix', 'text' => 'Login nativo (Capacitor): refresco de token CSRF antes del POST evita error de sesión expirada'],
            ['tag' => 'Fix', 'text' => 'Dashboard API 500: try/catch en consulta notas_medico cuando la tabla no existe aún'],
        ],
    ],

    [
        'version' => '1.38.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Notas de Médico: nueva categoría para valoraciones e indicaciones médicas persistentes. Solo médicos crean/editan; con historial, cifrado y audit BD'],
            ['tag' => 'UI', 'text' => 'Contraste mejorado de cd-sig-card (firmas legales) para tema oscuro'],
        ],
    ],

    [
        'version' => '1.37.2',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Registro: checkbox de consentimiento requiere firmar ambos documentos legales antes de activarse'],
            ['tag' => 'UI', 'text' => 'Sidebar de cuidados: títulos de sección ahora en color cd-accent para mejor separación visual'],
        ],
    ],

    [
        'version' => '1.37.1',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Registro: consentimiento legal unificado (T&C + AdP + facultad legal + no-urgencias) con tooltip, botón deshabilitado, y log en BD (terms_accepted_at, privacy_version)'],
        ],
    ],

    [
        'version' => '1.37.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Registro: nuevo checkbox obligatorio de declaración de facultad legal/familiar para gestionar datos de salud'],
        ],
    ],

    [
        'version' => '1.36.9',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Sueño: calidad preseleccionada en "Normal" ya no requiere interacción para ser válida'],
        ],
    ],

    [
        'version' => '1.36.8',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Auditoría BD detecta y corrige subtipo sin poblar en eliminación post-cifrado. Corregida detección falsa positiva en sueño'],
        ],
    ],

    [
        'version' => '1.36.7',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Badge heces mostraba “?” post-cifrado: backfill de columnas desnormalizadas ahora usa descifrado PHP en lugar de JSON_EXTRACT SQL'],
        ],
    ],

    [
        'version' => '1.36.6',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Auditoría BD: columnas cifradas (horarios, datos) ya no proponen ALTER a JSON (ciphertext no pasa JSON_VALID)'],
        ],
    ],

    [
        'version' => '1.36.5',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Cifrado: saveState() ahora lanza excepción si no puede escribir encryption_state.json (antes fallaba silencioso)'],
            ['tag' => 'Fix', 'text' => 'Generado encryption_state.json con 30 campos consolidated + logs_sistema.ip active'],
        ],
    ],

    [
        'version' => '1.36.4',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Cifrado Fase 4 Consolidar: corregido "There is no active transaction" — DDL combinado en ALTER atómico sin transacción'],
        ],
    ],

    [
        'version' => '1.36.3',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX', 'text' => 'reset-password.php: checklist animado de requisitos de contraseña (5 reglas + coincidencia, pop/shake)'],
        ],
    ],

    [
        'version' => '1.36.2',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX',   'text' => 'pw-force-modal: backdrop-filter blur(6px) para enfocar atención del usuario'],
            ['tag' => 'Feat', 'text' => 'Registro: visualización de T&C y Aviso de Privacidad vigentes con firma manuscrita y envío por correo'],
            ['tag' => 'Feat', 'text' => 'Registro: checkbox separado para Aviso de Privacidad (obligatorio)'],
            ['tag' => 'API',  'text' => 'Endpoint público GET ?action=public_vigente para documentos legales sin sesión'],
        ],
    ],

    [
        'version' => '1.36.1',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX',   'text' => 'Registro: checklist animado de requisitos de contraseña en tiempo real (5 reglas + coincidencia)'],
            ['tag' => 'UX',   'text' => 'Modal cambio forzoso: link "¿No recuerdas tu contraseña actual?" → forgot-password.php'],
        ],
    ],

    [
        'version' => '1.36.0',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX',   'text' => 'Botones de documentos legales (cfgPrivList, cfgTcList) ampliados para touch en móvil — min 36px, responsive <520px'],
            ['tag' => 'Feat', 'text' => 'Expediente Médico: campo "Especialidad médica" con dropdown buscable (57 especialidades)'],
            ['tag' => 'Fix',  'text' => 'Tarjetas de firma (cd-sig-card): variables CSS corregidas al prefijo --cd-* para dark mode'],
            ['tag' => 'UX',   'text' => 'Expediente Médico: filtro por rango de fecha, botón orden asc/desc, doctor en row1, especialidad en meta'],
            ['tag' => 'UX',   'text' => 'Documentos legales: rich text (headings, listas, blockquote) ahora se renderiza en firma y preview'],
            ['tag' => 'Feat', 'text' => 'Superadmin Auditoría BD: selector de perfil de conexión para auditar y corregir BDs remotas'],
            ['tag' => 'DB',   'text' => 'Nueva columna especialidad VARCHAR(100) en expediente_docs'],
        ],
    ],

    [
        'version' => '1.35.2',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Documentos legales: contenido HTML enviado en base64 para evitar que WAF/ModSecurity elimine tags HTML del POST'],
            ['tag' => 'Fix', 'text' => 'Diagnóstico: _diag_legal.php temporal para verificar formato de contenido almacenado en BD'],
        ],
    ],

    [
        'version' => '1.35.1',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Visor PDF Canvas: reescritura del zoom — redimensionado físico + scroll nativo, pinch anclado al centro, doble-tap 1x/2x'],
        ],
    ],

    [
        'version' => '1.35.0',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix',      'text' => 'Documentos legales: contenido pre-editor enriquecido (texto plano) ahora se renderiza con saltos de línea'],
            ['tag' => 'Seguridad','text' => 'Documentos legales: sanitización HTML (allowlist) client-side y server-side para prevenir XSS'],
            ['tag' => 'Fix',      'text' => 'Aviso de Privacidad (adp.php): sanitización server-side, texto plano viejo auto-convertido con nl2br'],
        ],
    ],

    [
        'version' => '1.34.9',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX',   'text' => 'Aviso de Privacidad (adp.php): página pública rediseñada, scrolleable y con texto enriquecido'],
            ['tag' => 'Feat', 'text' => 'Config Legal: link "Ver página pública" en la versión vigente de Privacidad para abrir adp.php'],
            ['tag' => 'Fix',  'text' => 'Visor PDF Canvas: corregido pinch-to-zoom que movía la referencia, ahora usa zoom relativo sin drift'],
        ],
    ],

    [
        'version' => '1.34.8',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX',      'text' => 'Login: spinner y botón deshabilitado al enviar credenciales, previene doble click'],
            ['tag' => 'Feat',    'text' => 'Config Legal: editor de texto enriquecido (negrita, cursiva, subrayado, listas) para T&C y Privacidad'],
            ['tag' => 'UX',      'text' => 'Config Legal: botón de vista previa en editor de documentos legales'],
            ['tag' => 'Fix',     'text' => 'Sidebar: corregido el texto "wide" que aparecía sin función en cdSidebarActions'],
        ],
    ],

    [
        'version' => '1.34.7',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX',   'text' => 'Expediente Médico: zoom/drag en lightbox de imágenes (rueda, pinch, doble click, botones)'],
            ['tag' => 'UX',   'text' => 'Expediente Médico: zoom en visor PDF canvas (Capacitor) con botones y pinch-to-zoom'],
            ['tag' => 'Feat', 'text' => 'Superadmin: botón "Corregir todo" en Auditoría BD, ejecuta todas las correcciones a la vez'],
        ],
    ],

    [
        'version' => '1.34.6',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UI',  'text' => 'Expediente Médico: sangría izquierda en timeline cards, fechas más visibles con línea separadora'],
            ['tag' => 'UX',  'text' => 'Expediente Médico: ghost/skeleton elements estilo timeline mientras cargan registros'],
            ['tag' => 'UX',  'text' => 'Expediente Médico: spinner overlay al abrir archivo/foto en sidebar, previene doble click'],
        ],
    ],

    [
        'version' => '1.34.5',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Al cambiar de residente, la página permanece en la sección actual y recarga datos con ghost elements'],
        ],
    ],

    [
        'version' => '1.34.4',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UI',   'text' => 'Expediente Médico: vista timeline en lugar de grid de tarjetas, agrupado por fecha'],
            ['tag' => 'UI',   'text' => 'Expediente Médico: sidebar más ancho en desktop (560px) para apreciar documentos'],
            ['tag' => 'Feat', 'text' => 'Expediente Médico: soporte multi-archivo — adjuntar múltiples fotos/PDFs por documento'],
            ['tag' => 'UI',   'text' => 'Expediente Médico: creador del documento visible en línea de tiempo'],
            ['tag' => 'UI',   'text' => 'Expediente Médico: galería de archivos en detalle (thumbnails clickeables)'],
            ['tag' => 'DB',   'text' => 'Nueva columna archivos_json (TEXT) en expediente_docs'],
        ],
    ],

    [
        'version' => '1.34.3',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Expediente Médico: layout corregido (max-width, centrado, padding como el resto de secciones)'],
            ['tag' => 'Fix', 'text' => 'Expediente Médico: error SQL "u.apellidos" — tabla usuarios solo tiene campo nombre'],
        ],
    ],

    [
        'version' => '1.34.2',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Superadmin: favicon dinámico amarillo para distinguirlo de la app principal'],
            ['tag' => 'UI', 'text' => 'testgeriapp: punto rojo en el favicon para diferenciar entorno de pruebas de producción'],
        ],
    ],

    [
        'version' => '1.34.1',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'DB',  'text' => 'expected_schema.php: tabla expediente_docs y prescripciones.expediente_id en esquema esperado'],
            ['tag' => 'SA',  'text' => 'Auditoría BD detecta/repara expediente_docs y expediente_id automáticamente'],
            ['tag' => 'SA',  'text' => 'run_full_migration crea expediente_docs + expediente_id en todos los tenants'],
        ],
    ],

    [
        'version' => '1.34.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'Nuevo', 'text' => 'Módulo Expediente Médico: CRUD de documentos clínicos (recetas, lab, imágenes, interpretaciones, hospitalización, legales, enfermería)'],
            ['tag' => 'Nuevo', 'text' => 'Subida de archivos (JPG/PNG/WebP/PDF, máx 10 MB) con compresión server-side GD'],
            ['tag' => 'Nuevo', 'text' => 'Visor inline: lightbox para imágenes, pdf.js canvas en Capacitor, iframe en web'],
            ['tag' => 'Nuevo', 'text' => 'Cámara nativa (Capacitor Camera) desde el formulario del expediente'],
            ['tag' => 'Nuevo', 'text' => 'Vinculación de documentos del expediente a prescripciones médicas'],
            ['tag' => 'UI',    'text' => 'Tarjetas con iconos por tipo (7 colores), miniaturas, badge PDF'],
            ['tag' => 'DB',    'text' => 'Nueva tabla expediente_docs + columna expediente_id en prescripciones'],
        ],
    ],

    [
        'version' => '1.33.8',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'PDF', 'text' => 'Gráficas a ancho completo de columna (1 por fila); datos en orden cronológico ascendente'],
            ['tag' => 'Fix', 'text' => 'Tabla Insumos cuenta todos los medicamentos administrados (con o sin inventario)'],
        ],
    ],

    [
        'version' => '1.33.7',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Visor PDF en Capacitor usa pdf.js para renderizar a canvas (sin descargar ni abrir app externa)'],
            ['tag' => 'UI',  'text' => 'Visor nativo: overlay desplazable a pantalla completa, calidad 2x DPR'],
        ],
    ],

    [
        'version' => '1.33.6',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Tabla Insumos: matching fuzzy entre nombres de prescripción e inventario (ej. "Memantina" ↔ "Memantina 10 mg")'],
            ['tag' => 'Fix', 'text' => 'Visor PDF en Capacitor usa FileOpener nativo (WebView no renderiza PDFs en iframes)'],
            ['tag' => 'UI',  'text' => 'Visor PDF web a ancho completo (100%, máx 900px)'],
        ],
    ],

    [
        'version' => '1.33.5',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Visor PDF en Capacitor: sube blob a pdf_temp.php y usa URL HTTP en iframe (blob URLs no renderizan)'],
            ['tag' => 'UI',  'text' => 'Visor PDF: ancho 80vw (máx 800px), zoom page-width, safe-area respetada'],
        ],
    ],

    [
        'version' => '1.33.4',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Visor PDF en Capacitor usa FileOpener nativo (iframe no renderiza blob URLs)'],
            ['tag' => 'UI',  'text' => 'Visor PDF web: ancho ajustado a proporción A4; safe-area respetada'],
        ],
    ],

    [
        'version' => '1.33.3',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'PDF', 'text' => 'Insumos incluye medicamentos administrados por prescripción; stock remanente = stock − consumido'],
            ['tag' => 'Reportes', 'text' => 'Visor PDF embebido a pantalla completa para lectura rápida'],
        ],
    ],

    [
        'version' => '1.33.2',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'PDF', 'text' => 'Etiquetas eje X verticales (-90°); insumos en columna derecha bajo medicación'],
            ['tag' => 'PDF', 'text' => 'Autor + hora alineados a la derecha de cada entrada del timeline'],
            ['tag' => 'PDF', 'text' => 'Margen inferior ampliado (18mm) para evitar solapamiento con pie de página'],
            ['tag' => 'Reportes', 'text' => 'Previsualización abre visor PDF embebido en la página en vez de descargar'],
        ],
    ],

    [
        'version' => '1.33.1',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Previsualizar activado por defecto; corregida descarga involuntaria en modo preview'],
        ],
    ],

    [
        'version' => '1.33.0',
        'date'    => '2026-04-16',
        'changes' => [
            ['tag' => 'PDF',       'text' => 'Autor + hora de registro en cada entrada del timeline del reporte'],
            ['tag' => 'PDF',       'text' => 'Tablas separadas: Medicación Prescrita (rx/SOS) e Insumos (administrado + stock)'],
            ['tag' => 'PDF',       'text' => 'Gráficas: formato dd-Mmm-yy, rotación −45°, salto inteligente de etiquetas'],
            ['tag' => 'Reportes',  'text' => 'Semana lunes–domingo; nuevos periodos: Últimos 30 Días y Fechas Personalizadas'],
            ['tag' => 'i18n',      'text' => 'Nuevas claves: report_last_30, report_custom*, report_custom_invalid_range'],
        ],
    ],

    [
        'version' => '1.32.0',
        'date'    => '2026-04-15',
        'changes' => [
            ['tag' => 'Legal',      'text' => 'Ver firma autógrafa: botón Volver al listado + contraste dark mode corregido'],
            ['tag' => 'Signos Vitales', 'text' => 'Timeline muestra SpO₂ y FR en ambos formatos (nuevo y legado)'],
            ['tag' => 'PDF',        'text' => 'Medicamentos ocasionales/SOS integrados al reporte con estilo diferenciado'],
            ['tag' => 'Reportes',   'text' => 'Toggle previsualizar: abre PDF en nueva pestaña sin descargar'],
            ['tag' => 'Permisos',   'text' => 'Nuevo permiso preview_reportes en la matriz de roles'],
            ['tag' => 'UI',         'text' => 'Filtro de categorías timeline muestra todas las categorías posibles'],
            ['tag' => 'i18n',       'text' => 'Nuevas claves: cat_bitacora, legal_back_to_list, perm_preview/report_preview*'],
        ],
    ],

    [
        'version' => '1.31.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Legal',      'text' => 'Email al firmar: copia automática al usuario + CC al correo configurado por admin'],
            ['tag' => 'Config',     'text' => 'Campo "Correo CC para documentos legales" en Configuración → General'],
            ['tag' => 'Privacidad', 'text' => 'Rediseño de adp.php: hero con gradiente, pills, info box, dark mode, impresión'],
            ['tag' => 'Legal',      'text' => 'Vista de firmas: tarjetas con avatar, búsqueda, botón para ver firma autógrafa'],
            ['tag' => 'API',        'text' => 'GET action=get_firma&id=X: obtener imagen base64 de firma autógrafa'],
            ['tag' => 'i18n',       'text' => 'Nuevas claves: legal_view_signature, legal_sig_search_placeholder, config_legal_cc_email*'],
        ],
    ],

    [
        'version' => '1.30.1',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Privacidad', 'text' => 'Nueva página pública /adp.php: Aviso de Privacidad vigente sin sesión requerida'],
            ['tag' => 'Privacidad', 'text' => 'Acepta ?inst=ID para cargar el documento de una institución específica'],
            ['tag' => 'Registro',   'text' => 'Enlace al Aviso de Privacidad en footer del registro (cuando hay institución)'],
        ],
    ],

    [
        'version' => '1.30.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Términos y Condiciones', 'text' => 'Ver documento: botón en lista admin para visualizar T&C/Privacidad con contenido y metadata'],
            ['tag' => 'Términos y Condiciones', 'text' => 'Editar documento: acceso directo a editor desde botón lápiz en lista'],
            ['tag' => 'Términos y Condiciones', 'text' => 'Formato pre-wrap: saltos línea y espacios preservados en modal firma y previsualizaciones'],
            ['tag' => 'Términos y Condiciones', 'text' => 'Checkbox obligatorio en registro: "Acepto T&C y cuento con facultades legales"'],
            ['tag' => 'Términos y Condiciones', 'text' => 'API GET action=get&id=X: obtener documento completo con contenido'],
            ['tag' => 'Términos y Condiciones', 'text' => 'Global scope: viewLegalDoc, editLegalDoc, viewLegalSignatures en window.* para onclick'],
            ['tag' => 'Fix', 'text' => 'ReferenceError: viewLegalSignatures not defined - funciones ahora expuestas a scope global'],
        ],
    ],

    [
        'version' => '1.29.9',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Superadmin', 'text' => 'fix_db: SET sql_mode compatible antes de ejecutar DDL, resuelve error 1067 en servidores estrictos'],
            ['tag' => 'Superadmin', 'text' => 'Toast: estilos inline forzados (position:fixed, z-index:99999) para visibilidad garantizada'],
        ],
    ],

    [
        'version' => '1.29.8',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Auditoría', 'text' => 'Sidebar log enriquecido: JSON estructurado con categoría, residente, fecha, hora, observaciones, datos completos'],
            ['tag' => 'Auditoría', 'text' => '3 secciones en sidebar: Detalle registro, Detalle cuidado, Técnicos. Badge de color por acción'],
            ['tag' => 'Auditoría', 'text' => 'Compatible con formato legacy pipe-delimited para logs antiguos'],
        ],
    ],

    [
        'version' => '1.29.7',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'DB', 'text' => 'Fix: DEFAULT CURRENT_TIMESTAMP en todas las columnas TIMESTAMP NOT NULL (26 cols: creado_at, firmado_at, expires_at, visto_at, ultimo_acceso)'],
        ],
    ],

    [        'version' => '1.29.6',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Parámetros de seguridad funcionales: contraseña mín., timeout sesión, max intentos, bloqueo'],
            ['tag' => 'Superadmin', 'text' => 'checkdbSummary excluye issues info del conteo de problemas'],
            ['tag' => 'Superadmin', 'text' => 'Botón hamburger flotante (FAB) en móvil, esquina inferior derecha'],
            ['tag' => 'Superadmin', 'text' => 'Toast notifications rediseñados: top-right, barra de progreso, colores por tipo'],
            ['tag' => 'Auditoría', 'text' => 'Detalle enriquecido en logs: nombre residente, resumen datos, ID registro'],
            ['tag' => 'DB', 'text' => 'Fix: updated_at con DEFAULT CURRENT_TIMESTAMP en expected_schema y SQL fix_sql'],
        ],
    ],

    [
        'version' => '1.29.5',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'DB', 'text' => 'Migración: db/migrate_documentos_legales.php'],
            ['tag' => 'UI', 'text' => 'cdSuenoBadge: icono con mejor contraste'],
            ['tag' => 'UI', 'text' => 'cd-date-nav arriba del toolbar, sticky al scroll'],
            ['tag' => 'UI', 'text' => 'cdInstSelect: flecha visible en tema oscuro'],
            ['tag' => 'UI', 'text' => 'cdAvatarOverlay: badge de cámara permanente'],
            ['tag' => 'Fix', 'text' => 'Bug sueño 23h+: cálculo usa fecha+hora completa'],
            ['tag' => 'UI', 'text' => 'Iconos mañana/tarde en RxTracker: SVG inline'],
        ],
    ],

    [        'version' => '1.29.5',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'DB', 'text' => 'Archivo de migración para tablas documentos_legales y firmas_documentos'],
            ['tag' => 'Fix', 'text' => 'Contraste de icono en cdSuenoBadge (fondo amarillo)'],
            ['tag' => 'UI', 'text' => 'cd-date-nav y toolbar intercambiados; date-nav sticky al scroll'],
            ['tag' => 'Fix', 'text' => 'Contraste de flecha en cdInstSelect en tema oscuro'],
            ['tag' => 'UX', 'text' => 'Badge persistente de cámara en avatar para indicar editabilidad'],
            ['tag' => 'Fix', 'text' => 'Badge de sueño: cálculo con fecha+hora completas (bug 23h+)'],
            ['tag' => 'UI', 'text' => 'Iconos mañana/tarde en RxTracker reemplazados por SVGs inline'],
        ],
    ],

    [
        'version' => '1.29.4',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Config', 'text' => 'Botón AI rewrite en título de notificación del sistema'],
            ['tag' => 'Config', 'text' => 'Tab General con sub-tabs: Generales, Seguridad, T&C, Aviso de Privacidad'],
            ['tag' => 'Config', 'text' => 'Gestor de documentos legales con versionado, vigencia y firmas'],
            ['tag' => 'Legal', 'text' => 'Modal de firma gráfica obligatoria al login para T&C y Privacidad pendientes'],
            ['tag' => 'DB', 'text' => 'Nuevas tablas: documentos_legales, firmas_documentos'],
            ['tag' => 'API', 'text' => 'Nuevo endpoint: api/documentos_legales.php'],
            ['tag' => 'i18n', 'text' => '~40 nuevas claves (es/en) para documentos legales'],
        ],
    ],

    [
        'version' => '1.29.3',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Sidebar derecho: cierre solo con click completo fuera del panel'],
            ['tag' => 'UI', 'text' => 'Confirm dialog sueño pendiente: click en overlay cierra y permanece en inicio'],
            ['tag' => 'UI', 'text' => 'Icono moon-zzz.png en cdSuenoBadge en lugar de emoji'],
            ['tag' => 'Config', 'text' => 'Tabs consolidados: Integraciones (SMTP+WA+IA) y Equipo (Personal+Roles+Sesiones)'],
            ['tag' => 'i18n', 'text' => 'Nuevas claves: config_integrations, config_team (es/en)'],
        ],
    ],

    [
        'version' => '1.29.2',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Checkbox pendiente reubicado dentro de columna Hora de despertar'],
            ['tag' => 'UI', 'text' => 'Texto \"Aún desconocida\" en hora de despertar cuando pendiente activo'],
            ['tag' => 'UI', 'text' => 'Hint de cruce de medianoche oculto cuando hora_fin no se conoce'],
            ['tag' => 'UI', 'text' => 'Texto de cdSleepDateHint centrado'],
            ['tag' => 'i18n', 'text' => 'Nueva clave: sleep_wake_unknown (es/en)'],
        ],
    ],

    [
        'version' => '1.29.1',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Limpieza', 'text' => 'app.js: eliminadas ~650 líneas muertas (sidebar, tabs, medicación, scroll, sortable, descargarReporteNativo)'],
            ['tag' => 'Limpieza', 'text' => 'cuidados.php: eliminado bloque PDF legend comentado'],
            ['tag' => 'CSS', 'text' => 'Corregidas 6 variables CSS indefinidas (--cd-error, --cd-card, --cd-hover)'],
            ['tag' => 'CSS', 'text' => 'Renombrada .cd-notif-empty duplicada a .cd-notif-push-empty en panel push'],
        ],
    ],

    [
        'version' => '1.29.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Botón Actualizar con texto visible, fondo blanco y letras negras'],
            ['tag' => 'UI', 'text' => 'Ghost/skeleton animado en cdRxTracker durante carga del dashboard'],
            ['tag' => 'UI', 'text' => 'Botón Notas con icono y texto más grande, alineado con las demás categorías'],
            ['tag' => 'UI', 'text' => 'Icono de notificaciones (campana) con panel lateral de historial push'],
            ['tag' => 'Nav', 'text' => 'Sección Reportes oculta temporalmente en sidebar y bottombar'],
            ['tag' => 'i18n', 'text' => 'Nuevas claves: header_notifications, notif_title, notif_empty (es/en)'],
        ],
    ],

    [
        'version' => '1.28.2',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Superadmin', 'text' => 'expected_schema.php: ~30 keys/indexes añadidos en 12 tablas, columnas faltantes (subtipo, pendiente, duracion_min, roles_destino) y tablas push_tokens/sesiones_activas'],
            ['tag' => 'DB', 'text' => 'schema.sql/schema_tenant.sql: eliminadas 6 tablas obsoletas de bitácora/historial (dropped v1.26.0)'],
            ['tag' => 'DB', 'text' => 'schema_tenant.sql: añadidas verificacion_biometrica y timestamp_firma a cuidados_registros'],
        ],
    ],

    [
        'version' => '1.28.1',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Botón CdConfirmNo mostraba "btn_new" — corregido a clave i18n sleep_pending_new'],
            ['tag' => 'UI', 'text' => 'cd-confirm-dialog: opción required bloquea overlay click y Escape'],
            ['tag' => 'Superadmin', 'text' => 'Auditoría BD detecta tablas y columnas residuales con botón Corregir (DROP TABLE/COLUMN) y Corregir todos'],
            ['tag' => 'Cuidados', 'text' => 'Badge de sueño pendiente muestra horas transcurridas en lugar de hora de inicio'],
            ['tag' => 'i18n', 'text' => 'Nuevas claves: sleep_pending_new, sleep_pending_hours (es/en)'],
        ],
    ],

    [
        'version' => '1.28.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Cifrado AES-256-GCM fase 2: 5 nuevas columnas PHI (prescripciones.nombre, inventario_items.nombre, cuidados_registros.datos/observaciones, notificaciones_log.mensaje). Total: 31 campos, 8 tablas'],
            ['tag' => 'Seguridad', 'text' => 'Columnas desnormalizadas (subtipo, pendiente, duracion_min) en cuidados_registros para consultas sin JSON_EXTRACT'],
            ['tag' => 'Seguridad', 'text' => 'ORDER BY cifrado → usort() PHP; LIKE CONCAT → stripos() PHP en reportes'],
            ['tag' => 'Seguridad', 'text' => 'EncryptionMap::dualWriteEnc() genérico para dual-write fuera de modelos'],
            ['tag' => 'DB', 'text' => 'Migración migrate_cuidados_denorm.php + sync_schema actualizado con columnas desnormalizadas'],
            ['tag' => 'Cron', 'text' => 'Cifrado/descifrado de notificaciones_log.mensaje, inventario_items.nombre y cuidados_registros en cron'],
        ],
    ],

    [
        'version' => '1.27.1',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Sueño pendiente: registro parcial sin hora de despertar, con badge ⏳ y flujo de completar'],
            ['tag' => 'UI', 'text' => 'Botón AI Rewrite en textarea de mensaje de notificación (configuración)'],
            ['tag' => 'i18n', 'text' => '8 nuevas claves de traducción para sueño pendiente (es/en)'],
            ['tag' => 'API', 'text' => 'Dashboard devuelve sueno_pendiente para el residente activo'],
        ],
    ],

    [
        'version' => '1.27.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Cifrado AES-256-GCM ampliado: 12 columnas nuevas (prescripciones, cuidados_notas, logs_sistema, configuracion)'],
            ['tag' => 'Seguridad', 'text' => 'Dual-write generalizado en Configuracion (antes solo smtp_password)'],
            ['tag' => 'Fix', 'text' => 'Prescripcion::getHistorialForResidente() ahora descifra datos correctamente'],
            ['tag' => 'Fix', 'text' => 'Reporte: cálculo de adherencia usa datos descifrados'],
            ['tag' => 'Fix', 'text' => 'Superadmin: cifrado resiliente a tablas master-only'],
        ],
    ],

    [
        'version' => '1.26.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Limpieza', 'text' => 'Eliminadas 4 tablas legacy bitacora_* (reemplazadas por cuidados_registros)'],
            ['tag' => 'Limpieza', 'text' => 'Eliminadas 3 tablas legacy historial_* (sin UI desde v6)'],
            ['tag' => 'Refactor', 'text' => 'Reportes migrados a cuidados_registros — datos actuales en vez de bitácora legacy'],
            ['tag' => 'DB', 'text' => 'Nuevo script migrate_drop_bitacora_historial.php (7 tablas)'],
        ],
    ],

    [
        'version' => '1.25.0',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Cifrado', 'text' => 'Fase 4 — Consolidar: elimina columnas originales y renombra _enc → original (irreversible)'],
            ['tag' => 'Cifrado', 'text' => 'Switch global de modo lectura cifrado/texto plano (verificación y emergencias)'],
            ['tag' => 'Cifrado', 'text' => 'Nueva fase consolidated: cifrado directo en columna original sin duplicados'],
        ],
    ],

    [
        'version' => '1.24.7',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Push cuidados.php: flush del token faltaba header X-CSRF-Token (copia independiente de app.js)'],
            ['tag' => 'Fix', 'text' => 'Diagnóstico push: flush_error residual no se limpiaba tras éxito — mostraba error falso'],
        ],
    ],

    [
        'version' => '1.24.6',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Fotos Capacitor: fallback a file input nativo si Camera falla o no existe (distingue cancel vs error)'],
        ],
    ],

    [
        'version' => '1.24.5',
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Auditoría BD: MariaDB JSON=LONGTEXT tratados como equivalentes (9 columnas en 8 tablas)'],
        ],
    ],

    [
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Push Capacitor: flush token FCM fallaba 403 (faltaba X-CSRF-Token en fetch)'],
            ['tag' => 'Fix', 'text' => 'cuidados.php: faltaba meta csrf-token — interceptor global enviaba CSRF vacío'],
        ],
    ],

    [
        'date'    => '2026-04-14',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'PDF en Capacitor: descarga nativa sin cookies → auth vía token hex efímero (capability URL)'],
        ],
    ],

    [
        'date'    => '2026-04-13',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'migrate_drop_expediente: ahora elimina tablas de BD master + tenants separados'],
        ],
    ],

    [
        'date'    => '2026-04-13',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Login en Android Capacitor: fetch() nativo no incluía _csrf en el body'],
            ['tag' => 'Config', 'text' => 'CORS: header X-CSRF-Token en Allow-Headers'],
        ],
    ],

    [
        'version' => '1.24.0',
        'date'    => '2026-04-13',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Tab Cifrado §5 en superadmin: gestión visual del cifrado de campos PHI/PII'],
            ['tag' => 'Seguridad', 'text' => '§5.1 Cipher AES-256-GCM con DATA_ENCRYPTION_KEY de .env'],
            ['tag' => 'Seguridad', 'text' => '§5.2 EncryptionMap: 20 campos en 5 tablas (excluidos campos usados en búsqueda/reportes)'],
            ['tag' => 'Seguridad', 'text' => '§5.3 Dual-write en 4 modelos (Residente, Prescripcion, Historial, Configuracion)'],
            ['tag' => 'Seguridad', 'text' => '§5.4 API cifrado multi-tenant (status/prepare/migrate/activate/rollback)'],
            ['tag' => 'Seguridad', 'text' => '§5.5 migrate_encryption.php idempotente (master + tenants)'],
            ['tag' => 'UI', 'text' => 'Confirmación con parámetros + progreso en tiempo real por lotes'],
        ],
    ],

    [
        'version' => '1.23.0',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'Feature', 'text' => 'Tab Logs: sub-tab Auditoría con exportación CSV PHIPA/NOM'],
            ['tag' => 'Feature', 'text' => 'Tab Logs: sub-tab Errores PHP con visor, toggle display_errors'],
            ['tag' => 'Seguridad', 'text' => '§7.1-7.3 Manejo de errores: handler custom, display_errors OFF, log centralizado'],
            ['tag' => 'Feature', 'text' => 'Tab BD: sub-tab Respaldos con historial/descarga/generación manual'],
            ['tag' => 'Seguridad', 'text' => '§8.1 Backup automatizado via cron con retención 30 días'],
            ['tag' => 'Seguridad', 'text' => '§8.2 Backup cifrado AES-256-CBC (clave de .env)'],
            ['tag' => 'Seguridad', 'text' => '§8.3 Plan de recuperación documentado en UI'],
        ],
    ],

    [
        'version' => '1.22.0',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => '§1.4-1.6 Session hardening: strict_mode, use_only_cookies, sid_length=96'],
            ['tag' => 'Seguridad', 'text' => '§1.7 Cron de limpieza de sesiones inactivas (cron/limpieza.php)'],
            ['tag' => 'Seguridad', 'text' => '§4.4 Audit logging en prueba de conexión BD'],
            ['tag' => 'Seguridad', 'text' => '§4.5 Retención y purga de logs (180/90 días) en master + tenants'],
            ['tag' => 'Seguridad', 'text' => '§4.6 Alertas automáticas: login fallido, CSRF inválido, brute force'],
            ['tag' => 'Seguridad', 'text' => '§6.5 Validación MIME con finfo en 5 endpoints de upload'],
            ['tag' => 'Seguridad', 'text' => '§9.4 API keys/passwords enmascarados en respuesta GET'],
        ],
    ],

    [
        'version' => '1.21.3',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'UX', 'text' => 'Post-cambio de contraseña forzado: confirmación visual + recarga automática'],
            ['tag' => 'Fix', 'text' => 'Modal biometría: contraste dark mode corregido (variables CSS correctas)'],
            ['tag' => 'UX', 'text' => 'Modal biometría: botón ojo para revelar/ocultar contraseña'],
        ],
    ],

    [
        'version' => '1.21.2',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Modal cambio contraseña: contraste de inputs corregido para tema oscuro (--cd-bg/--cd-text)'],
            ['tag' => 'UX', 'text' => 'Botón ojo en cada campo para revelar/ocultar contraseña'],
            ['tag' => 'Fix', 'text' => 'Color de error en modal adaptado para dark mode'],
        ],
    ],

    [
        'version' => '1.21.1',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Modal de cambio de contraseña: fuentes consistentes con el sitio (var(--cd-font))'],
            ['tag' => 'UX', 'text' => 'Requisitos de contraseña se colorean en verde en tiempo real conforme se cumplen'],
            ['tag' => 'UX', 'text' => 'Botón deshabilitado hasta cumplir todos los requisitos (incluye coincidencia)'],
        ],
    ],

    [
        'version' => '1.21.0',
        'date'    => '2026-04-13',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => '§1.8 Cookies de sesión con flags httponly, secure (HTTPS), samesite=Lax'],
            ['tag' => 'Seguridad', 'text' => '§1.9 Protección CSRF en todos los formularios y API (interceptor global fetch)'],
            ['tag' => 'Seguridad', 'text' => '§1.10 Timeout de sesión por inactividad (30 min)'],
            ['tag' => 'Seguridad', 'text' => '§1.11 Política de complejidad de contraseñas + cambio forzado para usuarios existentes con contraseña débil'],
            ['tag' => 'Seguridad', 'text' => '§2.3 Credenciales movidas a conf/.env (fuera de VCS)'],
            ['tag' => 'Seguridad', 'text' => '§2.4 Soporte SSL/TLS para MySQL en producción'],
            ['tag' => 'Seguridad', 'text' => '§2.5 Header HSTS condicional para dominios de producción'],
            ['tag' => 'Seguridad', 'text' => '§3.6 Registro cerrado: invitación obligatoria'],
            ['tag' => 'Seguridad', 'text' => '§3.7 Filtrado de datos para rol familiar (solo residentes vinculados, sin datos sensibles)'],
            ['tag' => 'Seguridad', 'text' => '§3.8 Endpoint db_config restringido a superadmin'],
            ['tag' => 'Seguridad', 'text' => '§6.6 Validación anti-SQLi en createTenantDB()'],
            ['tag' => 'Seguridad', 'text' => '§10.5 Directorio conf/ bloqueado via .htaccess'],
            ['tag' => 'Seguridad', 'text' => '§10.6 Uploads servidos con autenticación obligatoria'],
            ['tag' => 'Config', 'text' => '.gitignore para producción'],
            ['tag' => 'DB', 'text' => 'Migración PHIPA: columna password_change_required + índices de seguridad'],
        ],
    ],

    [
        'version' => '1.20.0',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'General', 'text' => 'Expediente Médico independizado como MediApp (app standalone en /mediapp)'],
            ['tag' => 'General', 'text' => 'cuidados.php: removidas 7 integraciones del módulo médico (CSS, sidebar, nav, include, callbacks, JS)'],
            ['tag' => 'General', 'text' => 'models.php: removido require de ExpedienteMedico.php'],
            ['tag' => 'General', 'text' => 'Superadmin: removido panel CIE-10 y endpoints cie10_stats/cie10_import'],
            ['tag' => 'DB', 'text' => 'migrate_drop_expediente.php: elimina 7 tablas de expediente de todas las BD tenant'],
            ['tag' => 'DB', 'text' => 'migrate_expediente_medico.php y cie10_import.php desactivados'],
        ],
    ],

    [
        'version' => '1.19.2',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'General', 'text' => 'Limpieza: eliminados 12 archivos/dirs obsoletos (debug scripts, APIs huérfanas, DB utilities, dump sensible)'],
            ['tag' => 'Seguridad', 'text' => 'Removidos archivos con credenciales en texto plano (install.php, config.db.local.example.php, db/bkp/)'],
        ],
    ],

    [
        'version' => '1.19.1',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Push: token no se enviaba al servidor porque cuidados.php no cargaba app.js donde se definía geriappFlushPushToken()'],
            ['tag' => 'Push', 'text' => 'cuidados.php: setup completo de push inline (flush + init Capacitor) — token se envía al backend al entrar a la app'],
        ],
    ],

    [
        'version' => '1.19.0',
        'date'    => '2026-04-12',
        'changes' => [
            ['tag' => 'Push', 'text' => 'iOS: conversión APNs→FCM server-side vía Firebase Instance ID batchImport (sin Firebase SDK nativo)'],
            ['tag' => 'Push', 'text' => 'push_token.php: detecta tokens iOS y los convierte a FCM automáticamente en el servidor'],
            ['tag' => 'Fix', 'text' => 'iOS: removido @capacitor-community/fcm que causaba crash al inicio por Firebase SDK'],
            ['tag' => 'Fix', 'text' => 'iOS: AppDelegate restaurado sin imports de Firebase, eliminado GoogleService-Info.plist'],
            ['tag' => 'Fix', 'text' => 'iOS: removido FirebaseAppDelegateProxyEnabled de Info.plist'],
            ['tag' => 'UI', 'text' => 'Header: rediseño a 2 filas — fila superior: institución + avatar + acciones; fila inferior: selector de residente a ancho completo'],
            ['tag' => 'Fix', 'text' => 'Push: limpieza automática de tokens FCM inválidos más robusta (cubre errorCode + status + NOT_FOUND)'],
            ['tag' => 'Push', 'text' => 'Test push ahora indica cuántos tokens inválidos fueron eliminados automáticamente'],
        ],
    ],

    [
        'version' => '1.18.1',
        'date'    => '2026-04-11',
        'changes' => [
            ['tag' => 'Push', 'text' => 'Config: botón de prueba push con feedback detallado + limpiar caché de diagnóstico'],
            ['tag' => 'Push', 'text' => 'Superadmin: feedback paso a paso al enviar push, errores detallados por token'],
            ['tag' => 'Fix', 'text' => 'Android: icono de notificación era círculo blanco — meta-data movido a nivel application en AndroidManifest.xml'],
        ],
    ],

    [
        'version' => '1.18.0',
        'date'    => '2026-04-10',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Sesiones: rediseño completo — tarjetas compactas, contador de expiración, filtros por chip, resumen inline y botones de acción'],
        ],
    ],

    [
        'version' => '1.17.2',
        'date'    => '2026-04-08',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Expediente Médico: addendum form sent update_nota instead of create_nota causing "id requerido" error; now correctly sends create_nota with es_addendum and nota_padre_id'],
        ],
    ],

    [
        'version' => '1.17.1',
        'date'    => '2026-04-08',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Login: photo lightbox modal leaked raw HTML onto login page; foot.php now skips modal on login-page'],
        ],
    ],

    [
        'version' => '1.16.0',
        'date'    => '2026-04-08',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Médico: catch vacío en API reemplazado por toast + abort (bug crítico: cambio de médico se perdía silenciosamente)'],
            ['tag' => 'Fix', 'text' => 'Inventario: 3 catch vacíos en deducciones de stock ahora muestran warning al usuario'],
            ['tag' => 'Fix', 'text' => 'WhatsApp: bucle de envío ya no muestra "enviado" si todos fallan; reporta parciales/total error'],
            ['tag' => 'Fix', 'text' => 'Cambio de residente: se limpia _editingRecord y se regresa a dashboard para evitar guardar en residente incorrecto'],
            ['tag' => 'Fix', 'text' => 'Prefs notificación: se re-carga residentInfo tras guardar para evitar datos stale en caché'],
            ['tag' => 'Fix', 'text' => 'Contactos familia: merge de prefs por identidad (nombre+teléfono) en vez de índice de array'],
            ['tag' => 'Fix', 'text' => 'Invitar usuario: catch vacío reemplazado por toast de error'],
            ['tag' => 'Fix', 'text' => 'Médico: se usaba ID de tabla pivot en vez de usuario_id al guardar médico — causaba Error 500 por FK inválido'],
            ['tag' => 'Cuidados', 'text' => 'Datalist de médico filtrado solo a usuarios con rol=medico'],
            ['tag' => 'Cuidados', 'text' => 'Enlace "+ Invitar médico" en ficha: abre sidebar de invitación con rol pre-seleccionado y bloqueado'],
            ['tag' => 'UI', 'text' => 'Textareas en ficha de residente se auto-ajustan al contenido (sin scroll)'],
            ['tag' => 'UI', 'text' => 'Etiqueta de Notas cambiada de "Notas de Turno" a "Notas"'],
            ['tag' => 'UI', 'text' => 'Foto de residente: avatar interactivo con cámara/galería (web + Capacitor), menú cambiar/eliminar'],
            ['tag' => 'API', 'text' => 'Nuevo endpoint POST residentes.php?id=N (multipart) para subir foto de residente'],
            ['tag' => 'API', 'text' => 'Nuevo endpoint DELETE residentes.php?id=N&foto=1 para eliminar foto de residente'],
        ],
    ],

    [
        'version' => '1.15.4',
        'date'    => '2026-04-07',
        'changes' => [
            ['tag' => 'Permiso', 'text' => 'Nuevo permiso ver_cuidados_grid: ver cuadrícula de categorías en modo solo lectura (read-only)'],
            ['tag' => 'Fix', 'text' => 'Capacitor Camera: se verifica/solicita permiso de cámara antes de capturar; resultType cambiado a uri (menos memoria)'],
            ['tag' => 'Fix', 'text' => 'Navegador móvil: se restauró capture="environment" en inputs de foto para que el browser ofrezca cámara'],
        ],
    ],

    [
        'version' => '1.15.2',
        'date'    => '2026-04-07',
        'changes' => [
            ['tag' => 'Config', 'text' => 'Sesiones: fechas ahora usan fmtDateTime (respeta timezone y formato de fecha configurados)'],
            ['tag' => 'UI', 'text' => 'Botón "Finalizar" en tarjeta de sesión movido al extremo derecho'],
            ['tag' => 'Fix', 'text' => 'Fotos alimentación: todas las instancias de reset usan bindPhotoBox para Capacitor Camera en móvil'],
        ],
    ],

    [
        'version' => '1.15.1',
        'date'    => '2026-04-07',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Foto en móvil/Capacitor: click programático + Capacitor Camera plugin para captura nativa'],
            ['tag' => 'Fix', 'text' => 'CSS input[file] con z-index y dimensiones explícitas para touch en webview'],
            ['tag' => 'Dep', 'text' => '@capacitor/camera añadido para captura nativa de fotos'],
        ],
    ],

    [
        'version' => '1.15.0',
        'date'    => '2026-04-07',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Captura de fotos en móvil: se removió capture="environment" para mostrar cámara + galería'],
            ['tag' => 'UI', 'text' => 'Fotos antes/después de alimentación se apilan verticalmente en pantallas pequeñas (<480px)'],
            ['tag' => 'Admin', 'text' => 'Botón "Ejecutar Todo" en panel Migraciones ejecuta migración completa (master + tenant)'],
            ['tag' => 'API', 'text' => 'Nuevo endpoint run_full_migration en superadmin API'],
        ],
    ],

    [
        'version' => '1.14.0',
        'date'    => '2026-04-07',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Rol familiar ya no puede acceder a los botones de categoría por defecto'],
            ['tag' => 'Config', 'text' => 'Nuevo tab "Sesiones" para ver y gestionar sesiones activas de la institución'],
            ['tag' => 'Config', 'text' => 'Forzar cierre de sesión de cualquier usuario conectado'],
            ['tag' => 'Fix', 'text' => 'Sidebar "Editar residente" ahora refleja los cambios guardados al reabrir'],
            ['tag' => 'API', 'text' => 'Nuevo endpoint /api/sesiones.php (listar y finalizar sesiones)'],
        ],
    ],

    [
        'version' => '1.13.0',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Seguridad', 'text' => 'Permiso editar_residentes de la matriz de roles ahora se aplica al botón de edición y acciones de la Ficha (cdResGrid / cdResExtraGrid)'],
            ['tag' => 'API', 'text' => 'PUT /api/residentes.php ahora permite rol enfermero (controlado por permiso editar_residentes)'],
            ['tag' => 'UI', 'text' => 'Captura de fotos desde cámara disponible en formulario de Alimentación (capture="environment")'],
        ],
    ],

    [
        'version' => '1.12.6',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Badge de signos vitales fuera de rango y alerta amarilla en formulario ahora soportan formato legacy (rows)'],
        ],
    ],

    [
        'version' => '1.12.5',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Usuarios con rol familiar ahora aparecen correctamente en la pestaña Familia de la Ficha del residente'],
            ['tag' => 'API', 'text' => 'Endpoint dashboard incluye familiares_usuarios vinculados al residente'],
        ],
    ],

    [
        'version' => '1.12.4',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Timestamps completos (fecha evento + fecha registro) en tabla de signos vitales del sidebar'],
            ['tag' => 'Cuidados', 'text' => 'Valores fuera de rango resaltados con color y efecto breathing en tabla de detalle'],
            ['tag' => 'UI', 'text' => 'Signos vitales fuera de rango resaltados con breathing en la línea de tiempo'],
        ],
    ],

    [
        'version' => '1.12.3',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Skeleton/ghost loading en listas de usuarios, invitaciones y logs de configuración'],
            ['tag' => 'UI', 'text' => 'Animación de giro en botones de refrescar mientras se cargan datos'],
        ],
    ],

    [
        'version' => '1.12.2',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Iconos PNG (handwash, sponge, toothbrush) en botones de tipo de higiene'],
        ],
    ],

    [
        'version' => '1.12.1',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Iconos sleep/awake reubicados junto a los inputs de hora (tamaño igualado al campo)'],
        ],
    ],

    [
        'version' => '1.12.0',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Icono Gemini reemplaza estrella en botones de reescritura IA'],
            ['tag' => 'Cuidados', 'text' => 'Calidad de sueño obligatoria con indicador rojo (*)'],
            ['tag' => 'Cuidados', 'text' => 'Botones de opción rápida con iconos en formulario de higiene (reemplaza select)'],
            ['tag' => 'UI', 'text' => 'Icono shower.png en botón categoría Higiene y formulario'],
            ['tag' => 'Seguridad', 'text' => 'Redirección automática a login al recibir error 401 (No Autenticado)'],
        ],
    ],

    [
        'version' => '1.11.0',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Familiares registrados se integran automáticamente en Ficha > Familia con badge "Usuario"'],
            ['tag' => 'Cuidados', 'text' => 'Edición de contactos familiares sincroniza cambios de vuelta al registro del usuario'],
            ['tag' => 'UI', 'text' => 'Iconos sleep.png y awake.png junto a hora de dormir/despertar en formulario de sueño'],
        ],
    ],

    [
        'version' => '1.10.3',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Fix', 'text' => 'Icono toilet.png visible en tema claro (filtro CSS por tema)'],
        ],
    ],

    [
        'version' => '1.10.2',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Icono toilet.png en botón Eliminación (reemplaza SVG)'],
        ],
    ],

    [
        'version' => '1.10.1',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'Cuidados', 'text' => 'Alerta de signos vitales muestra fecha y hora de la última lectura'],
            ['tag' => 'Fix', 'text' => 'Corrección de acentos en alerta de signos vitales'],
            ['tag' => 'UI', 'text' => 'Header de formularios sticky (visible al hacer scroll)'],
            ['tag' => 'Notificaciones', 'text' => 'Botón Vista Previa en sidebar de notificaciones admin'],
            ['tag' => 'Notificaciones', 'text' => 'Control deslizante para ajustar tamaño de imagen/GIF/video'],
        ],
    ],

    [
        'version' => '1.10.0',
        'date'    => '2026-04-06',
        'changes' => [
            ['tag' => 'UI', 'text' => 'Transición lateral (slide) al abrir/cerrar formularios de categoría'],
            ['tag' => 'UI', 'text' => 'RxTracker en móvil: Mañana/Tarde/Noche apilados verticalmente'],
            ['tag' => 'Cuidados', 'text' => 'Badge de alerta en Signos Vitales cuando la última lectura está fuera de rango'],
            ['tag' => 'Cuidados', 'text' => 'Banner de alerta dentro del formulario de Signos Vitales con detalle de signos anormales'],
            ['tag' => 'Cuidados', 'text' => 'Iconos vomit.png y blood.png en botones de Eliminación'],
            ['tag' => 'Cuidados', 'text' => 'Icono poop 50% más grande en badge de heces'],
            ['tag' => 'Inventario', 'text' => 'Botón Editar en tarjetas de inventario + campo Notas'],
            ['tag' => 'API', 'text' => 'Dashboard retorna ultimos_signos para evaluación de rango'],
        ],
    ],

    [
        'version' => '1.9.7',
        'date'    => '2026-04-04',
        'changes' => [
            ['tag' => 'Medicación', 'text' => 'Se eliminan registros vacíos al revertir todos los medicamentos'],
            ['tag' => 'Notificaciones', 'text' => 'Soporte para GIF/imagen ilustrativa en notificaciones'],
        ],
    ],

    [
        'version' => '1.9.6',
        'date'    => '2026-04-02',
        'changes' => [
            ['tag' => 'Config', 'text' => 'Archivos de configuración movidos a /conf'],
            ['tag' => 'Cuidados', 'text' => 'Badge de horas desde última evacuación en botón Eliminación'],
            ['tag' => 'Fix', 'text' => 'Efecto breathing de notas importantes visible al abrir cuidados'],
            ['tag' => 'i18n', 'text' => 'Claves heces_sin_registro y heces_horas en es/en'],
        ],
    ],

];
