<?php
/**
 * Moteur de Machine Learning pour le chatbot
 * 
 * @package TMMDashboardPro
 * @subpackage Chatbot/ML
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMChatbotML {
    
    /**
     * Initialiser le module ML
     */
    public static function init() {
        // Créer les tables nécessaires pour stocker les données ML
        self::createMLTables();
        
        // Planifier les tâches de training
        if (!wp_next_scheduled('tmm_ml_training')) {
            wp_schedule_event(time(), 'daily', 'tmm_ml_training');
        }
        
        add_action('tmm_ml_training', [__CLASS__, 'runDailyTraining']);
    }
    
    /**
     * Créer les tables pour le ML
     */
    private static function createMLTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table pour les features d'apprentissage
        $table_features = $wpdb->prefix . 'tmm_ml_features';
        $sql_features = "CREATE TABLE IF NOT EXISTS $table_features (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            feature_type varchar(50) NOT NULL,
            feature_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY feature_type (feature_type)
        ) $charset_collate;";
        
        // Table pour les prédictions
        $table_predictions = $wpdb->prefix . 'tmm_ml_predictions';
        $sql_predictions = "CREATE TABLE IF NOT EXISTS $table_predictions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            prediction_type varchar(50) NOT NULL,
            prediction_data longtext NOT NULL,
            confidence float NOT NULL,
            actual_outcome varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY prediction_type (prediction_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_features);
        dbDelta($sql_predictions);
    }
    
    /**
     * Modèle de prédiction du temps de complétion
     */
    public static function predictCompletionTime($user_id, $course_id) {
        // Récupérer les données historiques
        $historical_data = self::getUserHistoricalData($user_id);
        $course_complexity = self::assessCourseComplexity($course_id);
        
        // Features pour le modèle
        $features = [
            'avg_completion_time' => $historical_data['avg_completion_time'],
            'learning_speed' => $historical_data['learning_speed'],
            'consistency_score' => $historical_data['consistency_score'],
            'course_complexity' => $course_complexity,
            'current_workload' => self::getCurrentWorkload($user_id),
            'time_availability' => self::getTimeAvailability($user_id),
            'preferred_learning_time' => self::getPreferredLearningTime($user_id),
            'distraction_level' => self::estimateDistractionLevel($user_id)
        ];
        
        // Appliquer le modèle
        $prediction = self::applyPredictionModel('completion_time', $features);
        
        // Sauvegarder la prédiction pour validation future
        self::savePrediction($user_id, 'completion_time', $prediction, $course_id);
        
        return [
            'estimated_hours' => $prediction['hours'],
            'confidence' => $prediction['confidence'],
            'factors' => $prediction['factors'],
            'recommended_schedule' => self::generateOptimalSchedule($user_id, $prediction['hours'])
        ];
    }
    
    /**
     * Détection d'anomalies dans le comportement
     */
    public static function detectAnomalies($user_id) {
        $recent_activity = self::getRecentActivity($user_id, 30);
        $baseline = self::getUserBaseline($user_id);
        
        $anomalies = [];
        
        // Analyse multi-dimensionnelle
        $dimensions = [
            'activity_level' => [
                'current' => $recent_activity['avg_daily_time'],
                'baseline' => $baseline['avg_daily_time'],
                'threshold' => 0.5
            ],
            'performance' => [
                'current' => $recent_activity['avg_quiz_score'] ?? 75,
                'baseline' => $baseline['avg_quiz_score'],
                'threshold' => 0.8
            ],
            'engagement' => [
                'current' => $recent_activity['interaction_frequency'] ?? 5,
                'baseline' => $baseline['interaction_frequency'],
                'threshold' => 0.6
            ],
            'consistency' => [
                'current' => $recent_activity['login_consistency'] ?? 0.7,
                'baseline' => $baseline['login_consistency'],
                'threshold' => 0.7
            ]
        ];
        
        foreach ($dimensions as $dimension => $metrics) {
            $ratio = $metrics['current'] / ($metrics['baseline'] ?: 1);
            
            if ($ratio < $metrics['threshold']) {
                $anomalies[] = self::generateAnomalyAlert($dimension, $metrics, $ratio);
            }
        }
        
        // Détection de patterns inhabituels
        $unusual_patterns = self::detectUnusualPatterns($user_id);
        if (!empty($unusual_patterns)) {
            $anomalies = array_merge($anomalies, $unusual_patterns);
        }
        
        return $anomalies;
    }
    
    /**
     * Prédiction du risque d'abandon
     */
    public static function predictChurnRisk($user_id) {
        $risk_factors = [
            'days_since_last_login' => self::getDaysSinceLastLogin($user_id),
            'completion_rate_trend' => self::getCompletionRateTrend($user_id),
            'engagement_decline' => self::measureEngagementDecline($user_id),
            'difficulty_encounters' => self::countDifficultyEncounters($user_id),
            'support_requests' => self::getSupportRequestFrequency($user_id),
            'course_switching' => self::getCourseSwitchingRate($user_id)
        ];
        
        // Calculer le score de risque
        $risk_score = self::calculateChurnRiskScore($risk_factors);
        
        // Déterminer les interventions recommandées
        $interventions = self::recommendInterventions($risk_score, $risk_factors);
        
        return [
            'risk_level' => self::getRiskLevel($risk_score),
            'risk_score' => $risk_score,
            'main_factors' => self::getMainRiskFactors($risk_factors),
            'recommended_actions' => $interventions,
            'prediction_confidence' => self::calculatePredictionConfidence($risk_factors)
        ];
    }
    
    /**
     * Prédire les besoins de l'utilisateur
     */
    public static function predictUserNeeds($user_id) {
        $context = [
            'current_progress' => self::getUserProgress($user_id),
            'learning_patterns' => self::analyzeLearningPatterns($user_id),
            'recent_struggles' => self::identifyRecentStruggles($user_id),
            'time_constraints' => self::identifyTimeConstraints($user_id)
        ];
        
        $predictions = [
            'likely_needs_help' => self::predictNeedsHelp($context),
            'recommended_resources' => self::predictResourceNeeds($context),
            'optimal_learning_time' => self::predictOptimalLearningTime($user_id),
            'motivation_boost_needed' => self::predictMotivationNeeds($context)
        ];
        
        return $predictions;
    }
    
    /**
     * Système de recommandation basé sur le collaborative filtering
     */
    public static function getCollaborativeRecommendations($user_id) {
        // Trouver des utilisateurs similaires
        $similar_users = self::findSimilarUsers($user_id);
        
        // Analyser leurs parcours réussis
        $successful_paths = [];
        foreach ($similar_users as $similar_user) {
            $paths = self::getSuccessfulLearningPaths($similar_user['user_id']);
            foreach ($paths as $path) {
                $path['similarity_score'] = $similar_user['similarity'];
                $successful_paths[] = $path;
            }
        }
        
        // Filtrer et scorer les recommandations
        $recommendations = self::scoreRecommendations($user_id, $successful_paths);
        
        return array_slice($recommendations, 0, 5); // Top 5
    }
    
    /**
     * Analyse prédictive de la performance future
     */
    public static function predictFuturePerformance($user_id, $timeframe_days = 30) {
        $historical_performance = self::getPerformanceHistory($user_id);
        $learning_curve = self::analyzeLearningCurve($historical_performance);
        
        // Extrapolation basée sur la courbe d'apprentissage
        $predictions = [];
        for ($day = 1; $day <= $timeframe_days; $day++) {
            $predictions[] = [
                'day' => $day,
                'predicted_score' => self::extrapolateScore($learning_curve, $day),
                'confidence_interval' => self::calculateConfidenceInterval($learning_curve, $day)
            ];
        }
        
        return [
            'predictions' => $predictions,
            'expected_mastery_date' => self::predictMasteryDate($learning_curve),
            'recommended_pace' => self::calculateOptimalPace($learning_curve)
        ];
    }
    
    /**
     * Training quotidien du modèle
     */
    public static function runDailyTraining() {
        global $wpdb;
        
        // Récupérer les nouvelles données
        $new_data = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tmm_chatbot_messages 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        
        // Extraire les features
        $features = self::extractFeaturesFromData($new_data);
        
        // Mettre à jour les modèles
        self::updateModels($features);
        
        // Valider les prédictions précédentes
        self::validatePastPredictions();
        
        // Nettoyer les anciennes données
        self::cleanupOldData();
    }
    
    /**
     * Clustering des utilisateurs pour segmentation
     */
    public static function clusterUsers() {
        $all_users = get_users(['fields' => 'ID']);
        $user_vectors = [];
        
        foreach ($all_users as $user_id) {
            $user_vectors[$user_id] = self::getUserFeatureVector($user_id);
        }
        
        // K-means clustering simplifié
        $clusters = self::kMeansClustering($user_vectors, 5);
        
        // Analyser chaque cluster
        $cluster_profiles = [];
        foreach ($clusters as $cluster_id => $user_ids) {
            $cluster_profiles[$cluster_id] = self::analyzeCluster($user_ids);
        }
        
        return $cluster_profiles;
    }
    
    /**
     * MÉTHODES HELPER
     */
    
    /**
     * Obtenir le workload actuel
     */
    private static function getCurrentWorkload($user_id) {
        $courses = TMMDashboardData::getUserCourses($user_id);
        return count($courses['in_progress'] ?? []);
    }
    
    /**
     * Obtenir la disponibilité temporelle
     */
    private static function getTimeAvailability($user_id) {
        // Basé sur l'activité récente
        $recent_activity = self::getRecentActivity($user_id, 7);
        return $recent_activity['avg_daily_time'] ?? 2; // heures par jour
    }
    
    /**
     * Obtenir le temps d'apprentissage préféré
     */
    private static function getPreferredLearningTime($user_id) {
        global $wpdb;
        
        // Analyser les heures de connexion
        $login_times = $wpdb->get_col($wpdb->prepare(
            "SELECT HOUR(created_at) as hour
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'login'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT 100",
            $user_id
        ));
        
        if (empty($login_times)) return 'evening'; // par défaut
        
        $hour_counts = array_count_values($login_times);
        $peak_hour = array_search(max($hour_counts), $hour_counts);
        
        if ($peak_hour < 12) return 'morning';
        if ($peak_hour < 17) return 'afternoon';
        return 'evening';
    }
    
    /**
     * Estimer le niveau de distraction
     */
    private static function estimateDistractionLevel($user_id) {
        // Basé sur le taux d'achèvement des sessions
        $sessions = self::getRecentSessions($user_id, 30);
        $completed_sessions = array_filter($sessions, function($s) {
            return $s['completed'] ?? false;
        });
        
        $completion_rate = count($sessions) > 0 
            ? count($completed_sessions) / count($sessions) 
            : 0.5;
        
        return 1 - $completion_rate; // Plus le taux est bas, plus la distraction est élevée
    }
    
    /**
     * Générer un planning optimal
     */
    private static function generateOptimalSchedule($user_id, $estimated_hours) {
        $availability = self::getTimeAvailability($user_id);
        $preferred_time = self::getPreferredLearningTime($user_id);
        
        $days_needed = ceil($estimated_hours / $availability);
        
        return [
            'total_days' => $days_needed,
            'hours_per_day' => $availability,
            'preferred_time' => $preferred_time,
            'schedule_suggestion' => sprintf(
                'Nous recommandons %d heures par jour pendant %d jours, de préférence le %s.',
                $availability,
                $days_needed,
                $preferred_time === 'morning' ? 'matin' : ($preferred_time === 'afternoon' ? 'après-midi' : 'soir')
            )
        ];
    }
    
    /**
     * Obtenir l'activité récente
     */
    private static function getRecentActivity($user_id, $days) {
        global $wpdb;
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at))) / 60 as avg_daily_time,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(*) as total_events,
                AVG(CASE WHEN event_type = 'quiz_completed' 
                    THEN JSON_EXTRACT(event_data, '$.score') END) as avg_quiz_score
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d
             AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)",
            $user_id, $days
        ), ARRAY_A);
        
        // Calculer des métriques supplémentaires
        $interaction_frequency = $data ? $data['total_events'] / max(1, $data['active_days']) : 0;
        $login_consistency = $data && $days > 0 ? $data['active_days'] / $days : 0;
        
        return [
            'avg_daily_time' => floatval($data['avg_daily_time'] ?? 0),
            'active_days' => intval($data['active_days'] ?? 0),
            'total_events' => intval($data['total_events'] ?? 0),
            'activity_rate' => $days > 0 ? ($data['active_days'] ?? 0) / $days : 0,
            'avg_quiz_score' => floatval($data['avg_quiz_score'] ?? 75),
            'interaction_frequency' => $interaction_frequency,
            'login_consistency' => $login_consistency
        ];
    }
    
    /**
     * Obtenir les sessions récentes
     */
    private static function getRecentSessions($user_id, $days) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                session_id,
                MIN(created_at) as start_time,
                MAX(created_at) as end_time,
                COUNT(*) as events_count,
                MAX(CASE WHEN event_type = 'session_end' THEN 1 ELSE 0 END) as completed
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
             AND session_id IS NOT NULL
             GROUP BY session_id",
            $user_id, $days
        ), ARRAY_A);
    }
    
    /**
     * Obtenir les données historiques utilisateur
     */
    private static function getUserHistoricalData($user_id) {
        global $wpdb;
        
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                AVG(TIMESTAMPDIFF(HOUR, start_time, end_time)) as avg_completion_time,
                COUNT(DISTINCT DATE(start_time)) / DATEDIFF(MAX(start_time), MIN(start_time)) as learning_frequency,
                AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completion_rate
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d
             GROUP BY user_id",
            $user_id
        ), ARRAY_A);
        
        return [
            'avg_completion_time' => $data[0]['avg_completion_time'] ?? 0,
            'learning_speed' => 1 / ($data[0]['avg_completion_time'] ?? 1),
            'consistency_score' => $data[0]['learning_frequency'] ?? 0
        ];
    }
    
    /**
     * Évaluer la complexité d'un cours
     */
    private static function assessCourseComplexity($course_id) {
        $factors = [
            'duration' => get_post_meta($course_id, '_lp_duration', true),
            'lesson_count' => self::getCourseLessonCount($course_id),
            'quiz_count' => self::getCourseQuizCount($course_id),
            'level' => self::getCourseLevelScore($course_id),
            'prerequisite_count' => self::getCoursePrerequisiteCount($course_id)
        ];
        
        // Normaliser et pondérer
        $complexity = 0;
        $complexity += ($factors['duration'] / 60) * 0.2;  // Heures
        $complexity += $factors['lesson_count'] * 0.3;
        $complexity += $factors['quiz_count'] * 0.2;
        $complexity += $factors['level'] * 0.2;
        $complexity += $factors['prerequisite_count'] * 0.1;
        
        return min($complexity, 10); // Score sur 10
    }
    
    /**
     * Obtenir la baseline de performance de l'utilisateur
     */
    private static function getUserBaseline($user_id) {
        global $wpdb;
        
        // Récupérer les données historiques (6 derniers mois)
        $baseline_data = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(CASE WHEN meta_key = 'daily_time' THEN meta_value END) as avg_daily_time,
                AVG(CASE WHEN meta_key = 'quiz_score' THEN meta_value END) as avg_quiz_score,
                AVG(CASE WHEN meta_key = 'interaction_freq' THEN meta_value END) as interaction_frequency,
                AVG(CASE WHEN meta_key = 'login_consistency' THEN meta_value END) as login_consistency
             FROM {$wpdb->prefix}tmm_ml_features 
             WHERE user_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
             AND feature_type = 'user_baseline'",
            $user_id
        ), ARRAY_A);
        
        if (!$baseline_data || !$baseline_data['avg_daily_time']) {
            // Valeurs par défaut si pas d'historique
            return [
                'avg_daily_time' => 2.0,
                'avg_quiz_score' => 75.0,
                'interaction_frequency' => 5.0,
                'login_consistency' => 0.7
            ];
        }
        
        return [
            'avg_daily_time' => floatval($baseline_data['avg_daily_time'] ?? 2.0),
            'avg_quiz_score' => floatval($baseline_data['avg_quiz_score'] ?? 75.0),
            'interaction_frequency' => floatval($baseline_data['interaction_frequency'] ?? 5.0),
            'login_consistency' => floatval($baseline_data['login_consistency'] ?? 0.7)
        ];
    }
    
    /**
     * Détecter des patterns inhabituels
     */
    private static function detectUnusualPatterns($user_id) {
        global $wpdb;
        
        $patterns = [];
        
        // 1. Détection de pics d'activité inhabituels
        $activity_spikes = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as events
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             HAVING events > (
                 SELECT AVG(daily_events) * 2 
                 FROM (
                     SELECT COUNT(*) as daily_events
                     FROM {$wpdb->prefix}tmm_analytics
                     WHERE user_id = %d
                     AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
                     GROUP BY DATE(created_at)
                 ) as avg_table
             )",
            $user_id, $user_id
        ));
        
        if (!empty($activity_spikes)) {
            $patterns[] = [
                'type' => 'activity_spike',
                'description' => 'Pic d\'activité inhabituel détecté',
                'severity' => 'medium',
                'data' => $activity_spikes
            ];
        }
        
        // 2. Détection de changements brusques de performance
        $performance_drops = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                AVG(CASE WHEN event_type = 'quiz_completed' 
                    THEN JSON_EXTRACT(event_data, '$.score') END) as avg_score
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'quiz_completed'
             AND created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY DATE(created_at)
             HAVING avg_score < (
                 SELECT AVG(score) * 0.7
                 FROM (
                     SELECT JSON_EXTRACT(event_data, '$.score') as score
                     FROM {$wpdb->prefix}tmm_analytics
                     WHERE user_id = %d
                     AND event_type = 'quiz_completed'
                     AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 90 DAY) 
                     AND DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ) as baseline_scores
             )",
            $user_id, $user_id
        ));
        
        if (!empty($performance_drops)) {
            $patterns[] = [
                'type' => 'performance_drop',
                'description' => 'Baisse significative de performance',
                'severity' => 'high',
                'data' => $performance_drops
            ];
        }
        
        // 3. Détection de patterns de connexion inhabituels
        $login_patterns = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as logins
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'login'
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY HOUR(created_at)",
            $user_id
        ));
        
        // Analyse des heures de connexion
        $usual_hours = get_user_meta($user_id, 'tmm_usual_login_hours', true) ?: [];
        $current_hours = array_column($login_patterns, 'hour');
        $unusual_hours = array_diff($current_hours, $usual_hours);
        
        if (!empty($unusual_hours) && count($unusual_hours) > 3) {
            $patterns[] = [
                'type' => 'login_pattern_change',
                'description' => 'Changement dans les habitudes de connexion',
                'severity' => 'low',
                'data' => ['unusual_hours' => $unusual_hours]
            ];
        }
        
        return $patterns;
    }
    
    /**
     * Trouver des utilisateurs similaires (Collaborative Filtering)
     */
    private static function findSimilarUsers($user_id) {
        global $wpdb;
        
        // Récupérer le vecteur de features de l'utilisateur actuel
        $user_vector = self::getUserFeatureVector($user_id);
        
        if (empty($user_vector)) {
            return [];
        }
        
        // Récupérer tous les autres utilisateurs actifs
        $other_users = $wpdb->get_results(
            "SELECT DISTINCT user_id 
             FROM {$wpdb->prefix}tmm_analytics 
             WHERE user_id != {$user_id}
             AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
             LIMIT 1000"
        );
        
        $similarities = [];
        
        foreach ($other_users as $other_user) {
            $other_vector = self::getUserFeatureVector($other_user->user_id);
            
            if (!empty($other_vector)) {
                $similarity = self::calculateCosineSimilarity($user_vector, $other_vector);
                
                if ($similarity > 0.7) { // Seuil de similarité
                    $similarities[] = [
                        'user_id' => $other_user->user_id,
                        'similarity' => $similarity
                    ];
                }
            }
        }
        
        // Trier par similarité décroissante
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($similarities, 0, 10); // Top 10 utilisateurs similaires
    }
    
    /**
     * Obtenir le vecteur de features d'un utilisateur
     */
    private static function getUserFeatureVector($user_id) {
        global $wpdb;
        
        // Récupérer les features principales
        $features = [];
        
        // 1. Activité de l'utilisateur
        $activity = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(DISTINCT CASE WHEN event_type = 'course_completed' THEN event_type END) as completed_courses,
                AVG(CASE WHEN event_type = 'quiz_completed' 
                    THEN JSON_EXTRACT(event_data, '$.score') END) as avg_quiz_score
             FROM {$wpdb->prefix}tmm_analytics 
             WHERE user_id = %d 
             AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)",
            $user_id
        ), ARRAY_A);
        
        if (!$activity) {
            return [];
        }
        
        // 2. Préférences de cours (catégories)
        $course_prefs = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                tt.name as category,
                COUNT(*) as count
             FROM {$wpdb->prefix}tmm_analytics a
             JOIN {$wpdb->posts} p ON JSON_EXTRACT(a.event_data, '$.course_id') = p.ID
             JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             JOIN {$wpdb->term_taxonomy} tax ON tr.term_taxonomy_id = tax.term_taxonomy_id
             JOIN {$wpdb->terms} tt ON tax.term_id = tt.term_id
             WHERE a.user_id = %d 
             AND tax.taxonomy = 'course_category'
             AND a.event_type IN ('course_started', 'lesson_completed')
             GROUP BY tt.name
             ORDER BY count DESC
             LIMIT 5",
            $user_id
        ), ARRAY_A);
        
        // 3. Construire le vecteur de features normalisé
        $vector = [
            'activity_level' => min(1.0, ($activity['total_events'] ?? 0) / 100),
            'consistency' => min(1.0, ($activity['active_days'] ?? 0) / 30),
            'performance' => min(1.0, ($activity['avg_quiz_score'] ?? 0) / 100),
            'completion_rate' => min(1.0, ($activity['completed_courses'] ?? 0) / 10)
        ];
        
        // Ajouter les préférences de catégories (encodage one-hot simplifié)
        $main_categories = ['technology', 'business', 'design', 'marketing', 'development'];
        foreach ($main_categories as $category) {
            $vector['pref_' . $category] = 0;
        }
        
        // Marquer les catégories préférées
        foreach ($course_prefs as $pref) {
            $category_key = 'pref_' . strtolower($pref['category']);
            if (isset($vector[$category_key])) {
                $vector[$category_key] = min(1.0, $pref['count'] / 5);
            }
        }
        
        return $vector;
    }
    
    /**
     * Calculer la similarité cosinus entre deux vecteurs
     */
    private static function calculateCosineSimilarity($vector1, $vector2) {
        if (count($vector1) !== count($vector2)) {
            return 0;
        }
        
        $dot_product = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        $keys = array_keys($vector1);
        
        foreach ($keys as $key) {
            $v1 = $vector1[$key] ?? 0;
            $v2 = $vector2[$key] ?? 0;
            
            $dot_product += $v1 * $v2;
            $magnitude1 += $v1 * $v1;
            $magnitude2 += $v2 * $v2;
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }
        
        return $dot_product / ($magnitude1 * $magnitude2);
    }
    
    /**
     * Clustering K-means simplifié
     */
    private static function kMeansClustering($user_vectors, $k = 5) {
        if (count($user_vectors) < $k) {
            $k = count($user_vectors);
        }
        
        // Initialiser les centroïdes aléatoirement
        $centroids = array_slice($user_vectors, 0, $k, true);
        $clusters = [];
        $max_iterations = 10;
        
        for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
            // Réinitialiser les clusters
            $new_clusters = array_fill(0, $k, []);
            
            // Assigner chaque utilisateur au centroïde le plus proche
            foreach ($user_vectors as $user_id => $vector) {
                $closest_centroid = 0;
                $min_distance = PHP_FLOAT_MAX;
                
                foreach ($centroids as $c_id => $centroid) {
                    $distance = self::calculateEuclideanDistance($vector, $centroid);
                    if ($distance < $min_distance) {
                        $min_distance = $distance;
                        $closest_centroid = $c_id;
                    }
                }
                
                $new_clusters[$closest_centroid][] = $user_id;
            }
            
            // Recalculer les centroïdes
            $new_centroids = [];
            foreach ($new_clusters as $cluster_id => $cluster_users) {
                if (!empty($cluster_users)) {
                    $new_centroids[$cluster_id] = self::calculateCentroid($cluster_users, $user_vectors);
                } else {
                    $new_centroids[$cluster_id] = $centroids[$cluster_id];
                }
            }
            
            // Vérifier la convergence
            $converged = true;
            foreach ($centroids as $c_id => $centroid) {
                if (self::calculateEuclideanDistance($centroid, $new_centroids[$c_id]) > 0.01) {
                    $converged = false;
                    break;
                }
            }
            
            $centroids = $new_centroids;
            $clusters = $new_clusters;
            
            if ($converged) {
                break;
            }
        }
        
        return $clusters;
    }
    
    /**
     * Calculer la distance euclidienne entre deux vecteurs
     */
    private static function calculateEuclideanDistance($vector1, $vector2) {
        $sum = 0;
        $keys = array_keys($vector1);
        
        foreach ($keys as $key) {
            $diff = ($vector1[$key] ?? 0) - ($vector2[$key] ?? 0);
            $sum += $diff * $diff;
        }
        
        return sqrt($sum);
    }
    
    /**
     * Calculer le centroïde d'un cluster
     */
    private static function calculateCentroid($user_ids, $user_vectors) {
        if (empty($user_ids)) {
            return [];
        }
        
        $centroid = [];
        $first_vector = reset($user_vectors);
        $keys = array_keys($first_vector);
        
        foreach ($keys as $key) {
            $sum = 0;
            $count = 0;
            
            foreach ($user_ids as $user_id) {
                if (isset($user_vectors[$user_id][$key])) {
                    $sum += $user_vectors[$user_id][$key];
                    $count++;
                }
            }
            
            $centroid[$key] = $count > 0 ? $sum / $count : 0;
        }
        
        return $centroid;
    }
    
    /**
     * Obtenir les parcours de réussite pour des utilisateurs similaires
     */
    private static function getSuccessfulLearningPaths($user_id) {
        global $wpdb;
        
        // Récupérer les cours complétés par cet utilisateur dans l'ordre
        $completed_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(event_data, '$.course_id') as course_id,
                MIN(created_at) as started_at,
                MAX(created_at) as completed_at
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type IN ('course_started', 'course_completed')
             GROUP BY JSON_EXTRACT(event_data, '$.course_id')
             HAVING COUNT(CASE WHEN event_type = 'course_completed' THEN 1 END) > 0
             ORDER BY completed_at",
            $user_id
        ));
        
        $paths = [];
        
        foreach ($completed_courses as $course) {
            $course_info = get_post($course->course_id);
            if ($course_info) {
                $paths[] = [
                    'course_id' => $course->course_id,
                    'course_name' => $course_info->post_title,
                    'duration_days' => (strtotime($course->completed_at) - strtotime($course->started_at)) / (60 * 60 * 24),
                    'success_score' => self::calculateCourseSuccessScore($user_id, $course->course_id)
                ];
            }
        }
        
        return $paths;
    }
    
    /**
     * Calculer le score de succès pour un cours
     */
    private static function calculateCourseSuccessScore($user_id, $course_id) {
        global $wpdb;
        
        // Récupérer les scores de quiz pour ce cours
        $quiz_scores = $wpdb->get_col($wpdb->prepare(
            "SELECT JSON_EXTRACT(event_data, '$.score')
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'quiz_completed'
             AND JSON_EXTRACT(event_data, '$.course_id') = %d",
            $user_id, $course_id
        ));
        
        if (empty($quiz_scores)) {
            return 75; // Score par défaut
        }
        
        return array_sum($quiz_scores) / count($quiz_scores);
    }
    
    /**
     * Analyser la courbe d'apprentissage
     */
    private static function analyzeLearningCurve($performance_history) {
        if (count($performance_history) < 3) {
            return [
                'trend' => 0,
                'current_level' => 0,
                'acceleration' => 0,
                'confidence' => 0
            ];
        }
        
        $scores = array_column($performance_history, 'score');
        $n = count($scores);
        
        // Calculer la tendance (régression linéaire simple)
        $sum_x = array_sum(range(0, $n - 1));
        $sum_y = array_sum($scores);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $i * $scores[$i];
            $sum_x2 += $i * $i;
        }
        
        $trend = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $current_level = end($scores);
        
        // Calculer l'accélération (dérivée seconde approximée)
        $acceleration = 0;
        if ($n >= 3) {
            $recent_trend = ($scores[$n-1] - $scores[$n-3]) / 2;
            $early_trend = ($scores[2] - $scores[0]) / 2;
            $acceleration = $recent_trend - $early_trend;
        }
        
        // Calculer la confiance basée sur la variance
        $mean_score = $sum_y / $n;
        $variance = array_sum(array_map(function($score) use ($mean_score) {
            return pow($score - $mean_score, 2);
        }, $scores)) / $n;
        
        $confidence = max(0, min(1, 1 - ($variance / 1000))); // Normaliser la confiance
        
        return [
            'trend' => $trend,
            'current_level' => $current_level,
            'acceleration' => $acceleration,
            'confidence' => $confidence,
            'variance' => $variance
        ];
    }
    
    /**
     * Prédire la date de maîtrise
     */
    private static function predictMasteryDate($learning_curve) {
        if (empty($learning_curve) || !isset($learning_curve['trend'])) {
            return null;
        }
        
        $trend = $learning_curve['trend'];
        $current_level = $learning_curve['current_level'] ?? 0;
        $mastery_threshold = 90; // 90% de maîtrise
        
        if ($trend <= 0) {
            return null; // Pas de progression
        }
        
        $points_needed = $mastery_threshold - $current_level;
        $days_needed = $points_needed / $trend;
        
        return date('Y-m-d', strtotime("+{$days_needed} days"));
    }
    
    /**
     * Calculer le rythme optimal d'apprentissage
     */
    private static function calculateOptimalPace($learning_curve) {
        $trend = $learning_curve['trend'] ?? 0;
        $current_level = $learning_curve['current_level'] ?? 0;
        $acceleration = $learning_curve['acceleration'] ?? 0;
        
        // Logique pour déterminer le rythme optimal
        if ($trend > 2 && $acceleration > 0) {
            return 'accelerated'; // L'utilisateur peut accélérer
        } elseif ($trend < 0.5 || $acceleration < -1) {
            return 'slow_down'; // L'utilisateur devrait ralentir
        } else {
            return 'maintain'; // Maintenir le rythme actuel
        }
    }
    
    /**
     * Valider les prédictions passées (pour améliorer le modèle)
     */
    private static function validatePastPredictions() {
        global $wpdb;
        
        // Récupérer les prédictions anciennes avec résultats connus
        $old_predictions = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tmm_ml_predictions 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND actual_outcome IS NULL
             LIMIT 100"
        );
        
        foreach ($old_predictions as $prediction) {
            $actual_outcome = self::getActualOutcome($prediction);
            
            if ($actual_outcome !== null) {
                // Mettre à jour avec le résultat réel
                $wpdb->update(
                    $wpdb->prefix . 'tmm_ml_predictions',
                    ['actual_outcome' => $actual_outcome],
                    ['id' => $prediction->id]
                );
                
                // Calculer l'erreur de prédiction
                $prediction_data = json_decode($prediction->prediction_data, true);
                $error = self::calculatePredictionError($prediction_data, $actual_outcome);
                
                // Enregistrer l'erreur pour améliorer le modèle
                self::recordPredictionError($prediction->prediction_type, $error);
            }
        }
    }
    
    /**
     * Obtenir le résultat réel d'une prédiction
     */
    private static function getActualOutcome($prediction) {
        $prediction_data = json_decode($prediction->prediction_data, true);
        
        switch ($prediction->prediction_type) {
            case 'completion_time':
                return self::getActualCompletionTime($prediction->user_id, $prediction_data);
                
            case 'churn_risk':
                return self::getActualChurnStatus($prediction->user_id, $prediction->created_at);
                
            default:
                return null;
        }
    }
    
    /**
     * Nettoyer les anciennes données
     */
    private static function cleanupOldData() {
        global $wpdb;
        
        $retention_days = get_option('tmm_ml_data_retention_days', 365);
        
        // Supprimer les anciennes features
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}tmm_ml_features 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
        
        // Supprimer les anciennes prédictions validées
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}tmm_ml_predictions 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
             AND actual_outcome IS NOT NULL",
            $retention_days
        ));
    }
    
    /**
     * Méthodes helper supplémentaires
     */
    
    private static function getCourseLessonCount($course_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}learnpress_section_items si ON p.ID = si.item_id
             WHERE si.section_course_id = %d
             AND p.post_type = 'lp_lesson'",
            $course_id
        ));
    }
    
    private static function getCourseQuizCount($course_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}learnpress_section_items si ON p.ID = si.item_id
             WHERE si.section_course_id = %d
             AND p.post_type = 'lp_quiz'",
            $course_id
        ));
    }
    
    private static function getCourseLevelScore($course_id) {
        $level = get_post_meta($course_id, '_lp_level', true);
        
        $level_scores = [
            'beginner' => 1,
            'intermediate' => 5,
            'advanced' => 8,
            'expert' => 10
        ];
        
        return $level_scores[$level] ?? 5;
    }
    
    private static function getCoursePrerequisiteCount($course_id) {
        $prerequisites = get_post_meta($course_id, '_lp_prerequisite', true);
        return is_array($prerequisites) ? count($prerequisites) : 0;
    }
    
    private static function getModelWeights($model_type) {
        // Poids pré-entraînés pour différents modèles
        $weights = [
            'completion_time' => [
                'avg_completion_time' => 0.8,
                'learning_speed' => -0.5,
                'consistency_score' => -0.3,
                'course_complexity' => 1.2,
                'current_workload' => 0.6,
                'time_availability' => -0.4,
                'preferred_learning_time' => 0.1,
                'distraction_level' => 0.7
            ],
            'churn_risk' => [
                'days_since_last_login' => 2.0,
                'completion_rate_trend' => -1.5,
                'engagement_decline' => 1.8,
                'difficulty_encounters' => 0.9,
                'support_requests' => 0.7,
                'course_switching' => 0.5
            ]
        ];
        
        return $weights[$model_type] ?? [];
    }
    
    private static function calculateModelConfidence($features) {
        // Calculer la confiance basée sur la complétude des features
        $non_null_features = array_filter($features, function($v) { return $v !== null; });
        return count($non_null_features) / count($features);
    }
    
    private static function explainPrediction($features, $weights) {
        $factors = [];
        
        foreach ($features as $feature => $value) {
            if (isset($weights[$feature]) && $weights[$feature] != 0) {
                $impact = $value * $weights[$feature];
                $factors[$feature] = [
                    'value' => $value,
                    'weight' => $weights[$feature],
                    'impact' => $impact
                ];
            }
        }
        
        // Trier par impact
        uasort($factors, function($a, $b) {
            return abs($b['impact']) <=> abs($a['impact']);
        });
        
        return array_slice($factors, 0, 3); // Top 3 facteurs
    }
    
    private static function applyPredictionModel($model_type, $features) {
        // Simulation d'un modèle de régression
        $weights = self::getModelWeights($model_type);
        
        $prediction = 0;
        foreach ($features as $feature => $value) {
            $prediction += ($weights[$feature] ?? 0) * $value;
        }
        
        // Ajustements et normalisation
        $prediction = max(0, $prediction);
        
        return [
            'hours' => round($prediction, 1),
            'confidence' => self::calculateModelConfidence($features),
            'factors' => self::explainPrediction($features, $weights)
        ];
    }
    
    private static function savePrediction($user_id, $type, $prediction, $reference_id = null) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'tmm_ml_predictions',
            [
                'user_id' => $user_id,
                'prediction_type' => $type,
                'prediction_data' => json_encode([
                    'prediction' => $prediction,
                    'reference_id' => $reference_id,
                    'timestamp' => current_time('mysql')
                ]),
                'confidence' => $prediction['confidence'] ?? 0.5,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    private static function generateAnomalyAlert($dimension, $metrics, $ratio) {
        return [
            'type' => 'anomaly',
            'dimension' => $dimension,
            'severity' => $ratio < 0.3 ? 'high' : 'medium',
            'description' => sprintf(
                'Anomalie détectée : %s est à %.1f%% de la normale',
                $dimension,
                $ratio * 100
            ),
            'metrics' => $metrics,
            'recommendations' => self::getAnomalyRecommendations($dimension, $ratio)
        ];
    }
    
    private static function getAnomalyRecommendations($dimension, $ratio) {
        $recommendations = [
            'activity_level' => [
                'title' => 'Réengager l\'utilisateur',
                'actions' => ['Envoyer un rappel', 'Proposer du contenu personnalisé']
            ],
            'performance' => [
                'title' => 'Soutenir l\'apprentissage',
                'actions' => ['Proposer du tutorat', 'Recommander des ressources supplémentaires']
            ],
            'engagement' => [
                'title' => 'Stimuler l\'interaction',
                'actions' => ['Gamification', 'Défis hebdomadaires']
            ],
            'consistency' => [
                'title' => 'Encourager la régularité',
                'actions' => ['Rappels quotidiens', 'Objectifs progressifs']
            ]
        ];
        
        return $recommendations[$dimension] ?? [];
    }
    
    // Méthodes pour l'analyse des risques d'abandon
    
    private static function getDaysSinceLastLogin($user_id) {
        $last_login = get_user_meta($user_id, '_lp_last_activity', true);
        if (!$last_login) return 999;
        
        return (time() - strtotime($last_login)) / DAY_IN_SECONDS;
    }
    
    private static function getCompletionRateTrend($user_id) {
        // Analyser la tendance du taux de complétion sur 30 jours
        global $wpdb;
        
        $trend = $wpdb->get_var($wpdb->prepare(
            "SELECT 
                (COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 15 DAY) 
                    AND event_type = 'course_completed' THEN 1 END) - 
                 COUNT(CASE WHEN created_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) 
                    AND DATE_SUB(NOW(), INTERVAL 15 DAY) 
                    AND event_type = 'course_completed' THEN 1 END)) as trend
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d",
            $user_id
        ));
        
        return floatval($trend);
    }
    
    private static function measureEngagementDecline($user_id) {
        $recent = self::getRecentActivity($user_id, 7);
        $previous = self::getRecentActivity($user_id, 14);
        
        if ($previous['total_events'] == 0) return 0;
        
        return max(0, 1 - ($recent['total_events'] / $previous['total_events']));
    }
    
    private static function countDifficultyEncounters($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type IN ('quiz_failed', 'lesson_retry', 'help_requested')
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));
    }
    
    private static function getSupportRequestFrequency($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE user_id = %d 
             AND message LIKE '%aide%' OR message LIKE '%help%'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));
    }
    
    private static function getCourseSwitchingRate($user_id) {
        global $wpdb;
        
        $switches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT course_id) 
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND status = 'started'
             AND start_time > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));
        
        return min(1, $switches / 5); // Normaliser sur 5 cours max
    }
    
    private static function calculateChurnRiskScore($factors) {
        $weights = [
            'days_since_last_login' => 0.3,
            'completion_rate_trend' => 0.2,
            'engagement_decline' => 0.2,
            'difficulty_encounters' => 0.1,
            'support_requests' => 0.1,
            'course_switching' => 0.1
        ];
        
        $score = 0;
        foreach ($factors as $factor => $value) {
            $normalized_value = min(1, $value / 10); // Normalisation simple
            $score += ($weights[$factor] ?? 0) * $normalized_value;
        }
        
        return min(1, $score);
    }
    
    private static function getRiskLevel($score) {
        if ($score >= 0.7) return 'high';
        if ($score >= 0.4) return 'medium';
        return 'low';
    }
    
    private static function getMainRiskFactors($factors) {
        arsort($factors);
        return array_slice($factors, 0, 3, true);
    }
    
    private static function recommendInterventions($risk_score, $factors) {
        $interventions = [];
        
        if ($risk_score >= 0.7) {
            $interventions[] = 'Contact personnel immédiat';
            $interventions[] = 'Offre de session de coaching gratuite';
        }
        
        if ($factors['difficulty_encounters'] > 5) {
            $interventions[] = 'Proposer du contenu plus adapté au niveau';
        }
        
        if ($factors['engagement_decline'] > 0.5) {
            $interventions[] = 'Envoyer des notifications motivantes';
            $interventions[] = 'Créer des défis courts et engageants';
        }
        
        return $interventions;
    }
    
    private static function calculatePredictionConfidence($factors) {
        // Confiance basée sur la complétude des données
        $non_zero_factors = array_filter($factors, function($v) { return $v > 0; });
        return count($non_zero_factors) / count($factors);
    }
    
    // Autres méthodes helper nécessaires...
}
