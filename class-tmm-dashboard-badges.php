<?php
/**
 * Classe de gestion des badges utilisateur
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardBadges {
    
    /**
     * Types de badges disponibles
     */
    private static $badge_types = [
        'absent' => [
            'name' => 'Absent',
            'description' => 'Inactif depuis plus de 14 jours',
            'icon' => 'fa-ghost',
            'color' => '#777777',
            'requirements' => ['days_inactive' => 14]
        ],
        'curieux' => [
            'name' => 'Curieux',
            'description' => 'Première connexion et découverte de la plateforme',
            'icon' => 'fa-search',
            'color' => '#999999',
            'requirements' => ['min_activity' => 1]
        ],
        'explorateur' => [
            'name' => 'Explorateur',
            'description' => 'A terminé au moins 1 cours',
            'icon' => 'fa-compass',
            'color' => '#f7971e',
            'requirements' => ['completed_courses' => 1]
        ],
        'assidu' => [
            'name' => 'Assidu',
            'description' => 'Connecté régulièrement et actif sur la plateforme',
            'icon' => 'fa-calendar-check',
            'color' => '#f4c430',
            'requirements' => ['recent_activity' => 10, 'streak_days' => 7]
        ],
        'achiever' => [
            'name' => 'Achiever',
            'description' => 'A terminé 3 cours ou plus',
            'icon' => 'fa-trophy',
            'color' => '#1ed1c1',
            'requirements' => ['completed_courses' => 3]
        ],
        'mentor' => [
            'name' => 'Mentor',
            'description' => 'Expert reconnu avec un parcours complet terminé',
            'icon' => 'fa-graduation-cap',
            'color' => '#9b59b6',
            'requirements' => ['parcours_complet' => true, 'completed_courses' => 5]
        ],
        'champion' => [
            'name' => 'Champion',
            'description' => 'Top 10% des apprenants les plus actifs',
            'icon' => 'fa-crown',
            'color' => '#ffd700',
            'requirements' => ['top_learner' => true]
        ],
        'innovateur' => [
            'name' => 'Innovateur',
            'description' => 'Participe activement aux discussions et partage ses connaissances',
            'icon' => 'fa-lightbulb',
            'color' => '#e91e63',
            'requirements' => ['forum_posts' => 20, 'helpful_votes' => 10]
        ],
        'perfectionniste' => [
            'name' => 'Perfectionniste',
            'description' => 'Obtient 100% dans tous les quiz complétés',
            'icon' => 'fa-star',
            'color' => '#ff5722',
            'requirements' => ['perfect_quizzes' => 5]
        ],
        'speedrunner' => [
            'name' => 'Speedrunner',
            'description' => 'Termine les cours 50% plus vite que la moyenne',
            'icon' => 'fa-rocket',
            'color' => '#00bcd4',
            'requirements' => ['fast_completion' => true]
        ]
    ];

    /**
     * Mettre à jour le statut des badges d'un utilisateur
     */
    public static function updateBadgeStatus($user_id) {
        try {
            $user_stats = self::getUserStats($user_id);
            $current_badge = self::determineBadge($user_stats);
            
            // Sauvegarder le badge actuel
            update_user_meta($user_id, 'tmm_badge_status', $current_badge);
            
            // Vérifier et attribuer les badges spéciaux
            self::checkSpecialBadges($user_id, $user_stats);
            
            // Enregistrer l'événement (avec vérification de sécurité)
            if (class_exists('TMMDashboardAnalytics') && method_exists('TMMDashboardAnalytics', 'trackEvent')) {
                try {
                    TMMDashboardAnalytics::trackEvent($user_id, 'badge_update', [
                        'badge' => $current_badge,
                        'stats' => $user_stats
                    ]);
                } catch (Exception $e) {
                    // Log l'erreur mais ne pas faire planter l'application
                    error_log('TMM Dashboard Badge Analytics: ' . $e->getMessage());
                }
            }
            
            return $current_badge;
            
        } catch (Exception $e) {
            // En cas d'erreur, retourner un badge par défaut
            error_log('TMM Dashboard Badge Error: ' . $e->getMessage());
            return 'curieux';
        }
    }

    /**
     * Obtenir les statistiques de l'utilisateur
     */
    public static function getUserStats($user_id) {
        // Activité récente (30 derniers jours)
        $recent_activity = self::getRecentActivityCount($user_id, 30);
        
        // Cours terminés
        $completed_courses = self::getCompletedCoursesCount($user_id);
        
        // Parcours complet
        $parcours_complet = get_user_meta($user_id, 'tmm_parcours_complet', true);
        
        // Dernière activité
        $last_activity = self::getLastActivityTimestamp($user_id);
        $days_inactive = $last_activity ? floor((current_time('timestamp') - $last_activity) / DAY_IN_SECONDS) : 999;
        
        // Série d'apprentissage - utilise notre méthode locale
        $learning_streak = self::calculateLearningStreak($user_id);
        
        // Statistiques des quiz
        $quiz_stats = self::getQuizStats($user_id);
        
        // Participation forum (si bbPress est actif)
        $forum_stats = self::getForumStats($user_id);
        
        // Vitesse de complétion
        $completion_speed = self::getCompletionSpeed($user_id);
        
        // Points totaux
        $total_points = get_user_meta($user_id, 'tmm_total_points', true) ?: 0;
        
        return [
            'user_id' => $user_id,
            'recent_activity' => $recent_activity,
            'completed_courses' => $completed_courses,
            'parcours_complet' => $parcours_complet,
            'days_inactive' => $days_inactive,
            'learning_streak' => $learning_streak,
            'perfect_quizzes' => $quiz_stats['perfect_count'],
            'total_quizzes' => $quiz_stats['total_count'],
            'average_quiz_score' => $quiz_stats['average_score'],
            'forum_posts' => $forum_stats['posts'],
            'helpful_votes' => $forum_stats['votes'],
            'completion_speed' => $completion_speed,
            'total_time_spent' => self::getTotalTimeSpent($user_id),
            'certificates_earned' => self::getUserCertificatesCount($user_id),
            'points' => self::getUserPoints($user_id),
            'total_points' => intval($total_points),
            // Nouvelles statistiques
            'current_level' => self::getUserLevel($completed_courses),
            'progress_to_next_badge' => self::getProgressToNextBadge($user_id),
            'last_badge_earned' => self::getLastBadgeEarned($user_id),
            'weekly_activity' => self::getWeeklyActivityStats($user_id),
            'performance_trend' => self::getPerformanceTrend($user_id),
            'badges_earned' => count(get_user_meta($user_id, 'tmm_earned_badges', true) ?: [])
        ];
    }

    /**
     * Calculer la série d'apprentissage (jours consécutifs)
     */
    private static function calculateLearningStreak($user_id) {
        global $wpdb;
        
        // Vérifier d'abord la table analytics si elle existe
        $analytics_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_analytics'");
        if ($analytics_table_exists) {
            $streak = 0;
            $current_date = current_time('Y-m-d');
            
            // Vérifier jour par jour en remontant
            for ($i = 0; $i < 365; $i++) {
                $check_date = date('Y-m-d', strtotime("-{$i} days", strtotime($current_date)));
                
                $activity = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
                     WHERE user_id = %d 
                     AND DATE(created_at) = %s
                     AND event_type IN ('lesson_completed', 'quiz_completed', 'course_progress')",
                    $user_id, $check_date
                ));
                
                if (intval($activity) > 0) {
                    $streak++;
                } else if ($i > 0) { // Pas d'activité et ce n'est pas aujourd'hui
                    break;
                }
            }
            
            return $streak;
        }
        
        // Fallback sur learnpress_user_items
        $activities = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(GREATEST(
                COALESCE(start_time, '1970-01-01'),
                COALESCE(end_time, '1970-01-01')
            )) as activity_date
            FROM {$wpdb->prefix}learnpress_user_items
            WHERE user_id = %d 
            AND GREATEST(
                COALESCE(start_time, '1970-01-01'),
                COALESCE(end_time, '1970-01-01')
            ) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            AND GREATEST(
                COALESCE(start_time, '1970-01-01'),
                COALESCE(end_time, '1970-01-01')
            ) != '1970-01-01'
            ORDER BY activity_date DESC",
            $user_id
        ));
        
        if (empty($activities)) {
            return 0;
        }
        
        $streak = 1;
        $current_date = date('Y-m-d');
        
        // Si la dernière activité n'est pas aujourd'hui ou hier, pas de streak
        if ($activities[0] < date('Y-m-d', strtotime('-1 day'))) {
            return 0;
        }
        
        // Compter les jours consécutifs
        for ($i = 1; $i < count($activities); $i++) {
            $expected_date = date('Y-m-d', strtotime($activities[$i-1] . ' -1 day'));
            
            if ($activities[$i] === $expected_date) {
                $streak++;
            } else {
                break;
            }
        }
        
        return $streak;
    }

    /**
     * Obtenir le nombre de certificats de l'utilisateur
     */
    private static function getUserCertificatesCount($user_id) {
        // Calculer localement pour éviter les appels aux méthodes privées
        global $wpdb;
        
        // Vérifier si la table des certificats existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_certificates'");
        
        if ($table_exists) {
            $certificates_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_certificates
                WHERE user_id = %d",
                $user_id
            ));
            
            return intval($certificates_count);
        }
        
        // Fallback : compter les parcours terminés
        $parcours_complet = get_user_meta($user_id, 'tmm_parcours_complet', true);
        return $parcours_complet ? 1 : 0;
    }

    /**
     * Obtenir les points de l'utilisateur
     */
    private static function getUserPoints($user_id) {
        // Récupérer depuis les meta utilisateur (solution sûre)
        $points = get_user_meta($user_id, 'tmm_user_points', true);
        
        // Si pas de points enregistrés, calculer basiquement
        if (!$points) {
            $completed_courses = self::getCompletedCoursesCount($user_id);
            $points = $completed_courses * 100; // 100 points par cours terminé
            
            // Sauvegarder pour la prochaine fois
            update_user_meta($user_id, 'tmm_user_points', $points);
        }
        
        return intval($points);
    }

    /**
     * Déterminer le badge principal
     */
    private static function determineBadge($stats) {
        // Vérifier l'inactivité en premier
        if ($stats['days_inactive'] > 14) {
            return 'absent';
        }
        
        // Vérifier le parcours complet
        if ($stats['parcours_complet']) {
            return 'mentor';
        }
        
        // Vérifier les autres badges dans l'ordre de priorité
        if ($stats['completed_courses'] >= 3) {
            return 'achiever';
        }
        
        if ($stats['recent_activity'] >= 10 && $stats['learning_streak'] >= 7) {
            return 'assidu';
        }
        
        if ($stats['completed_courses'] >= 1) {
            return 'explorateur';
        }
        
        if ($stats['recent_activity'] >= 1) {
            return 'curieux';
        }
        
        return 'absent';
    }

    /**
     * Vérifier et attribuer les badges spéciaux
     */
    private static function checkSpecialBadges($user_id, $stats) {
        global $wpdb;
        
        $earned_badges = get_user_meta($user_id, 'tmm_earned_badges', true) ?: [];
        $new_badges = [];
        
        // Badge Champion (top 10%)
        if (self::isTopLearner($user_id)) {
            if (!in_array('champion', $earned_badges)) {
                $earned_badges[] = 'champion';
                $new_badges[] = 'champion';
            }
        }
        
        // Badge Innovateur
        if ($stats['forum_posts'] >= 20 && $stats['helpful_votes'] >= 10) {
            if (!in_array('innovateur', $earned_badges)) {
                $earned_badges[] = 'innovateur';
                $new_badges[] = 'innovateur';
            }
        }
        
        // Badge Perfectionniste
        if ($stats['perfect_quizzes'] >= 5) {
            if (!in_array('perfectionniste', $earned_badges)) {
                $earned_badges[] = 'perfectionniste';
                $new_badges[] = 'perfectionniste';
            }
        }
        
        // Badge Speedrunner
        if ($stats['completion_speed'] === 'fast') {
            if (!in_array('speedrunner', $earned_badges)) {
                $earned_badges[] = 'speedrunner';
                $new_badges[] = 'speedrunner';
            }
        }
        
        // Sauvegarder les badges gagnés
        if (!empty($new_badges)) {
            update_user_meta($user_id, 'tmm_earned_badges', $earned_badges);
            
            // Vérifier si la table des badges existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_user_badges'");
            
            if ($table_exists) {
                // Enregistrer dans la table des badges
                foreach ($new_badges as $badge) {
                    $wpdb->insert(
                        $wpdb->prefix . 'tmm_user_badges',
                        [
                            'user_id' => $user_id,
                            'badge_type' => $badge,
                            'earned_date' => current_time('mysql')
                        ]
                    );
                    
                    // Notification de nouveau badge
                    self::notifyNewBadge($user_id, $badge);
                }
            }
        }
    }

    /**
     * Obtenir le nombre d'activités récentes
     */
    private static function getRecentActivityCount($user_id, $days = 30) {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND GREATEST(
                 COALESCE(start_time, '0000-00-00'),
                 COALESCE(end_time, '0000-00-00')
             ) >= %s",
            $user_id, $since
        ));
    }

    /**
     * Obtenir le score d'activité récente
     */
    private static function getRecentActivityScore($user_id) {
        global $wpdb;
        
        // Utiliser d'abord la table analytics si elle existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_analytics'");
        if ($table_exists) {
            // Compter les activités des 7 derniers jours
            $activity_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
                 WHERE user_id = %d 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                 AND event_type IN ('lesson_completed', 'quiz_completed', 'course_started', 'course_completed')",
                $user_id
            ));
            
            return intval($activity_count);
        }
        
        // Fallback sur la méthode existante getRecentActivityCount
        return self::getRecentActivityCount($user_id, 7);
    }

    /**
     * Obtenir le nombre de cours terminés
     */
    private static function getCompletedCoursesCount($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_course'
             AND status IN ('completed', 'finished')",
            $user_id
        ));
    }

    /**
     * Obtenir le timestamp de la dernière activité
     */
    private static function getLastActivityTimestamp($user_id) {
        global $wpdb;
        
        $last_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(GREATEST(
                COALESCE(start_time, '0000-00-00'),
                COALESCE(end_time, '0000-00-00')
             )) FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d",
            $user_id
        ));
        
        return $last_activity ? strtotime($last_activity) : 0;
    }

    /**
     * Obtenir les statistiques des quiz
     */
    private static function getQuizStats($user_id) {
        global $wpdb;
        
        $quiz_results = $wpdb->get_results($wpdb->prepare(
            "SELECT graduation FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_quiz'
             AND status = 'completed'",
            $user_id
        ));
        
        $perfect_count = 0;
        $total_score = 0;
        $total_count = count($quiz_results);
        
        foreach ($quiz_results as $result) {
            $score = floatval($result->graduation);
            $total_score += $score;
            if ($score >= 100) {
                $perfect_count++;
            }
        }
        
        return [
            'total_count' => $total_count,
            'perfect_count' => $perfect_count,
            'average_score' => $total_count > 0 ? round($total_score / $total_count, 2) : 0
        ];
    }

    /**
     * Obtenir les statistiques du forum
     */
    private static function getForumStats($user_id) {
        $posts = 0;
        $votes = 0;
        
        // Si bbPress est actif
        if (function_exists('bbp_get_user_topic_count')) {
            $posts = bbp_get_user_topic_count($user_id) + bbp_get_user_reply_count($user_id);
        }
        
        // Récupérer les votes utiles (dépend du système de vote utilisé)
        $votes = get_user_meta($user_id, 'helpful_votes_received', true) ?: 0;
        
        return [
            'posts' => $posts,
            'votes' => $votes
        ];
    }

    /**
     * Obtenir la vitesse de complétion
     */
    private static function getCompletionSpeed($user_id) {
        global $wpdb;
        
        // Récupérer les temps de complétion des cours
        $completion_times = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                TIMESTAMPDIFF(HOUR, start_time, end_time) as hours_taken
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND item_type = 'lp_course'
             AND status = 'completed'
             AND start_time IS NOT NULL 
             AND end_time IS NOT NULL",
            $user_id
        ));
        
        if (empty($completion_times)) {
            return 'normal';
        }
        
        // Calculer la moyenne de l'utilisateur
        $user_average = 0;
        foreach ($completion_times as $time) {
            $user_average += $time->hours_taken;
        }
        $user_average = $user_average / count($completion_times);
        
        // Comparer avec la moyenne globale
        $global_average = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, start_time, end_time))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE item_type = 'lp_course'
             AND status = 'completed'
             AND start_time IS NOT NULL 
             AND end_time IS NOT NULL"
        );
        
        if ($user_average < ($global_average * 0.5)) {
            return 'fast';
        } elseif ($user_average > ($global_average * 1.5)) {
            return 'slow';
        }
        
        return 'normal';
    }

    /**
     * Obtenir le temps total passé
     */
    private static function getTotalTimeSpent($user_id) {
        global $wpdb;
        
        $total_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(HOUR, start_time, end_time))
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND start_time IS NOT NULL 
             AND end_time IS NOT NULL",
            $user_id
        ));
        
        return intval($total_hours);
    }

    /**
     * Vérifier si l'utilisateur est dans le top 10%
     */
    private static function isTopLearner($user_id) {
        global $wpdb;
        
        // Calculer le score de l'utilisateur
        $user_score = self::calculateUserScore($user_id);
        
        // Obtenir tous les scores
        $all_scores = $wpdb->get_col(
            "SELECT meta_value FROM {$wpdb->usermeta}
             WHERE meta_key = 'tmm_user_score'
             ORDER BY CAST(meta_value AS UNSIGNED) DESC"
        );
        
        if (empty($all_scores)) {
            return false;
        }
        
        // Trouver la position
        $position = array_search($user_score, $all_scores);
        $percentile = ($position / count($all_scores)) * 100;
        
        return $percentile <= 10;
    }

    /**
     * Calculer le score de l'utilisateur
     */
    private static function calculateUserScore($user_id) {
        $stats = self::getUserStats($user_id);
        
        $score = 0;
        $score += $stats['completed_courses'] * 100;
        $score += $stats['recent_activity'] * 10;
        $score += $stats['learning_streak'] * 20;
        $score += $stats['perfect_quizzes'] * 50;
        $score += $stats['certificates_earned'] * 200;
        $score += min($stats['forum_posts'], 50) * 5;
        $score += min($stats['helpful_votes'], 20) * 10;
        
        // Sauvegarder le score
        update_user_meta($user_id, 'tmm_user_score', $score);
        
        return $score;
    }

    /**
     * Déterminer le niveau de l'utilisateur
     */
    private static function getUserLevel($completed_courses) {
        if ($completed_courses >= 10) return 'expert';
        if ($completed_courses >= 5) return 'advanced';
        if ($completed_courses >= 3) return 'intermediate';
        if ($completed_courses >= 1) return 'beginner';
        return 'newcomer';
    }

    /**
     * Calculer la progression vers le prochain badge
     */
    private static function getProgressToNextBadge($user_id) {
        $current_badge = get_user_meta($user_id, 'tmm_badge_status', true) ?: 'curieux';
        
        // Utiliser le nombre de cours complétés
        $completed_courses = self::getCompletedCoursesCount($user_id);
        
        // Logique pour déterminer le prochain badge
        $badge_requirements = [
            'absent' => ['target' => 'curieux', 'requirement' => 1, 'current' => 0, 'type' => 'activity'],
            'curieux' => ['target' => 'explorateur', 'requirement' => 1, 'current' => $completed_courses, 'type' => 'courses'],
            'explorateur' => ['target' => 'achiever', 'requirement' => 3, 'current' => $completed_courses, 'type' => 'courses'],
            'achiever' => ['target' => 'mentor', 'requirement' => 5, 'current' => $completed_courses, 'type' => 'courses'],
            'mentor' => ['target' => 'champion', 'requirement' => 10, 'current' => $completed_courses, 'type' => 'courses']
        ];
        
        if (!isset($badge_requirements[$current_badge])) {
            return ['progress' => 100, 'next_badge' => 'none', 'requirement' => 0];
        }
        
        $next_badge_info = $badge_requirements[$current_badge];
        $progress = min(100, ($next_badge_info['current'] / $next_badge_info['requirement']) * 100);
        
        return [
            'progress' => round($progress),
            'next_badge' => $next_badge_info['target'],
            'requirement' => $next_badge_info['requirement'],
            'current' => $next_badge_info['current'],
            'remaining' => max(0, $next_badge_info['requirement'] - $next_badge_info['current'])
        ];
    }

    /**
     * Obtenir le dernier badge gagné
     */
    private static function getLastBadgeEarned($user_id) {
        global $wpdb;
        
        // Vérifier que la table badges existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_user_badges'");
        if (!$table_exists) {
            // Fallback sur les meta utilisateur
            $earned_badges = get_user_meta($user_id, 'tmm_earned_badges', true) ?: [];
            if (!empty($earned_badges)) {
                $last_badge = end($earned_badges);
                return [
                    'badge' => $last_badge,
                    'date' => null,
                    'time_ago' => 'récemment'
                ];
            }
            return null;
        }
        
        $last_badge = $wpdb->get_row($wpdb->prepare(
            "SELECT badge_type, earned_date 
             FROM {$wpdb->prefix}tmm_user_badges
             WHERE user_id = %d
             ORDER BY earned_date DESC
             LIMIT 1",
            $user_id
        ));
        
        if (!$last_badge) {
            return null;
        }
        
        return [
            'badge' => $last_badge->badge_type,
            'date' => $last_badge->earned_date,
            'time_ago' => human_time_diff(strtotime($last_badge->earned_date), current_time('timestamp'))
        ];
    }

    /**
     * Obtenir les statistiques d'activité hebdomadaire
     */
    private static function getWeeklyActivityStats($user_id) {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_analytics'");
        if (!$table_exists) {
            return ['this_week' => 0, 'last_week' => 0, 'trend' => 'stable'];
        }
        
        // Activité de cette semaine
        $this_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND created_at >= DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY)
             AND event_type IN ('lesson_completed', 'quiz_completed')",
            $user_id
        ));
        
        // Activité de la semaine dernière
        $last_week = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY), INTERVAL 7 DAY)
             AND created_at < DATE_SUB(NOW(), INTERVAL WEEKDAY(NOW()) DAY)
             AND event_type IN ('lesson_completed', 'quiz_completed')",
            $user_id
        ));
        
        // Déterminer la tendance
        $trend = 'stable';
        if ($this_week > $last_week) {
            $trend = 'up';
        } elseif ($this_week < $last_week) {
            $trend = 'down';
        }
        
        return [
            'this_week' => intval($this_week),
            'last_week' => intval($last_week),
            'trend' => $trend,
            'change' => $this_week - $last_week
        ];
    }

    /**
     * Obtenir la tendance de performance
     */
    private static function getPerformanceTrend($user_id) {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}tmm_analytics'");
        if (!$table_exists) {
            // Fallback sur les stats de quiz existantes
            $quiz_stats = self::getQuizStats($user_id);
            return [
                'trend' => 'stable',
                'average_score' => $quiz_stats['average_score']
            ];
        }
        
        // Scores des quiz récents (30 derniers jours)
        $recent_scores = $wpdb->get_col($wpdb->prepare(
            "SELECT JSON_EXTRACT(event_data, '$.score')
             FROM {$wpdb->prefix}tmm_analytics
             WHERE user_id = %d 
             AND event_type = 'quiz_completed'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY created_at DESC
             LIMIT 10",
            $user_id
        ));
        
        if (empty($recent_scores)) {
            return ['trend' => 'no_data', 'average_score' => 0];
        }
        
        $recent_scores = array_map('intval', $recent_scores);
        $average_score = array_sum($recent_scores) / count($recent_scores);
        
        // Calculer la tendance
        $trend = 'stable';
        if (count($recent_scores) >= 3) {
            $first_half = array_slice($recent_scores, 0, ceil(count($recent_scores) / 2));
            $second_half = array_slice($recent_scores, floor(count($recent_scores) / 2));
            
            $first_avg = array_sum($first_half) / count($first_half);
            $second_avg = array_sum($second_half) / count($second_half);
            
            if ($second_avg > $first_avg + 5) {
                $trend = 'improving';
            } elseif ($second_avg < $first_avg - 5) {
                $trend = 'declining';
            }
        }
        
        return [
            'trend' => $trend,
            'average_score' => round($average_score, 1),
            'recent_scores' => $recent_scores
        ];
    }

    /**
     * Notifier un nouveau badge
     */
    private static function notifyNewBadge($user_id, $badge_type) {
        $badge_info = self::$badge_types[$badge_type] ?? null;
        if (!$badge_info) return;
        
        // Créer une notification
        $notification = [
            'user_id' => $user_id,
            'type' => 'new_badge',
            'badge' => $badge_type,
            'badge_name' => $badge_info['name'],
            'message' => sprintf(
                __('Félicitations ! Vous avez débloqué le badge "%s" : %s', 'tmm-dashboard'),
                $badge_info['name'],
                $badge_info['description']
            ),
            'date' => current_time('mysql'),
            'read' => false
        ];
        
        // Sauvegarder la notification
        $notifications = get_user_meta($user_id, 'tmm_notifications', true) ?: [];
        array_unshift($notifications, $notification);
        update_user_meta($user_id, 'tmm_notifications', array_slice($notifications, 0, 50));
        
        // Envoyer un email si activé
        if (get_user_meta($user_id, 'tmm_email_notifications', true)) {
            self::sendBadgeEmail($user_id, $badge_info);
        }
    }

    /**
     * Envoyer un email pour un nouveau badge
     */
    private static function sendBadgeEmail($user_id, $badge_info) {
        $user = get_user_by('id', $user_id);
        if (!$user) return;
        
        $subject = sprintf(
            __('Nouveau badge débloqué : %s', 'tmm-dashboard'),
            $badge_info['name']
        );
        
        $message = sprintf(
            __("Bonjour %s,\n\nFélicitations ! Vous avez débloqué un nouveau badge sur notre plateforme.\n\nBadge : %s\nDescription : %s\n\nContinuez votre excellent travail !\n\nCordialement,\nL'équipe %s", 'tmm-dashboard'),
            $user->display_name,
            $badge_info['name'],
            $badge_info['description'],
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Mettre à jour les statistiques utilisateur
     */
    public static function updateUserStats($user_id) {
        if (!$user_id) {
            return false;
        }
        
        try {
            $now = current_time('mysql');
            update_user_meta($user_id, 'last_login', $now);

            // Incrémenter le compteur de connexions du mois
            $current_month = date('Y-m');
            $last_month = get_user_meta($user_id, 'last_login_month', true);

            if ($last_month !== $current_month) {
                update_user_meta($user_id, 'last_login_month', $current_month);
                update_user_meta($user_id, 'monthly_login_count', 1);
                
                $months_active = (int) get_user_meta($user_id, 'active_months', true);
                update_user_meta($user_id, 'active_months', $months_active + 1);
            } else {
                $count = (int) get_user_meta($user_id, 'monthly_login_count', true);
                update_user_meta($user_id, 'monthly_login_count', $count + 1);
            }

            // Mettre à jour le compteur d'activités récentes
            $recent_activity = self::getRecentActivityCount($user_id);
            update_user_meta($user_id, 'tmm_recent_activity_count', $recent_activity);

            // Mettre à jour le compteur de cours terminés
            $completed_courses = self::getCompletedCoursesCount($user_id);
            update_user_meta($user_id, 'tmm_completed_courses_count', $completed_courses);
            
            // Mettre à jour les points totaux (sans appel récursif !)
            $learning_streak = self::calculateLearningStreak($user_id);
            $total_points = $completed_courses * 100 + $learning_streak * 10;
            update_user_meta($user_id, 'tmm_total_points', $total_points);

            // Vérifier les badges
            self::updateBadgeStatus($user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log('TMM Badge Stats Update Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir le rendu HTML des badges d'un utilisateur
     */
    public static function renderUserBadges($user_id) {
        $current_badge = get_user_meta($user_id, 'tmm_badge_status', true) ?: 'curieux';
        $earned_badges = get_user_meta($user_id, 'tmm_earned_badges', true) ?: [];
        $badge_info = self::$badge_types[$current_badge] ?? self::$badge_types['curieux'];
        
        ob_start();
        ?>
        <div class="ps-badges-container">
            <!-- Badge principal -->
            <div class="ps-main-badge-section">
                <h3><?php _e('Badge actuel', 'tmm-dashboard'); ?></h3>
                <div class="ps-badge-card ps-main-badge">
                    <div class="ps-user-badge ps-badge-<?php echo esc_attr($current_badge); ?>" style="border-color: <?php echo esc_attr($badge_info['color']); ?>; color: <?php echo esc_attr($badge_info['color']); ?>;">
                        <span class="ps-badge-ring"></span>
                        <i class="fas <?php echo esc_attr($badge_info['icon']); ?>"></i>
                        <span class="ps-badge-label"><?php echo esc_html($badge_info['name']); ?></span>
                    </div>
                    <p class="ps-badge-description">
                        <?php echo esc_html($badge_info['description']); ?>
                    </p>
                </div>
            </div>

            <!-- Badges spéciaux gagnés -->
            <?php if (!empty($earned_badges)) : ?>
            <div class="ps-earned-badges-section">
                <h3><?php _e('Badges débloqués', 'tmm-dashboard'); ?></h3>
                <div class="ps-badges-grid">
                    <?php foreach ($earned_badges as $badge) : 
                        if (!isset(self::$badge_types[$badge])) continue;
                        $earned_badge_info = self::$badge_types[$badge];
                    ?>
                    <div class="ps-badge-card ps-earned-badge" data-badge="<?php echo esc_attr($badge); ?>">
                        <div class="ps-badge-icon" style="color: <?php echo esc_attr($earned_badge_info['color']); ?>;">
                            <i class="fas <?php echo esc_attr($earned_badge_info['icon']); ?>"></i>
                        </div>
                        <h4><?php echo esc_html($earned_badge_info['name']); ?></h4>
                        <p><?php echo esc_html($earned_badge_info['description']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Badges à débloquer -->
            <div class="ps-locked-badges-section">
                <h3><?php _e('Badges à débloquer', 'tmm-dashboard'); ?></h3>
                <div class="ps-badges-grid">
                    <?php 
                    foreach (self::$badge_types as $badge_key => $badge_data) : 
                        if (in_array($badge_key, $earned_badges) || $badge_key === $current_badge) continue;
                    ?>
                    <div class="ps-badge-card ps-locked-badge" data-badge="<?php echo esc_attr($badge_key); ?>">
                        <div class="ps-badge-icon ps-badge-locked">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h4><?php echo esc_html($badge_data['name']); ?></h4>
                        <p class="ps-badge-hint">
                            <?php echo self::getBadgeHint($badge_key); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Progression vers le prochain badge -->
            <div class="ps-badge-progress-section">
                <h3><?php _e('Progression', 'tmm-dashboard'); ?></h3>
                <?php echo self::renderBadgeProgress($user_id); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtenir un indice pour débloquer un badge
     */
    private static function getBadgeHint($badge_key) {
        $hints = [
            'explorateur' => __('Terminez votre premier cours', 'tmm-dashboard'),
            'assidu' => __('Connectez-vous 7 jours consécutifs', 'tmm-dashboard'),
            'achiever' => __('Terminez 3 cours au total', 'tmm-dashboard'),
            'mentor' => __('Complétez un parcours entier', 'tmm-dashboard'),
            'champion' => __('Soyez dans le top 10% des apprenants', 'tmm-dashboard'),
            'innovateur' => __('Participez activement aux forums', 'tmm-dashboard'),
            'perfectionniste' => __('Obtenez 100% dans 5 quiz', 'tmm-dashboard'),
            'speedrunner' => __('Terminez les cours rapidement', 'tmm-dashboard')
        ];
        
        return $hints[$badge_key] ?? __('Continuez à progresser !', 'tmm-dashboard');
    }

    /**
     * Afficher la progression vers les badges
     */
    private static function renderBadgeProgress($user_id) {
        $stats = self::getUserStats($user_id);
        $progress_items = [];
        
        // Progression vers Explorateur
        if ($stats['completed_courses'] < 1) {
            $progress_items[] = [
                'badge' => 'explorateur',
                'label' => __('Vers le badge Explorateur', 'tmm-dashboard'),
                'current' => $stats['completed_courses'],
                'target' => 1,
                'unit' => __('cours terminé(s)', 'tmm-dashboard')
            ];
        }
        
        // Progression vers Achiever
        if ($stats['completed_courses'] < 3) {
            $progress_items[] = [
                'badge' => 'achiever',
                'label' => __('Vers le badge Achiever', 'tmm-dashboard'),
                'current' => $stats['completed_courses'],
                'target' => 3,
                'unit' => __('cours terminés', 'tmm-dashboard')
            ];
        }
        
        // Progression vers Assidu
        if ($stats['learning_streak'] < 7) {
            $progress_items[] = [
                'badge' => 'assidu',
                'label' => __('Vers le badge Assidu', 'tmm-dashboard'),
                'current' => $stats['learning_streak'],
                'target' => 7,
                'unit' => __('jours consécutifs', 'tmm-dashboard')
            ];
        }
        
        ob_start();
        ?>
        <div class="ps-progress-items">
            <?php foreach ($progress_items as $item) : 
                $percentage = min(100, ($item['current'] / $item['target']) * 100);
            ?>
            <div class="ps-progress-item">
                <div class="ps-progress-header">
                    <span class="ps-progress-label"><?php echo esc_html($item['label']); ?></span>
                    <span class="ps-progress-text">
                        <?php echo esc_html($item['current']); ?> / <?php echo esc_html($item['target']); ?> <?php echo esc_html($item['unit']); ?>
                    </span>
                </div>
                <div class="ps-progress-bar">
                    <div class="ps-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtenir le nombre de badges d'un utilisateur
     */
    public static function getUserBadgesCount($user_id) {
        $earned_badges = get_user_meta($user_id, 'tmm_earned_badges', true) ?: [];
        return count($earned_badges) + 1; // +1 pour le badge principal
    }

    /**
     * Obtenir les informations d'un badge
     */
    public static function getBadgeInfo($badge_key) {
        return self::$badge_types[$badge_key] ?? null;
    }

    /**
     * Obtenir tous les types de badges
     */
    public static function getAllBadgeTypes() {
        return self::$badge_types;
    }
}
