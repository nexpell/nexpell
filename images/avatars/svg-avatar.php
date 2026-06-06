<?php
header('Content-Type: image/svg+xml');

// Fallback für fehlenden Namen
$name = isset($_GET['name']) ? strtoupper(trim($_GET['name'])) : 'U';
$words = explode(" ", $name);

// Initialen extrahieren
/*$initials = '';
if (!empty($words[0])) {
    $initials .= $words[0][0];
}
if (count($words) > 1 && !empty($words[count($words) - 1])) {
    $initials .= $words[count($words) - 1][0];
}*/

// Initialen extrahieren
if (strtoupper($name) === 'GAST') {
    $initials = 'G';
} else {
    $words = explode(" ", $name);
    $initials = '';
    if (!empty($words[0])) {
        $initials .= $words[0][0];
    }
    if (count($words) > 1 && !empty($words[count($words) - 1])) {
        $initials .= $words[count($words) - 1][0];
    }
}

// Hintergrundfarbe ermitteln
$bg_colors = ['#F0F8FF', '#FAEBD7', '#00FFFF', '#7FFFD4', '#F0FFFF', '#F5F5DC', '#FFE4C4', '#FFEBCD', '#0000FF', '#8A2BE2', '#A52A2A', '#DEB887', '#5F9EA0', '#7FFF00', '#D2691E', '#FF7F50', '#6495ED', '#DC143C', '#696969'];
$userID = hexdec(substr(md5($name), 0, 4));
$bg_color = $bg_colors[$userID % count($bg_colors)];
?>
<svg width="90" height="90" xmlns="http://www.w3.org/2000/svg">
    <rect x="0" y="0" width="90" height="90" style="fill: <?= $bg_color ?>;"></rect>
    <text x="50%" y="50%" dy=".1em" fill="#000000" font-size="50px" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif"><?= htmlentities($initials) ?></text>
</svg>
