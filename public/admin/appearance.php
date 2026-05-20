<?php
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireRole(['admin']);

$theme = Theme::current();
$errors = [];
$old = [
    'primary' => $theme['primary'],
    'secondary' => $theme['secondary'],
];

if (is_post()) {
    $old['primary'] = trim((string) post('primary'));
    $old['secondary'] = trim((string) post('secondary'));

    if (!Csrf::validate(post('_csrf_token'))) {
        $errors['general'] = 'La sesión expiró. Recarga la página e intenta otra vez.';
    } else {
        $result = Theme::save($old['primary'], $old['secondary']);
        if ($result['ok']) {
            Flash::set('success', 'Colores actualizados correctamente. El cambio quedó guardado de forma permanente.');
            redirect('admin/appearance.php');
        }
        $errors = $result['errors'];
    }
}

$primaryText = Theme::bestTextColor(Theme::sanitizeHex($old['primary'], Theme::DEFAULT_PRIMARY));
$secondaryText = Theme::bestTextColor(Theme::sanitizeHex($old['secondary'], Theme::DEFAULT_SECONDARY));
require_once __DIR__ . '/../partials/header.php';
?>
<section class="hero">
    <div class="card wide-card">
        <span class="badge">Solo Admin</span>
        <h1 style="font-size: 34px; margin-top: 14px;">Apariencia del sistema</h1>
        <p class="muted">
            Esta sección solo es visible para administradores. Permite cambiar el color primario y secundario de la página. El sistema calcula automáticamente si el texto debe ser claro u oscuro para mantener contraste con el fondo.
        </p>

        <?php if (isset($errors['general'])): ?>
            <div class="alert danger"><?= h($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= Csrf::field() ?>
            <div class="grid two" style="margin-top: 0;">
                <div class="form-group">
                    <label for="primary">Color primario</label>
                    <input id="primary" name="primary" type="color" value="<?= h($old['primary']) ?>" data-theme-color="primary" required>
                    <?php if (isset($errors['primary'])): ?><div class="error-text"><?= h($errors['primary']) ?></div><?php endif; ?>
                    <p class="muted">Se usa en botones principales, títulos y acentos fuertes.</p>
                </div>
                <div class="form-group">
                    <label for="secondary">Color secundario</label>
                    <input id="secondary" name="secondary" type="color" value="<?= h($old['secondary']) ?>" data-theme-color="secondary" required>
                    <?php if (isset($errors['secondary'])): ?><div class="error-text"><?= h($errors['secondary']) ?></div><?php endif; ?>
                    <p class="muted">Se usa en insignias, detalles y elementos de apoyo visual.</p>
                </div>
            </div>

            <div class="color-preview">
                <div class="color-preview-header">Vista previa del encabezado con contraste automático</div>
                <div class="color-preview-body">
                    <div class="color-chip-row">
                        <span class="color-chip primary" style="background: <?= h(Theme::sanitizeHex($old['primary'], Theme::DEFAULT_PRIMARY)) ?>; color: <?= h($primaryText) ?>;">Primario · texto automático</span>
                        <span class="color-chip secondary-chip" style="background: <?= h(Theme::sanitizeHex($old['secondary'], Theme::DEFAULT_SECONDARY)) ?>; color: <?= h($secondaryText) ?>;">Secundario · texto automático</span>
                    </div>
                    <p class="muted">Aunque selecciones colores muy claros u oscuros, el texto se ajusta automáticamente a blanco u oscuro según el mejor contraste.</p>
                </div>
            </div>

            <div class="actions" style="margin-top: 18px;">
                <button class="btn" type="submit">Guardar colores</button>
                <a class="btn secondary" href="<?= h(url('admin/dashboard.php')) ?>">Volver al dashboard</a>
            </div>
        </form>
    </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
