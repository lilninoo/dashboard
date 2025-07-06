<?php
/**
 * Plugin Name: TeachMeMore Dashboard Pro
 * Plugin URI: https://teachmemore.com/
 * Description: Dashboard professionnel avancé pour LearnPress et PMPro avec système de parcours, badges, chatbot intelligent et analytics
 * Version: 1.0.0
 * Author: TeachMeMore
 * Author URI: https://teachmemore.com/
 * License: GPL v2 or later
 * Text Domain: tmm-dashboard
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Constantes du plugin
define('TMM_DASHBOARD_VERSION', '1.0.0');
define('TMM_DASHBOARD_PATH', plugin_dir_path(__FILE__));
define('TMM_DASHBOARD_URL', plugin_dir_url(__FILE__));
define('TMM_DASHBOARD_BASENAME', plugin_basename(__FILE__));

// Classe principale du plugin
class TMMDashboardPro {
    
    private static $instance = null;
    
    /**
     * Instance unique du plugin (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructeur
     */
    private function __construct() {
        add_action('plugins_loaded', [$this, 'checkDependencies']);
        add_action('init', [$this, 'init']);
        
        $this->loadDependencies();
        $this->defineHooks();
    }
    
    /**
     * Vérifier les dépendances
     */
    public function checkDependencies() {
        $dependencies = [];
        
        // Vérifier LearnPress
        if (!class_exists('LearnPress')) {
            $dependencies[] = 'LearnPress';
        }
        
        // Vérifier PMPro
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            $dependencies[] = 'Paid Memberships Pro';
        }
        
        if (!empty($dependencies)) {
            add_action('admin_notices', function() use ($dependencies) {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>TMM Dashboard Pro</strong> nécessite les plugins suivants : ';
                echo implode(', ', $dependencies);
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Charger les dépendances
     */
    private function loadDependencies() {
        // Classes principales
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-data.php';
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-badges.php';
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-analytics.php';
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-parcours.php';
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-ajax.php';
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-shortcodes.php';
        require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-certificates.php';
        
        // Chatbot et ses composants
        if (file_exists(TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-chatbot.php')) {
            require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-dashboard-chatbot.php';
            
            // Charger les modules avancés du chatbot
            if (file_exists(TMM_DASHBOARD_PATH . 'includes/class-tmm-chatbot-learning.php')) {
                require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-chatbot-learning.php';
            }
            
            if (file_exists(TMM_DASHBOARD_PATH . 'includes/class-tmm-chatbot-ml.php')) {
                require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-chatbot-ml.php';
            }
            
            if (file_exists(TMM_DASHBOARD_PATH . 'includes/class-tmm-chatbot-nlp.php')) {
                require_once TMM_DASHBOARD_PATH . 'includes/class-tmm-chatbot-nlp.php';
            }
        }
    }
    
    /**
     * Définir les hooks WordPress
     */
    private function defineHooks() {
        // Activation/Désactivation
        register_activation_hook(TMM_DASHBOARD_BASENAME, [$this, 'activate']);
        register_deactivation_hook(TMM_DASHBOARD_BASENAME, [$this, 'deactivate']);
        
        // Scripts et styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // Menu admin
        add_action('admin_menu', [$this, 'addAdminMenu']);
        
        // Hooks de connexion
        add_action('wp_login', [$this, 'updateUserEngagement'], 10, 2);
        
        // Mise à jour des badges
        add_action('learn_press_user_complete_lesson', [$this, 'updateUserBadges']);
        add_action('learn_press_user_complete_course', [$this, 'updateUserBadges']);
        
        // Cron jobs
        add_action('tmm_daily_analytics', [$this, 'runDailyAnalytics']);
        add_action('tmm_weekly_report', [$this, 'runWeeklyReport']);
        
        // Templates
        add_filter('template_include', [$this, 'templateInclude']);
        
        // Ajouter le widget chatbot au footer
        if (get_option('tmm_enable_chatbot', true)) {
            add_action('wp_footer', [$this, 'renderChatbotWidget']);
        }
        
        // AJAX Handlers pour le chatbot
        add_action('wp_ajax_tmm_chatbot_message', [$this, 'handleChatbotMessage']);
        add_action('wp_ajax_nopriv_tmm_chatbot_message', [$this, 'handleChatbotMessage']);
        
        add_action('wp_ajax_tmm_chatbot_feedback', [$this, 'handleChatbotFeedback']);
        add_action('wp_ajax_nopriv_tmm_chatbot_feedback', [$this, 'handleChatbotFeedback']);
        
        add_action('wp_ajax_tmm_chatbot_get_suggestions', [$this, 'getChatbotSuggestions']);
        add_action('wp_ajax_nopriv_tmm_chatbot_get_suggestions', [$this, 'getChatbotSuggestions']);
    }
    
    /**
     * Initialisation
     */
    public function init() {
        // Charger les traductions
        load_plugin_textdomain('tmm-dashboard', false, dirname(TMM_DASHBOARD_BASENAME) . '/languages');
        
        // Initialiser les composants
        TMMDashboardShortcodes::init();
        TMMDashboardAjax::init();
        
        if (class_exists('TMMDashboardCertificates')) {
            TMMDashboardCertificates::init();
        }
        
        // Initialiser le module ML si disponible
        if (class_exists('TMMChatbotML')) {
            TMMChatbotML::init();
        }
        
        // Ajouter les endpoints personnalisés
        $this->addEndpoints();
        
        // Mettre à jour la base de données si nécessaire
        $this->maybeUpdateDatabase();
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        // Vérifier les dépendances
        if (!$this->checkDependencies()) {
            deactivate_plugins(TMM_DASHBOARD_BASENAME);
            wp_die('TMM Dashboard Pro nécessite LearnPress et Paid Memberships Pro.');
        }
        
        // Créer les tables nécessaires
        $this->createTables();
        
        // Créer les pages par défaut
        $this->createDefaultPages();
        
        // Planifier les tâches cron
        $this->scheduleCronJobs();
        
        // Options par défaut
        $this->setDefaultOptions();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        // Nettoyer les tâches cron
        wp_clear_scheduled_hook('tmm_daily_analytics');
        wp_clear_scheduled_hook('tmm_weekly_report');
        wp_clear_scheduled_hook('tmm_ml_training');
        
        flush_rewrite_rules();
    }
    
    /**
     * Créer les tables de la base de données
     */
    private function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table pour les analytics
        $table_analytics = $wpdb->prefix . 'tmm_analytics';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table pour les badges personnalisés
        $table_badges = $wpdb->prefix . 'tmm_user_badges';
        $sql_badges = "CREATE TABLE $table_badges (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            badge_type varchar(50) NOT NULL,
            earned_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_badge (user_id, badge_type),
            KEY earned_date (earned_date)
        ) $charset_collate;";
        
        // Table pour les messages du chatbot
        $table_chatbot = $wpdb->prefix . 'tmm_chatbot_messages';
        $sql_chatbot = "CREATE TABLE $table_chatbot (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            message_type varchar(20) NOT NULL,
            message longtext NOT NULL,
            response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table pour l'apprentissage du chatbot
        $table_learning = $wpdb->prefix . 'tmm_chatbot_learning';
        $sql_learning = "CREATE TABLE $table_learning (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            message longtext NOT NULL,
            intent_detected varchar(50),
            response_type varchar(50),
            confidence_score float,
            user_satisfaction tinyint(1),
            response_helpful tinyint(1),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY intent_detected (intent_detected),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        // Table pour les features ML
        $table_ml_features = $wpdb->prefix . 'tmm_ml_features';
        $sql_ml_features = "CREATE TABLE $table_ml_features (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            feature_type varchar(50) NOT NULL,
            feature_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY feature_type (feature_type)
        ) $charset_collate;";
        
        // Table pour les prédictions ML
        $table_ml_predictions = $wpdb->prefix . 'tmm_ml_predictions';
        $sql_ml_predictions = "CREATE TABLE $table_ml_predictions (
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
        dbDelta($sql_analytics);
        dbDelta($sql_badges);
        dbDelta($sql_chatbot);
        dbDelta($sql_learning);
        dbDelta($sql_ml_features);
        dbDelta($sql_ml_predictions);
        
        // Sauvegarder la version de la base de données
        update_option('tmm_dashboard_db_version', TMM_DASHBOARD_VERSION);
    }
    
    /**
     * Créer les pages par défaut
     */
    private function createDefaultPages() {
        // Vérifier si la page existe déjà
        $existing_page = get_option('tmm_dashboard_page_id');
        if ($existing_page && get_post($existing_page)) {
            return;
        }
        
        $dashboard_page = [
            'post_title'    => __('Mon Tableau de Bord', 'tmm-dashboard'),
            'post_content'  => '[tmm_dashboard]',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'tableau-de-bord',
            'post_author'   => 1,
        ];
        
        $page_id = wp_insert_post($dashboard_page);
        
        if ($page_id && !is_wp_error($page_id)) {
            update_option('tmm_dashboard_page_id', $page_id);
        }
    }
    
    /**
     * Planifier les tâches cron
     */
    private function scheduleCronJobs() {
        if (!wp_next_scheduled('tmm_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'tmm_daily_analytics');
        }
        
        if (!wp_next_scheduled('tmm_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'tmm_weekly_report');
        }
        
        // Ajouter le cron pour l'entraînement ML
        if (!wp_next_scheduled('tmm_ml_training')) {
            wp_schedule_event(time(), 'daily', 'tmm_ml_training');
        }
    }
    
    /**
     * Options par défaut
     */
    private function setDefaultOptions() {
        add_option('tmm_enable_chatbot', true);
        add_option('tmm_enable_notifications', true);
        add_option('tmm_enable_analytics', true);
        add_option('tmm_certificate_logo', TMM_DASHBOARD_URL . 'assets/img/logo.png');
        add_option('tmm_chatbot_position', 'bottom-right');
        add_option('tmm_chatbot_welcome_message', 'Bonjour ! Je suis votre assistant d\'apprentissage. Comment puis-je vous aider ?');
        add_option('tmm_enable_ml_features', true);
    }
    
    /**
     * Ajouter les endpoints personnalisés
     */
    private function addEndpoints() {
        add_rewrite_endpoint('dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('certificates', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('analytics', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Mise à jour de la base de données
     */
    private function maybeUpdateDatabase() {
        $current_version = get_option('tmm_dashboard_db_version', '0.0.0');
        
        if (version_compare($current_version, TMM_DASHBOARD_VERSION, '<')) {
            $this->createTables();
            update_option('tmm_dashboard_db_version', TMM_DASHBOARD_VERSION);
        }
    }
    
    /**
     * Enqueue scripts et styles frontend
     */
    public function enqueueScripts() {
        // CSS principal toujours chargé si le chatbot est activé
        if (get_option('tmm_enable_chatbot', true)) {
            wp_enqueue_style(
                'tmm-chatbot-style',
                TMM_DASHBOARD_URL . 'assets/css/chatbot.css',
                [],
                TMM_DASHBOARD_VERSION
            );
            
            wp_enqueue_script(
                'tmm-chatbot',
                TMM_DASHBOARD_URL . 'assets/js/chatbot.js',
                ['jquery'],
                TMM_DASHBOARD_VERSION,
                true
            );

        }
        
        // Charger seulement sur les pages appropriées pour le dashboard
        if (!$this->isDashboardPage() && !has_shortcode(get_post()->post_content ?? '', 'tmm_dashboard')) {
            return;
        }
        
        // CSS principal
        wp_enqueue_style(
            'tmm-dashboard-style',
            TMM_DASHBOARD_URL . 'assets/css/dashboard.css',
            [],
            TMM_DASHBOARD_VERSION
        );
        
        // Google Fonts
        wp_enqueue_style(
            'tmm-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@700&display=swap',
            [],
            null
        );
        
        // FontAwesome
        wp_enqueue_style(
            'fontawesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            [],
            '6.4.0'
        );
        
        // Chart.js
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            [],
            '4.4.0',
            true
        );
        
        // Scripts principaux
        wp_enqueue_script(
            'tmm-dashboard-script',
            TMM_DASHBOARD_URL . 'assets/js/dashboard.js',
            ['jquery', 'chartjs'],
            TMM_DASHBOARD_VERSION,
            true
        );
        
        // Localisation pour AJAX
        wp_localize_script('tmm-dashboard-script', 'tmm_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmm_dashboard_nonce'),
            'user_id' => get_current_user_id(),
            'dashboard_url' => TMM_DASHBOARD_URL,
            'strings' => [
                'loading' => __('Chargement...', 'tmm-dashboard'),
                'error' => __('Une erreur est survenue', 'tmm-dashboard'),
                'confirm_cancel' => __('Êtes-vous sûr de vouloir annuler votre abonnement ?', 'tmm-dashboard'),
                'success' => __('Opération réussie', 'tmm-dashboard'),
                'saved' => __('Sauvegardé', 'tmm-dashboard'),
            ],
        ]);
        
        // Localisation spécifique pour le chatbot si différente
        if (get_option('tmm_enable_chatbot', true)) {
            wp_localize_script('tmm-chatbot', 'tmm_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tmm_dashboard_nonce'),
                'user_id' => get_current_user_id(),
                'is_logged_in' => is_user_logged_in(),
                'strings' => [
                    'loading' => __('Chargement...', 'tmm-dashboard'),
                    'error' => __('Une erreur est survenue', 'tmm-dashboard'),
                    'typing' => __('En train d\'écrire...', 'tmm-dashboard'),
                    'offline' => __('Assistant hors ligne', 'tmm-dashboard'),
                ],
            ]);
        }
    }
    
    /**
     * Enqueue scripts admin
     */
    public function enqueueAdminScripts($hook) {
        if (strpos($hook, 'tmm-dashboard') === false) {
            return;
        }
        
        wp_enqueue_style(
            'tmm-admin-style',
            TMM_DASHBOARD_URL . 'assets/css/admin.css',
            [],
            TMM_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'tmm-admin-script',
            TMM_DASHBOARD_URL . 'assets/js/admin.js',
            ['jquery', 'chartjs'],
            TMM_DASHBOARD_VERSION,
            true
        );
        
        wp_localize_script('tmm-admin-script', 'tmm_admin_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tmm_admin_nonce'),
        ]);
    }
    
    /**
     * Ajouter le menu admin
     */
    public function addAdminMenu() {
        add_menu_page(
            __('TMM Dashboard', 'tmm-dashboard'),
            __('TMM Dashboard', 'tmm-dashboard'),
            'manage_options',
            'tmm-dashboard',
            [$this, 'renderAdminPage'],
            'dashicons-dashboard',
            30
        );
        
        add_submenu_page(
            'tmm-dashboard',
            __('Paramètres', 'tmm-dashboard'),
            __('Paramètres', 'tmm-dashboard'),
            'manage_options',
            'tmm-dashboard-settings',
            [$this, 'renderSettingsPage']
        );
        
        add_submenu_page(
            'tmm-dashboard',
            __('Analytics', 'tmm-dashboard'),
            __('Analytics', 'tmm-dashboard'),
            'manage_options',
            'tmm-dashboard-analytics',
            [$this, 'renderAnalyticsPage']
        );
        
        add_submenu_page(
            'tmm-dashboard',
            __('Parcours', 'tmm-dashboard'),
            __('Parcours', 'tmm-dashboard'),
            'manage_options',
            'tmm-dashboard-parcours',
            [$this, 'renderParcoursPage']
        );
        
        add_submenu_page(
            'tmm-dashboard',
            __('Chatbot', 'tmm-dashboard'),
            __('Chatbot', 'tmm-dashboard'),
            'manage_options',
            'tmm-dashboard-chatbot',
            [$this, 'renderChatbotPage']
        );
    }
    
    /**
     * Vérifier si on est sur une page du dashboard
     */
    private function isDashboardPage() {
        $dashboard_page_id = get_option('tmm_dashboard_page_id');
        return is_page($dashboard_page_id) || 
               (is_singular() && has_shortcode(get_post()->post_content ?? '', 'tmm_dashboard'));
    }
    
    /**
     * Mettre à jour l'engagement utilisateur à la connexion
     */
    public function updateUserEngagement($user_login, $user) {
        if (class_exists('TMMDashboardAnalytics')) {
            TMMDashboardAnalytics::trackEvent($user->ID, 'login', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
        }
        
        if (class_exists('TMMDashboardBadges')) {
            TMMDashboardBadges::updateUserStats($user->ID);
        }
        
        // Mettre à jour le profil d'apprentissage du chatbot
        if (class_exists('TMMChatbotLearning')) {
            TMMChatbotLearning::buildUserLearningProfile($user->ID);
        }
    }
    
    /**
     * Mettre à jour les badges utilisateur
     */
    public function updateUserBadges($user_id) {
        if (class_exists('TMMDashboardBadges')) {
            TMMDashboardBadges::updateBadgeStatus($user_id);
        }
    }
    
    /**
     * Tâche cron quotidienne
     */
    public function runDailyAnalytics() {
        if (class_exists('TMMDashboardAnalytics')) {
            // Nettoyer les anciennes données
            TMMDashboardChatbot::cleanOldHistory(30);
            
            // Entraîner le modèle ML
            if (class_exists('TMMChatbotML')) {
                TMMChatbotML::runDailyTraining();
            }
            
            // Autres tâches analytiques
            do_action('tmm_daily_analytics_run');
        }
    }
    
    /**
     * Tâche cron hebdomadaire
     */
    public function runWeeklyReport() {
        if (class_exists('TMMDashboardAnalytics')) {
            // Envoyer les rapports hebdomadaires
            $users = get_users(['meta_key' => 'tmm_pref_weekly_report', 'meta_value' => '1']);
            
            foreach ($users as $user) {
                TMMDashboardAnalytics::generatePeriodicReport($user->ID, 'weekly');
            }
        }
    }
    
    /**
     * Template include
     */
    public function templateInclude($template) {
        if (get_query_var('certificate')) {
            $certificate_template = TMM_DASHBOARD_PATH . 'templates/certificate.php';
            if (file_exists($certificate_template)) {
                return $certificate_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Afficher le widget chatbot
     */
    public function renderChatbotWidget() {
        if (!is_user_logged_in() && !get_option('tmm_chatbot_allow_guests', false)) {
            return;
        }
        
        $position = get_option('tmm_chatbot_position', 'bottom-right');
        ?>
        <div class="tmm-chatbot-widget" data-position="<?php echo esc_attr($position); ?>">
            <button class="tmm-chatbot-toggle" aria-label="<?php esc_attr_e('Ouvrir l\'assistant', 'tmm-dashboard'); ?>">
                <i class="fas fa-comments"></i>
                <span class="tmm-chatbot-badge" style="display: none;">1</span>
            </button>
            
            <div class="tmm-chatbot-window" style="display: none;">
                <div class="tmm-chatbot-header">
                    <div class="tmm-chatbot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="tmm-chatbot-info">
                        <h4><?php esc_html_e('Assistant TMM', 'tmm-dashboard'); ?></h4>
                        <span class="tmm-chatbot-status"><?php esc_html_e('En ligne', 'tmm-dashboard'); ?></span>
                    </div>
                    <button class="tmm-chatbot-close" aria-label="<?php esc_attr_e('Fermer', 'tmm-dashboard'); ?>">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="tmm-chatbot-messages">
                    <!-- Les messages seront ajoutés ici dynamiquement -->
                </div>
                
                <?php if (is_user_logged_in()) : ?>
                <div class="tmm-chatbot-suggestions">
                    <!-- Les suggestions seront ajoutées ici dynamiquement -->
                </div>
                <?php endif; ?>
                
                <form class="tmm-chatbot-input">
                    <input type="text" 
                           class="tmm-chatbot-text" 
                           placeholder="<?php esc_attr_e('Tapez votre message...', 'tmm-dashboard'); ?>"
                           autocomplete="off">
                    <button type="submit" aria-label="<?php esc_attr_e('Envoyer', 'tmm-dashboard'); ?>">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Gérer les messages du chatbot
     */
    public function handleChatbotMessage() {
        check_ajax_referer('tmm_dashboard_nonce', '_wpnonce');
        
        $user_id = get_current_user_id();
        if (!$user_id && !get_option('tmm_chatbot_allow_guests', false)) {
            wp_send_json_error(['message' => __('Vous devez être connecté pour utiliser l\'assistant.', 'tmm-dashboard')]);
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error(['message' => __('Message vide.', 'tmm-dashboard')]);
        }
        
        // Traiter le message avec le chatbot
        if (class_exists('TMMDashboardChatbot')) {
            $response = TMMDashboardChatbot::processMessage($user_id, $message);
            
            // Enrichir avec NLP si disponible
            if (class_exists('TMMChatbotNLP')) {
                $conversation_history = TMMDashboardChatbot::getChatHistory($user_id, 10);
                $context = TMMChatbotNLP::analyzeContext($user_id, $message, $conversation_history);
                $response = array_merge($response, $context);
            }
            
            // Ajouter des prédictions ML si disponible
            if (class_exists('TMMChatbotML') && $user_id) {
                $predictions = TMMChatbotML::predictUserNeeds($user_id);
                $response['predictions'] = $predictions;
            }
            
            wp_send_json_success([
                'message' => $response['text'],
                'type' => $response['type'] ?? 'text',
                'data' => $response['data'] ?? [],
                'actions' => $response['actions'] ?? [],
                'suggestions' => TMMDashboardChatbot::getSuggestions($user_id),
                'intent' => $response['intent'] ?? null
            ]);
        } else {
            wp_send_json_error(['message' => __('Le module chatbot n\'est pas disponible.', 'tmm-dashboard')]);
        }
    }
    
    /**
     * Gérer le feedback du chatbot
     */
    public function handleChatbotFeedback() {
        check_ajax_referer('tmm_dashboard_nonce', '_wpnonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('Non autorisé.', 'tmm-dashboard')]);
        }
        
        $message_id = sanitize_text_field($_POST['message_id'] ?? '');
        $helpful = filter_var($_POST['helpful'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        // Enregistrer le feedback
        if (class_exists('TMMChatbotLearning')) {
            TMMChatbotLearning::learnFromInteraction($user_id, '', '', [
                'satisfaction' => $helpful ? 5 : 1,
                'helpful' => $helpful
            ]);
        }
        
        wp_send_json_success(['message' => __('Merci pour votre retour !', 'tmm-dashboard')]);
    }
    
    /**
     * Obtenir les suggestions du chatbot
     */
    public function getChatbotSuggestions() {
        check_ajax_referer('tmm_dashboard_nonce', '_wpnonce');
        
        $user_id = get_current_user_id();
        
        if (class_exists('TMMDashboardChatbot')) {
            $suggestions = TMMDashboardChatbot::getSuggestions($user_id);
            wp_send_json_success($suggestions);
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * Pages d'administration
     */
    public function renderAdminPage() {
        $template = TMM_DASHBOARD_PATH . 'admin/views/dashboard.php';
        if (file_exists($template)) {
            include $template;
        } else {
            $this->renderDefaultAdminPage();
        }
    }
    
    public function renderSettingsPage() {
        $template = TMM_DASHBOARD_PATH . 'admin/views/settings.php';
        if (file_exists($template)) {
            include $template;
        } else {
            $this->renderDefaultSettingsPage();
        }
    }
    
    public function renderAnalyticsPage() {
        $template = TMM_DASHBOARD_PATH . 'admin/views/analytics.php';
        if (file_exists($template)) {
            include $template;
        } else {
            $this->renderDefaultAnalyticsPage();
        }
    }
    
    public function renderParcoursPage() {
        $template = TMM_DASHBOARD_PATH . 'admin/views/parcours.php';
        if (file_exists($template)) {
            include $template;
        } else {
            $this->renderDefaultParcoursPage();
        }
    }
    
    public function renderChatbotPage() {
        if (class_exists('TMMDashboardChatbot')) {
            $insights = TMMDashboardChatbot::getChatbotInsights();
        }
        ?>
        <div class="wrap">
            <h1>TMM Dashboard - Gestion du Chatbot</h1>
            
            <?php if (isset($insights)) : ?>
            <div class="tmm-admin-stats">
                <div class="tmm-stat-box">
                    <h3>Conversations totales</h3>
                    <p class="tmm-big-number"><?php echo esc_html($insights['total_conversations']); ?></p>
                </div>
                
                <div class="tmm-stat-box">
                    <h3>Taux de résolution</h3>
                    <p class="tmm-big-number"><?php echo esc_html($insights['resolution_rate']); ?>%</p>
                </div>
                
                <div class="tmm-stat-box">
                    <h3>Utilisateurs actifs</h3>
                    <p class="tmm-big-number"><?php echo count($insights['active_users']); ?></p>
                </div>
            </div>
            
            <h2>Questions fréquentes</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Message</th>
                        <th>Nombre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insights['frequent_messages'] as $msg) : ?>
                    <tr>
                        <td><?php echo esc_html($msg->message); ?></td>
                        <td><?php echo esc_html($msg->count); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <h2>Configuration</h2>
            <form method="post" action="options.php">
                <?php settings_fields('tmm_chatbot_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Position du chatbot</th>
                        <td>
                            <select name="tmm_chatbot_position">
                                <option value="bottom-right" <?php selected(get_option('tmm_chatbot_position'), 'bottom-right'); ?>>
                                    Bas droite
                                </option>
                                <option value="bottom-left" <?php selected(get_option('tmm_chatbot_position'), 'bottom-left'); ?>>
                                    Bas gauche
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Message de bienvenue</th>
                        <td>
                            <textarea name="tmm_chatbot_welcome_message" rows="3" cols="50"><?php 
                                echo esc_textarea(get_option('tmm_chatbot_welcome_message')); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Autoriser les invités</th>
                        <td>
                            <input type="checkbox" name="tmm_chatbot_allow_guests" value="1" 
                                   <?php checked(get_option('tmm_chatbot_allow_guests', false)); ?>>
                            <p class="description">Permettre aux utilisateurs non connectés d'utiliser le chatbot</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Activer le Machine Learning</th>
                        <td>
                            <input type="checkbox" name="tmm_enable_ml_features" value="1" 
                                   <?php checked(get_option('tmm_enable_ml_features', true)); ?>>
                            <p class="description">Active les fonctionnalités d'apprentissage automatique</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Pages admin par défaut
     */
    private function renderDefaultAdminPage() {
        ?>
        <div class="wrap">
            <h1>TMM Dashboard - Tableau de bord Admin</h1>
            
            <div class="tmm-admin-stats">
                <div class="tmm-stat-box">
                    <h3>Utilisateurs actifs</h3>
                    <p class="tmm-big-number"><?php echo count_users()['total_users']; ?></p>
                </div>
                
                <div class="tmm-stat-box">
                    <h3>Badges distribués</h3>
                    <p class="tmm-big-number"><?php 
                        global $wpdb;
                        echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_user_badges");
                    ?></p>
                </div>
                
                <div class="tmm-stat-box">
                    <h3>Événements trackés</h3>
                    <p class="tmm-big-number"><?php 
                        echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics");
                    ?></p>
                </div>
                
                <div class="tmm-stat-box">
                    <h3>Messages chatbot</h3>
                    <p class="tmm-big-number"><?php 
                        echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_chatbot_messages");
                    ?></p>
                </div>
            </div>
            
            <h2>Actions rapides</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-settings'); ?>" class="button button-primary">
                    Paramètres
                </a>
                <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-analytics'); ?>" class="button">
                    Analytics
                </a>
                <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-parcours'); ?>" class="button">
                    Gérer les parcours
                </a>
                <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-chatbot'); ?>" class="button">
                    Gérer le chatbot
                </a>
            </p>
        </div>
        <?php
    }
    
    private function renderDefaultSettingsPage() {
        // Enregistrer les settings
        register_setting('tmm_dashboard_settings', 'tmm_enable_chatbot');
        register_setting('tmm_dashboard_settings', 'tmm_enable_notifications');
        register_setting('tmm_dashboard_settings', 'tmm_enable_analytics');
        register_setting('tmm_dashboard_settings', 'tmm_enable_ml_features');
        
        // Enregistrer les settings du chatbot
        register_setting('tmm_chatbot_settings', 'tmm_chatbot_position');
        register_setting('tmm_chatbot_settings', 'tmm_chatbot_welcome_message');
        register_setting('tmm_chatbot_settings', 'tmm_chatbot_allow_guests');
        ?>
        <div class="wrap">
            <h1>TMM Dashboard - Paramètres</h1>
            <form method="post" action="options.php">
                <?php settings_fields('tmm_dashboard_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Activer le chatbot</th>
                        <td>
                            <input type="checkbox" name="tmm_enable_chatbot" value="1" 
                                   <?php checked(get_option('tmm_enable_chatbot', true)); ?>>
                            <p class="description">Active l'assistant chatbot intelligent</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Activer les notifications</th>
                        <td>
                            <input type="checkbox" name="tmm_enable_notifications" value="1" 
                                   <?php checked(get_option('tmm_enable_notifications', true)); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Activer les analytics</th>
                        <td>
                            <input type="checkbox" name="tmm_enable_analytics" value="1" 
                                   <?php checked(get_option('tmm_enable_analytics', true)); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Activer le Machine Learning</th>
                        <td>
                            <input type="checkbox" name="tmm_enable_ml_features" value="1" 
                                   <?php checked(get_option('tmm_enable_ml_features', true)); ?>>
                            <p class="description">Active les fonctionnalités avancées d'IA et ML</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private function renderDefaultAnalyticsPage() {
        ?>
        <div class="wrap">
            <h1>TMM Dashboard - Analytics</h1>
            
            <div class="tmm-analytics-overview">
                <h2>Vue d'ensemble</h2>
                <canvas id="tmm-analytics-chart" width="400" height="200"></canvas>
            </div>
            
            <div class="tmm-analytics-details">
                <h2>Détails</h2>
                <p>Les rapports analytiques détaillés seront disponibles ici.</p>
            </div>
        </div>
        <?php
    }
    
    private function renderDefaultParcoursPage() {
        ?>
        <div class="wrap">
            <h1>TMM Dashboard - Gestion des Parcours</h1>
            
            <div class="tmm-parcours-list">
                <h2>Parcours existants</h2>
                <?php
                $parcours = TMMDashboardParcours::getAllParcours();
                if (!empty($parcours)) :
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Niveau</th>
                            <th>Durée</th>
                            <th>Utilisateurs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcours as $p) : ?>
                        <tr>
                            <td><?php echo esc_html($p['name']); ?></td>
                            <td><?php echo esc_html($p['level']); ?></td>
                            <td><?php echo esc_html($p['duration']); ?></td>
                            <td><?php echo TMMDashboardParcours::getParcoursUserCount($p['id']); ?></td>
                            <td>
                                <a href="#" class="button button-small">Modifier</a>
                                <a href="#" class="button button-small">Voir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p>Aucun parcours créé pour le moment.</p>
                <?php endif; ?>
            </div>
            
            <p><a href="#" class="button button-primary">Créer un nouveau parcours</a></p>
        </div>
        <?php
    }
    
    /**
     * Méthode pour l'export des données utilisateur
     */
    public function exportUserData() {
        add_action('admin_post_tmm_export_user_data', function() {
            if (!current_user_can('export')) {
                wp_die('Permissions insuffisantes');
            }
            
            check_admin_referer('tmm_export_data');
            
            $user_id = intval($_GET['user_id'] ?? 0);
            if (!$user_id) {
                wp_die('Utilisateur invalide');
            }
            
            $format = sanitize_text_field($_GET['format'] ?? 'json');
            
            // Récupérer toutes les données
            $data = [
                'user_info' => get_userdata($user_id)->data,
                'courses' => TMMDashboardData::getUserCourses($user_id),
                'badges' => TMMDashboardBadges::getUserStats($user_id),
                'certificates' => TMMDashboardCertificates::getUserCertificates($user_id),
                'analytics' => TMMDashboardAnalytics::getUserAnalytics($user_id, 'overview', 365),
                'chatbot_history' => TMMDashboardChatbot::getChatHistory($user_id, 1000)
            ];
            
            switch ($format) {
                case 'csv':
                    $this->exportAsCSV($data, $user_id);
                    break;
                case 'json':
                default:
                    $this->exportAsJSON($data, $user_id);
            }
        });
    }
    
    /**
     * Export en format JSON
     */
    private function exportAsJSON($data, $user_id) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="tmm-user-data-' . $user_id . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Export en format CSV
     */
    private function exportAsCSV($data, $user_id) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tmm-user-data-' . $user_id . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes
        fputcsv($output, ['Type', 'Data']);
        
        // Données utilisateur
        foreach ($data['user_info'] as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                fputcsv($output, ['User Info - ' . $key, $value]);
            }
        }
        
        // Cours
        foreach ($data['courses'] as $status => $courses) {
            foreach ($courses as $course) {
                fputcsv($output, ['Course - ' . $status, $course['name'] . ' (' . $course['progress'] . '%)']);
            }
        }
        
        fclose($output);
        exit;
    }
}

// Initialiser le plugin
add_action('plugins_loaded', function() {
    TMMDashboardPro::getInstance();
});

// Fonction helper globale
function tmm_dashboard() {
    return TMMDashboardPro::getInstance();
}

// Hook d'installation
function tmm_dashboard_install() {
    TMMDashboardPro::getInstance()->activate();
}

// Hook de désinstallation
function tmm_dashboard_uninstall() {
    // Nettoyer toutes les données si souhaité
    if (get_option('tmm_delete_data_on_uninstall')) {
        global $wpdb;
        
        // Supprimer les tables
        $tables = [
            'tmm_analytics',
            'tmm_user_badges',
            'tmm_chatbot_messages',
            'tmm_chatbot_learning',
            'tmm_ml_features',
            'tmm_ml_predictions'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        // Supprimer les options
        $options = [
            'tmm_dashboard_db_version',
            'tmm_dashboard_page_id',
            'tmm_enable_chatbot',
            'tmm_enable_notifications',
            'tmm_enable_analytics',
            'tmm_certificate_logo',
            'tmm_chatbot_position',
            'tmm_chatbot_welcome_message',
            'tmm_chatbot_allow_guests',
            'tmm_enable_ml_features'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Supprimer les métadonnées utilisateur
        delete_metadata('user', 0, 'tmm_badge_status', '', true);
        delete_metadata('user', 0, 'tmm_earned_badges', '', true);
        delete_metadata('user', 0, 'tmm_certificates', '', true);
        delete_metadata('user', 0, 'tmm_notifications', '', true);
        delete_metadata('user', 0, 'tmm_chatbot_learning_profile', '', true);
    }
}

register_uninstall_hook(__FILE__, 'tmm_dashboard_uninstall');