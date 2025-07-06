<?php
/**
 * Classe de gestion des analytics et statistiques
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardAnalytics {
    
    /**
     * Enregistrer un événement
     */
    public static function trackEvent($user_id, $event_type, $event_data = []) {
        global $wpdb;
        
        // Préparer les données
        $data = [
            'user_id' => $user_id,
            'event_type' => sanitize_text_field($event_type),
            'event_data' => json_encode($event_data),
            'created_at' => current_time('mysql')
        ];
        
        // Insérer dans la base de données
        $result = $wpdb->insert(
            $wpdb->prefix . 'tmm_analytics',
            $data,
            ['%d', '%s', '%s', '%s']
        );
        
        // Déclencher une action pour permettre des extensions
        do_action('tmm_analytics_event_tracked', $user_id, $event_type, $event_data);
        
        return $result;
    }
    
    /**
     * Obtenir les analytics d'un utilisateur
     */
    public static function getUserAnalytics($user_id, $type = 'overview', $days = 30) {
        switch ($type) {
            case 'overview':
                return self::getOverviewAnalytics($user_id, $days);
            case 'courses':
                return self::getCourseAnalytics($user_id, $days);
            case 'activity':
                return self::getActivityAnalytics($user_id, $days);
            case 'performance':
                return self::getPerformanceAnalytics($user_id, $days);
            case 'engagement':
                return self::getEngagementAnalytics($user_id, $days);
            default:
                return [];
        }
    }
    
    /**
     * Analytics de vue d'ensemble
     */
    private static function getOverviewAnalytics($user_id, $days) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Statistiques générales
        $total_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s",
            $user_id, $start_date
        ));
        
        // Temps d'apprentissage total
        $total_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND start_time >= %s
             AND end_time IS NOT NULL",
            $user_id, $start_date
        ));
        
        // Cours commencés et terminés
        $courses_started = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT item_id)
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_course'
             AND start_time >= %s",
            $user_id, $start_date
        ));
        
        $courses_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_course'
             AND status = 'completed'
             AND end_time >= %s",
            $user_id, $start_date
        ));
        
        // Quiz complétés
        $quizzes_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_quiz'
             AND status = 'completed'
             AND end_time >= %s",
            $user_id, $start_date
        ));
        
        // Score moyen des quiz
        $average_quiz_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(graduation AS DECIMAL(5,2)))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_quiz'
             AND status = 'completed'
             AND end_time >= %s",
            $user_id, $start_date
        ));
        
        // Activité par jour de la semaine
        $activity_by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DAYNAME(created_at) as day, COUNT(*) as count
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s
             GROUP BY DAYOFWEEK(created_at)
             ORDER BY DAYOFWEEK(created_at)",
            $user_id, $start_date
        ));
        
        // Préférences d'apprentissage (matin/après-midi/soir)
        $activity_by_hour = $wpdb->get_results($wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s
             GROUP BY HOUR(created_at)",
            $user_id, $start_date
        ));
        
        $learning_preference = self::determineLearningPreference($activity_by_hour);
        
        return [
            'summary' => [
                'total_events' => intval($total_events),
                'total_time_minutes' => intval($total_time),
                'total_time_hours' => round($total_time / 60, 1),
                'courses_started' => intval($courses_started),
                'courses_completed' => intval($courses_completed),
                'quizzes_completed' => intval($quizzes_completed),
                'average_quiz_score' => round($average_quiz_score, 1),
                'completion_rate' => $courses_started > 0 ? round(($courses_completed / $courses_started) * 100, 1) : 0
            ],
            'activity_by_day' => self::formatActivityByDay($activity_by_day),
            'learning_preference' => $learning_preference,
            'trends' => self::getTrends($user_id, $days)
        ];
    }
    
    /**
     * Analytics des cours
     */
    private static function getCourseAnalytics($user_id, $days) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Progression par cours
        $course_progress = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                c.item_id as course_id,
                p.post_title as course_name,
                c.status,
                c.start_time,
                c.end_time,
                COUNT(DISTINCT l.item_id) as total_items,
                COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.item_id END) as completed_items
             FROM {$wpdb->prefix}learnpress_user_items c
             LEFT JOIN {$wpdb->posts} p ON c.item_id = p.ID
             LEFT JOIN {$wpdb->prefix}learnpress_user_items l ON l.ref_id = c.item_id AND l.user_id = c.user_id
             WHERE c.user_id = %d 
             AND c.item_type = 'lp_course'
             AND c.start_time >= %s
             GROUP BY c.item_id",
            $user_id, $start_date
        ));
        
        // Temps passé par cours
        $time_per_course = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                ref_id as course_id,
                SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as minutes_spent
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND ref_id IS NOT NULL
             AND start_time >= %s
             AND end_time IS NOT NULL
             GROUP BY ref_id",
            $user_id, $start_date
        ));
        
        // Catégories les plus populaires
        $popular_categories = self::getPopularCategories($user_id, $start_date);
        
        // Vitesse de progression
        $completion_speed = self::getCompletionSpeed($user_id, $start_date);
        
        return [
            'courses' => self::formatCourseProgress($course_progress, $time_per_course),
            'popular_categories' => $popular_categories,
            'completion_speed' => $completion_speed,
            'recommendations' => self::generateRecommendations($user_id, $popular_categories)
        ];
    }
    
    /**
     * Analytics d'activité
     */
    private static function getActivityAnalytics($user_id, $days) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Heatmap d'activité
        $activity_heatmap = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as activity_count
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND created_at >= %s
             GROUP BY DATE(created_at)",
            $user_id, $start_date
        ));
        
        // Types d'événements
        $event_types = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_type,
                COUNT(*) as count
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND created_at >= %s
             GROUP BY event_type
             ORDER BY count DESC",
            $user_id, $start_date
        ));
        
        // Séries d'apprentissage
        $learning_streaks = self::calculateLearningStreaks($user_id, $start_date);
        
        // Périodes d'inactivité
        $inactive_periods = self::findInactivePeriods($user_id, $start_date);
        
        return [
            'heatmap' => self::formatHeatmapData($activity_heatmap, $days),
            'event_distribution' => self::formatEventTypes($event_types),
            'streaks' => $learning_streaks,
            'inactive_periods' => $inactive_periods,
            'consistency_score' => self::calculateConsistencyScore($activity_heatmap, $days)
        ];
    }
    
    /**
     * Analytics de performance
     */
    private static function getPerformanceAnalytics($user_id, $days) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Performance des quiz
        $quiz_performance = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                q.item_id as quiz_id,
                p.post_title as quiz_name,
                q.graduation as score,
                q.status,
                q.end_time,
                c.post_title as course_name
             FROM {$wpdb->prefix}learnpress_user_items q
             LEFT JOIN {$wpdb->posts} p ON q.item_id = p.ID
             LEFT JOIN {$wpdb->posts} c ON q.ref_id = c.ID
             WHERE q.user_id = %d 
             AND q.item_type = 'lp_quiz'
             AND q.end_time >= %s
             ORDER BY q.end_time DESC",
            $user_id, $start_date
        ));
        
        // Évolution du score moyen
        $score_evolution = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(end_time) as date,
                AVG(CAST(graduation AS DECIMAL(5,2))) as average_score,
                COUNT(*) as quiz_count
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_quiz'
             AND status = 'completed'
             AND end_time >= %s
             GROUP BY DATE(end_time)
             ORDER BY date",
            $user_id, $start_date
        ));
        
        // Analyse des difficultés
        $difficulty_analysis = self::analyzeDifficulties($quiz_performance);
        
        // Comparaison avec la moyenne
        $comparison = self::compareWithAverage($user_id, $start_date);
        
        return [
            'quiz_results' => self::formatQuizPerformance($quiz_performance),
            'score_evolution' => self::formatScoreEvolution($score_evolution),
            'difficulty_analysis' => $difficulty_analysis,
            'comparison' => $comparison,
            'improvement_suggestions' => self::generateImprovementSuggestions($difficulty_analysis)
        ];
    }
    
    /**
     * Analytics d'engagement
     */
    private static function getEngagementAnalytics($user_id, $days) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Métriques d'engagement
        $engagement_metrics = [
            'login_frequency' => self::getLoginFrequency($user_id, $start_date),
            'session_duration' => self::getAverageSessionDuration($user_id, $start_date),
            'interaction_rate' => self::getInteractionRate($user_id, $start_date),
            'completion_velocity' => self::getCompletionVelocity($user_id, $start_date)
        ];
        
        // Score d'engagement global
        $engagement_score = self::calculateEngagementScore($engagement_metrics);
        
        // Prédiction de rétention
        $retention_prediction = self::predictRetention($user_id, $engagement_metrics);
        
        // Recommandations d'engagement
        $recommendations = self::generateEngagementRecommendations($engagement_score, $engagement_metrics);
        
        return [
            'metrics' => $engagement_metrics,
            'engagement_score' => $engagement_score,
            'retention_prediction' => $retention_prediction,
            'recommendations' => $recommendations,
            'trends' => self::getEngagementTrends($user_id, $days)
        ];
    }
    
    /**
     * Déterminer la préférence d'apprentissage
     */
    private static function determineLearningPreference($activity_by_hour) {
        $morning = 0;   // 6-12
        $afternoon = 0; // 12-18
        $evening = 0;   // 18-24
        $night = 0;     // 0-6
        
        foreach ($activity_by_hour as $activity) {
            $hour = intval($activity->hour);
            $count = intval($activity->count);
            
            if ($hour >= 6 && $hour < 12) {
                $morning += $count;
            } elseif ($hour >= 12 && $hour < 18) {
                $afternoon += $count;
            } elseif ($hour >= 18) {
                $evening += $count;
            } else {
                $night += $count;
            }
        }
        
        $max = max($morning, $afternoon, $evening, $night);
        
        if ($max === $morning) return __('Matinal', 'tmm-dashboard');
        if ($max === $afternoon) return __('Après-midi', 'tmm-dashboard');
        if ($max === $evening) return __('Soirée', 'tmm-dashboard');
        return __('Nocturne', 'tmm-dashboard');
    }
    
    /**
     * Formater l'activité par jour
     */
    private static function formatActivityByDay($activity_by_day) {
        $days = [
            'Monday' => __('Lundi', 'tmm-dashboard'),
            'Tuesday' => __('Mardi', 'tmm-dashboard'),
            'Wednesday' => __('Mercredi', 'tmm-dashboard'),
            'Thursday' => __('Jeudi', 'tmm-dashboard'),
            'Friday' => __('Vendredi', 'tmm-dashboard'),
            'Saturday' => __('Samedi', 'tmm-dashboard'),
            'Sunday' => __('Dimanche', 'tmm-dashboard')
        ];
        
        $formatted = [];
        foreach ($activity_by_day as $activity) {
            $formatted[] = [
                'day' => $days[$activity->day] ?? $activity->day,
                'count' => intval($activity->count)
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Obtenir les tendances
     */
    private static function getTrends($user_id, $days) {
        global $wpdb;
        
        // Comparer avec la période précédente
        $current_start = date('Y-m-d', strtotime("-{$days} days"));
        $previous_start = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));
        $previous_end = date('Y-m-d', strtotime("-{$days} days"));
        
        // Activité actuelle vs précédente
        $current_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s",
            $user_id, $current_start
        ));
        
        $previous_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at BETWEEN %s AND %s",
            $user_id, $previous_start, $previous_end
        ));
        
        $activity_trend = $previous_activity > 0 
            ? round((($current_activity - $previous_activity) / $previous_activity) * 100, 1)
            : 100;
        
        // Temps d'apprentissage
        $current_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND start_time >= %s AND end_time IS NOT NULL",
            $user_id, $current_start
        ));
        
        $previous_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d AND start_time BETWEEN %s AND %s AND end_time IS NOT NULL",
            $user_id, $previous_start, $previous_end
        ));
        
        $time_trend = $previous_time > 0 
            ? round((($current_time - $previous_time) / $previous_time) * 100, 1)
            : 100;
        
        return [
            'activity' => [
                'current' => $current_activity,
                'previous' => $previous_activity,
                'trend' => $activity_trend,
                'direction' => $activity_trend >= 0 ? 'up' : 'down'
            ],
            'time_spent' => [
                'current' => round($current_time / 60, 1),
                'previous' => round($previous_time / 60, 1),
                'trend' => $time_trend,
                'direction' => $time_trend >= 0 ? 'up' : 'down'
            ]
        ];
    }
    
    /**
     * Obtenir les catégories populaires
     */
    private static function getPopularCategories($user_id, $start_date) {
        global $wpdb;
        
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.name as category,
                COUNT(DISTINCT ui.item_id) as course_count
             FROM {$wpdb->prefix}learnpress_user_items ui
             INNER JOIN {$wpdb->prefix}term_relationships tr ON ui.item_id = tr.object_id
             INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
             WHERE ui.user_id = %d 
             AND ui.item_type = 'lp_course'
             AND ui.start_time >= %s
             AND tt.taxonomy = 'course_category'
             GROUP BY t.term_id
             ORDER BY course_count DESC
             LIMIT 5",
            $user_id, $start_date
        ));
        
        return $categories;
    }
    
    /**
     * Obtenir la vitesse de complétion
     */
    private static function getCompletionSpeed($user_id, $start_date) {
        global $wpdb;
        
        $completion_times = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                TIMESTAMPDIFF(DAY, start_time, end_time) as days_to_complete,
                p.post_title as course_name
             FROM {$wpdb->prefix}learnpress_user_items ui
             LEFT JOIN {$wpdb->posts} p ON ui.item_id = p.ID
             WHERE ui.user_id = %d 
             AND ui.item_type = 'lp_course'
             AND ui.status = 'completed'
             AND ui.start_time >= %s
             AND ui.end_time IS NOT NULL",
            $user_id, $start_date
        ));
        
        if (empty($completion_times)) {
            return ['average_days' => 0, 'courses' => []];
        }
        
        $total_days = 0;
        $courses = [];
        
        foreach ($completion_times as $course) {
            $total_days += $course->days_to_complete;
            $courses[] = [
                'name' => $course->course_name,
                'days' => $course->days_to_complete
            ];
        }
        
        return [
            'average_days' => round($total_days / count($completion_times), 1),
            'courses' => $courses
        ];
    }
    
    /**
     * Générer des recommandations
     */
    private static function generateRecommendations($user_id, $popular_categories) {
        $recommendations = [];
        
        // Basé sur les catégories populaires
        if (!empty($popular_categories)) {
            $top_category = $popular_categories[0]->category;
            $recommendations[] = sprintf(
                __('Vous semblez apprécier les cours de %s. Explorez plus de cours dans cette catégorie.', 'tmm-dashboard'),
                $top_category
            );
        }
        
        // Basé sur le temps d'apprentissage
        $avg_time = self::getAverageSessionDuration($user_id, date('Y-m-d', strtotime('-30 days')));
        if ($avg_time < 30) {
            $recommendations[] = __('Essayez d\'augmenter vos sessions d\'apprentissage à au moins 30 minutes pour une meilleure rétention.', 'tmm-dashboard');
        }
        
        return $recommendations;
    }
    
    /**
     * Formater la progression des cours
     */
    private static function formatCourseProgress($course_progress, $time_per_course) {
        $time_map = [];
        foreach ($time_per_course as $time) {
            $time_map[$time->course_id] = $time->minutes_spent;
        }
        
        $formatted = [];
        foreach ($course_progress as $course) {
            $progress = $course->total_items > 0 
                ? round(($course->completed_items / $course->total_items) * 100, 1)
                : 0;
            
            $formatted[] = [
                'id' => $course->course_id,
                'name' => $course->course_name,
                'status' => $course->status,
                'progress' => $progress,
                'completed_items' => intval($course->completed_items),
                'total_items' => intval($course->total_items),
                'time_spent' => isset($time_map[$course->course_id]) 
                    ? round($time_map[$course->course_id] / 60, 1)
                    : 0
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Calculer les séries d'apprentissage
     */
    private static function calculateLearningStreaks($user_id, $start_date) {
        global $wpdb;
        
        $activities = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(created_at) as activity_date
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s
             ORDER BY activity_date",
            $user_id, $start_date
        ));
        
        if (empty($activities)) {
            return ['current' => 0, 'longest' => 0];
        }
        
        $streaks = [];
        $current_streak = 1;
        
        for ($i = 1; $i < count($activities); $i++) {
            $prev_date = strtotime($activities[$i - 1]);
            $curr_date = strtotime($activities[$i]);
            
            if (($curr_date - $prev_date) === 86400) { // Jours consécutifs
                $current_streak++;
            } else {
                $streaks[] = $current_streak;
                $current_streak = 1;
            }
        }
        $streaks[] = $current_streak;
        
        // Vérifier si la série actuelle est toujours active
        $last_activity = end($activities);
        $is_current_active = (strtotime($last_activity) >= strtotime('-1 day'));
        
        return [
            'current' => $is_current_active ? $current_streak : 0,
            'longest' => max($streaks)
        ];
    }
    
    /**
     * Trouver les périodes d'inactivité
     */
    private static function findInactivePeriods($user_id, $start_date) {
        global $wpdb;
        
        $activities = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(created_at) as activity_date
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s
             ORDER BY activity_date",
            $user_id, $start_date
        ));
        
        if (count($activities) < 2) {
            return [];
        }
        
        $inactive_periods = [];
        
        for ($i = 1; $i < count($activities); $i++) {
            $prev_date = strtotime($activities[$i - 1]);
            $curr_date = strtotime($activities[$i]);
            $days_diff = ($curr_date - $prev_date) / 86400;
            
            if ($days_diff > 1) {
                $inactive_periods[] = [
                    'start' => date('Y-m-d', $prev_date + 86400),
                    'end' => date('Y-m-d', $curr_date - 86400),
                    'days' => $days_diff - 1
                ];
            }
        }
        
        return $inactive_periods;
    }
    
    /**
     * Formater les données de heatmap
     */
    private static function formatHeatmapData($activity_heatmap, $days) {
        $data = [];
        $start_date = strtotime("-{$days} days");
        
        // Créer un tableau avec toutes les dates
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days", $start_date));
            $data[$date] = 0;
        }
        
        // Remplir avec les données réelles
        foreach ($activity_heatmap as $activity) {
            $data[$activity->date] = intval($activity->activity_count);
        }
        
        return $data;
    }
    
    /**
     * Formater les types d'événements
     */
    private static function formatEventTypes($event_types) {
        $formatted = [];
        $total = 0;
        
        foreach ($event_types as $event) {
            $total += intval($event->count);
        }
        
        foreach ($event_types as $event) {
            $count = intval($event->count);
            $formatted[] = [
                'type' => $event->event_type,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Calculer le score de consistance
     */
    private static function calculateConsistencyScore($activity_heatmap, $days) {
        $active_days = count($activity_heatmap);
        $consistency = ($active_days / $days) * 100;
        
        return round($consistency, 1);
    }
    
    /**
     * Formater la performance des quiz
     */
    private static function formatQuizPerformance($quiz_performance) {
        $formatted = [];
        
        foreach ($quiz_performance as $quiz) {
            $formatted[] = [
                'id' => $quiz->quiz_id,
                'name' => $quiz->quiz_name,
                'course' => $quiz->course_name,
                'score' => floatval($quiz->score),
                'status' => $quiz->status,
                'date' => date_i18n(get_option('date_format'), strtotime($quiz->end_time))
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Formater l'évolution du score
     */
    private static function formatScoreEvolution($score_evolution) {
        $formatted = [];
        
        foreach ($score_evolution as $evolution) {
            $formatted[] = [
                'date' => date_i18n('j M', strtotime($evolution->date)),
                'average_score' => round($evolution->average_score, 1),
                'quiz_count' => intval($evolution->quiz_count)
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Analyser les difficultés
     */
    private static function analyzeDifficulties($quiz_performance) {
        $failed_topics = [];
        $success_topics = [];
        
        foreach ($quiz_performance as $quiz) {
            $score = floatval($quiz->score);
            $topic = $quiz->course_name ?: __('Non catégorisé', 'tmm-dashboard');
            
            if ($score < 70) {
                $failed_topics[] = $topic;
            } else {
                $success_topics[] = $topic;
            }
        }
        
        return [
            'struggling_areas' => array_unique($failed_topics),
            'strong_areas' => array_unique($success_topics)
        ];
    }
    
    /**
     * Comparer avec la moyenne
     */
    private static function compareWithAverage($user_id, $start_date) {
        global $wpdb;
        
        // Score moyen de l'utilisateur
        $user_avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(graduation AS DECIMAL(5,2)))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_quiz'
             AND status = 'completed'
             AND end_time >= %s",
            $user_id, $start_date
        ));
        
        // Score moyen global
        $global_avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(graduation AS DECIMAL(5,2)))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE item_type = 'lp_quiz'
             AND status = 'completed'
             AND end_time >= %s",
            $start_date
        ));
        
        return [
            'user_average' => round($user_avg, 1),
            'global_average' => round($global_avg, 1),
            'difference' => round($user_avg - $global_avg, 1),
            'percentile' => self::calculatePercentile($user_id, $user_avg)
        ];
    }
    
    /**
     * Calculer le percentile
     */
    private static function calculatePercentile($user_id, $user_score) {
        global $wpdb;
        
        $lower_scores = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE item_type = 'lp_quiz'
             AND status = 'completed'
             AND CAST(graduation AS DECIMAL(5,2)) < %f",
            $user_score
        ));
        
        $total_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE item_type = 'lp_quiz'
             AND status = 'completed'"
        );
        
        return $total_users > 0 ? round(($lower_scores / $total_users) * 100, 1) : 50;
    }
    
    /**
     * Générer des suggestions d'amélioration
     */
    private static function generateImprovementSuggestions($difficulty_analysis) {
        $suggestions = [];
        
        if (!empty($difficulty_analysis['struggling_areas'])) {
            $suggestions[] = sprintf(
                __('Concentrez-vous sur les sujets suivants : %s', 'tmm-dashboard'),
                implode(', ', array_slice($difficulty_analysis['struggling_areas'], 0, 3))
            );
        }
        
        if (!empty($difficulty_analysis['strong_areas'])) {
            $suggestions[] = sprintf(
                __('Continuez à exceller dans : %s', 'tmm-dashboard'),
                implode(', ', array_slice($difficulty_analysis['strong_areas'], 0, 3))
            );
        }
        
        $suggestions[] = __('Revoyez les quiz échoués pour identifier les concepts à approfondir.', 'tmm-dashboard');
        
        return $suggestions;
    }
    
    /**
     * Obtenir la fréquence de connexion
     */
    private static function getLoginFrequency($user_id, $start_date) {
        global $wpdb;
        
        $login_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'login'
             AND created_at >= %s",
            $user_id, $start_date
        ));
        
        $days = ceil((strtotime('now') - strtotime($start_date)) / 86400);
        
        return round($login_count / $days, 2);
    }
    
    /**
     * Obtenir la durée moyenne de session
     */
    private static function getAverageSessionDuration($user_id, $start_date) {
        // Estimation basée sur l'écart entre les événements
        global $wpdb;
        
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT created_at
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s
             ORDER BY created_at",
            $user_id, $start_date
        ));
        
        if (count($events) < 2) {
            return 0;
        }
        
        $sessions = [];
        $session_start = strtotime($events[0]->created_at);
        $last_event = $session_start;
        
        for ($i = 1; $i < count($events); $i++) {
            $current_event = strtotime($events[$i]->created_at);
            
            // Si plus de 30 minutes entre les événements, nouvelle session
            if (($current_event - $last_event) > 1800) {
                $sessions[] = ($last_event - $session_start) / 60;
                $session_start = $current_event;
            }
            
            $last_event = $current_event;
        }
        
        // Ajouter la dernière session
        $sessions[] = ($last_event - $session_start) / 60;
        
        return count($sessions) > 0 ? round(array_sum($sessions) / count($sessions), 1) : 0;
    }
    
    /**
     * Obtenir le taux d'interaction
     */
    private static function getInteractionRate($user_id, $start_date) {
        global $wpdb;
        
        $total_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d AND created_at >= %s",
            $user_id, $start_date
        ));
        
        $login_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'login'
             AND created_at >= %s",
            $user_id, $start_date
        ));
        
        return $login_events > 0 ? round($total_events / $login_events, 1) : 0;
    }
    
    /**
     * Obtenir la vélocité de complétion
     */
    private static function getCompletionVelocity($user_id, $start_date) {
        global $wpdb;
        
        $completed_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND status = 'completed'
             AND end_time >= %s",
            $user_id, $start_date
        ));
        
        $days = ceil((strtotime('now') - strtotime($start_date)) / 86400);
        $weeks = $days / 7;
        
        return round($completed_items / $weeks, 1);
    }
    
    /**
     * Calculer le score d'engagement
     */
    private static function calculateEngagementScore($metrics) {
        $score = 0;
        
        // Fréquence de connexion (max 25 points)
        $score += min($metrics['login_frequency'] * 10, 25);
        
        // Durée de session (max 25 points)
        $score += min($metrics['session_duration'] / 2, 25);
        
        // Taux d'interaction (max 25 points)
        $score += min($metrics['interaction_rate'] * 2, 25);
        
        // Vélocité de complétion (max 25 points)
        $score += min($metrics['completion_velocity'] * 5, 25);
        
        return round($score, 1);
    }
    
    /**
     * Prédire la rétention
     */
    private static function predictRetention($user_id, $metrics) {
        $engagement_score = self::calculateEngagementScore($metrics);
        
        if ($engagement_score >= 80) {
            return [
                'risk' => 'low',
                'probability' => 90,
                'message' => __('Excellente rétention prévue', 'tmm-dashboard')
            ];
        } elseif ($engagement_score >= 60) {
            return [
                'risk' => 'medium',
                'probability' => 70,
                'message' => __('Bonne rétention prévue', 'tmm-dashboard')
            ];
        } elseif ($engagement_score >= 40) {
            return [
                'risk' => 'high',
                'probability' => 40,
                'message' => __('Risque de désengagement', 'tmm-dashboard')
            ];
        } else {
            return [
                'risk' => 'critical',
                'probability' => 20,
                'message' => __('Risque élevé d\'abandon', 'tmm-dashboard')
            ];
        }
    }
    
    /**
     * Générer des recommandations d'engagement
     */
    private static function generateEngagementRecommendations($engagement_score, $metrics) {
        $recommendations = [];
        
        if ($metrics['login_frequency'] < 0.5) {
            $recommendations[] = __('Essayez de vous connecter au moins tous les deux jours pour maintenir votre élan.', 'tmm-dashboard');
        }
        
        if ($metrics['session_duration'] < 20) {
            $recommendations[] = __('Augmentez vos sessions d\'apprentissage à au moins 20 minutes pour une meilleure progression.', 'tmm-dashboard');
        }
        
        if ($metrics['interaction_rate'] < 5) {
            $recommendations[] = __('Explorez plus de contenu lors de chaque connexion pour maximiser votre apprentissage.', 'tmm-dashboard');
        }
        
        if ($metrics['completion_velocity'] < 1) {
            $recommendations[] = __('Fixez-vous l\'objectif de terminer au moins un module par semaine.', 'tmm-dashboard');
        }
        
        if ($engagement_score >= 80) {
            $recommendations[] = __('Excellent travail ! Continuez sur cette lancée.', 'tmm-dashboard');
        }
        
        return $recommendations;
    }
    
    /**
     * Obtenir les tendances d'engagement
     */
    private static function getEngagementTrends($user_id, $days) {
        global $wpdb;
        
        // Diviser la période en semaines
        $weeks = ceil($days / 7);
        $trends = [];
        
        for ($i = 0; $i < $weeks; $i++) {
            $week_start = date('Y-m-d', strtotime("-" . (($i + 1) * 7) . " days"));
            $week_end = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
            
            $week_events = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}tmm_analytics
                 WHERE user_id = %d 
                 AND created_at BETWEEN %s AND %s",
                $user_id, $week_start, $week_end
            ));
            
            $trends[] = [
                'week' => sprintf(__('Semaine %d', 'tmm-dashboard'), $weeks - $i),
                'events' => intval($week_events),
                'start' => $week_start,
                'end' => $week_end
            ];
        }
        
        return array_reverse($trends);
    }
    
    /**
     * Générer un rapport périodique
     */
    public static function generatePeriodicReport($user_id, $period = 'weekly') {
        $days = $period === 'weekly' ? 7 : 30;
        
        $analytics = [
            'overview' => self::getOverviewAnalytics($user_id, $days),
            'courses' => self::getCourseAnalytics($user_id, $days),
            'activity' => self::getActivityAnalytics($user_id, $days),
            'performance' => self::getPerformanceAnalytics($user_id, $days),
            'engagement' => self::getEngagementAnalytics($user_id, $days)
        ];
        
        // Générer le rapport HTML
        $report = self::generateReportHTML($analytics, $period);
        
        // Envoyer par email si activé
        if (get_user_meta($user_id, 'tmm_pref_weekly_report', true)) {
            self::sendReportEmail($user_id, $report, $period);
        }
        
        return $report;
    }
    
    /**
     * Générer le HTML du rapport
     */
    private static function generateReportHTML($analytics, $period) {
        ob_start();
        ?>
        <div class="tmm-analytics-report">
            <h2><?php echo $period === 'weekly' ? __('Rapport hebdomadaire', 'tmm-dashboard') : __('Rapport mensuel', 'tmm-dashboard'); ?></h2>
            
            <div class="report-section">
                <h3><?php _e('Vue d\'ensemble', 'tmm-dashboard'); ?></h3>
                <ul>
                    <li><?php printf(__('Temps total d\'apprentissage : %s heures', 'tmm-dashboard'), $analytics['overview']['summary']['total_time_hours']); ?></li>
                    <li><?php printf(__('Cours complétés : %d', 'tmm-dashboard'), $analytics['overview']['summary']['courses_completed']); ?></li>
                    <li><?php printf(__('Score moyen aux quiz : %s%%', 'tmm-dashboard'), $analytics['overview']['summary']['average_quiz_score']); ?></li>
                </ul>
            </div>
            
            <div class="report-section">
                <h3><?php _e('Engagement', 'tmm-dashboard'); ?></h3>
                <p><?php printf(__('Score d\'engagement : %s/100', 'tmm-dashboard'), $analytics['engagement']['engagement_score']); ?></p>
                <p><?php echo $analytics['engagement']['retention_prediction']['message']; ?></p>
            </div>
            
            <div class="report-section">
                <h3><?php _e('Recommandations', 'tmm-dashboard'); ?></h3>
                <ul>
                    <?php foreach ($analytics['engagement']['recommendations'] as $recommendation): ?>
                        <li><?php echo esc_html($recommendation); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Envoyer le rapport par email
     */
    private static function sendReportEmail($user_id, $report, $period) {
        $user = get_user_by('id', $user_id);
        if (!$user) return;
        
        $subject = $period === 'weekly' 
            ? sprintf(__('[%s] Votre rapport hebdomadaire d\'apprentissage', 'tmm-dashboard'), get_bloginfo('name'))
            : sprintf(__('[%s] Votre rapport mensuel d\'apprentissage', 'tmm-dashboard'), get_bloginfo('name'));
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        wp_mail($user->user_email, $subject, $report, $headers);
    }
}