<?php

use nexpell\LanguageService;

// Session absichern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standard setzen, wenn nicht vorhanden
$_SESSION['language'] = $_SESSION['language'] ?? 'de';

// Initialisieren
global $languageService;
$languageService = new LanguageService($_database);

// Admin-Modul laden
$languageService->readModule('overview', true);




use nexpell\AccessControl;
// Den Admin-Zugriff für das Modul überprüfen
AccessControl::checkAdminAccess('ac_overview');

$version = trim(require __DIR__ . '/../system/version.php');

$phpversion = phpversion() < '4.3' ? '<font color="#FF0000">' . phpversion() . '</font>' :
    '<font color="#008000">' . phpversion() . '</font>';
$zendversion = zend_version() < '1.3' ? '<font color="#FF0000">' . zend_version() . '</font>' :
    '<font color="#008000">' . zend_version() . '</font>';
$mysqlversion = mysqli_get_server_version($_database) < '40000' ?
    '<font color="#FF0000">' . mysqli_get_server_info($_database) . '</font>' :
    '<font color="#008000">' . mysqli_get_server_info($_database) . '</font>';
$get_phpini_path = get_cfg_var('cfg_file_path');
$get_allow_url_fopen =
    get_cfg_var('allow_url_fopen') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
        '<font color="#FF0000">' . $languageService->module[ 'off' ] . '</font>';
$get_allow_url_include =
    get_cfg_var('allow_url_include') ? '<font color="#FF0000">' . $languageService->module[ 'on' ] . '</font>' :
        '<font color="#008000">' . $languageService->module[ 'off' ] . '</font>';
$get_display_errors =
    get_cfg_var('display_errors') ? '<font color="#FFA500">' . $languageService->module[ 'on' ] . '</font>' :
        '<font color="#008000">' . $languageService->module[ 'off' ] . '</font>';
$get_file_uploads = get_cfg_var('file_uploads') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
    '<font color="#FF0000">' . $languageService->module[ 'off' ] . '</font>';
$get_log_errors = get_cfg_var('log_errors') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
    '<font color="#FF0000">' . $languageService->module[ 'off' ] . '</font>';
#$get_magic_quotes =
#    get_cfg_var('magic_quotes_gpc') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
#        '<font color="#FFA500">' . $languageService->module[ 'off' ] . '</font>';
$get_max_execution_time = get_cfg_var('max_execution_time') < 30 ?
    '<font color="#FF0000">' . get_cfg_var('max_execution_time') . '</font> <small>(min. > 30)</small>' :
    '<font color="#008000">' . get_cfg_var('max_execution_time') . '</font>';
#$get_memory_limit =
#    get_cfg_var('memory_limit') > 128 ? '<font color="#FFA500">' . get_cfg_var('memory_limit') . '</font>' :
#        '<font color="#008000">' . get_cfg_var('memory_limit') . '</font>';
$get_open_basedir = get_cfg_var('open_basedir') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
    '<font color="#FFA500">' . $languageService->module[ 'off' ] . '</font>';
$get_post_max_size =
    get_cfg_var('post_max_size') > 8 ? '<font color="#FFA500">' . get_cfg_var('post_max_size') . '</font>' :
        '<font color="#008000">' . get_cfg_var('post_max_size') . '</font>';
$get_register_globals =
    get_cfg_var('register_globals') ? '<font color="#FF0000">' . $languageService->module[ 'on' ] . '</font>' :
        '<font color="#008000">' . $languageService->module[ 'off' ] . '</font>';
#$get_safe_mode = get_cfg_var('safe_mode') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
#    '<font color="#FF0000">' . $languageService->module[ 'off' ] . '</font>';
$get_short_open_tag =
    get_cfg_var('short_open_tag') ? '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>' :
        '<font color="#FFA500">' . $languageService->module[ 'off' ] . '</font>';

if (function_exists('curl_version')) {
    $curl_check = '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>';
} else {
    $curl_check = '<font color="#FF0000">' . $languageService->module[ 'off' ] . '</font>';
    $fatal_error = true;
}
if (function_exists('curl_exec')) {
    $curlexec_check = '<font color="#008000">' . $languageService->module[ 'on' ] . '</font>';
} else {
    $curlexec_check = '<font color="#FF0000">' . $languageService->module[ 'off' ] . '</font>';
    $fatal_error = true;
}

$get_upload_max_filesize = get_cfg_var('upload_max_filesize') > 16 ?
    '<font color="#FFA500">' . get_cfg_var('upload_max_filesize') . '</font>' :
    '<font color="#008000">' . get_cfg_var('upload_max_filesize') . '</font>';
$info_na = '<font color="#8F8F8F">' . $languageService->module[ 'na' ] . '</font>';
if (function_exists("gd_info")) {
    $gdinfo = gd_info();
    $get_gd_info = '<font color="#008000">' . $languageService->module[ 'enable' ] . '</font>';
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
    $get_gd_info = '<font color="#FF0000">' . $languageService->module[ 'disable' ] . '</font>';
    $gdinfo[ 'GD Version' ] = '---';
    $get_gdtypes = '---';
}

$gd = gd_info();

