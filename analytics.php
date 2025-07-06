<?php
/**
 * Page Analytics Admin
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les statistiques
global $wpdb;

$stats = [
    'total_users' => count_users()['total_users'],
    'active_users_30d' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}tmm_analytics WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    'total_events' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics"),
    'events_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics WHERE DATE(created_at) = CURDATE()"),
    'total_badges' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_user_badges"),
    'badges_this_week' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_user_badges WHERE earned_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'chatbot_messages' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_chatbot_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
];

// Événements les plus fréquents
$top_events = $wpdb->get_results("
    SELECT event_type, COUNT(*) as count 
    FROM {$wpdb->prefix}tmm_analytics 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY event_type 
    ORDER BY count DESC 
    LIMIT 10
");

// Utilisateurs les plus actifs
$top_users = $wpdb->get_results("
    SELECT u.display_name, COUNT(*) as activity_count
    FROM {$wpdb->prefix}tmm_analytics a
    JOIN {$wpdb->users} u ON a.user_id = u.ID
    WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY a.user_id
    ORDER BY activity_count DESC
    LIMIT 10
");

// Activité par jour (7 derniers jours)
$daily_activity = $wpdb->get_results("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM {$wpdb->prefix}tmm_analytics
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

// Badges les plus attribués
$top_badges = $wpdb->get_results("
    SELECT badge_type, COUNT(*) as count
    FROM {$wpdb->prefix}tmm_user_badges
    GROUP BY badge_type
    ORDER BY count DESC
    LIMIT 10
");
?>

<div class="wrap tmm-analytics-page">
    <h1>
        <span class="dashicons dashicons-chart-line"></span>
        TMM Dashboard - Analytics
    </h1>
    
    <div class="tmm-analytics-header">
        <p>Analyse détaillée de l'utilisation et de l'engagement des utilisateurs.</p>
        
        <div class="tmm-analytics-filters">
            <select id="analytics-period">
                <option value="7">7 derniers jours</option>
                <option value="30" selected>30 derniers jours</option>
                <option value="90">90 derniers jours</option>
                <option value="365">1 an</option>
            </select>
            
            <button type="button" class="button" id="export-analytics">
                <span class="dashicons dashicons-download"></span>
                Exporter
            </button>
            
            <button type="button" class="button button-primary" id="refresh-analytics">
                <span class="dashicons dashicons-update"></span>
                Actualiser
            </button>
        </div>
    </div>

    <!-- Métriques principales -->
    <div class="tmm-analytics-overview">
        <div class="tmm-metric-card">
            <div class="tmm-metric-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="tmm-metric-content">
                <h3><?php echo number_format($stats['total_users']); ?></h3>
                <p>Utilisateurs total</p>
                <span class="tmm-metric-change">
                    <?php echo number_format($stats['active_users_30d']); ?> actifs (30j)
                </span>
            </div>
        </div>
        
        <div class="tmm-metric-card">
            <div class="tmm-metric-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="tmm-metric-content">
                <h3><?php echo number_format($stats['total_events']); ?></h3>
                <p>Événements total</p>
                <span class="tmm-metric-change">
                    +<?php echo number_format($stats['events_today']); ?> aujourd'hui
                </span>
            </div>
        </div>
        
        <div class="tmm-metric-card">
            <div class="tmm-metric-icon">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <div class="tmm-metric-content">
                <h3><?php echo number_format($stats['total_badges']); ?></h3>
                <p>Badges attribués</p>
                <span class="tmm-metric-change">
                    +<?php echo number_format($stats['badges_this_week']); ?> cette semaine
                </span>
            </div>
        </div>
        
        <div class="tmm-metric-card">
            <div class="tmm-metric-icon">
                <span class="dashicons dashicons-format-chat"></span>
            </div>
            <div class="tmm-metric-content">
                <h3><?php echo number_format($stats['chatbot_messages']); ?></h3>
                <p>Messages chatbot</p>
                <span class="tmm-metric-change">30 derniers jours</span>
            </div>
        </div>
    </div>

    <!-- Graphiques principaux -->
    <div class="tmm-analytics-charts">
        <div class="tmm-chart-container">
            <div class="tmm-chart-header">
                <h3>Activité quotidienne</h3>
                <div class="tmm-chart-controls">
                    <select id="chart-type">
                        <option value="events">Événements</option>
                        <option value="users">Utilisateurs actifs</option>
                        <option value="badges">Nouveaux badges</option>
                    </select>
                </div>
            </div>
            <div class="tmm-chart-body">
                <canvas id="daily-activity-chart" width="800" height="300"></canvas>
            </div>
        </div>
        
        <div class="tmm-chart-container">
            <div class="tmm-chart-header">
                <h3>Répartition des événements</h3>
            </div>
            <div class="tmm-chart-body">
                <canvas id="events-pie-chart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Tableaux de données -->
    <div class="tmm-analytics-tables">
        <div class="tmm-table-section">
            <h3>Événements les plus fréquents (30 jours)</h3>
            <div class="tmm-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Type d'événement</th>
                            <th>Nombre</th>
                            <th>Pourcentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_events)) : 
                            $total_events_30d = array_sum(array_column($top_events, 'count'));
                            foreach ($top_events as $event) : 
                                $percentage = $total_events_30d > 0 ? ($event->count / $total_events_30d) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($event->event_type); ?></strong>
                                <div class="tmm-event-description">
                                    <?php echo esc_html(self::getEventDescription($event->event_type)); ?>
                                </div>
                            </td>
                            <td><?php echo number_format($event->count); ?></td>
                            <td>
                                <div class="tmm-percentage-bar">
                                    <div class="tmm-percentage-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    <span><?php echo number_format($percentage, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        else : ?>
                        <tr>
                            <td colspan="3">Aucune donnée disponible</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="tmm-table-section">
            <h3>Utilisateurs les plus actifs (30 jours)</h3>
            <div class="tmm-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Activités</th>
                            <th>Niveau d'engagement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_users)) : 
                            foreach ($top_users as $index => $user) : 
                                $engagement_level = self::getEngagementLevel($user->activity_count);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <?php if ($index < 3) : ?>
                                <span class="tmm-top-badge tmm-top-<?php echo $index + 1; ?>">
                                    #<?php echo $index + 1; ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($user->activity_count); ?></td>
                            <td>
                                <span class="tmm-engagement-badge tmm-engagement-<?php echo $engagement_level['class']; ?>">
                                    <?php echo esc_html($engagement_level['label']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; 
                        else : ?>
                        <tr>
                            <td colspan="3">Aucune donnée disponible</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="tmm-table-section">
            <h3>Badges les plus attribués</h3>
            <div class="tmm-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Badge</th>
                            <th>Nombre d'attributions</th>
                            <th>Popularité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_badges)) : 
                            $max_badges = max(array_column($top_badges, 'count'));
                            foreach ($top_badges as $badge) : 
                                $badge_info = TMMDashboardBadges::getBadgeInfo($badge->badge_type);
                                $popularity = $max_badges > 0 ? ($badge->count / $max_badges) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="tmm-badge-info">
                                    <i class="fas <?php echo esc_attr($badge_info['icon'] ?? 'fa-award'); ?>" 
                                       style="color: <?php echo esc_attr($badge_info['color'] ?? '#gold'); ?>"></i>
                                    <strong><?php echo esc_html($badge_info['name'] ?? $badge->badge_type); ?></strong>
                                    <div class="tmm-badge-description">
                                        <?php echo esc_html($badge_info['description'] ?? ''); ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo number_format($badge->count); ?></td>
                            <td>
                                <div class="tmm-popularity-bar">
                                    <div class="tmm-popularity-fill" style="width: <?php echo $popularity; ?>%"></div>
                                    <span><?php echo number_format($popularity, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; 
                        else : ?>
                        <tr>
                            <td colspan="3">Aucun badge attribué</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Insights et recommandations -->
    <div class="tmm-analytics-insights">
        <h3>Insights et recommandations</h3>
        
        <div class="tmm-insights-grid">
            <div class="tmm-insight-card tmm-insight-success">
                <div class="tmm-insight-icon">
                    <span class="dashicons dashicons-thumbs-up"></span>
                </div>
                <div class="tmm-insight-content">
                    <h4>Engagement élevé</h4>
                    <p><?php echo number_format($stats['active_users_30d']); ?> utilisateurs actifs sur 30 jours représentent un bon taux d'engagement.</p>
                </div>
            </div>
            
            <div class="tmm-insight-card tmm-insight-info">
                <div class="tmm-insight-icon">
                    <span class="dashicons dashicons-lightbulb"></span>
                </div>
                <div class="tmm-insight-content">
                    <h4>Optimisation possible</h4>
                    <p>Analysez les heures de pointe pour optimiser l'envoi de notifications et de contenu.</p>
                </div>
            </div>
            
            <div class="tmm-insight-card tmm-insight-warning">
                <div class="tmm-insight-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="tmm-insight-content">
                    <h4>Points d'attention</h4>
                    <p>Surveillez les utilisateurs inactifs depuis plus de 14 jours pour des actions de réengagement.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tmm-analytics-page {
    max-width: 1200px;
}

.tmm-analytics-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007cba;
}

.tmm-analytics-filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.tmm-analytics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.tmm-metric-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.tmm-metric-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #007cba;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.tmm-metric-content h3 {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
    color: #1d2327;
}

.tmm-metric-content p {
    margin: 5px 0;
    color: #646970;
    font-weight: 600;
}

.tmm-metric-change {
    font-size: 12px;
    color: #0073aa;
    background: #e7f3ff;
    padding: 2px 8px;
    border-radius: 12px;
}

.tmm-analytics-charts {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.tmm-chart-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tmm-chart-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tmm-chart-header h3 {
    margin: 0;
    color: #1d2327;
}

.tmm-chart-body {
    padding: 20px;
}

.tmm-analytics-tables {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.tmm-table-section {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.tmm-table-section h3 {
    margin: 0;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
    color: #1d2327;
}

.tmm-table-container {
    max-height: 400px;
    overflow-y: auto;
}

.tmm-event-description,
.tmm-badge-description {
    font-size: 12px;
    color: #646970;
    margin-top: 4px;
}

.tmm-percentage-bar,
.tmm-popularity-bar {
    position: relative;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.tmm-percentage-fill,
.tmm-popularity-fill {
    height: 100%;
    background: linear-gradient(90deg, #007cba, #005a87);
    transition: width 0.3s ease;
}

.tmm-percentage-bar span,
.tmm-popularity-bar span {
    position: absolute;
    top: 50%;
    left: 8px;
    transform: translateY(-50%);
    font-size: 11px;
    font-weight: bold;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.tmm-top-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: bold;
    margin-left: 8px;
}

.tmm-top-1 { background: #ffd700; color: #b8860b; }
.tmm-top-2 { background: #c0c0c0; color: #666; }
.tmm-top-3 { background: #cd7f32; color: #8b4513; }

.tmm-engagement-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
}

.tmm-engagement-high { background: #d4edda; color: #155724; }
.tmm-engagement-medium { background: #fff3cd; color: #856404; }
.tmm-engagement-low { background: #f8d7da; color: #721c24; }

.tmm-badge-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tmm-badge-info i {
    font-size: 18px;
}

.tmm-analytics-insights {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tmm-insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.tmm-insight-card {
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    border-left: 4px solid;
}

.tmm-insight-success {
    background: #d4edda;
    border-left-color: #28a745;
}

.tmm-insight-info {
    background: #d1ecf1;
    border-left-color: #17a2b8;
}

.tmm-insight-warning {
    background: #fff3cd;
    border-left-color: #ffc107;
}

.tmm-insight-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.tmm-insight-success .tmm-insight-icon { background: #28a745; color: white; }
.tmm-insight-info .tmm-insight-icon { background: #17a2b8; color: white; }
.tmm-insight-warning .tmm-insight-icon { background: #ffc107; color: #212529; }

.tmm-insight-content h4 {
    margin: 0 0 8px 0;
    color: #1d2327;
}

.tmm-insight-content p {
    margin: 0;
    color: #646970;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .tmm-analytics-charts {
        grid-template-columns: 1fr;
    }
    
    .tmm-analytics-tables {
        grid-template-columns: 1fr;
    }
    
    .tmm-analytics-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Données pour les graphiques
    var dailyActivityData = {
        labels: [<?php 
            $labels = [];
            foreach ($daily_activity as $day) {
                $labels[] = "'" . date('j M', strtotime($day->date)) . "'";
            }
            echo implode(',', $labels);
        ?>],
        datasets: [{
            label: 'Activités',
            data: [<?php echo implode(',', array_column($daily_activity, 'count')); ?>],
            backgroundColor: 'rgba(0, 124, 186, 0.8)',
            borderColor: 'rgba(0, 124, 186, 1)',
            borderWidth: 1
        }]
    };
    
    var eventsData = {
        labels: [<?php 
            $labels = [];
            foreach (array_slice($top_events, 0, 6) as $event) {
                $labels[] = "'" . $event->event_type . "'";
            }
            echo implode(',', $labels);
        ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column(array_slice($top_events, 0, 6), 'count')); ?>],
            backgroundColor: [
                '#007cba', '#0073aa', '#005a87', '#32373c', 
                '#40464d', '#50575e'
            ]
        }]
    };
    
    // Graphique d'activité quotidienne
    var dailyChart = new Chart(document.getElementById('daily-activity-chart'), {
        type: 'line',
        data: dailyActivityData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Graphique en secteurs
    var pieChart = new Chart(document.getElementById('events-pie-chart'), {
        type: 'doughnut',
        data: eventsData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Actualisation des données
    $('#refresh-analytics').click(function() {
        location.reload();
    });
    
    // Export des données
    $('#export-analytics').click(function() {
        // Implémentation de l'export
        alert('Fonctionnalité d\'export en cours de développement');
    });
});
</script>

<?php
// Fonctions utilitaires pour cette page
class TMMAnalyticsHelpers {
    public static function getEventDescription($event_type) {
        $descriptions = [
            'login' => 'Connexion utilisateur',
            'course_started' => 'Cours démarré',
            'course_completed' => 'Cours terminé',
            'lesson_completed' => 'Leçon terminée',
            'quiz_completed' => 'Quiz complété',
            'badge_earned' => 'Badge obtenu',
            'certificate_generated' => 'Certificat généré',
            'profile_update' => 'Profil mis à jour',
            'password_change' => 'Mot de passe changé',
            'week_progress' => 'Progression hebdomadaire'
        ];
        
        return $descriptions[$event_type] ?? $event_type;
    }
    
    public static function getEngagementLevel($activity_count) {
        if ($activity_count >= 50) {
            return ['class' => 'high', 'label' => 'Très actif'];
        } elseif ($activity_count >= 20) {
            return ['class' => 'medium', 'label' => 'Actif'];
        } else {
            return ['class' => 'low', 'label' => 'Peu actif'];
        }
    }
}
?>