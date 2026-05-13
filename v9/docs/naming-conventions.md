# GeriApp v9 — Convenciones de nombres

Este documento es la fuente de verdad de cómo se nombran las cosas en v9.
Sirve para mantener el código predecible y para que el asistente IA pueda
razonar sobre la estructura sin confundirse.

## 1. Vistas SPA (`<section id="viewXxx">`)

Todas las vistas viven dentro de `cuidados.php` y se incluyen desde
`v9/includes/views/view-*.php`. El **ID técnico siempre empieza por `view`**
en *camelCase*. **No se renombra** aunque cambie el rótulo visible.

| ID técnico         | Archivo de vista                              | Rótulo UI (es)         | Rótulo UI (en)        | Hash (asistente) |
|--------------------|-----------------------------------------------|------------------------|-----------------------|------------------|
| `viewResidentes`   | `includes/views/view-residentes.php`          | Inicio                 | Home                  | `#residentes`    |
| `viewDashboard`    | `includes/views/view-dashboard.php`           | Cuidados               | Care                  | `#dashboard`     |
| `viewRecords`      | `includes/views/view-records.php`             | Registros              | Records               | `#registros`     |
| `viewInventory`    | `includes/views/view-inventory.php`           | **Medicinas**          | **Medicines**         | `#inventario`    |
| `viewFicha`        | `includes/views/view-ficha.php`               | Ficha                  | Profile               | `#ficha`         |
| `viewExpediente`   | `includes/views/view-expediente.php`          | Expediente             | Chart                 | `#expediente`    |
| `viewConfig`       | `includes/views/view-config.php`              | Configuración          | Settings              | `#config`        |
| `viewFormSueno`              | (dentro del flujo de cuidados) | Registrar sueño         | Log sleep              | `#form-sueno`         |
| `viewFormAlimentacion`       | ″                              | Registrar alimentación  | Log meal               | `#form-alimentacion`  |
| `viewFormMedicacion`         | ″                              | Suministrar medicación  | Administer medication  | `#form-medicacion`    |
| `viewFormSignosVitales`      | ″                              | Registrar signos vitales| Log vital signs        | `#form-signos`        |
| `viewFormNotas`              | ″                              | Notas de turno          | Shift notes            | `#form-notas`         |
| `viewFormNotasMedico`        | ″                              | Nota médica             | Medical note           | `#form-nota-medico`   |

> Regla: **el rótulo UI nunca debe colarse en código** (selectores, IDs, JS).
> Si la UI cambia, sólo se actualizan claves `lang/*.php` y este documento.

### Decisiones históricas
- `nav_home` → "Inicio" (UI) pero el ID interno se llama `viewResidentes`.
- `nav_pharmacy` → "Medicinas" (es) / "Medicines" (en); ID interno `viewInventory`.
- "Dashboard" sólo aparece como ID técnico (`viewDashboard`); en UI se dice "Cuidados".

### Vocabulario obligatorio cara al usuario
La UI, los textos del Asistente IA, los correos y la documentación pública
**deben** usar exclusivamente estos términos. Está **prohibido** usar
sinónimos como "Dashboard", "Tablero", "Bitácora", "Farmacia", "Inventario"
u otros sustitutos cuando se hable con el usuario:

| Término oficial | Significado |
|---|---|
| **Inicio**        | Pantalla principal según rol (lista de residentes para personal; panel del residente para familiares). |
| **Cuidados**      | Panel del residente activo (mismo destino que `#dashboard`). |
| **Registros**     | Bitácora cronológica de cuidados del residente. |
| **Expediente**    | **Archivero electrónico de documentos** (PDFs, escaneos, recetas digitalizadas, identificaciones, consentimientos). NO contiene notas clínicas estructuradas. |
| **Ficha**         | Datos personales, contactos y alergias del residente. |
| **Medicinas**     | Tracker de medicación e inventario de medicamentos. |
| **Configuración** | Ajustes de la institución y del usuario. |

## 2. Claves de internacionalización (`lang/es.php`, `lang/en.php`)

`snake_case` con prefijo de dominio:

