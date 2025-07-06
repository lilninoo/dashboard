<?php
/**
 * Méthodes helper pour le chatbot
 * 
 * @package TMMDashboardPro
 * @subpackage Chatbot/Helpers
 * @since 1.0.0
 */

// Ajouter ces méthodes dans la classe TMMDashboardData
class TMMDashboardDataExtensions {
    
    /**
     * Obtenir des recommandations intelligentes pour un utilisateur
     */
    public static function getSmartRecommendations($user_id, $limit = 5) {
        global $wpdb;
        
        // Récupérer les préférences de l'utilisateur
        $user_courses = self::getUserCourses($user_id);
        $completed_courses = $user_courses['completed'] ?? [];
        $in_progress_courses = $user_courses['in_progress'] ?? [];
        
        // Récupérer les catégories des cours terminés
        $completed_categories = [];
        foreach ($completed_courses as $course) {
            $categories = wp_get_post_terms($course['id'], 'course_category', ['fields' => 'ids']);
            $completed_categories = array_merge($completed_categories, $categories);
        }
        $completed_categories = array_unique($completed_categories);
        
        // Récupérer les cours recommandés
        $exclude_ids = array_merge(
            array_column($completed_courses, 'id'),
            array_column($in_progress_courses, 'id')
        );
        
        $args = [
            'post_type' => 'lp_course',
            'posts_per_page' => $limit * 2, // Récupérer plus pour filtrer ensuite
            'post__not_in' => $exclude_ids,
            'meta_key' => '_lp_students',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        ];
        
        // Si l'utilisateur a des préférences, prioriser ces catégories
        if (!empty($completed_categories)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'course_category',
                    'field' => 'term_id',
                    'terms' => $completed_categories
                ]
            ];
        }
        
        $query = new WP_Query($args);
        $recommendations = [];
        
        foreach ($query->posts as $post) {
            $course_id = $post->ID;
            
            // Calculer le score de pertinence
            $relevance_score = self::calculateRelevanceScore($user_id, $course_id, $completed_categories);
            
            $recommendations[] = [
                'id' => $course_id,
                'name' => $post->post_title,
                'url' => get_permalink($course_id),
                'image' => get_the_post_thumbnail_url($course_id, 'medium'),
                'relevance_score' => $relevance_score,
                'time_investment' => self::estimateTimeInvestment($course_id),
                'difficulty' => get_post_meta($course_id, '_lp_level', true),
                'students_count' => intval(get_post_meta($course_id, '_lp_students', true))
            ];
        }
        
        // Trier par score de pertinence
        usort($recommendations, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });
        
        return array_slice($recommendations, 0, $limit);
    }
    
    /**
     * Calculer le score de pertinence
     */
    private static function calculateRelevanceScore($user_id, $course_id, $user_categories) {
        $score = 50; // Score de base
        
        // Bonus si le cours est dans les catégories préférées
        $course_categories = wp_get_post_terms($course_id, 'course_category', ['fields' => 'ids']);
        $common_categories = array_intersect($user_categories, $course_categories);
        $score += count($common_categories) * 15;
        
        // Bonus basé sur la popularité
        $students_count = intval(get_post_meta($course_id, '_lp_students', true));
        if ($students_count > 100) $score += 10;
        if ($students_count > 500) $score += 10;
        
        // Bonus basé sur les évaluations
        $rating = floatval(get_post_meta($course_id, '_lp_course_rating', true));
        $score += ($rating - 3) * 5; // +/- 10 points selon la note
        
        // Pénalité si le cours est trop difficile
        $user_level = self::getUserLevel($user_id);
        $course_level = get_post_meta($course_id, '_lp_level', true);
        
        $level_map = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
        $user_level_num = $level_map[$user_level] ?? 2;
        $course_level_num = $level_map[$course_level] ?? 2;
        
        if ($course_level_num > $user_level_num + 1) {
            $score -= 20;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Estimer le temps d'investissement
     */
    private static function estimateTimeInvestment($course_id) {
        $duration = get_post_meta($course_id, '_lp_duration', true);
        $lessons_count = self::getCourseLessonCount($course_id);
        
        if ($duration) {
            return $duration;
        }
        
        // Estimation basée sur le nombre de leçons
        $hours = ceil($lessons_count * 0.5);
        return $hours . ' heures';
    }
    
    /**
     * Obtenir le niveau de l'utilisateur
     */
    private static function getUserLevel($user_id) {
        $completed_count = count(self::getUserCourses($user_id)['completed'] ?? []);
        
        if ($completed_count < 3) return 'beginner';
        if ($completed_count < 10) return 'intermediate';
        return 'advanced';
    }
}

// Ajouter ces méthodes dans la classe TMMDashboardParcours
class TMMDashboardParcoursExtensions {
    
    /**
     * Obtenir tous les parcours
     */
    public static function getAllParcours() {
        // Simulation de parcours pour le développement
        return [
            [
                'id' => 'dev-web',
                'name' => 'Développement Web Full Stack',
                'level' => 'Intermédiaire',
                'duration' => '6 mois',
                'membership_id' => 2
            ],
            [
                'id' => 'data-science',
                'name' => 'Data Science et Machine Learning',
                'level' => 'Avancé',
                'duration' => '8 mois',
                'membership_id' => 3
            ],
            [
                'id' => 'design-ui',
                'name' => 'Design UI/UX',
                'level' => 'Débutant',
                'duration' => '4 mois',
                'membership_id' => 1
            ]
        ];
    }
    
    /**
     * Obtenir le nombre d'utilisateurs d'un parcours
     */
    public static function getParcoursUserCount($parcours_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'tmm_user_parcours' 
             AND meta_value = %s",
            $parcours_id
        ));
    }
    
    /**
     * Vérifier les alertes de progression
     */
    public static function checkProgressAlerts($user_id, $parcours_id) {
        $alerts = [];
        
        // Vérifier l'inactivité
        $last_activity = get_user_meta($user_id, 'tmm_last_activity', true);
        if ($last_activity) {
            $days_inactive = (time() - strtotime($last_activity)) / (60 * 60 * 24);
            
            if ($days_inactive > 7) {
                $alerts[] = [
                    'type' => 'inactivity',
                    'level' => 'warning',
                    'message' => sprintf(
                        'Vous n\'avez pas étudié depuis %d jours. Reprenez votre parcours pour ne pas perdre vos acquis !',
                        intval($days_inactive)
                    )
                ];
            }
        }
        
        // Vérifier les deadlines
        $next_deadline = get_user_meta($user_id, 'tmm_next_deadline', true);
        if ($next_deadline && strtotime($next_deadline) < strtotime('+7 days')) {
            $alerts[] = [
                'type' => 'deadline',
                'level' => 'info',
                'message' => sprintf(
                    'Vous avez une échéance le %s. Assurez-vous d\'être prêt !',
                    date_i18n(get_option('date_format'), strtotime($next_deadline))
                )
            ];
        }
        
        // Vérifier les récompenses proches
        $progress = self::getParcoursProgress($user_id, $parcours_id);
        if ($progress['percentage'] > 80 && $progress['percentage'] < 100) {
            $alerts[] = [
                'type' => 'achievement',
                'level' => 'success',
                'message' => 'Vous êtes proche de terminer votre parcours ! Plus que quelques étapes.'
            ];
        }
        
        return $alerts;
    }
}

