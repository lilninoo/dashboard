<?php
/**
 * Classe de gestion des requêtes AJAX
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardAjax {
    
    /**
     * Initialiser les handlers AJAX
     */
    public static function init() {
        // Actions pour les utilisateurs connectés
        $ajax_actions = [
            'tmm_save_week_progress' => 'saveWeekProgress',
            'tmm_update_user_profile' => 'updateUserProfile',
            'tmm_change_user_password' => 'changeUserPassword',
            'tmm_cancel_membership' => 'cancelMembership',
            'tmm_get_progress_data' => 'getProgressData',
            'tmm_search_courses' => 'searchCourses',
            'tmm_enroll_course' => 'enrollCourse',
            'tmm_get_notifications' => 'getNotifications',
            'tmm_mark_notification_read' => 'markNotificationRead',
            'tmm_get_activity_feed' => 'getActivityFeed',
            'tmm_update_timeline_date' => 'updateTimelineDate',
            'tmm_get_course_details' => 'getCourseDetails',
            'tmm_update_user_preferences' => 'updateUserPreferences',
            'tmm_generate_certificate' => 'generateCertificate',
            'tmm_get_analytics_data' => 'getAnalyticsData',
            'tmm_export_user_data' => 'exportUserData',
            'tmm_chatbot_message' => 'processChatbotMessage'
        ];

        foreach ($ajax_actions as $action => $method) {
            add_action('wp_ajax_' . $action, [__CLASS__, $method]);
        }

        // Actions publiques (non connecté)
        add_action('wp_ajax_nopriv_tmm_get_course_preview', [__CLASS__, 'getCoursePreview']);
    }

    /**
     * Vérifier le nonce et les permissions
     */
    private static function verifyRequest($nonce_action = 'tmm_dashboard_nonce', $capability = null) {
        // Vérifier le nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], $nonce_action)) {
            wp_send_json_error(['message' => __('Requête non autorisée', 'tmm-dashboard')]);
        }

        // Vérifier si l'utilisateur est connecté
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Vous devez être connecté', 'tmm-dashboard')]);
        }

        // Vérifier les capacités si spécifiées
        if ($capability && !current_user_can($capability)) {
            wp_send_json_error(['message' => __('Permissions insuffisantes', 'tmm-dashboard')]);
        }

        return true;
    }

    /**
     * Sauvegarder la progression hebdomadaire
     */
    public static function saveWeekProgress() {
        self::verifyRequest('save_week_progress');

        $user_id = get_current_user_id();
        $parcours_id = sanitize_text_field($_POST['parcours'] ?? '');
        $month = intval($_POST['month'] ?? 0);
        $week = intval($_POST['value'] ?? 0);
        $checked = $_POST['checked'] === '1';

        if (!$parcours_id || !$month || !$week) {
            wp_send_json_error(['message' => __('Données invalides', 'tmm-dashboard')]);
        }

        $result = TMMDashboardParcours::saveWeekProgress($user_id, $parcours_id, $month, $week, $checked);

        if ($result) {
            // Récupérer la progression mise à jour
            $progress = TMMDashboardParcours::getParcoursProgress($user_id, $parcours_id);
            
            wp_send_json_success([
                'message' => __('Progression sauvegardée', 'tmm-dashboard'),
                'progress' => $progress,
                'week' => $week,
                'checked' => $checked
            ]);
        } else {
            wp_send_json_error(['message' => __('Erreur lors de la sauvegarde', 'tmm-dashboard')]);
        }
    }

    /**
     * Mettre à jour le profil utilisateur
     */
    public static function updateUserProfile() {
        self::verifyRequest('update_profile_nonce');

        $user_id = get_current_user_id();
        $user_data = [];

        // Données autorisées à mettre à jour
        $allowed_fields = ['display_name', 'first_name', 'last_name', 'user_email', 'description'];
        
        foreach ($allowed_fields as $field) {
            if (isset($_POST[$field])) {
                $user_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        // Valider l'email
        if (isset($user_data['user_email']) && !is_email($user_data['user_email'])) {
            wp_send_json_error(['message' => __('Adresse email invalide', 'tmm-dashboard')]);
        }

        // Vérifier si l'email existe déjà
        if (isset($user_data['user_email'])) {
            $email_exists = email_exists($user_data['user_email']);
            if ($email_exists && $email_exists != $user_id) {
                wp_send_json_error(['message' => __('Cette adresse email est déjà utilisée', 'tmm-dashboard')]);
            }
        }

        // Gérer l'upload d'avatar
        if (!empty($_FILES['avatar'])) {
            $avatar_id = self::handleAvatarUpload($_FILES['avatar']);
            if ($avatar_id) {
                update_user_meta($user_id, 'tmm_avatar_id', $avatar_id);
            }
        }

        // Mettre à jour les données utilisateur
        $user_data['ID'] = $user_id;
        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Enregistrer l'événement
        TMMDashboardAnalytics::trackEvent($user_id, 'profile_update', $user_data);

        wp_send_json_success([
            'message' => __('Profil mis à jour avec succès', 'tmm-dashboard'),
            'user' => [
                'display_name' => get_userdata($user_id)->display_name,
                'email' => get_userdata($user_id)->user_email,
                'avatar' => get_avatar_url($user_id)
            ]
        ]);
    }

    /**
     * Gérer l'upload d'avatar
     */
    private static function handleAvatarUpload($file) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Vérifier le type de fichier
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }

        // Limiter la taille (2MB)
        if ($file['size'] > 2097152) {
            return false;
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($upload['error'])) {
            return false;
        }

        // Créer l'attachment
        $attachment = [
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    /**
     * Changer le mot de passe utilisateur
     */
    public static function changeUserPassword() {
        self::verifyRequest('change_password_nonce');

        $user_id = get_current_user_id();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(['message' => __('Tous les champs sont requis', 'tmm-dashboard')]);
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error(['message' => __('Les mots de passe ne correspondent pas', 'tmm-dashboard')]);
        }

        if (strlen($new_password) < 8) {
            wp_send_json_error(['message' => __('Le mot de passe doit contenir au moins 8 caractères', 'tmm-dashboard')]);
        }

        // Vérifier le mot de passe actuel
        $user = get_user_by('id', $user_id);
        if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
            wp_send_json_error(['message' => __('Mot de passe actuel incorrect', 'tmm-dashboard')]);
        }

        // Mettre à jour le mot de passe
        wp_set_password($new_password, $user_id);

        // Enregistrer l'événement
        TMMDashboardAnalytics::trackEvent($user_id, 'password_change', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        // Envoyer un email de notification
        $subject = __('Mot de passe modifié', 'tmm-dashboard');
        $message = sprintf(
            __("Bonjour %s,\n\nVotre mot de passe a été modifié avec succès.\n\nSi vous n'êtes pas à l'origine de cette modification, veuillez nous contacter immédiatement.\n\nCordialement,\nL'équipe %s", 'tmm-dashboard'),
            $user->display_name,
            get_bloginfo('name')
        );
        wp_mail($user->user_email, $subject, $message);

        wp_send_json_success([
            'message' => __('Mot de passe modifié avec succès', 'tmm-dashboard'),
            'redirect' => wp_login_url()
        ]);
    }

    /**
     * Annuler l'abonnement
     */
    public static function cancelMembership() {
        self::verifyRequest();

        $user_id = get_current_user_id();

        if (!function_exists('pmpro_cancelMembershipLevel')) {
            wp_send_json_error(['message' => __('PMPro non installé', 'tmm-dashboard')]);
        }

        $result = pmpro_cancelMembershipLevel(null, $user_id);

        if ($result) {
            // Enregistrer l'événement
            TMMDashboardAnalytics::trackEvent($user_id, 'membership_cancelled', [
                'previous_level' => pmpro_getMembershipLevelForUser($user_id)
            ]);

            wp_send_json_success([
                'message' => __('Abonnement annulé avec succès', 'tmm-dashboard'),
                'redirect' => home_url()
            ]);
        } else {
            wp_send_json_error(['message' => __('Erreur lors de l\'annulation', 'tmm-dashboard')]);
        }
    }

    /**
     * Obtenir les données de progression
     */
    public static function getProgressData() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $range = sanitize_text_field($_POST['range'] ?? '7');
        $type = sanitize_text_field($_POST['type'] ?? 'activity');

        $data = [];

        switch ($type) {
            case 'activity':
                $weeks = $range == '30' ? 4 : ($range == '90' ? 12 : 1);
                $data = TMMDashboardData::getWeeklyProgress($user_id, $weeks);
                break;
                
            case 'courses':
                $data = TMMDashboardData::getUserCourses($user_id);
                break;
                
            case 'time':
                $data = self::getTimeSpentData($user_id, $range);
                break;
                
            case 'performance':
                $data = self::getPerformanceData($user_id, $range);
                break;
        }

        wp_send_json_success($data);
    }

    /**
     * Rechercher des cours
     */
    public static function searchCourses() {
        $query = sanitize_text_field($_POST['query'] ?? '');
        $filters = $_POST['filters'] ?? [];

        if (strlen($query) < 2) {
            wp_send_json_error(['message' => __('Requête trop courte', 'tmm-dashboard')]);
        }

        $args = [
            'post_type' => 'lp_course',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            's' => $query,
            'orderby' => 'relevance',
            'order' => 'DESC'
        ];

        // Appliquer les filtres
        if (!empty($filters['category'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'course_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($filters['category'])
            ];
        }

        if (!empty($filters['level'])) {
            $args['meta_query'][] = [
                'key' => '_lp_level',
                'value' => sanitize_text_field($filters['level']),
                'compare' => '='
            ];
        }

        if (!empty($filters['duration'])) {
            $duration_range = explode('-', $filters['duration']);
            if (count($duration_range) == 2) {
                $args['meta_query'][] = [
                    'key' => '_lp_duration',
                    'value' => [$duration_range[0], $duration_range[1]],
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ];
            }
        }

        $query = new WP_Query($args);
        $results = [];

        foreach ($query->posts as $course) {
            $results[] = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'excerpt' => wp_trim_words($course->post_excerpt, 20),
                'url' => get_permalink($course->ID),
                'thumbnail' => get_the_post_thumbnail_url($course->ID, 'medium'),
                'price' => learn_press_get_course_price($course->ID),
                'students' => get_post_meta($course->ID, '_lp_students', true),
                'rating' => TMMDashboardData::getCourseRating($course->ID),
                'duration' => get_post_meta($course->ID, '_lp_duration', true),
                'level' => get_post_meta($course->ID, '_lp_level', true)
            ];
        }

        wp_send_json_success([
            'results' => $results,
            'total' => $query->found_posts,
            'query' => $query
        ]);
    }

    /**
     * S'inscrire à un cours
     */
    public static function enrollCourse() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $course_id = intval($_POST['course_id'] ?? 0);

        if (!$course_id) {
            wp_send_json_error(['message' => __('ID de cours invalide', 'tmm-dashboard')]);
        }

        // Vérifier que le cours existe
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'lp_course') {
            wp_send_json_error(['message' => __('Cours introuvable', 'tmm-dashboard')]);
        }

        // Vérifier l'abonnement
        $membership = pmpro_getMembershipLevelForUser($user_id);
        if (!$membership) {
            wp_send_json_error(['message' => __('Abonnement requis pour s\'inscrire', 'tmm-dashboard')]);
        }

        // S'inscrire au cours
        if (function_exists('learn_press_enroll_course')) {
            $result = learn_press_enroll_course($user_id, $course_id);
            
            if ($result) {
                // Enregistrer l'événement
                TMMDashboardAnalytics::trackEvent($user_id, 'course_enrolled', [
                    'course_id' => $course_id,
                    'course_name' => $course->post_title
                ]);

                wp_send_json_success([
                    'message' => __('Inscription réussie', 'tmm-dashboard'),
                    'redirect' => get_permalink($course_id)
                ]);
            }
        }

        wp_send_json_error(['message' => __('Erreur lors de l\'inscription', 'tmm-dashboard')]);
    }

    /**
     * Obtenir les notifications
     */
    public static function getNotifications() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);

        $notifications = get_user_meta($user_id, 'tmm_notifications', true) ?: [];
        $unread_count = 0;

        foreach ($notifications as &$notification) {
            if (!$notification['read']) {
                $unread_count++;
            }
            // Formater la date
            $notification['time_ago'] = human_time_diff(strtotime($notification['date']), current_time('timestamp'));
        }

        $paginated = array_slice($notifications, $offset, $limit);

        wp_send_json_success([
            'notifications' => $paginated,
            'total' => count($notifications),
            'unread_count' => $unread_count
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public static function markNotificationRead() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $notification_id = intval($_POST['notification_id'] ?? -1);

        $notifications = get_user_meta($user_id, 'tmm_notifications', true) ?: [];

        if (isset($notifications[$notification_id])) {
            $notifications[$notification_id]['read'] = true;
            update_user_meta($user_id, 'tmm_notifications', $notifications);
            wp_send_json_success(['message' => __('Notification marquée comme lue', 'tmm-dashboard')]);
        }

        wp_send_json_error(['message' => __('Notification introuvable', 'tmm-dashboard')]);
    }

    /**
     * Obtenir le flux d'activité
     */
    public static function getActivityFeed() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');

        $activities = TMMDashboardData::getUserActivity($user_id, $limit, $offset);

        // Filtrer par type si nécessaire
        if ($filter !== 'all') {
            $activities = array_filter($activities, function($activity) use ($filter) {
                return $activity['type'] === $filter;
            });
        }

        wp_send_json_success([
            'activities' => array_values($activities),
            'has_more' => count($activities) === $limit
        ]);
    }

    /**
     * Mettre à jour la date de la timeline
     */
    public static function updateTimelineDate() {
        $parcours_id = sanitize_text_field($_POST['parcours'] ?? '');
        $month = intval($_POST['month'] ?? 0);
        
        if ($parcours_id && $month && is_user_logged_in()) {
            $key = 'timeline_' . sanitize_title($parcours_id) . '_mois_' . $month;
            update_user_meta(get_current_user_id(), $key . '_last_update', current_time('timestamp'));
            wp_send_json_success();
        }
        
        wp_send_json_error();
    }

    /**
     * Obtenir les détails d'un cours
     */
    public static function getCourseDetails() {
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$course_id) {
            wp_send_json_error(['message' => __('ID de cours invalide', 'tmm-dashboard')]);
        }

        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'lp_course') {
            wp_send_json_error(['message' => __('Cours introuvable', 'tmm-dashboard')]);
        }

        $user_id = get_current_user_id();
        $is_enrolled = false;
        $progress = 0;

        if ($user_id) {
            $progress_data = TMMDashboardData::calculateCourseProgress($user_id, $course_id);
            $is_enrolled = $progress_data['percentage'] > 0;
            $progress = $progress_data['percentage'];
        }

        $details = [
            'id' => $course_id,
            'title' => $course->post_title,
            'description' => $course->post_content,
            'excerpt' => $course->post_excerpt,
            'thumbnail' => get_the_post_thumbnail_url($course_id, 'full'),
            'url' => get_permalink($course_id),
            'author' => [
                'id' => $course->post_author,
                'name' => get_the_author_meta('display_name', $course->post_author),
                'avatar' => get_avatar_url($course->post_author)
            ],
            'meta' => [
                'duration' => get_post_meta($course_id, '_lp_duration', true),
                'level' => get_post_meta($course_id, '_lp_level', true),
                'students' => get_post_meta($course_id, '_lp_students', true),
                'lessons' => get_post_meta($course_id, '_lp_lessons', true),
                'quizzes' => get_post_meta($course_id, '_lp_quizzes', true),
                'price' => learn_press_get_course_price($course_id),
                'sale_price' => learn_press_get_course_sale_price($course_id),
                'rating' => TMMDashboardData::getCourseRating($course_id)
            ],
            'user_data' => [
                'is_enrolled' => $is_enrolled,
                'progress' => $progress,
                'can_enroll' => !$is_enrolled && is_user_logged_in()
            ],
            'curriculum' => self::getCourseCurriculum($course_id)
        ];

        wp_send_json_success($details);
    }

    /**
     * Obtenir le curriculum d'un cours
     */
    private static function getCourseCurriculum($course_id) {
        if (!function_exists('learn_press_get_course_curriculum')) {
            return [];
        }

        $curriculum = learn_press_get_course_curriculum($course_id);
        $formatted = [];

        foreach ($curriculum as $section) {
            $section_data = [
                'id' => $section->section_id,
                'title' => $section->section_name,
                'description' => $section->section_description,
                'items' => []
            ];

            if (!empty($section->items)) {
                foreach ($section->items as $item) {
                    $section_data['items'][] = [
                        'id' => $item->ID,
                        'title' => $item->post_title,
                        'type' => $item->post_type,
                        'duration' => get_post_meta($item->ID, '_lp_duration', true),
                        'preview' => get_post_meta($item->ID, '_lp_preview', true) === 'yes'
                    ];
                }
            }

            $formatted[] = $section_data;
        }

        return $formatted;
    }

    /**
     * Mettre à jour les préférences utilisateur
     */
    public static function updateUserPreferences() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $preferences = $_POST['preferences'] ?? [];

        $allowed_preferences = [
            'email_notifications',
            'push_notifications',
            'weekly_report',
            'course_recommendations',
            'theme_mode',
            'language',
            'timezone'
        ];

        foreach ($allowed_preferences as $pref) {
            if (isset($preferences[$pref])) {
                update_user_meta($user_id, 'tmm_pref_' . $pref, sanitize_text_field($preferences[$pref]));
            }
        }

        wp_send_json_success([
            'message' => __('Préférences mises à jour', 'tmm-dashboard'),
            'preferences' => $preferences
        ]);
    }

    /**
     * Générer un certificat
     */
    public static function generateCertificate() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $parcours_id = sanitize_text_field($_POST['parcours_id'] ?? '');

        if (!$parcours_id) {
            wp_send_json_error(['message' => __('Parcours invalide', 'tmm-dashboard')]);
        }

        // Vérifier que le parcours est complété
        $progress = TMMDashboardParcours::getParcoursProgress($user_id, $parcours_id);
        if (!$progress['is_complete']) {
            wp_send_json_error(['message' => __('Parcours non complété', 'tmm-dashboard')]);
        }

        $certificate_url = TMMDashboardCertificates::generateCertificate($user_id, $parcours_id);

        if ($certificate_url) {
            wp_send_json_success([
                'message' => __('Certificat généré', 'tmm-dashboard'),
                'url' => $certificate_url
            ]);
        }

        wp_send_json_error(['message' => __('Erreur lors de la génération', 'tmm-dashboard')]);
    }

    /**
     * Obtenir les données analytics
     */
    public static function getAnalyticsData() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $type = sanitize_text_field($_POST['type'] ?? 'overview');
        $range = sanitize_text_field($_POST['range'] ?? '30');

        $data = TMMDashboardAnalytics::getUserAnalytics($user_id, $type, $range);

        wp_send_json_success($data);
    }

    /**
     * Exporter les données utilisateur
     */
    public static function exportUserData() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $format = sanitize_text_field($_POST['format'] ?? 'json');

        $data = [
            'user_info' => get_userdata($user_id)->data,
            'courses' => TMMDashboardData::getUserCourses($user_id),
            'badges' => TMMDashboardBadges::getUserStats($user_id),
            'certificates' => get_user_meta($user_id, 'tmm_certificates', true),
            'activity' => TMMDashboardData::getUserActivity($user_id, 100),
            'preferences' => self::getUserPreferences($user_id)
        ];

        // Enregistrer l'export
        TMMDashboardAnalytics::trackEvent($user_id, 'data_export', ['format' => $format]);

        switch ($format) {
            case 'csv':
                $export_url = self::generateCSVExport($data, $user_id);
                break;
            case 'pdf':
                $export_url = self::generatePDFExport($data, $user_id);
                break;
            default:
                $export_url = self::generateJSONExport($data, $user_id);
        }

        wp_send_json_success([
            'message' => __('Export généré', 'tmm-dashboard'),
            'url' => $export_url
        ]);
    }

    /**
     * Traiter un message du chatbot
     */
    public static function processChatbotMessage() {
        self::verifyRequest();

        $user_id = get_current_user_id();
        $message = sanitize_text_field($_POST['message'] ?? '');

        if (empty($message)) {
            wp_send_json_error(['message' => __('Message vide', 'tmm-dashboard')]);
        }

        // Traiter le message avec le chatbot
        $response = TMMDashboardChatbot::processMessage($user_id, $message);

        wp_send_json_success([
            'response' => $response,
            'suggestions' => TMMDashboardChatbot::getSuggestions($user_id)
        ]);
    }

    /**
     * Obtenir les données de temps passé
     */
    private static function getTimeSpentData($user_id, $range) {
        global $wpdb;

        $days = intval($range);
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(start_time) as date,
                SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as minutes
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND start_time >= %s
             AND end_time IS NOT NULL
             GROUP BY DATE(start_time)
             ORDER BY date ASC",
            $user_id, $start_date
        ));

        $data = [];
        foreach ($results as $row) {
            $data['labels'][] = date_i18n('j M', strtotime($row->date));
            $data['values'][] = round($row->minutes / 60, 1); // Convertir en heures
        }

        return $data;
    }

    /**
     * Obtenir les données de performance
     */
    private static function getPerformanceData($user_id, $range) {
        global $wpdb;

        $days = intval($range);
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $quiz_results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(end_time) as date,
                AVG(CAST(graduation AS DECIMAL(5,2))) as average_score,
                COUNT(*) as quiz_count
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_quiz'
             AND end_time >= %s
             AND status = 'completed'
             GROUP BY DATE(end_time)
             ORDER BY date ASC",
            $user_id, $start_date
        ));

        $data = [
            'labels' => [],
            'scores' => [],
            'counts' => []
        ];

        foreach ($quiz_results as $row) {
            $data['labels'][] = date_i18n('j M', strtotime($row->date));
            $data['scores'][] = round($row->average_score, 1);
            $data['counts'][] = intval($row->quiz_count);
        }

        return $data;
    }

    /**
     * Obtenir les préférences utilisateur
     */
    private static function getUserPreferences($user_id) {
        $preferences = [];
        $pref_keys = [
            'email_notifications',
            'push_notifications',
            'weekly_report',
            'course_recommendations',
            'theme_mode',
            'language',
            'timezone'
        ];

        foreach ($pref_keys as $key) {
            $preferences[$key] = get_user_meta($user_id, 'tmm_pref_' . $key, true);
        }

        return $preferences;
    }

    /**
     * Générer un export JSON
     */
    private static function generateJSONExport($data, $user_id) {
        $upload_dir = wp_upload_dir();
        $filename = 'tmm-export-' . $user_id . '-' . time() . '.json';
        $filepath = $upload_dir['path'] . '/' . $filename;

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return $upload_dir['url'] . '/' . $filename;
    }

    /**
     * Générer un export CSV
     */
    private static function generateCSVExport($data, $user_id) {
        $upload_dir = wp_upload_dir();
        $filename = 'tmm-export-' . $user_id . '-' . time() . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;

        $fp = fopen($filepath, 'w');

        // En-têtes
        fputcsv($fp, ['Type', 'Data']);

        // Données utilisateur
        fputcsv($fp, ['User Info', json_encode($data['user_info'])]);

        // Cours
        foreach ($data['courses'] as $status => $courses) {
            foreach ($courses as $course) {
                fputcsv($fp, ['Course - ' . $status, $course['name']]);
            }
        }

        fclose($fp);

        return $upload_dir['url'] . '/' . $filename;
    }

    /**
     * Générer un export PDF
     */
    private static function generatePDFExport($data, $user_id) {
        // Nécessite une librairie PDF comme TCPDF ou mPDF
        // Pour l'instant, on retourne un JSON
        return self::generateJSONExport($data, $user_id);
    }

    /**
     * Obtenir un aperçu de cours (public)
     */
    public static function getCoursePreview() {
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$course_id) {
            wp_send_json_error(['message' => __('ID de cours invalide', 'tmm-dashboard')]);
        }

        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'lp_course' || $course->post_status !== 'publish') {
            wp_send_json_error(['message' => __('Cours introuvable', 'tmm-dashboard')]);
        }

        $preview = [
            'id' => $course_id,
            'title' => $course->post_title,
            'excerpt' => $course->post_excerpt,
            'thumbnail' => get_the_post_thumbnail_url($course_id, 'large'),
            'price' => learn_press_get_course_price($course_id),
            'students' => get_post_meta($course_id, '_lp_students', true),
            'lessons' => get_post_meta($course_id, '_lp_lessons', true),
            'duration' => get_post_meta($course_id, '_lp_duration', true),
            'level' => get_post_meta($course_id, '_lp_level', true)
        ];

        wp_send_json_success($preview);
    }
}