<?php

// Sprach-Array für das Modul 'database'
$language_array = array(
    // Allgemeine Überschriften
    'database'         => 'Datenbank',
    'sql_query'        => 'SQL-Abfragen / Backups',
    'backup_file'      => 'Backup-Datei',
    'file'             => 'Datei',
    'date'             => 'Datum',
    'created_by'       => 'Erstellt von',
    'actions'          => 'Aktionen',

    // Buttons
    'export'           => 'Backup erstellen',
    'upload'           => 'Hochladen / Wiederherstellen',
    'optimize'         => 'Optimieren',
    'delete'           => 'Löschen',
    'download'         => 'Download',
    'close'            => 'Schließen',

    // Infos / Hinweise
    'export_info'      => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-hdd-network"></i> Datenbank-Backup</h5>
  <p>
    Dieser Vorgang <strong>erstellt ein vollständiges Backup</strong> aller Tabellen 
    in der Datenbank und speichert es als <code>.sql</code>-Datei im Ordner 
    <code>myphp-backup-files/</code>. 
    Das Backup wird automatisch in der Tabelle <code>backups</code> registriert, 
    sodass du es später bei Bedarf direkt aus dem Adminbereich 
    <strong>wiederherstellen</strong> kannst.
  </p>
</div>',
    'upload_info'      => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Hinweis zur Datenbank-Wiederherstellung</h5>
  <p>
    Du lädst eine vorher erzeugte <code>.sql</code>-Datei über das Admin-Formular hoch. 
    Die Datei wird im Ordner <code>myphp-backup-files/</code> gespeichert und in der Tabelle 
    <code>backups</code> vermerkt. Anschließend kannst du in der Backup-Liste auf 
    <strong>Wiederherstellen</strong> klicken. Das Skript spielt die Datenbank neu ein, 
    <strong>ohne</strong> dabei die Tabelle <code>backups</code> zu verändern – 
    so bleibt deine gesamte Backup-Historie erhalten.
  </p>
</div>',
    'import_info1'     => 'Hier können Backups erstellt oder importiert werden.',
    'import_info2'     => 'Liste aller verfügbaren Backups mit Datum, Ersteller und Aktionen.',
    'optimize_info'    => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-gear-wide-connected"></i> Datenbank-Optimierung</h5>
  <p>
    Dieser Vorgang <strong>optimiert alle Tabellen</strong> in der Datenbank, 
    <strong>entfernt Fragmentierungen</strong> und 
    <strong>verbessert die Performance</strong> des Systems. 
    Dabei werden ungenutzte Speicherbereiche bereinigt und die Tabellenstruktur 
    für schnellere Abfragen neu organisiert – ohne dass Daten verloren gehen.
  </p>
</div>',
    'really_delete'    => 'Möchten Sie dieses Backup wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.',

    // Sonstiges
    'select_option'    => 'Aktion auswählen',
);


