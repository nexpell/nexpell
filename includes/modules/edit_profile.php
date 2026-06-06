<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use nexpell\LanguageService;
use nexpell\SeoUrlHandler;

global $_database,$languageService;

$lang = $languageService->detectLanguage();
$languageService->readModule('edit_profile');

// Style aus settings holen
$config = mysqli_fetch_array(safe_query("SELECT selected_style FROM settings_headstyle_config WHERE id=1"));
$class = htmlspecialchars($config['selected_style']);

// Header-Daten
$data_array = [
    'class'    => $class,
    'title' => $languageService->get('title'),
    'subtitle' => 'Imprint'
];

echo $tpl->loadTemplate("edit_profiles", "head", $data_array, 'theme');




$userID = $_SESSION['userID'] ?? null;
if (!$userID) die('Nicht eingeloggt.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST-Daten holen
    $firstname = escape($_POST['firstname'] ?? '');
    $lastname  = escape($_POST['lastname'] ?? '');
    $location  = escape($_POST['location'] ?? '');
    $about_me  = escape($_POST['about_me'] ?? '');
    $birthday  = escape($_POST['birthday'] ?? '');
    $gender    = escape($_POST['gender'] ?? '');
    $signatur  = escape($_POST['signatur'] ?? '');
    $twitter   = escape($_POST['twitter'] ?? '');
    $facebook  = escape($_POST['facebook'] ?? '');
    $website   = escape($_POST['website'] ?? '');
    $github    = escape($_POST['github'] ?? '');
    $instagram = escape($_POST['instagram'] ?? '');

    $croppedAvatar = $_POST['croppedAvatar'] ?? null;
    $avatar_url = null;
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;

    // Avatar speichern
    if ($croppedAvatar && preg_match('/^data:image\/(\w+);base64,/', $croppedAvatar, $type)) {
        $data = base64_decode(substr($croppedAvatar, strpos($croppedAvatar, ',') + 1));
        if ($data !== false) {
            $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : $type[1];
            $uploadDir = 'images/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $avatarFileName = "avatar_user{$userID}_" . time() . '.' . $ext;
            $avatarPath = $uploadDir . $avatarFileName;
            file_put_contents($avatarPath, $data);
            $avatar_url = $avatarPath;
        }
    }

    // ----------------------------
    // user_profiles
    // ----------------------------
    $columns = ['firstname','lastname','location','about_me','birthday','gender','signatur'];
    $values = [$firstname,$lastname,$location,$about_me,$birthday,$gender,$signatur];
    $types = str_repeat('s', count($columns));

    if ($avatar_url) {
        $columns[] = 'avatar';
        $values[] = $avatar_url;
        $types .= 's';
    }

    $stmt = $_database->prepare(
        "INSERT INTO user_profiles (userID,".implode(',', $columns).") VALUES (?,".str_repeat('?,', count($columns)-1)."?) 
        ON DUPLICATE KEY UPDATE ".implode('=?,', $columns).'=?'
    );
    $stmt->bind_param('i'.$types.$types, $userID, ...$values, ...$values);
    $stmt->execute();
    $stmt->close();

    // ----------------------------
    // user_socials
    // ----------------------------
    $stmt = $_database->prepare("
        INSERT INTO user_socials (userID, twitter, facebook, website, github, instagram) 
        VALUES (?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE
            twitter = VALUES(twitter),
            facebook = VALUES(facebook),
            website = VALUES(website),
            github = VALUES(github),
            instagram = VALUES(instagram)
    ");
    $stmt->bind_param("isssss", $userID, $twitter, $facebook, $website, $github, $instagram);
    $stmt->execute();
    $stmt->close();

    // ----------------------------
    // user_settings
    // ----------------------------
    $stmt = $_database->prepare("
        INSERT INTO user_settings (userID, dark_mode, email_notifications) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            dark_mode=VALUES(dark_mode),
            email_notifications=VALUES(email_notifications)
    ");
    $stmt->bind_param("iii", $userID, $dark_mode, $email_notifications);
    $stmt->execute();
    $stmt->close();

    header("Location: " . SeoUrlHandler::convertToSeoUrl("index.php?site=profile&userID=$userID"));
    exit;
}

// ----------------------------
// Werte für Template laden
// ----------------------------
$firstname = $lastname = $location = $about_me = $avatar = $birthday = $gender = $signatur = '';
$twitter = $facebook = $website = $github = $instagram = '';
$dark_mode = $email_notifications = 0;

if ($row = $_database->query("SELECT * FROM user_profiles WHERE userID = $userID")->fetch_assoc()) {
    extract($row);
}

if ($row = $_database->query("SELECT * FROM user_socials WHERE userID = $userID")->fetch_assoc()) {
    extract($row);
}

if ($row = $_database->query("SELECT * FROM user_settings WHERE userID = $userID")->fetch_assoc()) {
    extract($row);
}

$gender = trim($gender ?? '');

$gender_options = [
    'gender'             => $gender,
    'gender_selected_male'   => $gender === 'male' ? 'selected' : '',
    'gender_selected_female' => $gender === 'female' ? 'selected' : '',
    'gender_selected_other'  => $gender === 'other' ? 'selected' : '',
    'gender_selected_empty'  => $gender === '' ? 'selected' : '',
];

$edit_password = '<a type="button" class="btn btn-warning" href="' . SeoUrlHandler::convertToSeoUrl("index.php?site=edit_password") . '">Edit Passwort</a>';
$edit_email    = '<a type="button" class="btn btn-warning" href="' . SeoUrlHandler::convertToSeoUrl("index.php?site=edit_email") . '">Edit eMail</a>';


$data_array = [

    'edit_password' => $edit_password,
    'edit_email' => $edit_email,
    // Userdaten mit htmlspecialchars zur Sicherheit
    'userID' => htmlspecialchars($userID ?? ''),
    'firstname' => htmlspecialchars($firstname ?? ($user['firstname'] ?? '')),
    'lastname' => htmlspecialchars($lastname ?? ($user['lastname'] ?? '')),
    'location' => htmlspecialchars($location ?? ($user['location'] ?? '')),
    'about_me' => htmlspecialchars($about_me ?? ($user['about_me'] ?? '')),
    'birthday' => htmlspecialchars($birthday ?? ($user['birthday'] ?? '')),
    'age'      => isset($user['birthday']) ? (date_diff(date_create($user['birthday']), date_create('today'))->y) : '',
    'gender' => $gender,
    'gender_select_empty'  => $gender_options['gender_selected_empty'],
    'gender_select_male'   => $gender_options['gender_selected_male'],
    'gender_select_female' => $gender_options['gender_selected_female'],
    'gender_select_other'  => $gender_options['gender_selected_other'],
    'signatur' => htmlspecialchars($signatur ?? ($user['signatur'] ?? '')),
    
    // Avatar-URL: wenn $avatar gesetzt, sonst aus $user['avatar']
    'avatar_url' => !empty($avatar) ? $avatar : (!empty($user['avatar']) ? '/path/to/avatars/' . htmlspecialchars($user['avatar']) : ''),
    
    // Social Media
    'twitter' => htmlspecialchars($twitter ?? ($user['twitter'] ?? '')),
    'facebook' => htmlspecialchars($facebook ?? ($user['facebook'] ?? '')),
    'website' => htmlspecialchars($website ?? ($user['website'] ?? '')),
    'github' => htmlspecialchars($github ?? ($user['github'] ?? '')),
    'instagram' => htmlspecialchars($instagram ?? ($user['instagram'] ?? '')),
    
    // Settings Checkbox (checked oder leer)
    'dark_mode_checked' => !empty($dark_mode) ? 'checked' : (!empty($user['dark_mode']) ? 'checked' : ''),
    'email_notifications_checked' => !empty($email_notifications) ? 'checked' : (!empty($user['email_notifications']) ? 'checked' : ''),
    
    // Sprachstrings
    'edit_profile_title' => $languageService->get('edit_profile_title'),
    'label_firstname' => $languageService->get('label_firstname'),
    'label_lastname' => $languageService->get('label_lastname'),
    'label_location' => $languageService->get('label_location'),
    'label_about_me' => $languageService->get('label_about_me'),
    'label_signature' => $languageService->get('label_signature'),
    'label_avatar' => $languageService->get('label_avatar'),
    'title_social_networks' => $languageService->get('title_social_networks'),
    'label_twitter' => $languageService->get('label_twitter'),
    'label_facebook' => $languageService->get('label_facebook'),
    'label_website' => $languageService->get('label_website'),
    'label_github' => $languageService->get('label_github'),
    'label_instagram' => $languageService->get('label_instagram'),
    'title_settings' => $languageService->get('title_settings'),
    'label_dark_mode' => $languageService->get('label_dark_mode'),
    'label_email_notifications' => $languageService->get('label_email_notifications'),
    'btn_save' => $languageService->get('btn_save'),
    'btn_crop' => $languageService->get('btn_crop'),
    'label_birthday'    => $languageService->get('label_birthday'),
    'label_gender'      => $languageService->get('label_gender'),
    'select_gender'     => $languageService->get('select_gender'),
    'gender_male'       => $languageService->get('gender_male'),
    'gender_female'     => $languageService->get('gender_female'),
    'gender_other'      => $languageService->get('gender_other'),
];
echo $tpl->loadTemplate("edit_profiles", "content", $data_array, 'theme');
?>