// Ajouter ces méthodes helper dans la classe TMMChatbotML
class TMMChatbotMLHelpers {
    
    /**
     * Obtenir le nombre de leçons d'un cours
     */
    public static function getCourseLessonCount($course_id) {
        $course = learn_press_get_course($course_id);
        if (!$course) return 0;
        
        $items = $course->get_items();
        $lesson_count = 0;
        
        foreach ($items as $item) {
            if (get_post_type($item) === 'lp_lesson') {
                $lesson_count++;
            }
        }
        
        return $lesson_count;
    }
    
    /**
     * Obtenir le nombre de quiz d'un cours
     */
    public static function getCourseQuizCount($course_id) {
        $course = learn_press_get_course($course_id);
        if (!$course) return 0;
        
        $items = $course->get_items();
        $quiz_count = 0;
        
        foreach ($items as $item) {
            if (get_post_type($item) === 'lp_quiz') {
                $quiz_count++;
            }
        }
        
        return $quiz_count;
    }
    
    /**
     * Obtenir le score de niveau d'un cours
     */
    public static function getCourseLevelScore($course_id) {
        $level = get_post_meta($course_id, '_lp_level', true);
        
        $scores = [
            'beginner' => 1,
            'intermediate' => 2,
            'advanced' => 3,
            'expert' => 4
        ];
        
        return $scores[$level] ?? 2;
    }
    
    /**
     * Obtenir le nombre de prérequis d'un cours
     */
    public static function getCoursePrerequisiteCount($course_id) {
        $prerequisites = get_post_meta($course_id, '_lp_course_prerequisite', true);
        
        if (empty($prerequisites)) return 0;
        if (!is_array($prerequisites)) return 1;
        
        return count($prerequisites);
    }
    
    /**
     * Obtenir les poids du modèle
     */
    public static function getModelWeights($model_type) {
        // Poids par défaut pour différents modèles
        $weights = [
            'completion_time' => [
                'avg_completion_time' => 0.3,
                'learning_speed' => 0.2,
                'consistency_score' => 0.15,
                'course_complexity' => 0.25,
                'current_workload' => 0.05,
                'time_availability' => 0.05
            ],
            'churn_risk' => [
                'days_since_last_login' => 0.3,
                'completion_rate_trend' => 0.25,
                'engagement_decline' => 0.2,
                'difficulty_encounters' => 0.15,
                'support_requests' => 0.1
            ]
        ];
        
        return $weights[$model_type] ?? [];
    }
}

// Fonction pour intégrer les extensions
function tmm_integrate_extensions() {
    // Ajouter les méthodes aux classes existantes si nécessaire
    if (class_exists('TMMDashboardData')) {
        // Les méthodes peuvent être ajoutées directement dans les classes originales
        // ou utilisées comme des méthodes statiques séparées
    }
}

add_action('plugins_loaded', 'tmm_integrate_extensions', 20);