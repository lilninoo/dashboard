<?php
/**
 * Classe de gestion des données du dashboard - VERSION COMPLÈTE
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardData {
    
    /**
     * Obtenir des recommandations intelligentes
     */
    public static function getSmartRecommendations($user_id, $limit = 5) {
        global $wpdb;
        
        // Récupérer l'historique de l'utilisateur
        $user_courses = self::getUserCourses($user_id);
        $completed_ids = array_column($user_courses['completed'] ?? [], 'id');
        $in_progress_ids = array_column($user_courses['in_progress'] ?? [], 'id');
        
        // Exclure les cours déjà suivis
        $exclude_ids = array_merge($completed_ids, $in_progress_ids);
        
        // Récupérer les catégories préférées
        $preferred_categories = [];
        if (!empty($completed_ids)) {
            foreach ($completed_ids as $course_id) {
                $cats = wp_get_post_terms($course_id, 'course_category', ['fields' => 'ids']);
                $preferred_categories = array_merge($preferred_categories, $cats);
            }
            $preferred_categories = array_unique($preferred_categories);
        }
        
        // Requête pour les recommandations
        $args = [
            'post_type' => 'lp_course',
            'posts_per_page' => $limit,
            'post__not_in' => $exclude_ids,
            'orderby' => 'meta_value_num',
            'meta_key' => '_lp_students',
            'order' => 'DESC'
        ];
        
        if (!empty($preferred_categories)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'course_category',
                    'field' => 'term_id',
                    'terms' => $preferred_categories
                ]
            ];
        }
        
        $query = new WP_Query($args);
        $recommendations = [];
        
        foreach ($query->posts as $post) {
            // Vérifier si la fonction existe avant de l'utiliser
            if (function_exists('learn_press_get_course')) {
                $course = learn_press_get_course($post->ID);
                if (!$course) continue;
                $duration = $course->get_duration();
            } else {
                // Fallback si LearnPress n'est pas disponible
                $duration = get_post_meta($post->ID, '_lp_duration', true) ?: '2-3 heures';
            }
            
            $default_image = defined('TMM_DASHBOARD_URL') 
                ? TMM_DASHBOARD_URL . 'assets/img/course-default.jpg'
                : plugin_dir_url(dirname(__FILE__)) . 'assets/img/course-default.jpg';
            
            $recommendations[] = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'url' => get_permalink($post->ID),
                'image' => get_the_post_thumbnail_url($post->ID, 'medium') ?: $default_image,
                'relevance_score' => rand(70, 95), // Simulé pour l'instant
                'time_investment' => self::formatDuration($duration),
                'level' => get_post_meta($post->ID, '_lp_level', true) ?: 'intermediate',
                'students_count' => intval(get_post_meta($post->ID, '_lp_students', true))
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Récupérer les cours LearnPress de l'utilisateur + cours accessibles via abonnement
     */
    public static function getUserCourses($user_id) {
        global $wpdb;

        if (!is_user_logged_in() || !$user_id) {
            return ['error' => __('Vous devez être connecté.', 'tmm-dashboard')];
        }

        // Vérifier que la table existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_user_items'");
        if (!$table_exists) {
            return [
                'enrolled' => [],
                'in_progress' => [],
                'completed' => [],
                'accessible' => []
            ];
        }

        // Récupérer les données d'abonnement
        $membership_data = self::getMembershipData($user_id);
        $membership_level_id = $membership_data['level_id'] ?? 0;

        // 1. Récupérer les cours auxquels l'utilisateur est inscrit
        $enrolled_results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT ui.item_id, ui.status, ui.end_time, ui.start_time, 'enrolled' as source
             FROM {$wpdb->prefix}learnpress_user_items ui
             WHERE ui.user_id = %d AND ui.item_type = %s
             ORDER BY ui.start_time DESC",
            $user_id, 'lp_course'
        ));

        // 2. Récupérer les cours accessibles via l'abonnement PMPro (mais pas encore inscrits)
        $accessible_courses = [];
        if ($membership_level_id > 0) {
            $accessible_courses = self::getCoursesForMembershipLevel($membership_level_id);
        }

        // Combiner les résultats
        $all_course_data = [];
        
        // Ajouter les cours inscrits
        foreach ($enrolled_results as $row) {
            $all_course_data[$row->item_id] = [
                'item_id' => $row->item_id,
                'status' => $row->status,
                'end_time' => $row->end_time,
                'start_time' => $row->start_time,
                'source' => 'enrolled',
                'membership_level' => null
            ];
        }

        // Ajouter les cours accessibles via abonnement (s'ils ne sont pas déjà inscrits)
        foreach ($accessible_courses as $course_id => $level_name) {
            if (!isset($all_course_data[$course_id])) {
                $all_course_data[$course_id] = [
                    'item_id' => $course_id,
                    'status' => 'accessible', // Nouveau statut
                    'end_time' => null,
                    'start_time' => null,
                    'source' => 'membership',
                    'membership_level' => $level_name
                ];
            } else {
                // Cours déjà inscrit, mais on ajoute l'info du niveau d'abonnement
                $all_course_data[$course_id]['membership_level'] = $level_name;
            }
        }

        $courses = [
            'enrolled' => [],
            'in_progress' => [],
            'completed' => [],
            'accessible' => [] // Nouvelle catégorie
        ];

        foreach ($all_course_data as $row_data) {
            $course_id = $row_data['item_id'];
            $status = $row_data['status'];

            $course = get_post($course_id);
            if (!$course || $course->post_type !== 'lp_course') {
                continue;
            }

            // Calculer la progression seulement pour les cours inscrits
            if ($row_data['source'] === 'enrolled') {
                $progress_data = self::calculateCourseProgress($user_id, $course_id);
                $progress = $progress_data['percentage'];
            } else {
                $progress_data = ['percentage' => 0, 'total_items' => 0, 'completed_items' => 0, 'remaining_items' => 0];
                $progress = 0;
            }

            // Déterminer la catégorie
            if ($status === 'accessible') {
                $category = 'accessible';
                $status_label = 'accessible';
                $completed_date = null;
            } elseif ($status === 'completed' || $progress === 100) {
                $category = 'completed';
                $status_label = 'completed';
                $completed_date = $row_data['end_time'];
            } elseif ($progress > 0) {
                $category = 'in_progress';
                $status_label = 'in_progress';
                $completed_date = null;
            } else {
                $category = 'enrolled';
                $status_label = 'not_started';
                $completed_date = null;
            }

            // Récupérer les métadonnées du cours
            $course_meta = self::getCourseMetadata($course_id);
            
            $default_image = defined('TMM_DASHBOARD_URL') 
                ? TMM_DASHBOARD_URL . 'assets/img/course-placeholder.jpg'
                : plugin_dir_url(dirname(__FILE__)) . 'assets/img/course-placeholder.jpg';

            $course_data = [
                'id' => $course_id,
                'name' => get_the_title($course_id),
                'url' => get_permalink($course_id),
                'image' => get_the_post_thumbnail_url($course_id, 'medium') ?: $default_image,
                'duration' => $course_meta['duration'],
                'level' => $course_meta['level'],
                'progress' => $progress,
                'progress_details' => $progress_data,
                'status' => $status_label,
                'completed_date' => $completed_date,
                'start_date' => $row_data['start_time'],
                'last_activity' => $row_data['source'] === 'enrolled' ? self::getLastCourseActivity($user_id, $course_id) : null,
                'instructor' => $course_meta['instructor'],
                'categories' => $course_meta['categories'],
                'tags' => $course_meta['tags'],
                'membership_level' => $row_data['membership_level'], // Niveau d'abonnement associé
                'source' => $row_data['source'] // enrolled ou membership
            ];

            $courses[$category][] = $course_data;
        }

        return $courses;
    }

    /**
     * Récupérer les cours associés à un niveau d'abonnement PMPro
     */
    private static function getCoursesForMembershipLevel($membership_level_id) {
        global $wpdb;
        
        $courses = [];

        // Méthode 1: Via les règles PMPro (si configuré)
        if (function_exists('pmpro_getAllLevels')) {
            $level = pmpro_getLevel($membership_level_id);
            if ($level) {
                // Récupérer les cours associés via les post meta ou custom fields
                $course_ids = get_option('pmpro_courses_level_' . $membership_level_id, []);
                
                if (!empty($course_ids) && is_array($course_ids)) {
                    foreach ($course_ids as $course_id) {
                        $courses[$course_id] = $level->name;
                    }
                } else {
                    // Méthode alternative: chercher les cours avec un meta spécifique
                    $course_posts = get_posts([
                        'post_type' => 'lp_course',
                        'posts_per_page' => -1,
                        'meta_query' => [
                            [
                                'key' => '_pmpro_membership_level',
                                'value' => $membership_level_id,
                                'compare' => 'LIKE'
                            ]
                        ]
                    ]);
                    
                    foreach ($course_posts as $course_post) {
                        $courses[$course_post->ID] = $level->name;
                    }
                }
            }
        }

        // Méthode 2: Configuration hardcodée basée sur votre ancienne logique
        if (empty($courses)) {
            $courses = self::getHardcodedCoursesForLevel($membership_level_id);
        }

        return $courses;
    }

    /**
     * Configuration hardcodée des cours par niveau (basée sur votre ancien code)
     */
    private static function getHardcodedCoursesForLevel($membership_level_id) {
        $courses = [];

        // Configuration basée sur votre ancien code des parcours
        $parcours_config = [
            29 => [ // SysOps
                'name' => 'SysOps',
                'courses' => [
                    14658, 10269, 8061, 22582, 19941, 23661, // Mois 1
                    20279, 45863, 35714, 9179, // Mois 2
                    37688, 45863 // Mois 3
                ]
            ],
            30 => [ // Bootcamp DevOps
                'name' => 'Bootcamp DevOps – 3 mois',
                'courses' => [
                    19941, 23661, 22582, 35714, 37200, // Mois 1
                    38399, 29810, 72342, 23170, 24380, // Mois 2
                    52614, 47166, 48876, 46369 // Mois 3
                ]
            ]
        ];

        if (isset($parcours_config[$membership_level_id])) {
            $config = $parcours_config[$membership_level_id];
            foreach ($config['courses'] as $course_id) {
                // Vérifier que le cours existe
                if (get_post($course_id) && get_post_type($course_id) === 'lp_course') {
                    $courses[$course_id] = $config['name'];
                }
            }
        }

        return $courses;
    }

    /**
     * Calculer la progression détaillée d'un cours
     */
    public static function calculateCourseProgress($user_id, $course_id) {
        global $wpdb;
        
        // Vérifier que les tables existent
        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_section_items'") &&
                       $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_sections'");
        
        if (!$tables_exist) {
            return [
                'total_items' => 0,
                'completed_items' => 0,
                'percentage' => 0,
                'remaining_items' => 0
            ];
        }
    
        // Total des items du cours
        $total_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_section_items si
             INNER JOIN {$wpdb->prefix}learnpress_sections s ON si.section_id = s.section_id
             WHERE s.section_course_id = %d AND si.item_type IN ('lp_lesson', 'lp_quiz')",
            $course_id
        ));
    
        // Items complétés
        $completed_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND ref_id = %d 
             AND item_type IN ('lp_lesson', 'lp_quiz') 
             AND status = 'completed'",
            $user_id, $course_id
        ));
    
        $percentage = ($total_items > 0) ? round(($completed_items / $total_items) * 100) : 0;
    
        return [
            'total_items' => $total_items,
            'completed_items' => $completed_items,
            'percentage' => $percentage,
            'remaining_items' => $total_items - $completed_items
        ];
    }

    /**
     * Obtenir le dernier cours actif de l'utilisateur
     */
    public static function getLastActiveCourse($user_id) {
        global $wpdb;
        
        // Vérifier que la table existe
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_user_items'")) {
            return null;
        }
        
        // Récupérer le dernier cours avec activité
        $last_course = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                ui.item_id,
                ui.status,
                ui.ref_id,
                MAX(GREATEST(
                    COALESCE(ui.start_time, '0000-00-00'),
                    COALESCE(ui.end_time, '0000-00-00')
                )) as last_activity,
                p.post_title,
                p.post_status
            FROM {$wpdb->prefix}learnpress_user_items ui
            INNER JOIN {$wpdb->posts} p ON ui.item_id = p.ID
            WHERE ui.user_id = %d 
            AND ui.item_type = 'lp_course'
            AND ui.status IN ('in_progress', 'enrolled')
            AND p.post_status = 'publish'
            GROUP BY ui.item_id
            ORDER BY last_activity DESC
            LIMIT 1",
            $user_id
        ));
        
        if (!$last_course) {
            return null;
        }
        
        // Calculer la progression
        $progress_data = self::calculateCourseProgress($user_id, $last_course->item_id);
        
        // Récupérer la dernière leçon/quiz actif dans ce cours
        $last_item = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                ui.item_id,
                ui.item_type,
                p.post_title as item_title,
                MAX(GREATEST(
                    COALESCE(ui.start_time, '0000-00-00'),
                    COALESCE(ui.end_time, '0000-00-00')
                )) as last_activity
            FROM {$wpdb->prefix}learnpress_user_items ui
            INNER JOIN {$wpdb->posts} p ON ui.item_id = p.ID
            WHERE ui.user_id = %d 
            AND ui.ref_id = %d
            AND ui.item_type IN ('lp_lesson', 'lp_quiz')
            AND ui.status NOT IN ('completed', 'finished')
            GROUP BY ui.item_id
            ORDER BY last_activity DESC
            LIMIT 1",
            $user_id,
            $last_course->item_id
        ));
        
        // Si pas d'item spécifique, prendre le prochain non complété
        if (!$last_item) {
            $last_item = self::getNextCourseItem($user_id, $last_course->item_id);
        }
        
        return [
            'id' => $last_course->item_id,
            'name' => $last_course->post_title,
            'url' => $last_item ? get_permalink($last_item->item_id) : get_permalink($last_course->item_id),
            'progress' => $progress_data['percentage'],
            'last_item' => $last_item ? $last_item->item_title : null,
            'last_activity' => $last_course->last_activity
        ];
    }

    /**
     * Obtenir le prochain item non complété d'un cours
     */
    private static function getNextCourseItem($user_id, $course_id) {
        global $wpdb;
        
        // Vérifier que les tables existent
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_section_items'")) {
            return null;
        }
        
        // Récupérer tous les items du cours dans l'ordre
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                si.item_id,
                si.item_type,
                p.post_title as item_title,
                ui.status as user_status
            FROM {$wpdb->prefix}learnpress_section_items si
            INNER JOIN {$wpdb->prefix}learnpress_sections s ON si.section_id = s.section_id
            INNER JOIN {$wpdb->posts} p ON si.item_id = p.ID
            LEFT JOIN {$wpdb->prefix}learnpress_user_items ui 
                ON ui.item_id = si.item_id 
                AND ui.user_id = %d
                AND ui.ref_id = %d
            WHERE s.section_course_id = %d
            AND si.item_type IN ('lp_lesson', 'lp_quiz')
            ORDER BY s.section_order, si.item_order",
            $user_id,
            $course_id,
            $course_id
        ));
        
        // Trouver le premier non complété
        foreach ($items as $item) {
            if (!$item->user_status || !in_array($item->user_status, ['completed', 'finished', 'passed'])) {
                return $item;
            }
        }
        
        // Si tous complétés, retourner le premier
        return !empty($items) ? $items[0] : null;
    }

    /**
     * Récupérer les métadonnées d'un cours
     */
    public static function getCourseMetadata($course_id) {
        $duration = get_post_meta($course_id, '_lp_duration', true) ?: '0';
        $level = get_post_meta($course_id, '_lp_level', true) ?: __('Tous niveaux', 'tmm-dashboard');
        
        // Récupérer l'instructeur
        $author_id = get_post_field('post_author', $course_id);
        $instructor = [
            'id' => $author_id,
            'name' => get_the_author_meta('display_name', $author_id),
            'avatar' => get_avatar_url($author_id, ['size' => 64])
        ];
    
        // Catégories et tags
        $categories = wp_get_post_terms($course_id, 'course_category', ['fields' => 'names']);
        $tags = wp_get_post_terms($course_id, 'course_tag', ['fields' => 'names']);
    
        return [
            'duration' => self::formatDuration($duration),
            'level' => $level,
            'instructor' => $instructor,
            'categories' => $categories ?: [],
            'tags' => $tags ?: []
        ];
    }

    /**
     * Formater la durée
     */
    private static function formatDuration($duration) {
        if (empty($duration) || $duration === '0') {
            return __('Non définie', 'tmm-dashboard');
        }

        // Si la durée est en format "10 week 2 day 30 minute"
        if (strpos($duration, ' ') !== false) {
            return $duration;
        }

        // Si c'est juste un nombre (minutes)
        $minutes = intval($duration);
        if ($minutes < 60) {
            return sprintf(__('%d min', 'tmm-dashboard'), $minutes);
        } elseif ($minutes < 1440) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $mins > 0 
                ? sprintf(__('%dh %dmin', 'tmm-dashboard'), $hours, $mins)
                : sprintf(__('%dh', 'tmm-dashboard'), $hours);
        } else {
            $days = floor($minutes / 1440);
            return sprintf(__('%d jours', 'tmm-dashboard'), $days);
        }
    }

    /**
     * Obtenir la dernière activité sur un cours
     */
    private static function getLastCourseActivity($user_id, $course_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(end_time) FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND ref_id = %d",
            $user_id, $course_id
        ));
    }

    /**
     * Récupérer les données d'adhésion PMPro
     */
    public static function getMembershipData($user_id) {
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            return ['error' => __('PMPro n\'est pas installé', 'tmm-dashboard')];
        }

        $membership = pmpro_getMembershipLevelForUser($user_id);
        
        if (!$membership) {
            return ['has_membership' => false];
        }

        // Calculer les jours restants
        $days_remaining = null;
        if ($membership->enddate) {
            $end_timestamp = strtotime($membership->enddate);
            $days_remaining = max(0, floor(($end_timestamp - time()) / DAY_IN_SECONDS));
        }

        return [
            'has_membership' => true,
            'level_id' => $membership->id,
            'level_name' => $membership->name,
            'level_description' => $membership->description,
            'start_date' => $membership->startdate,
            'end_date' => $membership->enddate,
            'days_remaining' => $days_remaining,
            'billing_amount' => $membership->billing_amount,
            'cycle_number' => $membership->cycle_number,
            'cycle_period' => $membership->cycle_period,
            'trial_amount' => $membership->trial_amount,
            'trial_limit' => $membership->trial_limit,
            'status' => self::getMembershipStatus($membership),
            'next_payment_date' => self::getNextPaymentDate($user_id, $membership)
        ];
    }

    /**
     * Déterminer le statut de l'adhésion
     */
    private static function getMembershipStatus($membership) {
        if (!$membership->enddate) {
            return 'lifetime';
        }

        $end_timestamp = strtotime($membership->enddate);
        $days_until_end = floor(($end_timestamp - time()) / DAY_IN_SECONDS);

        if ($days_until_end < 0) {
            return 'expired';
        } elseif ($days_until_end <= 7) {
            return 'expiring_soon';
        } else {
            return 'active';
        }
    }

    /**
     * Obtenir la date du prochain paiement
     */
    private static function getNextPaymentDate($user_id, $membership) {
        if (!function_exists('pmpro_next_payment')) {
            return null;
        }

        $next_payment = pmpro_next_payment($user_id);
        return $next_payment ? date_i18n(get_option('date_format'), $next_payment) : null;
    }

    /**
     * Récupérer les recommandations de cours
     */
    public static function getRecommendations($user_id, $limit = 8) {
        $user_courses = self::getUserCourses($user_id);

        // IDs des cours déjà suivis par l'utilisateur
        $user_course_ids = [];
        foreach (['enrolled', 'in_progress', 'completed', 'accessible'] as $status) {
            if (isset($user_courses[$status])) {
                $user_course_ids = array_merge(
                    $user_course_ids,
                    array_column($user_courses[$status], 'id')
                );
            }
        }

        // Tags et catégories préférés de l'utilisateur
        $preferred_tags = self::getUserPreferredTags($user_id, $user_course_ids);
        $preferred_categories = self::getUserPreferredCategories($user_id, $user_course_ids);

        $args = [
            'post_type' => 'lp_course',
            'posts_per_page' => $limit * 2, // Récupérer plus pour filtrer ensuite
            'post_status' => 'publish',
            'orderby' => 'meta_value_num',
            'meta_key' => '_lp_students',
            'order' => 'DESC',
            'post__not_in' => $user_course_ids,
            'meta_query' => [
                [
                    'key' => '_lp_has_sale',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        // Ajouter les préférences si disponibles
        if (!empty($preferred_tags) || !empty($preferred_categories)) {
            $args['tax_query'] = ['relation' => 'OR'];
            
            if (!empty($preferred_tags)) {
                $args['tax_query'][] = [
                    'taxonomy' => 'course_tag',
                    'field' => 'term_id',
                    'terms' => $preferred_tags
                ];
            }
            
            if (!empty($preferred_categories)) {
                $args['tax_query'][] = [
                    'taxonomy' => 'course_category',
                    'field' => 'term_id',
                    'terms' => $preferred_categories
                ];
            }
        }

        $query = new WP_Query($args);
        $recommendations = [];
        $added = 0;

        $default_image = defined('TMM_DASHBOARD_URL') 
            ? TMM_DASHBOARD_URL . 'assets/img/course-placeholder.jpg'
            : plugin_dir_url(dirname(__FILE__)) . 'assets/img/course-placeholder.jpg';

        foreach ($query->posts as $course) {
            if ($added >= $limit) break;

            $course_meta = self::getCourseMetadata($course->ID);
            $score = self::calculateRecommendationScore($course->ID, $preferred_tags, $preferred_categories);

            $recommendations[] = [
                'id' => $course->ID,
                'name' => get_the_title($course->ID),
                'url' => get_permalink($course->ID),
                'image' => get_the_post_thumbnail_url($course->ID, 'medium') ?: $default_image,
                'duration' => $course_meta['duration'],
                'level' => $course_meta['level'],
                'instructor' => $course_meta['instructor'],
                'categories' => $course_meta['categories'],
                'tags' => $course_meta['tags'],
                'is_new' => (strtotime($course->post_date) > strtotime('-30 days')),
                'is_popular' => (get_post_meta($course->ID, '_lp_students', true) > 100),
                'score' => $score,
                'price' => function_exists('learn_press_get_course_price') ? learn_press_get_course_price($course->ID) : 0,
                'sale_price' => function_exists('learn_press_get_course_sale_price') ? learn_press_get_course_sale_price($course->ID) : 0,
                'rating' => self::getCourseRating($course->ID)
            ];

            $added++;
        }

        // Trier par score de recommandation
        usort($recommendations, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $recommendations;
    }

    /**
     * Obtenir les tags préférés de l'utilisateur
     */
    private static function getUserPreferredTags($user_id, $course_ids) {
        if (empty($course_ids)) return [];

        $tags = [];
        foreach ($course_ids as $course_id) {
            $course_tags = wp_get_post_terms($course_id, 'course_tag', ['fields' => 'ids']);
            $tags = array_merge($tags, $course_tags);
        }

        return array_unique($tags);
    }

    /**
     * Obtenir les catégories préférées de l'utilisateur
     */
    private static function getUserPreferredCategories($user_id, $course_ids) {
        if (empty($course_ids)) return [];

        $categories = [];
        foreach ($course_ids as $course_id) {
            $course_categories = wp_get_post_terms($course_id, 'course_category', ['fields' => 'ids']);
            $categories = array_merge($categories, $course_categories);
        }

        return array_unique($categories);
    }

    /**
     * Calculer le score de recommandation
     */
    private static function calculateRecommendationScore($course_id, $preferred_tags, $preferred_categories) {
        $score = 0;

        // Score basé sur les tags
        $course_tags = wp_get_post_terms($course_id, 'course_tag', ['fields' => 'ids']);
        $tag_matches = array_intersect($course_tags, $preferred_tags);
        $score += count($tag_matches) * 10;

        // Score basé sur les catégories
        $course_categories = wp_get_post_terms($course_id, 'course_category', ['fields' => 'ids']);
        $category_matches = array_intersect($course_categories, $preferred_categories);
        $score += count($category_matches) * 15;

        // Score basé sur la popularité
        $students = get_post_meta($course_id, '_lp_students', true);
        $score += min($students / 10, 20); // Max 20 points pour la popularité

        // Score basé sur la note
        $rating = self::getCourseRating($course_id);
        $score += $rating * 5;

        // Bonus pour les cours récents
        $post_date = get_the_date('U', $course_id);
        if ($post_date > strtotime('-7 days')) {
            $score += 20;
        } elseif ($post_date > strtotime('-30 days')) {
            $score += 10;
        }

        return $score;
    }

    /**
     * Obtenir la note d'un cours
     */
    private static function getCourseRating($course_id) {
        if (function_exists('learn_press_get_course_rate')) {
            return learn_press_get_course_rate($course_id);
        }
        return 0;
    }

    /**
     * Récupérer l'activité récente de l'utilisateur
     */
    public static function getUserActivity($user_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        // Vérifier que la table existe
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_user_items'")) {
            return [];
        }

        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT ui.*, p.post_title as item_title, p.post_type
             FROM {$wpdb->prefix}learnpress_user_items ui
             LEFT JOIN {$wpdb->posts} p ON ui.item_id = p.ID
             WHERE ui.user_id = %d 
             AND ui.item_type IN ('lp_lesson', 'lp_quiz', 'lp_course')
             ORDER BY GREATEST(
                 COALESCE(ui.start_time, '0000-00-00'),
                 COALESCE(ui.end_time, '0000-00-00')
             ) DESC
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));

        $formatted_activities = [];

        foreach ($activities as $activity) {
            $course_title = '';
            if ($activity->item_type !== 'lp_course' && $activity->ref_id) {
                $course = get_post($activity->ref_id);
                $course_title = $course ? $course->post_title : __('Cours inconnu', 'tmm-dashboard');
            }

            // Déterminer l'icône et l'action
            $icon = '';
            $action = '';
            switch ($activity->item_type) {
                case 'lp_course':
                    $icon = 'fa-book';
                    $action = self::getActivityAction($activity->status, 'course');
                    break;
                case 'lp_lesson':
                    $icon = 'fa-file-alt';
                    $action = self::getActivityAction($activity->status, 'lesson');
                    break;
                case 'lp_quiz':
                    $icon = 'fa-question-circle';
                    $action = self::getActivityAction($activity->status, 'quiz');
                    break;
            }

            // Déterminer le timestamp le plus récent
            $timestamp = max(
                strtotime($activity->start_time ?: '0'),
                strtotime($activity->end_time ?: '0')
            );

            $formatted_activities[] = [
                'id' => $activity->user_item_id,
                'course_id' => $activity->ref_id ?: $activity->item_id,
                'course_name' => $course_title ?: $activity->item_title,
                'item_id' => $activity->item_id,
                'item_name' => $activity->item_title,
                'type' => $activity->item_type,
                'status' => $activity->status,
                'action' => $action,
                'icon' => $icon,
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
                'timestamp' => $timestamp,
                'time_ago' => human_time_diff($timestamp, current_time('timestamp')),
                'time_formatted' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp),
                'grade' => $activity->item_type === 'lp_quiz' ? $activity->graduation : null,
                'description' => self::formatActivityDescription($activity),
                'url' => get_permalink($activity->item_id)
            ];
        }

        return $formatted_activities;
    }

    /**
     * Obtenir l'action de l'activité
     */
    private static function getActivityAction($status, $type) {
        $actions = [
            'course' => [
                'enrolled' => __('inscrit à', 'tmm-dashboard'),
                'started' => __('a commencé', 'tmm-dashboard'),
                'completed' => __('a terminé', 'tmm-dashboard'),
                'finished' => __('a terminé', 'tmm-dashboard')
            ],
            'lesson' => [
                'started' => __('a commencé', 'tmm-dashboard'),
                'completed' => __('a terminé', 'tmm-dashboard')
            ],
            'quiz' => [
                'started' => __('a commencé', 'tmm-dashboard'),
                'completed' => __('a complété', 'tmm-dashboard'),
                'passed' => __('a réussi', 'tmm-dashboard'),
                'failed' => __('a échoué', 'tmm-dashboard')
            ]
        ];

        return $actions[$type][$status] ?? __('a interagi avec', 'tmm-dashboard');
    }

    /**
     * Formater la description de l'activité
     */
    private static function formatActivityDescription($activity) {
        $type_labels = [
            'lp_course' => __('Cours', 'tmm-dashboard'),
            'lp_lesson' => __('Leçon', 'tmm-dashboard'),
            'lp_quiz' => __('Quiz', 'tmm-dashboard')
        ];

        $status_labels = [
            'enrolled' => __('Inscription', 'tmm-dashboard'),
            'started' => __('Début', 'tmm-dashboard'),
            'completed' => __('Terminé', 'tmm-dashboard'),
            'passed' => __('Réussi', 'tmm-dashboard'),
            'failed' => __('Échoué', 'tmm-dashboard')
        ];

        $type = $type_labels[$activity->item_type] ?? $activity->item_type;
        $status = $status_labels[$activity->status] ?? $activity->status;

        return sprintf(
            __('%s "%s" - %s', 'tmm-dashboard'),
            $type,
            $activity->item_title,
            $status
        );
    }

    /**
     * Obtenir les statistiques de progression hebdomadaire
     */
    public static function getWeeklyProgress($user_id, $weeks = 1) {
        global $wpdb;
        
        // Vérifier que la table existe
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_user_items'")) {
            return [
                'labels' => [],
                'data' => ['completed' => [], 'total' => []],
                'current_week_total' => 0,
                'previous_week_total' => 0,
                'variation' => 0,
                'comparison' => '0%',
                'trend' => 'stable'
            ];
        }

        $end_date = current_time('mysql');
        $start_date = date('Y-m-d 00:00:00', strtotime("-{$weeks} week"));

        // Récupération des activités par jour
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(GREATEST(
                COALESCE(start_time, '0000-00-00'),
                COALESCE(end_time, '0000-00-00')
             )) as activity_date,
             COUNT(DISTINCT CASE WHEN status = 'completed' THEN user_item_id END) as completed_count,
             COUNT(DISTINCT user_item_id) as total_count
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND GREATEST(
                 COALESCE(start_time, '0000-00-00'),
                 COALESCE(end_time, '0000-00-00')
             ) BETWEEN %s AND %s
             GROUP BY activity_date
             ORDER BY activity_date ASC",
            $user_id, $start_date, $end_date
        ));

        // Organiser les données par jour
        $daily_data = [];
        foreach ($results as $row) {
            $daily_data[$row->activity_date] = [
                'completed' => intval($row->completed_count),
                'total' => intval($row->total_count)
            ];
        }

        // Créer un tableau complet pour la période
        $data = [];
        $labels = [];
        $current_date = strtotime($start_date);
        $end_timestamp = strtotime($end_date);

        while ($current_date <= $end_timestamp) {
            $date_key = date('Y-m-d', $current_date);
            $labels[] = date_i18n('D', $current_date);
            $data['completed'][] = $daily_data[$date_key]['completed'] ?? 0;
            $data['total'][] = $daily_data[$date_key]['total'] ?? 0;
            $current_date = strtotime('+1 day', $current_date);
        }

        // Calculer les statistiques de comparaison
        $current_week_total = array_sum(array_slice($data['completed'], -7));
        $previous_week_total = array_sum(array_slice($data['completed'], -14, 7));
        
        $variation = 0;
        if ($previous_week_total > 0) {
            $variation = round((($current_week_total - $previous_week_total) / $previous_week_total) * 100);
        } elseif ($current_week_total > 0) {
            $variation = 100;
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'current_week_total' => $current_week_total,
            'previous_week_total' => $previous_week_total,
            'variation' => $variation,
            'comparison' => ($variation >= 0 ? '+' : '') . $variation . '%',
            'trend' => $variation >= 0 ? 'up' : 'down'
        ];
    }

    /**
     * Obtenir toutes les données du dashboard
     */
    public static function getDashboardData($user_id) {
        // Données des cours (incluant maintenant les cours accessibles)
        $courses_data = self::getUserCourses($user_id);
        
        // Recommandations
        $recommended_courses = self::getRecommendations($user_id);
        
        // Activité récente
        $recent_activity = self::getUserActivity($user_id, 5);
        
        // Données d'adhésion
        $membership_data = self::getMembershipData($user_id);
        
        // Progression hebdomadaire
        $weekly_progress = self::getWeeklyProgress($user_id);

        // Calculer les statistiques
        $all_courses = array_merge(
            $courses_data['enrolled'] ?? [],
            $courses_data['in_progress'] ?? [],
            $courses_data['completed'] ?? [],
            $courses_data['accessible'] ?? [] // Inclure les cours accessibles
        );

        $total_courses = count($all_courses);
        $total_progress = 0;

        foreach ($all_courses as $course) {
            $total_progress += intval($course['progress']);
        }

        $average_progress = ($total_courses > 0) ? round($total_progress / $total_courses) : 0;

        // Statistiques
        $stats = [
            'total_courses' => $total_courses,
            'enrolled_count' => count($courses_data['enrolled'] ?? []),
            'in_progress_count' => count($courses_data['in_progress'] ?? []),
            'completed_count' => count($courses_data['completed'] ?? []),
            'accessible_count' => count($courses_data['accessible'] ?? []), // Nouveau
            'completion_percentage' => $average_progress,
            'weekly_progress' => $weekly_progress,
            'membership' => $membership_data,
            'certificates_count' => self::getUserCertificatesCount($user_id),
            'badges_count' => class_exists('TMMDashboardBadges') ? TMMDashboardBadges::getUserBadgesCount($user_id) : 0,
            'learning_streak' => self::getLearningStreak($user_id),
            'total_points' => self::getUserPoints($user_id)
        ];

        return [
            'stats' => $stats,
            'courses' => $courses_data,
            'recommended_courses' => $recommended_courses,
            'activity' => $recent_activity
        ];
    }

    /**
     * Obtenir le nombre de certificats de l'utilisateur
     */
    private static function getUserCertificatesCount($user_id) {
        $certificates = get_user_meta($user_id, 'tmm_certificates', true);
        return is_array($certificates) ? count($certificates) : 0;
    }

    /**
     * Obtenir la série d'apprentissage
     */
    private static function getLearningStreak($user_id) {
        global $wpdb;
        
        // Vérifier que la table existe
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}learnpress_user_items'")) {
            return 0;
        }

        $streak = 0;
        $current_date = current_time('Y-m-d');

        // Vérifier jour par jour en remontant
        for ($i = 0; $i < 365; $i++) {
            $check_date = date('Y-m-d', strtotime("-{$i} days", strtotime($current_date)));
            
            $activity = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_items
                 WHERE user_id = %d 
                 AND DATE(GREATEST(
                     COALESCE(start_time, '0000-00-00'),
                     COALESCE(end_time, '0000-00-00')
                 )) = %s",
                $user_id, $check_date
            ));

            if ($activity > 0) {
                $streak++;
            } else if ($i > 0) { // Pas d'activité et ce n'est pas aujourd'hui
                break;
            }
        }

        return $streak;
    }

    /**
     * Obtenir les points de l'utilisateur
     */
    private static function getUserPoints($user_id) {
        $points = get_user_meta($user_id, 'tmm_points', true);
        return intval($points);
    }
}