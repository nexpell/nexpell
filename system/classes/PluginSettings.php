<?php 

namespace nexpell;

class PluginSettings
{
    public static function load_widget_settings(string $getsite): ?array
    {
        $allowed_sites = [
            'contact', 'imprint', 'privacy_policy', 'profile', 'edit_profile',
            'error_404', 'report', 'static', 'loginoverview', 'register',
            'lostpassword', 'login', 'logout', 'footer', 'navigation', 'topbar',
            'news_comments', 'articles_comments', 'blog_comments', 'gallery_comments',
            'news_recomments', 'polls_comments', 'videos_comments', 'activate'
        ];

        if (in_array($getsite, $allowed_sites)) {
            return [
                'widget-css/contact.css',
                'widget-css/common.css'
            ];
        }

        return null;
    }

    public static function load_widget_settings_css(string $getsite): ?array
    {
        // Whitelist erlaubter statischer Seiten
        $allowed_sites = [
            'contact', 'imprint', 'privacy_policy', 'profile', 'edit_profile',
            'error_404', 'report', 'static', 'loginoverview', 'register',
            'lostpassword', 'login', 'logout', 'footer', 'navigation', 'topbar',
            'news_comments', 'articles_comments', 'blog_comments', 'gallery_comments',
            'news_recomments', 'polls_comments', 'videos_comments', 'activate'
        ];

        if (in_array($getsite, $allowed_sites)) {
            // Beispiel: CSS-Dateien oder Klassen für Widgets zurückgeben
            return [
                'widget-css/contact.css',
                'widget-css/common.css'
            ];
        }

        // Keine Widgets notwendig für diese Seite
        return null;
    }
}
