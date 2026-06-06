<?php

$language_array = array(
    'database'         => 'Database',
    'sql_query'        => 'Query SQL / Backup',
    'backup_file'      => 'File di Backup',
    'file'             => 'File',
    'date'             => 'Data',
    'created_by'       => 'Creato da',
    'actions'          => 'Azioni',

    'export'           => 'Crea Backup',
    'upload'           => 'Carica / Ripristina',
    'optimize'         => 'Ottimizza',
    'delete'           => 'Elimina',
    'download'         => 'Download',
    'close'            => 'Chiudi',

    'export_info'      => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-hdd-network"></i> Backup del Database</h5>
  <p>
    Questa operazione <strong>crea un backup completo</strong> di tutte le tabelle 
    nel database e lo salva come file <code>.sql</code> nella cartella 
    <code>myphp-backup-files/</code>. 
    Il backup viene registrato automaticamente nella tabella <code>backups</code>, 
    in modo da poterlo <strong>ripristinare</strong> successivamente direttamente dal pannello di amministrazione.
  </p>
</div>',
    'upload_info'      => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Nota sul Ripristino del Database</h5>
  <p>
    Carica un file <code>.sql</code> precedentemente generato tramite il modulo di amministrazione. 
    Il file verrà salvato nella cartella <code>myphp-backup-files/</code> e registrato nella tabella 
    <code>backups</code>. Poi puoi cliccare <strong>Ripristina</strong> nella lista dei backup. 
    Lo script ripristina il database <strong>senza</strong> modificare la tabella <code>backups</code> – 
    mantenendo intatta tutta la cronologia dei backup.
  </p>
</div>',
    'import_info1'     => 'Qui puoi creare o importare backup.',
    'import_info2'     => 'Elenco di tutti i backup disponibili con data, creatore e azioni.',
    'optimize_info'    => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-gear-wide-connected"></i> Ottimizzazione del Database</h5>
  <p>
    Questa operazione <strong>ottimizza tutte le tabelle</strong> nel database, 
    <strong>rimuove la frammentazione</strong> e 
    <strong>migliora le prestazioni</strong> del sistema. 
    Gli spazi inutilizzati vengono liberati e le strutture delle tabelle riorganizzate 
    per query più veloci – senza perdita di dati.
  </p>
</div>',
    'really_delete'    => 'Vuoi davvero eliminare questo backup? Questa azione non può essere annullata.',

    'select_option'    => 'Seleziona azione',
);
