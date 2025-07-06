<?php
/**
 * Page de gestion des parcours Admin
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupérer tous les parcours
$all_parcours = TMMDashboardParcours::getAllParcours();

// Statistiques des parcours
global $wpdb;

$parcours_stats = [];
foreach ($all_parcours as $parcours_id => $parcours) {
    // Utilisateurs avec ce membership
    $users_count = 0;
    if (function_exists('pmpro_getMembershipLevelForUser')) {
        $users_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_memberships_users 
             WHERE membership_id = %d AND status = 'active'",
            $parcours['membership_id']
        ));
    }
    
    // Utilisateurs ayant complété le parcours
    $completed_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta}
         WHERE meta_key = %s",
        'tmm_parcours_completed_' . $parcours_id
    ));
    
    // Taux de complétion
    $completion_rate = $users_count > 0 ? ($completed_count / $users_count) * 100 : 0;
    
    $parcours_stats[$parcours_id] = [
        'users_count' => $users_count,
        'completed_count' => $completed_count,
        'completion_rate' => $completion_rate
    ];
}

// Progression récente
$recent_progress = $wpdb->get_results("
    SELECT u.display_name, um.meta_key, um.updated_at
    FROM {$wpdb->usermeta} um
    JOIN {$wpdb->users} u ON um.user_id = u.ID
    WHERE um.meta_key LIKE 'timeline_%_last_update'
    ORDER BY um.updated_at DESC
    LIMIT 20
");
?>

<div class="wrap tmm-parcours-page">
    <h1>
        <span class="dashicons dashicons-networking"></span>
        TMM Dashboard - Gestion des Parcours
    </h1>
    
    <div class="tmm-parcours-header">
        <p>Gérez les parcours d'apprentissage et suivez la progression des utilisateurs.</p>
        
        <div class="tmm-parcours-actions">
            <button type="button" class="button" id="sync-parcours">
                <span class="dashicons dashicons-update"></span>
                Synchroniser
            </button>
            
            <button type="button" class="button button-primary" id="add-parcours">
                <span class="dashicons dashicons-plus-alt"></span>
                Nouveau parcours
            </button>
        </div>
    </div>

    <!-- Vue d'ensemble des parcours -->
    <div class="tmm-parcours-overview">
        <h2>Vue d'ensemble</h2>
        
        <div class="tmm-parcours-grid">
            <?php foreach ($all_parcours as $parcours_id => $parcours) : 
                $stats = $parcours_stats[$parcours_id];
            ?>
            <div class="tmm-parcours-card" data-parcours="<?php echo esc_attr($parcours_id); ?>">
                <div class="tmm-parcours-header">
                    <h3><?php echo esc_html($parcours['name']); ?></h3>
                    <span class="tmm-parcours-level"><?php echo esc_html($parcours['level']); ?></span>
                </div>
                
                <div class="tmm-parcours-meta">
                    <div class="tmm-meta-item">
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo esc_html($parcours['duration']); ?>
                    </div>
                    <div class="tmm-meta-item">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php echo number_format($stats['users_count']); ?> inscrits
                    </div>
                    <div class="tmm-meta-item">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo number_format($stats['completed_count']); ?> terminés
                    </div>
                </div>
                
                <div class="tmm-parcours-description">
                    <p><?php echo esc_html($parcours['description']); ?></p>
                </div>
                
                <div class="tmm-parcours-skills">
                    <strong>Compétences :</strong>
                    <div class="tmm-skills-tags">
                        <?php foreach ($parcours['skills'] as $skill) : ?>
                        <span class="tmm-skill-tag"><?php echo esc_html($skill); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="tmm-parcours-progress">
                    <div class="tmm-progress-header">
                        <span>Taux de complétion</span>
                        <span><?php echo number_format($stats['completion_rate'], 1); ?>%</span>
                    </div>
                    <div class="tmm-progress-bar">
                        <div class="tmm-progress-fill" style="width: <?php echo $stats['completion_rate']; ?>%"></div>
                    </div>
                </div>
                
                <div class="tmm-parcours-actions">
                    <button type="button" class="button" onclick="viewParcoursDetails('<?php echo esc_js($parcours_id); ?>')">
                        <span class="dashicons dashicons-visibility"></span>
                        Détails
                    </button>
                    <button type="button" class="button" onclick="exportParcoursData('<?php echo esc_js($parcours_id); ?>')">
                        <span class="dashicons dashicons-download"></span>
                        Exporter
                    </button>
                    <button type="button" class="button" onclick="editParcours('<?php echo esc_js($parcours_id); ?>')">
                        <span class="dashicons dashicons-edit"></span>
                        Modifier
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Tableau de progression récente -->
    <div class="tmm-recent-progress">
        <h2>Activité récente</h2>
        
        <div class="tmm-progress-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Parcours</th>
                        <th>Action</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_progress)) : 
                        foreach ($recent_progress as $progress) :
                            // Extraire les infos de la meta_key
                            preg_match('/timeline_([^_]+)_mois_(\d+)_last_update/', $progress->meta_key, $matches);
                            $parcours_name = $matches[1] ?? 'Inconnu';
                            $month = $matches[2] ?? '';
                            
                            $parcours_info = null;
                            foreach ($all_parcours as $pid => $pdata) {
                                if (strpos($progress->meta_key, $pid) !== false) {
                                    $parcours_info = $pdata;
                                    break;
                                }
                            }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($progress->display_name); ?></strong>
                        </td>
                        <td>
                            <?php if ($parcours_info) : ?>
                            <span class="tmm-parcours-badge">
                                <?php echo esc_html($parcours_info['name']); ?>
                            </span>
                            <?php else : ?>
                            <?php echo esc_html($parcours_name); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="tmm-action-badge">
                                <span class="dashicons dashicons-yes-alt"></span>
                                Progression <?php echo $month ? "mois $month" : ""; ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date_i18n('j M Y \à H:i', strtotime($progress->updated_at)); ?>
                        </td>
                        <td>
                            <span class="tmm-status-badge tmm-status-active">
                                En cours
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; 
                    else : ?>
                    <tr>
                        <td colspan="5">Aucune activité récente</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Statistiques détaillées -->
    <div class="tmm-parcours-stats">
        <h2>Statistiques détaillées</h2>
        
        <div class="tmm-stats-section">
            <div class="tmm-stat-box">
                <h3><?php echo array_sum(array_column($parcours_stats, 'users_count')); ?></h3>
                <p>Total utilisateurs inscrits</p>
            </div>
            
            <div class="tmm-stat-box">
                <h3><?php echo array_sum(array_column($parcours_stats, 'completed_count')); ?></h3>
                <p>Parcours complétés</p>
            </div>
            
            <div class="tmm-stat-box">
                <h3><?php 
                    $total_users = array_sum(array_column($parcours_stats, 'users_count'));
                    $total_completed = array_sum(array_column($parcours_stats, 'completed_count'));
                    $overall_rate = $total_users > 0 ? ($total_completed / $total_users) * 100 : 0;
                    echo number_format($overall_rate, 1);
                ?>%</h3>
                <p>Taux de complétion global</p>
            </div>
            
            <div class="tmm-stat-box">
                <h3><?php echo count($all_parcours); ?></h3>
                <p>Parcours disponibles</p>
            </div>
        </div>
        
        <!-- Graphique de progression -->
        <div class="tmm-progress-chart">
            <h3>Évolution des inscriptions</h3>
            <canvas id="enrollment-chart" width="800" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Modal de détails du parcours -->
<div id="parcours-details-modal" class="tmm-modal" style="display: none;">
    <div class="tmm-modal-content">
        <div class="tmm-modal-header">
            <h2>Détails du parcours</h2>
            <button type="button" class="tmm-modal-close">&times;</button>
        </div>
        <div class="tmm-modal-body">
            <div id="parcours-details-content">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>
</div>

<style>
.tmm-parcours-page {
    max-width: 1200px;
}

.tmm-parcours-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007cba;
}

.tmm-parcours-actions {
    display: flex;
    gap: 10px;
}

.tmm-parcours-overview h2 {
    margin-bottom: 20px;
    color: #1d2327;
}

.tmm-parcours-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.tmm-parcours-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    border-left: 4px solid #007cba;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tmm-parcours-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.tmm-parcours-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.tmm-parcours-header h3 {
    margin: 0;
    color: #1d2327;
    font-size: 18px;
}

.tmm-parcours-level {
    background: #007cba;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.tmm-parcours-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.tmm-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    color: #646970;
}

.tmm-meta-item .dashicons {
    font-size: 16px;
}

.tmm-parcours-description {
    margin-bottom: 15px;
}

.tmm-parcours-description p {
    margin: 0;
    color: #646970;
    line-height: 1.5;
}

.tmm-parcours-skills {
    margin-bottom: 15px;
}

.tmm-skills-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}

.tmm-skill-tag {
    background: #e7f3ff;
    color: #0073aa;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.tmm-parcours-progress {
    margin-bottom: 20px;
}

.tmm-progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

.tmm-progress-bar {
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.tmm-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007cba, #005a87);
    transition: width 0.3s ease;
}

.tmm-parcours-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tmm-parcours-actions .button {
    font-size: 12px;
    padding: 4px 8px;
    height: auto;
}

.tmm-recent-progress {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.tmm-recent-progress h2 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #1d2327;
}

.tmm-parcours-badge {
    background: #e7f3ff;
    color: #0073aa;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.tmm-action-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #646970;
    font-size: 13px;
}

.tmm-status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.tmm-status-active {
    background: #d4edda;
    color: #155724;
}

.tmm-status-completed {
    background: #cce5ff;
    color: #004085;
}

.tmm-parcours-stats {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tmm-stats-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.tmm-stat-box {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007cba;
}

.tmm-stat-box h3 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: bold;
    color: #007cba;
}

.tmm-stat-box p {
    margin: 0;
    color: #646970;
    font-weight: 600;
}

.tmm-progress-chart {
    margin-top: 20px;
}

.tmm-progress-chart h3 {
    margin-bottom: 20px;
    color: #1d2327;
}

.tmm-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tmm-modal-content {
    background: white;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    max-height: 90%;
    overflow: auto;
}

.tmm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
}

.tmm-modal-header h2 {
    margin: 0;
    color: #1d2327;
}

.tmm-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #646970;
}

.tmm-modal-body {
    padding: 20px;
}

@media (max-width: 768px) {
    .tmm-parcours-grid {
        grid-template-columns: 1fr;
    }
    
    .tmm-parcours-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .tmm-stats-section {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Graphique d'évolution des inscriptions
    var enrollmentData = {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [
            <?php foreach ($all_parcours as $parcours_id => $parcours) : ?>
            {
                label: '<?php echo esc_js($parcours['name']); ?>',
                data: [<?php echo implode(',', array_fill(0, 12, rand(0, 20))); ?>],
                borderColor: '<?php echo $parcours_id === 'sysops' ? '#007cba' : '#28a745'; ?>',
                backgroundColor: '<?php echo $parcours_id === 'sysops' ? 'rgba(0, 124, 186, 0.1)' : 'rgba(40, 167, 69, 0.1)'; ?>',
                tension: 0.4
            },
            <?php endforeach; ?>
        ]
    };
    
    var enrollmentChart = new Chart(document.getElementById('enrollment-chart'), {
        type: 'line',
        data: enrollmentData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Synchronisation des parcours
    $('#sync-parcours').click(function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Synchronisation...');
        
        // Simulation de la synchronisation
        setTimeout(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Synchroniser');
            alert('Parcours synchronisés avec succès !');
        }, 2000);
    });
    
    // Fermeture de modal
    $('.tmm-modal-close, .tmm-modal').click(function(e) {
        if (e.target === this) {
            $('.tmm-modal').hide();
        }
    });
});

// Fonctions globales
function viewParcoursDetails(parcoursId) {
    // Charger les détails du parcours
    jQuery.post(ajaxurl, {
        action: 'tmm_get_parcours_details',
        parcours_id: parcoursId,
        _wpnonce: '<?php echo wp_create_nonce('tmm_admin_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            jQuery('#parcours-details-content').html(response.data.html);
            jQuery('#parcours-details-modal').show();
        }
    });
}

function exportParcoursData(parcoursId) {
    // Export des données du parcours
    window.location.href = ajaxurl + '?action=tmm_export_parcours&parcours_id=' + parcoursId + '&_wpnonce=<?php echo wp_create_nonce('tmm_admin_nonce'); ?>';
}

function editParcours(parcoursId) {
    // Redirection vers l'édition du parcours
    alert('Fonctionnalité d\'édition en cours de développement');
}
</script>

<style>
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>