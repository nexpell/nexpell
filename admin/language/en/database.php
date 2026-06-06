<?php

$language_array = array(
    'database'         => 'Database',
    'sql_query'        => 'SQL Queries / Backups',
    'backup_file'      => 'Backup File',
    'file'             => 'File',
    'date'             => 'Date',
    'created_by'       => 'Created by',
    'actions'          => 'Actions',

    'export'           => 'Create Backup',
    'upload'           => 'Upload / Restore',
    'optimize'         => 'Optimize',
    'delete'           => 'Delete',
    'download'         => 'Download',
    'close'            => 'Close',

    'export_info'      => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-hdd-network"></i> Database Backup</h5>
  <p>
    This operation <strong>creates a full backup</strong> of all tables in the database 
    and saves it as a <code>.sql</code> file in the folder 
    <code>myphp-backup-files/</code>. 
    The backup is automatically recorded in the <code>backups</code> table, 
    so you can <strong>restore</strong> it later directly from the admin panel if needed.
  </p>
</div>',
    'upload_info'      => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Database Restore Notice</h5>
  <p>
    Upload a previously generated <code>.sql</code> file via the admin form. 
    The file will be saved in <code>myphp-backup-files/</code> and recorded in the 
    <code>backups</code> table. Then you can click <strong>Restore</strong> in the backup list. 
    The script restores the database <strong>without</strong> touching the <code>backups</code> table – 
    keeping your entire backup history intact.
  </p>
</div>',
    'import_info1'     => 'Create or import backups here.',
    'import_info2'     => 'List of all available backups with date, creator, and actions.',
    'optimize_info'    => '<div class="alert alert-info" role="alert">
  <h5 class="alert-heading"><i class="bi bi-gear-wide-connected"></i> Database Optimization</h5>
  <p>
    This operation <strong>optimizes all tables</strong> in the database, 
    <strong>removes fragmentation</strong>, and 
    <strong>improves system performance</strong>. 
    Unused space is cleared and table structures reorganized for faster queries – 
    without losing any data.
  </p>
</div>',
    'really_delete'    => 'Do you really want to delete this backup? This action cannot be undone.',

    'select_option'    => 'Select action',
);

