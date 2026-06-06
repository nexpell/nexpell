<?php

// Array di lingua per il modulo 'database'
$language_array = array(
  'alert_no_file_selected'      => 'Nessun file selezionato.',
  'backup_file'                 => 'File di backup',
  'created_by'                  => 'Creato da',
  'database'                    => 'Database',
  'export'                      => 'Esporta',
  'file'                        => 'File',
  'optimize'                    => 'Ottimizza',
  'sql_query'                   => 'Query SQL / backup',
  'upload'                      => 'Upload',
  'export_info'             => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-hdd-network"></i> Backup del database</h5>
  <p>
    Questa operazione <strong>crea un backup completo</strong> di tutte le tabelle 
    del database e lo salva come file <code>.sql</code> nella cartella 
    <code>myphp-backup-files/</code>. 
    Il backup viene registrato automaticamente nella tabella <code>backups</code>, 
    così potrai <strong>ripristinarlo</strong> direttamente dall’area di amministrazione 
    in un secondo momento, se necessario.
  </p>
</div>',
    'upload_info'             => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Nota sul ripristino del database</h5>
  <p>
    Carichi un file <code>.sql</code> creato in precedenza tramite il modulo di amministrazione. 
    Il file viene salvato nella cartella <code>myphp-backup-files/</code> e registrato 
    nella tabella <code>backups</code>. Successivamente puoi fare clic su 
    <strong>Ripristina</strong> nell’elenco dei backup. Lo script ripristina il database 
    <strong>senza</strong> modificare la tabella <code>backups</code> – 
    mantenendo così l’intera cronologia dei backup.
  </p>
</div>',
    'import_info1'            => 'Qui è possibile creare o importare backup.',
    'import_info2'            => 'Elenco di tutti i backup disponibili con data, autore e azioni.',
    'optimize_info'           => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-gear-wide-connected"></i> Ottimizzazione del database</h5>
  <p>
    Questa operazione <strong>ottimizza tutte le tabelle</strong> del database, 
    <strong>rimuove la frammentazione</strong> e 
    <strong>migliora le prestazioni</strong> del sistema. 
    Le aree di memoria inutilizzate vengono pulite e la struttura delle tabelle 
    viene riorganizzata per query più veloci – senza perdita di dati.
  </p>
</div>',
);
?>