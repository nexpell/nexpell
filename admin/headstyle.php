<?php
use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;
// Admin Rechte
AccessControl::checkAdminAccess('ac_headstyle');

// Aktuellen Style laden
$res = safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id = 1");
$current = mysqli_fetch_assoc($res);
$selected = $current['selected_style'] ?? '';

// Styles array (key → label)
$styles = [];
for ($i = 1; $i <= 10; $i++) {
    $key = "head-boxes-$i";
    $styles[$key] = sprintf($languageService->get('headline_style'), $i);
}
?>

<style>
.style-card {
    cursor: pointer;
    transition: 0.25s ease;
}

.style-card .card-title {
    padding: 0px;
    min-height: unset;
}

.style-card.active {
    border: 2px solid #28a745 !important;
    box-shadow: 0 0 15px rgba(40, 167, 69, 0.45) !important;
    transform: scale(1.01);
}

.style-card:hover {
    border-color: #fe821d;
}

</style>

<div id="ac-live-alert" class="mb-3"></div>
<div class="card-body">
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($styles as $key => $label): ?>
            <div class="col">

                <!-- Klickbare Card -->
                <label class="card style-card shadow-sm border-0 mt-3 h-100 <?= ($selected === $key ? 'active' : '') ?>">

                    <!-- Radio -->
                    <input type="radio"
                           name="selected_style"
                           class="select-radio d-none"
                           value="<?= htmlspecialchars($key) ?>"
                           <?= $selected === $key ? 'checked' : '' ?> />

                    <div class="card-header">
                        <div class="card-title">
                            <div class="fw-semibold mb-2 custom-height">
                                <?= htmlspecialchars($label) ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">

                        <img src="/admin/images/headlines/<?= str_replace('head-boxes-', 'headlines-', $key) ?>.jpg"
                             alt="<?= htmlspecialchars($label) ?>"
                             class="img-fluid rounded"
                             style="max-height: 230px;">
                    </div>

                </label>

            </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- JAVASCRIPT: Auswahl & Speichern per AJAX -->
<script>
// Systemmeldung im Seitenkontext anzeigen (Bootstrap Alert)
function showInlineAlert(type, message) {
    const host = document.getElementById('ac-live-alert');
    if (!host) return;

    // alte Meldung ersetzen
    host.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;

    // nach oben scrollen, damit die Meldung "über der Seite" sichtbar ist
    host.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Live-Auswahl & Speichern
document.querySelectorAll('.select-radio').forEach(radio => {

    radio.addEventListener('change', function () {

        let selectedStyle = this.value;

        document.querySelectorAll('.style-card').forEach(card => {
            card.classList.remove('active');
        });
        this.closest('.style-card').classList.add('active');

        fetch("headstyle_save.php", {
    method: "POST",
    credentials: 'include',
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "style=" + encodeURIComponent(selectedStyle)
})
.then(res => res.text())
.then(msg => {
    console.log("Antwort:", msg);

    if (msg.trim() === "OK") {
        showInlineAlert('success', '<?= $languageService->get("toast_success") ?>');

        // optional: Seite nach 3 Sekunden neu laden, damit ggf. serverseitige Flash-Alerts / Status sauber reflektiert werden
        //window.setTimeout(() => {
        //    window.location.reload();
        //}, 3000);
    } else {
        showInlineAlert('danger', '<?= $languageService->get("toast_error") ?>'.replace('%s', msg));
    }
})
.catch(err => {
    console.error(err);
    showInlineAlert('danger', '<?= $languageService->get("toast_ajax_error") ?>');
});

    });

});
</script>