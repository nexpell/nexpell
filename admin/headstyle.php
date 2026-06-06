<?php
use nexpell\LanguageService;
use nexpell\AccessControl;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard Sprache setzen wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Language init
global $languageService,$_database;;
$languageService = new LanguageService($_database);
$languageService->readModule('headstyle', true);

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

.style-card.active {
    border: 2px solid #28a745 !important;
    box-shadow: 0 0 15px rgba(40, 167, 69, 0.45);
    transform: scale(1.01);
}

.style-card:hover {
    border-color: #fe821d;
}

/* Bootstrap Toast rechts unten */
.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 99999;
}
</style>

<div class="toast-container"></div>

<div class="card">
    <div class="card-header">
        <?= $languageService->get('select_headline_style'); ?>
    </div>

    <div class="card-body">
        <div class="container py-5">

            <div class="row">

            <?php foreach (array_chunk($styles, 5, true) as $column): ?>
                <div class="col-md-6">
                    <div class="row row-cols-1">
                    <?php foreach ($column as $key => $label): ?>
                        <div class="col">

                            <!-- Klickbare Card -->
                            <label class="card style-card <?= ($selected === $key ? 'active' : '') ?>">

                                <!-- Radio (unsichtbar, aber klickbar über die Karte) -->
                                <input type="radio"
                                       name="selected_style"
                                       class="select-radio d-none"
                                       value="<?= htmlspecialchars($key) ?>"
                                       <?= $selected === $key ? 'checked' : '' ?> />

                                <div class="card-body text-center">

                                    <div class="fw-bold mb-2 custom-height">
                                        <?= htmlspecialchars($label) ?>
                                    </div>

                                    <img src="/admin/images/headlines/<?= str_replace('head-boxes-', 'headlines-', $key) ?>.jpg"
                                        alt="<?= htmlspecialchars($label) ?>"
                                        class="img-fluid rounded"
                                        style="max-height: 180px; border:1px solid #dee2e6;">
                                </div>

                            </label>

                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            </div>

        </div>
    </div>
</div>

<!-- JAVASCRIPT: Auswahl & Speichern per AJAX -->
<script>
// Toast Nachricht anzeigen
// Toast Nachricht anzeigen
function showToast(type, message) {
    const id = "toast-" + Math.random().toString(36).substring(2);

    const toastHTML = `
        <div id="${id}" class="toast align-items-center text-white bg-${type} border-0 mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;

    document.querySelector(".toast-container").insertAdjacentHTML("beforeend", toastHTML);

    const toastElement = new bootstrap.Toast(document.getElementById(id));
    toastElement.show();
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
        showToast('success', '✔ <?= $languageService->get("toast_success") ?>');
    } else {
        showToast('danger', '❌ <?= $languageService->get("toast_error") ?>'.replace('%s', msg));
    }
})
.catch(err => {
    console.error(err);
    showToast('danger', '❌ <?= $languageService->get("toast_ajax_error") ?>');
});

    });

});

</script>
