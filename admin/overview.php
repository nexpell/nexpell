<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\AccessControl;

// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_overview');

$version = trim(require __DIR__ . '/../system/version.php');

$phpversion = phpversion() < '4.3' ? '<span class="badge text-bg-danger">' . phpversion() . '</span>' :
    '<span class="badge text-bg-success">' . phpversion() . '</span>';
$zendversion = zend_version() < '1.3' ? '<span class="badge text-bg-danger">' . zend_version() . '</span>' :
    '<span class="badge text-bg-success">' . zend_version() . '</span>';
$mysqlversion = mysqli_get_server_version($_database) < '40000' ?
    '<span class="badge text-bg-danger">' . mysqli_get_server_info($_database) . '</span>' :
    '<span class="badge text-bg-success">' . mysqli_get_server_info($_database) . '</span>';
$get_phpini_path = get_cfg_var('cfg_file_path');
$get_allow_url_fopen =
    get_cfg_var('allow_url_fopen') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
        '<span class="badge text-bg-danger">' . $languageService->module[ 'off' ] . '</span>';
$get_allow_url_include =
    get_cfg_var('allow_url_include') ? '<span class="badge text-bg-danger">' . $languageService->module[ 'on' ] . '</span>' :
        '<span class="badge text-bg-success">' . $languageService->module[ 'off' ] . '</span>';
$get_display_errors =
    get_cfg_var('display_errors') ? '<span class="badge text-bg-warning">' . $languageService->module[ 'on' ] . '</span>' :
        '<span class="badge text-bg-success">' . $languageService->module[ 'off' ] . '</span>';
$get_file_uploads = get_cfg_var('file_uploads') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
    '<span class="badge text-bg-danger">' . $languageService->module[ 'off' ] . '</span>';
$get_log_errors = get_cfg_var('log_errors') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
    '<span class="badge text-bg-danger">' . $languageService->module[ 'off' ] . '</span>';
#$get_magic_quotes =
#    get_cfg_var('magic_quotes_gpc') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
#        '<span class="badge text-bg-warning">' . $languageService->module[ 'off' ] . '</span>';
$get_max_execution_time = get_cfg_var('max_execution_time') < 30 ?
    '<span class="badge text-bg-danger">' . get_cfg_var('max_execution_time') . '</span> <small>(min. > 30)</small>' :
    '<span class="badge text-bg-success">' . get_cfg_var('max_execution_time') . '</span>';
#$get_memory_limit =
#    get_cfg_var('memory_limit') > 128 ? '<span class="badge text-bg-warning">' . get_cfg_var('memory_limit') . '</span>' :
#        '<span class="badge text-bg-success">' . get_cfg_var('memory_limit') . '</span>';
$get_open_basedir = get_cfg_var('open_basedir') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
    '<span class="badge text-bg-warning">' . $languageService->module[ 'off' ] . '</span>';
$get_post_max_size =
    get_cfg_var('post_max_size') > 8 ? '<span class="badge text-bg-warning">' . get_cfg_var('post_max_size') . '</span>' :
        '<span class="badge text-bg-success">' . get_cfg_var('post_max_size') . '</span>';
$get_register_globals =
    get_cfg_var('register_globals') ? '<span class="badge text-bg-danger">' . $languageService->module[ 'on' ] . '</span>' :
        '<span class="badge text-bg-success">' . $languageService->module[ 'off' ] . '</span>';
#$get_safe_mode = get_cfg_var('safe_mode') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
#    '<span class="badge text-bg-danger">' . $languageService->module[ 'off' ] . '</span>';
$get_short_open_tag =
    get_cfg_var('short_open_tag') ? '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>' :
        '<span class="badge text-bg-warning">' . $languageService->module[ 'off' ] . '</span>';

if (function_exists('curl_version')) {
    $curl_check = '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>';
} else {
    $curl_check = '<span class="badge text-bg-danger">' . $languageService->module[ 'off' ] . '</span>';
    $fatal_error = true;
}
if (function_exists('curl_exec')) {
    $curlexec_check = '<span class="badge text-bg-success">' . $languageService->module[ 'on' ] . '</span>';
} else {
    $curlexec_check = '<span class="badge text-bg-danger">' . $languageService->module[ 'off' ] . '</span>';
    $fatal_error = true;
}

$get_upload_max_filesize = get_cfg_var('upload_max_filesize') > 16 ?
    '<span class="badge text-bg-warning">' . get_cfg_var('upload_max_filesize') . '</span>' :
    '<span class="badge text-bg-success">' . get_cfg_var('upload_max_filesize') . '</span>';