if (!empty($gd['FreeType Support']) && $gd['FreeType Support']) {
    $freetype = '<font color="#008000">aktiviert</font>';
} else {
    $freetype = '<font color="#FF0000">nicht aktiviert</font>';
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


<div class="card">
<div class="card-header">
        <?php echo $languageService->module['system_information']; ?>
    </div>
    <div class="card-body"><div class="container py-5">

<!-- Serverinfo und GD Graphics -->
<div class="row">
    <div class="col-md-6">
        <h4 class="mb-3"><?php echo $languageService->module['serverinfo']; ?></h4>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><?php echo $languageService->module['nexpell_version']; ?></td><td><em class="text-success"><?php echo $version; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['php_version']; ?></td><td><em><?php echo $phpversion; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['zend_version']; ?></td><td><em><?php echo $zendversion; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['mysql_version']; ?></td><td><em><?php echo $mysqlversion; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['databasename']; ?></td><td><em><?php echo $db; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['server_os']; ?></td><td><em><?php echo (($php_s = @php_uname('s')) ? $php_s : $info_na); ?></em></td></tr>
                <tr><td><?php echo $languageService->module['server_host']; ?></td><td><em><?php echo (($php_n = @php_uname('n')) ? $php_n : $info_na); ?></em></td></tr>
                <tr><td><?php echo $languageService->module['server_release']; ?></td><td><em><?php echo (($php_r = @php_uname('r')) ? $php_r : $info_na); ?></em></td></tr>
                <tr><td><?php echo $languageService->module['server_version']; ?></td><td><em><?php echo (($php_v = @php_uname('v')) ? $php_v : $info_na); ?></em></td></tr>
                <tr><td><?php echo $languageService->module['server_machine']; ?></td><td><em><?php echo (($php_m = @php_uname('m')) ? $php_m : $info_na); ?></em></td></tr>
            </tbody>
        </table>
    </div>

    <div class="col-md-6">
        <h4 class="mb-3">GD Graphics Library</h4>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td>GD Graphics Library</td><td><em><?php echo $get_gd_info; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['supported_types']; ?></td><td><em><?php echo $get_gdtypes; ?></em></td></tr>
                <tr><td>GD Lib <?php echo $languageService->module['version']; ?></td><td><em><?php echo $gdinfo['GD Version']; ?></em></td></tr>
                <tr><td>FreeType</td><td><em><?php echo $freetype; ?></em></td></tr>
            </tbody>
        </table>

        <h4 class="mb-3"><?php echo $languageService->module['interface']; ?></h4>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td><?php echo $languageService->module['server_api']; ?></td><td><em><?php echo php_sapi_name(); ?></em></td></tr>
                <tr><td><?php echo $languageService->module['apache']; ?></td><td><em><?php if(function_exists("apache_get_version")) echo apache_get_version(); else echo $languageService->module['na']; ?></em></td></tr>
                <tr><td><?php echo $languageService->module['apache_modules']; ?></td><td><em><?php if(function_exists("apache_get_modules")){if(count(apache_get_modules()) > 1) $get_apache_modules = implode(", ", apache_get_modules()); echo $get_apache_modules;} else{ echo $languageService->module['na'];} ?></em></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- PHP Settings -->
<div class="row">
    <div class="col-md-12">
        <h4 class="mb-3"></i> <?php echo $languageService->module['php_settings']; ?></h4>
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th><?php echo $languageService->module['property']; ?></th>
                    <th><?php echo $languageService->module['value']; ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2">
                        <?php echo $languageService->module['legend']; ?>:
                        <span class="text-success"><?php echo $languageService->module['green']; ?>:</span> <?php echo $languageService->module['setting_ok']; ?> -
                        <span class="text-warning"><?php echo $languageService->module['orange']; ?>:</span> <?php echo $languageService->module['setting_notice']; ?> -
                        <span class="text-danger"><?php echo $languageService->module['red']; ?>:</span> <?php echo $languageService->module['setting_error']; ?>
                    </td>
                </tr>
                <tr><td>php.ini <?php echo $languageService->module['path']; ?></td><td><em><?php echo $get_phpini_path; ?></em></td></tr>
                <tr><td>Allow URL fopen</td><td><em><?php echo $get_allow_url_fopen; ?></em></td></tr>
                <tr><td>Allow URL Include</td><td><em><?php echo $get_allow_url_include; ?></em></td></tr>
                <tr><td>Display Errors</td><td><em><?php echo $get_display_errors; ?></em></td></tr>
                <tr><td>Error Log</td><td><em><?php echo $get_log_errors; ?></em></td></tr>
                <tr><td>File Uploads</td><td><em><?php echo $get_file_uploads; ?></em></td></tr>
                <tr><td>max. Execution Time</td><td><em><?php echo $get_max_execution_time; ?></em></td></tr>
                <tr><td>Open Basedir</td><td><em><?php echo $get_open_basedir; ?></em></td></tr>
                <tr><td>max. Upload (Filesize)</td><td><em><?php echo $get_upload_max_filesize; ?></em></td></tr>
                <tr><td>Post max Size</td><td><em><?php echo $get_post_max_size; ?></em></td></tr>
                <tr><td>Register Globals</td><td><em><?php echo $get_register_globals; ?></em></td></tr>
                <tr><td>Short Open Tag</td><td><em><?php echo $get_short_open_tag; ?></em></td></tr>
            </tbody>
        </table>
    </div>
</div>

</div>
</div>
</div>
