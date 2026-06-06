<?php

// Language array for the module 'database'
$language_array = array(
  'alert_no_file_selected'      => 'No file selected.',
  'backup_file'                 => 'Backup file',
  'created_by'                  => 'Created by',
  'database'                    => 'Database',
  'export'                      => 'Export',
  'file'                        => 'File',
  'optimize'                    => 'Optimize',
  'sql_query'                   => 'SQL queries / backups',
  'upload'                      => 'Upload',
  'export_info'             => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-hdd-network"></i> Database backup</h5>
  <p>
    This process <strong>creates a complete backup</strong> of all tables 
    in the database and saves it as a <code>.sql</code> file in the 
    <code>myphp-backup-files/</code> directory. 
    The backup is automatically registered in the <code>backups</code> table, 
    so you can later <strong>restore</strong> it directly from the admin area 
    if needed.
  </p>
</div>',
    'upload_info'             => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Note on database restoration</h5>
  <p>
    You upload a previously created <code>.sql</code> file via the admin form. 
    The file is saved in the <code>myphp-backup-files/</code> directory and recorded 
    in the <code>backups</code> table. Afterwards, you can click 
    <strong>Restore</strong> in the backup list. The script restores the database 
    <strong>without</strong> modifying the <code>backups</code> table – 
    ensuring that your entire backup history is preserved.
  </p>
</div>',
    'import_info1'            => 'Here, backups can be created or imported.',
    'import_info2'            => 'List of all available backups with date, creator, and actions.',
    'optimize_info'           => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-gear-wide-connected"></i> Database optimization</h5>
  <p>
    This process <strong>optimizes all tables</strong> in the database, 
    <strong>removes fragmentation</strong>, and 
    <strong>improves system performance</strong>. 
    Unused storage areas are cleaned up and the table structure is reorganized 
    for faster queries – without any data loss.
  </p>
</div>',
);
?>