$info_na = '<span class="badge text-bg-secondary">' . $languageService->module[ 'na' ] . '</span>';
if (function_exists("gd_info")) {
    $gdinfo = gd_info();
    $get_gd_info = '<span class="badge text-bg-success">' . $languageService->module[ 'enable' ] . '</span>';
    $get_gdtypes = array();
    if (isset($gdinfo[ 'FreeType Support' ]) && $gdinfo[ 'FreeType Support' ] === true) {
        $get_gdtypes[ ] = "FreeType";
    }
    if (isset($gdinfo[ 'T1Lib Support' ]) && $gdinfo[ 'T1Lib Support' ] === true) {
        $get_gdtypes[ ] = "T1Lib";
    }
    if (isset($gdinfo[ 'GIF Read Support' ]) && $gdinfo[ 'GIF Read Support' ] === true) {
        $get_gdtypes[ ] = "*.gif " . $languageService->module[ 'read' ];
    }
    if (isset($gdinfo[ 'GIF Create Support' ]) && $gdinfo[ 'GIF Create Support' ] === true) {
        $get_gdtypes[ ] = "*.gif " . $languageService->module[ 'create' ];
    }
    if (isset($gdinfo[ 'JPG Support' ]) && $gdinfo[ 'JPG Support' ] === true) {
        $get_gdtypes[ ] = "*.jpg";
    } elseif (isset($gdinfo[ 'JPEG Support' ]) && $gdinfo[ 'JPEG Support' ] === true) {
        $get_gdtypes[ ] = "*.jpg";
    }
    if (isset($gdinfo[ 'PNG Support' ]) && $gdinfo[ 'PNG Support' ] === true) {
        $get_gdtypes[ ] = "*.png";
    }
    if (isset($gdinfo[ 'WBMP Support' ]) && $gdinfo[ 'WBMP Support' ] === true) {
        $get_gdtypes[ ] = "*.wbmp";
    }
    if (isset($gdinfo[ 'XBM Support' ]) && $gdinfo[ 'XBM Support' ] === true) {
        $get_gdtypes[ ] = "*.xbm";
    }
    if (isset($gdinfo[ 'XPM Support' ]) && $gdinfo[ 'XPM Support' ] === true) {
        $get_gdtypes[ ] = "*.xpm";
    }
    $get_gdtypes = implode(", ", $get_gdtypes);
} else {
    $get_gd_info = '<span class="badge text-bg-danger">' . $languageService->module[ 'disable' ] . '</span>';
    $gdinfo[ 'GD Version' ] = '---';
    $get_gdtypes = '---';
}

$gd = gd_info();

if (!empty($gd['FreeType Support']) && $gd['FreeType Support']) {
    $freetype = '<span class="badge text-bg-success">aktiviert</span>';
} else {
    $freetype = '<span class="badge text-bg-danger">nicht aktiviert</span>';
}

if (function_exists("apache_get_modules")) {
    $apache_modules = implode(", ", apache_get_modules());
} else {
    $apache_modules = $languageService->module[ 'na' ];
}

$get = safe_query("SELECT DATABASE()");
$ret = mysqli_fetch_array($get);
$db = $ret[ 0 ];
 ?>

