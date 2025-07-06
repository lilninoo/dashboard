<?php
/**
 * Classe de gestion des shortcodes
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardShortcodes {
    
    /**
     * Initialiser les shortcodes
     */
    public static function init() {
        add_shortcode('tmm_dashboard', [__CLASS__, 'renderDashboard']);
        add_shortcode('tmm_parcours', [__CLASS__, 'renderParcours']);
        add_shortcode('tmm_badges', [__CLASS__, 'renderBadges']);
        add_shortcode('tmm_progress', [__CLASS__, 'renderProgress']);
        add_shortcode('tmm_certificates', [__CLASS__, 'renderCertificates']);
        add_shortcode('tmm_courses', [__CLASS__, 'renderCourses']);
        add_shortcode('tmm_stats', [__CLASS__, 'renderStats']);
    }
    
    /**
     * Shortcode principal du dashboard
     */
    public static function renderDashboard($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'style' => 'full',
            'sections' => 'all'
        ], $atts);
        
        // Enqueue scripts et styles
        wp_enqueue_style('tmm-dashboard-style');
        wp_enqueue_script('tmm-dashboard-script');
        
        ob_start();
        include TMM_DASHBOARD_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher le parcours
     */
    public static function renderParcours($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'parcours_id' => '',
            'show_tools' => 'true'
        ], $atts);
        
        wp_enqueue_style('tmm-dashboard-style');
        wp_enqueue_script('tmm-dashboard-script');
        
        $user_id = get_current_user_id();
        
        // Si parcours spécifique demandé
        if (!empty($atts['parcours_id'])) {
            $parcours = TMMDashboardParcours::getParcours($atts['parcours_id']);
            if (!$parcours) {
                return '<p>Parcours introuvable.</p>';
            }
            
            // Vérifier l'accès
            $membership = pmpro_getMembershipLevelForUser($user_id);
            if (!$membership || $membership->id != $parcours['membership_id']) {
                return '<p>Vous n\'avez pas accès à ce parcours.</p>';
            }
        }
        
        return TMMDashboardParcours::renderParcours($user_id);
    }
    
    /**
     * Shortcode pour afficher les badges
     */
    public static function renderBadges($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'style' => 'grid',
            'show_progress' => 'true'
        ], $atts);
        
        wp_enqueue_style('tmm-dashboard-style');
        
        $user_id = get_current_user_id();
        return TMMDashboardBadges::renderUserBadges($user_id);
    }
    
    /**
     * Shortcode pour afficher la progression
     */
    public static function renderProgress($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'type' => 'overview',
            'period' => '30',
            'chart' => 'true'
        ], $atts);
        
        wp_enqueue_style('tmm-dashboard-style');
        wp_enqueue_script('tmm-dashboard-script');
        wp_enqueue_script('chartjs');
        
        $user_id = get_current_user_id();
        $analytics = TMMDashboardAnalytics::getUserAnalytics($user_id, $atts['type'], intval($atts['period']));
        
        ob_start();
        ?>
        <div class="tmm-progress-widget">
            <?php if ($atts['type'] === 'overview' && isset($analytics['summary'])) : 
                $summary = $analytics['summary'];
            ?>
            <div class="tmm-progress-stats">
                <div class="tmm-stat-item">
                    <span class="tmm-stat-number"><?php echo $summary['courses_completed']; ?></span>
                    <span class="tmm-stat-label">Cours terminés</span>
                </div>
                <div class="tmm-stat-item">
                    <span class="tmm-stat-number"><?php echo $summary['total_time_hours']; ?>h</span>
                    <span class="tmm-stat-label">Temps d'apprentissage</span>
                </div>
                <div class="tmm-stat-item">
                    <span class="tmm-stat-number"><?php echo $summary['average_quiz_score']; ?>%</span>
                    <span class="tmm-stat-label">Score moyen</span>
                </div>
                <div class="tmm-stat-item">
                    <span class="tmm-stat-number"><?php echo $summary['completion_rate']; ?>%</span>
                    <span class="tmm-stat-label">Taux de complétion</span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['chart'] === 'true') : ?>
            <div class="tmm-progress-chart">
                <canvas id="tmm-progress-chart-<?php echo uniqid(); ?>" width="400" height="200"></canvas>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les certificats
     */
    public static function renderCertificates($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'style' => 'cards',
            'limit' => '10'
        ], $atts);
        
        wp_enqueue_style('tmm-dashboard-style');
        
        $user_id = get_current_user_id();
        $certificates = TMMDashboardCertificates::getUserCertificates($user_id);
        
        if (empty($certificates)) {
            return '<div class="tmm-empty-certificates">
                        <p>Vous n\'avez pas encore de certificat.</p>
                        <p>Terminez un parcours complet pour obtenir votre premier certificat !</p>
                    </div>';
        }
        
        $limit = intval($atts['limit']);
        if ($limit > 0) {
            $certificates = array_slice($certificates, 0, $limit);
        }
        
        ob_start();
        ?>
        <div class="tmm-certificates-widget tmm-style-<?php echo esc_attr($atts['style']); ?>">
            <?php foreach ($certificates as $certificate) : ?>
            <div class="tmm-certificate-item">
                <div class="tmm-certificate-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="tmm-certificate-info">
                    <h4><?php echo esc_html($certificate['parcours_name']); ?></h4>
                    <p>Délivré le <?php echo date_i18n(get_option('date_format'), strtotime($certificate['date_completed'])); ?></p>
                    <span class="tmm-certificate-number">N° <?php echo esc_html($certificate['certificate_number']); ?></span>
                </div>
                <div class="tmm-certificate-actions">
                    <a href="<?php echo esc_url(TMMDashboardCertificates::getCertificateUrl($user_id, $certificate['parcours_id'])); ?>" 
                       class="tmm-btn tmm-btn-primary" target="_blank">
                        <i class="fas fa-download"></i> Télécharger
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les cours
     */
    public static function renderCourses($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'status' => 'all', // all, enrolled, in_progress, completed
            'limit' => '12',
            'style' => 'grid',
            'show_progress' => 'true'
        ], $atts);
        
        wp_enqueue_style('tmm-dashboard-style');
        
        $user_id = get_current_user_id();
        $courses_data = TMMDashboardData::getUserCourses($user_id);
        
        $courses = [];
        if ($atts['status'] === 'all') {
            $courses = array_merge(
                $courses_data['enrolled'] ?? [],
                $courses_data['in_progress'] ?? [],
                $courses_data['completed'] ?? []
            );
        } else {
            $courses = $courses_data[$atts['status']] ?? [];
        }
        
        if (empty($courses)) {
            return '<div class="tmm-empty-courses">
                        <p>Aucun cours trouvé.</p>
                    </div>';
        }
        
        $limit = intval($atts['limit']);
        if ($limit > 0) {
            $courses = array_slice($courses, 0, $limit);
        }
        
        ob_start();
        ?>
        <div class="tmm-courses-widget tmm-style-<?php echo esc_attr($atts['style']); ?>">
            <?php foreach ($courses as $course) : ?>
            <div class="tmm-course-item">
                <div class="tmm-course-thumbnail">
                    <img src="<?php echo esc_url($course['image']); ?>" alt="<?php echo esc_attr($course['name']); ?>">
                    <div class="tmm-course-status tmm-status-<?php echo esc_attr($course['status']); ?>">
                        <?php echo esc_html(ucfirst($course['status'])); ?>
                    </div>
                </div>
                
                <div class="tmm-course-content">
                    <h4><?php echo esc_html($course['name']); ?></h4>
                    
                    <div class="tmm-course-meta">
                        <span class="tmm-course-level"><?php echo esc_html($course['level']); ?></span>
                        <span class="tmm-course-duration"><?php echo esc_html($course['duration']); ?></span>
                    </div>
                    
                    <?php if ($atts['show_progress'] === 'true' && $course['progress'] > 0) : ?>
                    <div class="tmm-course-progress">
                        <div class="tmm-progress-bar">
                            <div class="tmm-progress-fill" style="width: <?php echo esc_attr($course['progress']); ?>%"></div>
                        </div>
                        <span class="tmm-progress-text"><?php echo $course['progress']; ?>% complété</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="tmm-course-actions">
                        <a href="<?php echo esc_url($course['url']); ?>" class="tmm-btn tmm-btn-primary">
                            <?php echo $course['progress'] > 0 ? 'Continuer' : 'Commencer'; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les statistiques
     */
    public static function renderStats($atts) {
        if (!is_user_logged_in()) {
            return self::renderLoginMessage();
        }
        
        $atts = shortcode_atts([
            'type' => 'overview',
            'period' => '30',
            'layout' => 'horizontal'
        ], $atts);
        
        wp_enqueue_style('tmm-dashboard-style');
        
        $user_id = get_current_user_id();
        $dashboard_data = TMMDashboardData::getDashboardData($user_id);
        $stats = $dashboard_data['stats'];
        
        ob_start();
        ?>
        <div class="tmm-stats-widget tmm-layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="tmm-stat-item">
                <div class="tmm-stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="tmm-stat-content">
                    <span class="tmm-stat-number"><?php echo $stats['completed_count']; ?></span>
                    <span class="tmm-stat-label">Cours terminés</span>
                </div>
            </div>
            
            <div class="tmm-stat-item">
                <div class="tmm-stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="tmm-stat-content">
                    <span class="tmm-stat-number"><?php echo $stats['completion_percentage']; ?>%</span>
                    <span class="tmm-stat-label">Progression</span>
                </div>
            </div>
            
            <div class="tmm-stat-item">
                <div class="tmm-stat-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="tmm-stat-content">
                    <span class="tmm-stat-number"><?php echo $stats['learning_streak']; ?></span>
                    <span class="tmm-stat-label">Jours consécutifs</span>
                </div>
            </div>
            
            <div class="tmm-stat-item">
                <div class="tmm-stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="tmm-stat-content">
                    <span class="tmm-stat-number"><?php echo $stats['badges_count']; ?></span>
                    <span class="tmm-stat-label">Badges obtenus</span>
                </div>
            </div>
            
            <div class="tmm-stat-item">
                <div class="tmm-stat-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="tmm-stat-content">
                    <span class="tmm-stat-number"><?php echo $stats['certificates_count']; ?></span>
                    <span class="tmm-stat-label">Certificats</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Message de connexion
     */
    private static function renderLoginMessage() {
        $login_url = wp_login_url(get_permalink());
        return sprintf(
            '<div class="tmm-login-required">
                <p>Vous devez être connecté pour accéder à cette fonctionnalité.</p>
                <p><a href="%s" class="tmm-btn tmm-btn-primary">Se connecter</a></p>
            </div>',
            esc_url($login_url)
        );
    }
}