- `nav_*` → ítems de navegación (sidebar/bottom bar).
- `ficha_*` → campos y secciones de la Ficha.
- `tl_*` → timeline/registros.
- `notif_*` → notificaciones.
- `rx_*` → prescripciones / Rx.
- `inv_*` → inventario.
- `btn_*` → botones genéricos.
- `form_*` → labels comunes de formularios.
- `period_*` → tabs día/semana/mes.
- `gender_*`, `civil_*`, `status_*` → enumeraciones.
- `confirm_*` → mensajes de confirmación.
- `error_*` → mensajes de error.

## 3. Selectores DOM e IDs

- IDs de elementos: prefijo `cd` + camelCase (ej. `cdPatientName`, `cdResNombre`,
  `cdHeaderResidentCard`, `cdRecTimeline`).
- Clases CSS de la app: prefijo `cd-` + kebab-case (ej. `cd-form-header`,
  `cd-tl-item`, `cd-header-res-select`).
- Clases utilitarias internas: `cd-` + kebab-case (`cd-skeleton`, `cd-ghost-loading`,
  `cd-loading-pill`, `cd-role-locked`).
- `data-*` attributes: kebab-case (`data-nav`, `data-perm-id`, `data-cd-locked`).

## 4. Variables JS (módulos `assets/js/cd-*.js[.php]`)

- Estado/módulo (privado por convención): prefijo `_` + camelCase
  (`_residenteId`, `_currentView`, `_resData`, `_recordsData`, `_recordPeriod`,
  `_careFormDirty`).
- Constantes globales en mayúsculas: `BASE`, `API_URL`, `CURRENT_USER_ID`,
  `USER_ROLE`, `RESIDENTES`, `CAN_EDIT`, `IS_ADMIN`.
- Funciones: camelCase, verbo + sustantivo (`loadDashboard`, `showView`,
  `reloadCurrentView`, `updatePatientName`, `updateHeaderResidentCard`).
- Helpers utilitarios cortos: `$()`, `$$()`, `t()`, `esc()`, `api()`,
  `skeleton(n)`, `btnLoading()`, `showToast()`.

## 5. Endpoints PHP (`v9/api/*.php`)

- `snake_case.php` (ej. `asistente.php`, `dashboard.php`, `residentes.php`).
- Acciones expuestas vía `?action=...` también en `snake_case` (`list`, `get`,
  `send`, `delete`, `rename`, …).
- Helpers comunes en `api/helpers.php`: `api_auth()`, `api_inst_id()`,
  `api_user_id()`, `api_rol()`, `api_body()`, `api_int()`, `api_ok()`,
  `api_error()`, `api_method()`.

## 6. Esquema de base de datos (`v9/db/expected_schema.php`)

- Tablas y columnas en `snake_case`. Tablas tenant-scoped: `'scope' => 'tenant'`.
- Tablas del dominio del chat asistente: prefijo `chat_asistente_*`
  (`chat_asistente_conv`, `chat_asistente_mensajes`, `chat_asistente_memoria`).
- Campos cifrados: gemelo `*_enc` (BLOB) + el original NULLable, ambos
  declarados en `conf/encryption_state.json` con fase `consolidated`.
- Índices: `idx_<tabla>_<sufijo>` (ej. `idx_chat_msg_conv`).
- Foreign keys lógicas: `fk_<tabla>_<sufijo>` (cuando aplica).

## 7. Roles

Slug en minúscula: `superadmin`, `admin`, `medico`, `enfermero`, `familiar`.
Rótulos human-readable se viven en `lang/*.php` (`role_*`).

## 8. Permisos

- Identificadores `data-perm-id` en kebab/snake mixto descriptivo
  (ej. `inv_add_item_btn`, `ficha_edit_info_btn`, `dash_qs_view_inventory_btn`).
- Banderas PHP: `$canSec*` para secciones (`$canSecRegistros`,
  `$canSecMedicacion`, `$canSecFicha`, …) y `$canEdit*` para acciones
  (`$canEditResidents`, `$canEditFamily`, …).

## 9. Memoria del asistente

Tabla `chat_asistente_memoria` (scope tenant). Tipos válidos:
`preferencia`, `hecho`, `instruccion`, `contexto`. Alcance: `usuario`
(asociado a `usuario_id`) o `institucion` (`usuario_id IS NULL`).
La IA emite `<recordar tipo="..." alcance="..." importancia="1-10">texto</recordar>`
y el backend extrae, cifra y persiste. El usuario nunca ve esas etiquetas.
