<section id="viewFormNotas" class="cd-view">
<div class="cd-form-view">
    <div class="cd-form-header">
        <h2 class="cd-form-title">
            <img src="assets/icons/notes.png" alt="" class="cd-form-title-icon" aria-hidden="true">
            <?= t('form_notes_title') ?> <span id="cdNotesCount" style="font-weight:400;color:var(--cd-text-muted)">(0)</span>
        </h2>
        <button class="cd-form-back" data-back>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            <?= t('form_back') ?>
        </button>
    </div>
    <div id="cdNotesList" class="cd-notes-list"></div>
    <div class="cd-note-form" id="cdNoteForm">
        <div id="cdNoteImgPreview"></div>
        <div class="cd-note-priority-chips">
            <button type="button" class="cd-note-priority-chip active" data-val="normal"><?= t('note_priority_normal') ?></button>
            <button type="button" class="cd-note-priority-chip" data-val="importante"><?= t('note_priority_important') ?></button>
            <button type="button" class="cd-note-priority-chip" data-val="urgente"><?= t('note_priority_urgent') ?></button>
        </div>
        <div class="cd-note-input-row">
            <button type="button" class="cd-note-attach-btn<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdNoteAttachBtn" data-perm-id="form_note_attach_photo_btn" title="<?= t('note_attach') ?>"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar notas."' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </button>
            <textarea id="cdNoteInput" placeholder="<?= t('note_write') ?>" rows="1"></textarea>
            <button class="cd-note-send<?= $canEdit ? '' : ' cd-role-locked' ?>" id="cdNoteSubmit" data-perm-id="form_save_nota_btn" title="<?= t('note_send') ?>"<?= $canEdit ? '' : ' data-cd-locked data-lock-title="Acceso restringido" data-lock-msg="Solo el personal autorizado puede agregar notas."' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
        <input type="file" id="cdNoteFileInput" accept="image/jpeg,image/png,image/webp" style="display:none">
    </div>
</div>
</section>