<!-- Page Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div class="alert alert-light py-2 px-3 mb-0 d-flex flex-wrap align-items-center justify-content-end gap-2">
      <span class="fw-semibold"><?php echo $languageService->module['legend']; ?>:</span>
      <span class="badge text-bg-success">Grün</span><span class="small text-muted">Einstellung korrekt</span>
      <span class="badge text-bg-warning">Orange</span><span class="small text-muted">Einstellung beachten</span>
      <span class="badge text-bg-danger">Rot</span><span class="small text-muted">Fehlerhafte Einstellung</span>
    </div>
  </div>

  <div class="row g-4 align-items-stretch">
    <!-- Serverinfo -->
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-hdd-network"></i>
            <span><?php echo $languageService->module['serverinfo']; ?></span>
          </div>
          <hr class="my-1">
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table">
            <thead>
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><?php echo $languageService->module['nexpell_version']; ?></td><td><span class="badge text-bg-success"><?php echo $version; ?></span></td></tr>
                <tr><td><?php echo $languageService->module['php_version']; ?></td><td><?php echo $phpversion; ?></td></tr>
                <tr><td><?php echo $languageService->module['zend_version']; ?></td><td><?php echo $zendversion; ?></td></tr>
                <tr><td><?php echo $languageService->module['mysql_version']; ?></td><td><?php echo $mysqlversion; ?></td></tr>
                <tr><td><?php echo $languageService->module['databasename']; ?></td><td><?php echo $db; ?></td></tr>
                <tr><td><?php echo $languageService->module['server_os']; ?></td><td><?php echo (($php_s = @php_uname('s')) ? $php_s : $info_na); ?></td></tr>
                <tr><td><?php echo $languageService->module['server_host']; ?></td><td><?php echo (($php_n = @php_uname('n')) ? $php_n : $info_na); ?></td></tr>
                <tr><td><?php echo $languageService->module['server_release']; ?></td><td><?php echo (($php_r = @php_uname('r')) ? $php_r : $info_na); ?></td></tr>
                <tr><td><?php echo $languageService->module['server_version']; ?></td><td><?php echo (($php_v = @php_uname('v')) ? $php_v : $info_na); ?></td></tr>
                <tr><td><?php echo $languageService->module['server_machine']; ?></td><td><?php echo (($php_m = @php_uname('m')) ? $php_m : $info_na); ?></td></tr>
            </tbody>
        </table>
          </div>
        </div>
      </div>
    </div>

    <!-- GD + Interface -->
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-diagram-3"></i>
            <span><?php echo $languageService->module['interface']; ?></span>
            <small class="small-muted">GD Graphics Library &amp; <?php echo $languageService->module['interface']; ?></small>
          </div>
          <hr class="my-1">
        </div>

        <div class="card-body p-0">
          <div class="p-3 pb-2">
            <div class="fw-semibold mb-2">GD Graphics Library</div>
          </div>
          <div class="table-responsive">
            <table class="table">
            <thead>
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td>GD Graphics Library</td><td><?php echo $get_gd_info; ?></td></tr>
                <tr><td><?php echo $languageService->module['supported_types']; ?></td><td><?php echo $get_gdtypes; ?></td></tr>
                <tr><td>GD Lib <?php echo $languageService->module['version']; ?></td><td><?php echo $gdinfo['GD Version']; ?></td></tr>
                <tr><td>FreeType</td><td><?php echo $freetype; ?></td></tr>
            </tbody>
        </table>
          </div>

          <div class="p-3 pb-2 border-top">
            <div class="fw-semibold mb-2"><?php echo $languageService->module['interface']; ?></div>
          </div>
          <div class="table-responsive">
            <table class="table">
            <thead>
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><?php echo $languageService->module['server_api']; ?></td><td><?php echo php_sapi_name(); ?></td></tr>
                <tr><td><?php echo $languageService->module['apache']; ?></td><td><?php if(function_exists("apache_get_version")) echo apache_get_version(); else echo $languageService->module['na']; ?></td></tr>
                <tr><td><?php echo $languageService->module['apache_modules']; ?></td><td><?php if(function_exists("apache_get_modules")){if(count(apache_get_modules()) > 1) $get_apache_modules = implode(", ", apache_get_modules()); echo $get_apache_modules;} else{ echo $languageService->module['na'];} ?></td></tr>
            </tbody>
        </table>
          </div>
        </div>
      </div>
    </div>

    <!-- PHP Settings -->
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header">
          <div class="card-title">
            <i class="bi bi-sliders"></i>
            <span><?php echo $languageService->module['php_settings']; ?></span>
          </div>
          <hr class="my-1">
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table">
            <thead>
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td>php.ini <?php echo $languageService->module['path']; ?></td><td><?php echo $get_phpini_path; ?></td></tr>
                <tr><td>Allow URL fopen</td><td><?php echo $get_allow_url_fopen; ?></td></tr>
                <tr><td>Allow URL Include</td><td><?php echo $get_allow_url_include; ?></td></tr>
                <tr><td>Display Errors</td><td><?php echo $get_display_errors; ?></td></tr>
                <tr><td>Error Log</td><td><?php echo $get_log_errors; ?></td></tr>
                <tr><td>File Uploads</td><td><?php echo $get_file_uploads; ?></td></tr>
                <tr><td>max. Execution Time</td><td><?php echo $get_max_execution_time; ?></td></tr>
                <tr><td>Open Basedir</td><td><?php echo $get_open_basedir; ?></td></tr>
                <tr><td>max. Upload (Filesize)</td><td><?php echo $get_upload_max_filesize; ?></td></tr>
                <tr><td>Post max Size</td><td><?php echo $get_post_max_size; ?></td></tr>
                <tr><td>Register Globals</td><td><?php echo $get_register_globals; ?></td></tr>
                <tr><td>Short Open Tag</td><td><?php echo $get_short_open_tag; ?></td></tr>
            </tbody>
        </table>
          </div>
        </div>
      </div>
    </div>
</div>