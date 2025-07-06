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
                'current' => $recent_activity['avg_quiz_score'],
                'baseline' => $baseline['avg_quiz_score'],
                'threshold' => 0.8
            ],
            'engagement' => [
                'current' => $recent_activity['interaction_frequency'],
                'baseline' => $baseline['interaction_frequency'],
                'threshold' => 0.6
            ],
            'consistency' => [
                'current' => $recent_activity['login_consistency'],
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
     * Helpers privés
     */
     
     /**
 * Méthodes helper manquantes
 */
private static function getCurrentWorkload($user_id) {
    $courses = TMMDashboardData::getUserCourses($user_id);
    return count($courses['in_progress'] ?? []);
}

private static function getTimeAvailability($user_id) {
    // Basé sur l'activité récente
    $recent_activity = self::getRecentActivity($user_id, 7);
    return $recent_activity['avg_daily_time'] ?? 2; // heures par jour
}

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

private static function getRecentActivity($user_id, $days) {
    global $wpdb;
    
    $data = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at))) / 60 as avg_daily_time,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            COUNT(*) as total_events
         FROM {$wpdb->prefix}tmm_analytics
         WHERE user_id = %d
         AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
         GROUP BY DATE(created_at)",
        $user_id, $days
    ), ARRAY_A);
    
    return [
        'avg_daily_time' => floatval($data['avg_daily_time'] ?? 0),
        'active_days' => intval($data['active_days'] ?? 0),
        'total_events' => intval($data['total_events'] ?? 0),
        'activity_rate' => $days > 0 ? ($data['active_days'] ?? 0) / $days : 0
    ];
}

private static function getRecentSessions($user_id, $days) {
    // Simuler des sessions pour l'instant
    // À remplacer par une vraie logique de sessions
    return [
        ['completed' => true, 'duration' => 45],
        ['completed' => true, 'duration' => 60],
        ['completed' => false, 'duration' => 15],
        ['completed' => true, 'duration' => 30],
    ];
}

     
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
}