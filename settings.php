<?php
/**
 * Page de paramètres admin
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Traitement du formulaire
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'tmm_settings_nonce')) {
    // Paramètres généraux
    update_option('tmm_enable_chatbot', isset($_POST['tmm_enable_chatbot']));
    update_option('tmm_enable_notifications', isset($_POST['tmm_enable_notifications']));
    update_option('tmm_enable_analytics', isset($_POST['tmm_enable_analytics']));
    update_option('tmm_enable_badges', isset($_POST['tmm_enable_badges']));
    
    // Paramètres des certificats
    if (!empty($_POST['tmm_certificate_logo'])) {
        update_option('tmm_certificate_logo', esc_url($_POST['tmm_certificate_logo']));
    }
    
    update_option('tmm_certificate_director_name', sanitize_text_field($_POST['tmm_certificate_director_name'] ?? ''));
    update_option('tmm_certificate_manager_name', sanitize_text_field($_POST['tmm_certificate_manager_name'] ?? ''));
    
    // Paramètres d'email
    update_option('tmm_email_from_name', sanitize_text_field($_POST['tmm_email_from_name'] ?? ''));
    update_option('tmm_email_from_email', sanitize_email($_POST['tmm_email_from_email'] ?? ''));
    
    // Paramètres de maintenance
    update_option('tmm_cleanup_analytics_days', intval($_POST['tmm_cleanup_analytics_days'] ?? 90));
    update_option('tmm_cleanup_chatbot_days', intval($_POST['tmm_cleanup_chatbot_days'] ?? 30));
    
    echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès !</p></div>';
}

// Récupérer les paramètres actuels
$settings = [
    'enable_chatbot' => get_option('tmm_enable_chatbot', true),
    'enable_notifications' => get_option('tmm_enable_notifications', true),
    'enable_analytics' => get_option('tmm_enable_analytics', true),
    'enable_badges' => get_option('tmm_enable_badges', true),
    'certificate_logo' => get_option('tmm_certificate_logo', TMM_DASHBOARD_URL . 'assets/img/logo.png'),
    'certificate_director_name' => get_option('tmm_certificate_director_name', 'Directeur de formation'),
    'certificate_manager_name' => get_option('tmm_certificate_manager_name', 'Responsable pédagogique'),
    'email_from_name' => get_option('tmm_email_from_name', get_bloginfo('name')),
    'email_from_email' => get_option('tmm_email_from_email', get_option('admin_email')),
    'cleanup_analytics_days' => get_option('tmm_cleanup_analytics_days', 90),
    'cleanup_chatbot_days' => get_option('tmm_cleanup_chatbot_days', 30)
];
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        TMM Dashboard - Paramètres
    </h1>
    
    <div class="tmm-admin-header">
        <p>Configurez le comportement et l'apparence de votre dashboard TeachMeMore.</p>
    </div>

    <form method="post" action="" class="tmm-settings-form">
        <?php wp_nonce_field('tmm_settings_nonce'); ?>
        
        <div class="tmm-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active">Général</a>
                <a href="#certificates" class="nav-tab">Certificats</a>
                <a href="#emails" class="nav-tab">Emails</a>
                <a href="#maintenance" class="nav-tab">Maintenance</a>
                <a href="#advanced" class="nav-tab">Avancé</a>
            </nav>

            <!-- Onglet Général -->
            <div id="general" class="tmm-tab-content tmm-tab-active">
                <h2>Paramètres généraux</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tmm_enable_chatbot">Chatbot Assistant</label>
                        </th>
                        <td>
                            <input type="checkbox" id="tmm_enable_chatbot" name="tmm_enable_chatbot" value="1" 
                                   <?php checked($settings['enable_chatbot']); ?>>
                            <label for="tmm_enable_chatbot">Activer le chatbot intelligent</label>
                            <p class="description">Le chatbot aide les utilisateurs à naviguer et trouve des réponses à leurs questions.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_enable_notifications">Notifications</label>
                        </th>
                        <td>
                            <input type="checkbox" id="tmm_enable_notifications" name="tmm_enable_notifications" value="1" 
                                   <?php checked($settings['enable_notifications']); ?>>
                            <label for="tmm_enable_notifications">Activer le système de notifications</label>
                            <p class="description">Notifications pour les nouveaux badges, certificats, etc.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_enable_analytics">Analytics</label>
                        </th>
                        <td>
                            <input type="checkbox" id="tmm_enable_analytics" name="tmm_enable_analytics" value="1" 
                                   <?php checked($settings['enable_analytics']); ?>>
                            <label for="tmm_enable_analytics">Activer le tracking des analytics</label>
                            <p class="description">Collecte des données pour améliorer l'expérience utilisateur.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_enable_badges">Système de badges</label>
                        </th>
                        <td>
                            <input type="checkbox" id="tmm_enable_badges" name="tmm_enable_badges" value="1" 
                                   <?php checked($settings['enable_badges']); ?>>
                            <label for="tmm_enable_badges">Activer les badges et la gamification</label>
                            <p class="description">Système de récompenses pour motiver les apprenants.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Onglet Certificats -->
            <div id="certificates" class="tmm-tab-content">
                <h2>Paramètres des certificats</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tmm_certificate_logo">Logo du certificat</label>
                        </th>
                        <td>
                            <input type="url" id="tmm_certificate_logo" name="tmm_certificate_logo" 
                                   value="<?php echo esc_url($settings['certificate_logo']); ?>" class="regular-text">
                            <button type="button" class="button" id="upload-logo-btn">Choisir une image</button>
                            <p class="description">URL du logo à afficher sur les certificats (recommandé : 120x120px).</p>
                            
                            <div class="tmm-logo-preview">
                                <img src="<?php echo esc_url($settings['certificate_logo']); ?>" 
                                     alt="Aperçu du logo" style="max-width: 120px; max-height: 120px;">
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_certificate_director_name">Nom du directeur</label>
                        </th>
                        <td>
                            <input type="text" id="tmm_certificate_director_name" name="tmm_certificate_director_name" 
                                   value="<?php echo esc_attr($settings['certificate_director_name']); ?>" class="regular-text">
                            <p class="description">Nom affiché sous la signature de gauche sur les certificats.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_certificate_manager_name">Nom du responsable pédagogique</label>
                        </th>
                        <td>
                            <input type="text" id="tmm_certificate_manager_name" name="tmm_certificate_manager_name" 
                                   value="<?php echo esc_attr($settings['certificate_manager_name']); ?>" class="regular-text">
                            <p class="description">Nom affiché sous la signature de droite sur les certificats.</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Aperçu du certificat</h3>
                <div class="tmm-certificate-preview">
                    <div class="tmm-cert-preview-container">
                        <div class="tmm-cert-header">
                            <img src="<?php echo esc_url($settings['certificate_logo']); ?>" alt="Logo" class="tmm-cert-logo">
                            <h3>CERTIFICAT DE RÉUSSITE</h3>
                            <p>Ce certificat est décerné à</p>
                            <h4>NOM DE L'UTILISATEUR</h4>
                            <p>Pour avoir complété avec succès le parcours</p>
                            <h5>Nom du Parcours</h5>
                        </div>
                        <div class="tmm-cert-signatures">
                            <div class="tmm-cert-signature">
                                <div class="tmm-signature-line"></div>
                                <p><?php echo esc_html($settings['certificate_director_name']); ?></p>
                            </div>
                            <div class="tmm-cert-signature">
                                <div class="tmm-signature-line"></div>
                                <p><?php echo esc_html($settings['certificate_manager_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Emails -->
            <div id="emails" class="tmm-tab-content">
                <h2>Paramètres des emails</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tmm_email_from_name">Nom de l'expéditeur</label>
                        </th>
                        <td>
                            <input type="text" id="tmm_email_from_name" name="tmm_email_from_name" 
                                   value="<?php echo esc_attr($settings['email_from_name']); ?>" class="regular-text">
                            <p class="description">Nom affiché comme expéditeur des emails automatiques.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_email_from_email">Email de l'expéditeur</label>
                        </th>
                        <td>
                            <input type="email" id="tmm_email_from_email" name="tmm_email_from_email" 
                                   value="<?php echo esc_attr($settings['email_from_email']); ?>" class="regular-text">
                            <p class="description">Adresse email utilisée pour l'envoi des notifications.</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Test d'envoi d'email</h3>
                <p>Envoyez un email de test pour vérifier la configuration :</p>
                <button type="button" class="button" id="send-test-email">Envoyer un email de test</button>
                <div id="email-test-result"></div>
            </div>

            <!-- Onglet Maintenance -->
            <div id="maintenance" class="tmm-tab-content">
                <h2>Maintenance et nettoyage</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tmm_cleanup_analytics_days">Nettoyage analytics</label>
                        </th>
                        <td>
                            <input type="number" id="tmm_cleanup_analytics_days" name="tmm_cleanup_analytics_days" 
                                   value="<?php echo esc_attr($settings['cleanup_analytics_days']); ?>" min="30" max="365" class="small-text">
                            jours
                            <p class="description">Supprimer automatiquement les données analytics plus anciennes que X jours.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tmm_cleanup_chatbot_days">Nettoyage chatbot</label>
                        </th>
                        <td>
                            <input type="number" id="tmm_cleanup_chatbot_days" name="tmm_cleanup_chatbot_days" 
                                   value="<?php echo esc_attr($settings['cleanup_chatbot_days']); ?>" min="7" max="90" class="small-text">
                            jours
                            <p class="description">Supprimer automatiquement les conversations du chatbot plus anciennes que X jours.</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Actions manuelles</h3>
                <div class="tmm-maintenance-actions">
                    <button type="button" class="button" id="cleanup-analytics">
                        Nettoyer les analytics maintenant
                    </button>
                    <button type="button" class="button" id="cleanup-chatbot">
                        Nettoyer le chatbot maintenant
                    </button>
                    <button type="button" class="button button-secondary" id="export-data">
                        Exporter toutes les données
                    </button>
                </div>
                
                <div class="tmm-maintenance-status">
                    <h4>Statistiques de la base de données</h4>
                    <?php
                    global $wpdb;
                    $analytics_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics");
                    $badges_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_user_badges");
                    $chatbot_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_chatbot_messages");
                    ?>
                    <ul>
                        <li>Événements analytics : <strong><?php echo number_format($analytics_count); ?></strong></li>
                        <li>Badges attribués : <strong><?php echo number_format($badges_count); ?></strong></li>
                        <li>Messages du chatbot : <strong><?php echo number_format($chatbot_count); ?></strong></li>
                    </ul>
                </div>
            </div>

            <!-- Onglet Avancé -->
            <div id="advanced" class="tmm-tab-content">
                <h2>Paramètres avancés</h2>
                
                <div class="tmm-advanced-section">
                    <h3>Cache et performance</h3>
                    <button type="button" class="button" id="clear-cache">Vider le cache</button>
                    <p class="description">Vide tous les caches liés au dashboard.</p>
                </div>
                
                <div class="tmm-advanced-section">
                    <h3>Debug</h3>
                    <label>
                        <input type="checkbox" name="tmm_debug_mode" value="1" 
                               <?php checked(get_option('tmm_debug_mode', false)); ?>>
                        Activer le mode debug
                    </label>
                    <p class="description">Active les logs détaillés pour le dépannage.</p>
                </div>
                
                <div class="tmm-advanced-section">
                    <h3>Réinitialisation</h3>
                    <button type="button" class="button button-secondary" id="reset-badges">
                        Recalculer tous les badges
                    </button>
                    <button type="button" class="button button-delete" id="reset-all-data" 
                            onclick="return confirm('Êtes-vous sûr ? Cette action est irréversible !')">
                        Réinitialiser toutes les données
                    </button>
                    <p class="description">
                        <strong>Attention :</strong> La réinitialisation supprimera toutes les données des utilisateurs 
                        (badges, progression, certificats, etc.)
                    </p>
                </div>
                
                <div class="tmm-advanced-section">
                    <h3>Import/Export</h3>
                    <button type="button" class="button" id="export-settings">
                        Exporter les paramètres
                    </button>
                    <input type="file" id="import-settings" accept=".json" style="display: none;">
                    <button type="button" class="button" onclick="document.getElementById('import-settings').click()">
                        Importer les paramètres
                    </button>
                </div>
            </div>
        </div>

        <div class="tmm-submit-section">
            <?php submit_button('Sauvegarder les paramètres', 'primary', 'submit', false); ?>
        </div>
    </form>
</div>

<style>
.tmm-admin-header {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007cba;
}

.tmm-settings-tabs .nav-tab-wrapper {
    margin-bottom: 20px;
}

.tmm-tab-content {
    display: none;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tmm-tab-content.tmm-tab-active {
    display: block;
}

.tmm-logo-preview {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
    max-width: 200px;
}

.tmm-certificate-preview {
    background: #f0f0f0;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.tmm-cert-preview-container {
    background: white;
    padding: 40px;
    border-radius: 8px;
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.tmm-cert-logo {
    max-width: 80px;
    margin-bottom: 20px;
}

.tmm-cert-signatures {
    display: flex;
    justify-content: space-around;
    margin-top: 40px;
}

.tmm-signature-line {
    width: 150px;
    height: 1px;
    background: #333;
    margin: 0 auto 10px;
}

.tmm-maintenance-actions {
    margin: 20px 0;
}

.tmm-maintenance-actions .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.tmm-maintenance-status {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-top: 20px;
}

.tmm-advanced-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.tmm-submit-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.button-delete {
    background: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
}

#email-test-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
    display: none;
}

#email-test-result.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

#email-test-result.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gestion des onglets
    $('.nav-tab').click(function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tmm-tab-content').removeClass('tmm-tab-active');
        $(target).addClass('tmm-tab-active');
    });
    
    // Upload de logo
    $('#upload-logo-btn').click(function() {
        var frame = wp.media({
            title: 'Choisir un logo',
            button: { text: 'Utiliser cette image' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#tmm_certificate_logo').val(attachment.url);
            $('.tmm-logo-preview img').attr('src', attachment.url);
        });
        
        frame.open();
    });
    
    // Test d'email
    $('#send-test-email').click(function() {
        var button = $(this);
        var result = $('#email-test-result');
        
        button.prop('disabled', true).text('Envoi...');
        
        $.post(ajaxurl, {
            action: 'tmm_test_email',
            _wpnonce: '<?php echo wp_create_nonce('tmm_test_email'); ?>'
        }, function(response) {
            result.removeClass('success error').show();
            
            if (response.success) {
                result.addClass('success').text('Email de test envoyé avec succès !');
            } else {
                result.addClass('error').text('Erreur : ' + response.data.message);
            }
            
            button.prop('disabled', false).text('Envoyer un email de test');
        });
    });
    
    // Actions de maintenance
    $('#cleanup-analytics').click(function() {
        if (confirm('Nettoyer les anciennes données analytics ?')) {
            // Implémentation AJAX
        }
    });
    
    $('#cleanup-chatbot').click(function() {
        if (confirm('Nettoyer les anciens messages du chatbot ?')) {
            // Implémentation AJAX
        }
    });
    
    $('#reset-badges').click(function() {
        if (confirm('Recalculer tous les badges utilisateur ?')) {
            // Implémentation AJAX
        }
    });
});
</script>