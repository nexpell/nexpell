<?php
declare(strict_types=1);

use nexpell\CMSDatabaseMigration;

return function (CMSDatabaseMigration $m): void {

    $m->log("🚀 Migration 1.0.3.2 gestartet");

    // ❗ KEINE Dateioperationen
    // ❗ KEIN rootBase
    // ❗ KEIN updater_lock

    $m->log("ℹ️ Neuer Updater wird nach Dateiaustausch aktiviert");

    $m->log("🎉 Migration 1.0.3.2 abgeschlossen");
};
