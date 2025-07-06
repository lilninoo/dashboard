<?php
/**
 * Classe de gestion des certificats
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardCertificates {
    
    /**
     * Initialiser les hooks pour les certificats
     */
    public static function init() {
        // Endpoint pour générer les certificats
        add_action('init', [__CLASS__, 'addCertificateEndpoint']);
        add_action('template_redirect', [__CLASS__, 'handleCertificateRequest']);
        
        // Shortcode pour afficher un certificat
        add_shortcode('tmm_certificate', [__CLASS__, 'renderCertificateShortcode']);
        
        // AJAX pour régénérer un certificat
        add_action('wp_ajax_tmm_regenerate_certificate', [__CLASS__, 'regenerateCertificate']);
    }
    
    /**
     * Ajouter l'endpoint pour les certificats
     */
    public static function addCertificateEndpoint() {
        add_rewrite_endpoint('certificate', EP_ROOT);
        add_rewrite_rule('^certificate/([^/]+)/?', 'index.php?certificate=$matches[1]', 'top');
    }
    
    /**
     * Gérer les requêtes de certificat
     */
    public static function handleCertificateRequest() {
        $certificate_id = get_query_var('certificate');
        if (!$certificate_id) return;
        
        // Vérifier les paramètres
        $user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;
        $parcours_id = isset($_GET['parcours']) ? sanitize_text_field($_GET['parcours']) : '';
        
        if (!$user_id || !$parcours_id) {
            wp_die(__('Paramètres invalides', 'tmm-dashboard'));
        }
        
        // Vérifier que le certificat appartient bien à l'utilisateur
        $expected_id = md5($user_id . $parcours_id . NONCE_SALT);
        if ($certificate_id !== $expected_id) {
            wp_die(__('Certificat invalide', 'tmm-dashboard'));
        }
        
        // Générer et afficher le certificat
        self::generateCertificatePDF($user_id, $parcours_id);
        exit;
    }
    
    /**
     * Générer un certificat
     */
    public static function generateCertificate($user_id, $parcours_id) {
        $parcours = TMMDashboardParcours::getParcours($parcours_id);
        if (!$parcours) return false;
        
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        // Vérifier que le parcours est complété
        $progress = TMMDashboardParcours::getParcoursProgress($user_id, $parcours_id);
        if (!$progress['is_complete']) return false;
        
        $certificate_data = [
            'id' => wp_generate_uuid4(),
            'user_id' => $user_id,
            'parcours_id' => $parcours_id,
            'parcours_name' => $parcours['name'],
            'user_name' => $user->display_name,
            'date_completed' => current_time('mysql'),
            'certificate_number' => self::generateCertificateNumber($user_id, $parcours_id),
            'skills' => $parcours['skills'],
            'duration' => $parcours['duration'],
            'level' => $parcours['level']
        ];
        
        // Sauvegarder le certificat
        $certificates = get_user_meta($user_id, 'tmm_certificates', true) ?: [];
        
        // Vérifier si un certificat existe déjà pour ce parcours
        $existing_index = array_search($parcours_id, array_column($certificates, 'parcours_id'));
        if ($existing_index !== false) {
            $certificates[$existing_index] = $certificate_data;
        } else {
            $certificates[] = $certificate_data;
        }
        
        update_user_meta($user_id, 'tmm_certificates', $certificates);
        
        // Enregistrer l'événement
        TMMDashboardAnalytics::trackEvent($user_id, 'certificate_generated', [
            'parcours' => $parcours_id,
            'certificate_number' => $certificate_data['certificate_number']
        ]);
        
        return self::getCertificateUrl($user_id, $parcours_id);
    }
    
    /**
     * Générer l'URL d'un certificat
     */
    public static function getCertificateUrl($user_id, $parcours_id) {
        $certificate_id = md5($user_id . $parcours_id . NONCE_SALT);
        return add_query_arg([
            'user' => $user_id,
            'parcours' => $parcours_id
        ], home_url('/certificate/' . $certificate_id));
    }
    
    /**
     * Générer un numéro de certificat unique
     */
    private static function generateCertificateNumber($user_id, $parcours_id) {
        $prefix = strtoupper(substr($parcours_id, 0, 3));
        $year = date('Y');
        $unique = substr(md5($user_id . $parcours_id . time()), 0, 6);
        
        return sprintf('%s-%s-%s', $prefix, $year, strtoupper($unique));
    }
    
    /**
     * Générer le PDF du certificat
     */
    public static function generateCertificatePDF($user_id, $parcours_id) {
        // Récupérer les données du certificat
        $certificates = get_user_meta($user_id, 'tmm_certificates', true) ?: [];
        $certificate = null;
        
        foreach ($certificates as $cert) {
            if ($cert['parcours_id'] === $parcours_id) {
                $certificate = $cert;
                break;
            }
        }
        
        if (!$certificate) {
            wp_die(__('Certificat introuvable', 'tmm-dashboard'));
        }
        
        // Si TCPDF n'est pas disponible, utiliser une méthode alternative
        if (!class_exists('TCPDF')) {
            self::generateHTMLCertificate($certificate);
            return;
        }
        
        // Créer le PDF avec TCPDF
        require_once TMM_DASHBOARD_PATH . 'lib/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configuration du document
        $pdf->SetCreator('TeachMeMore');
        $pdf->SetAuthor('TeachMeMore');
        $pdf->SetTitle(__('Certificat de réussite', 'tmm-dashboard'));
        $pdf->SetSubject($certificate['parcours_name']);
        
        // Supprimer les marges
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Fond du certificat
        $bg_image = TMM_DASHBOARD_PATH . 'assets/img/certificate-bg.jpg';
        if (file_exists($bg_image)) {
            $pdf->Image($bg_image, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
        } else {
            // Fond coloré si pas d'image
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Rect(0, 0, 297, 210, 'F');
        }
        
        // Bordure décorative
        $pdf->SetDrawColor(255, 215, 0);
        $pdf->SetLineWidth(2);
        $pdf->Rect(10, 10, 277, 190, 'D');
        
        // Logo
        $logo = get_option('tmm_certificate_logo', TMM_DASHBOARD_URL . 'assets/img/logo.png');
        if ($logo) {
            $pdf->Image($logo, 133.5, 20, 30, 0, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Titre
        $pdf->SetY(60);
        $pdf->SetFont('helvetica', 'B', 36);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 20, __('CERTIFICAT DE RÉUSSITE', 'tmm-dashboard'), 0, 1, 'C');
        
        // Sous-titre
        $pdf->SetFont('helvetica', '', 16);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 10, __('Ce certificat est décerné à', 'tmm-dashboard'), 0, 1, 'C');
        
        // Nom du participant
        $pdf->SetY(95);
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetTextColor(255, 215, 0);
        $pdf->Cell(0, 15, strtoupper($certificate['user_name']), 0, 1, 'C');
        
        // Description
        $pdf->SetY(115);
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor(80, 80, 80);
        $description = sprintf(
            __('Pour avoir complété avec succès le parcours de formation', 'tmm-dashboard')
        );
        $pdf->Cell(0, 10, $description, 0, 1, 'C');
        
        // Nom du parcours
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 10, $certificate['parcours_name'], 0, 1, 'C');
        
        // Compétences acquises
        if (!empty($certificate['skills'])) {
            $pdf->SetY(140);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetTextColor(100, 100, 100);
            $skills_text = __('Compétences acquises : ', 'tmm-dashboard') . implode(', ', $certificate['skills']);
            $pdf->MultiCell(200, 10, $skills_text, 0, 'C', false, 1, 48.5, '', true);
        }
        
        // Date et numéro
        $pdf->SetY(165);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(120, 120, 120);
        
        // Date
        $date_text = sprintf(__('Délivré le %s', 'tmm-dashboard'), date_i18n(get_option('date_format'), strtotime($certificate['date_completed'])));
        $pdf->Text(50, 165, $date_text);
        
        // Numéro de certificat
        $pdf->Text(200, 165, __('N° ', 'tmm-dashboard') . $certificate['certificate_number']);
        
        // Signatures
        $pdf->SetY(175);
        
        // Signature gauche
        $pdf->SetX(60);
        $pdf->Cell(60, 0, '', 'T', 0, 'C');
        $pdf->SetX(60);
        $pdf->SetY(180);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 5, __('Directeur de formation', 'tmm-dashboard'), 0, 0, 'C');
        
        // Signature droite
        $pdf->SetY(175);
        $pdf->SetX(177);
        $pdf->Cell(60, 0, '', 'T', 0, 'C');
        $pdf->SetX(177);
        $pdf->SetY(180);
        $pdf->Cell(60, 5, __('Responsable pédagogique', 'tmm-dashboard'), 0, 0, 'C');
        
        // QR Code de vérification
        $verification_url = add_query_arg([
            'action' => 'verify',
            'certificate' => $certificate['certificate_number']
        ], home_url());
        
        $pdf->write2DBarcode($verification_url, 'QRCODE,H', 260, 170, 25, 25);
        
        // Sortie du PDF
        $filename = sprintf('certificat-%s-%s.pdf', 
            sanitize_title($certificate['parcours_name']), 
            $certificate['certificate_number']
        );
        
        $pdf->Output($filename, 'D');
    }
    
    /**
     * Générer un certificat HTML (alternative au PDF)
     */
    private static function generateHTMLCertificate($certificate) {
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo get_locale(); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Certificat de réussite', 'tmm-dashboard'); ?></title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Open+Sans:wght@400;600&display=swap');
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Open Sans', sans-serif;
                    background: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                
                .certificate-container {
                    max-width: 1000px;
                    width: 100%;
                    background: white;
                    box-shadow: 0 0 50px rgba(0,0,0,0.1);
                    position: relative;
                    overflow: hidden;
                }
                
                .certificate {
                    padding: 60px;
                    text-align: center;
                    position: relative;
                    background: url('<?php echo TMM_DASHBOARD_URL; ?>assets/img/certificate-pattern.png') no-repeat center center;
                    background-size: cover;
                }
                
                .certificate::before {
                    content: '';
                    position: absolute;
                    top: 20px;
                    left: 20px;
                    right: 20px;
                    bottom: 20px;
                    border: 3px solid #FFD700;
                    border-radius: 10px;
                    pointer-events: none;
                }
                
                .certificate-logo {
                    width: 120px;
                    height: auto;
                    margin-bottom: 30px;
                }
                
                .certificate-title {
                    font-family: 'Playfair Display', serif;
                    font-size: 42px;
                    font-weight: 700;
                    color: #333;
                    margin-bottom: 20px;
                    letter-spacing: 2px;
                }
                
                .certificate-subtitle {
                    font-size: 18px;
                    color: #666;
                    margin-bottom: 30px;
                }
                
                .certificate-recipient {
                    font-family: 'Playfair Display', serif;
                    font-size: 36px;
                    font-weight: 700;
                    color: #FFD700;
                    margin: 30px 0;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                .certificate-description {
                    font-size: 16px;
                    color: #555;
                    line-height: 1.8;
                    margin-bottom: 20px;
                }
                
                .certificate-course {
                    font-family: 'Playfair Display', serif;
                    font-size: 28px;
                    font-weight: 700;
                    color: #333;
                    margin: 20px 0;
                }
                
                .certificate-skills {
                    font-size: 14px;
                    color: #666;
                    margin: 20px auto;
                    max-width: 600px;
                    line-height: 1.6;
                }
                
                .certificate-footer {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-end;
                    margin-top: 60px;
                }
                
                .certificate-date,
                .certificate-number {
                    font-size: 12px;
                    color: #888;
                }
                
                .certificate-signatures {
                    display: flex;
                    justify-content: space-around;
                    margin-top: 60px;
                }
                
                .signature {
                    text-align: center;
                }
                
                .signature-line {
                    width: 200px;
                    height: 1px;
                    background: #333;
                    margin: 0 auto 10px;
                }
                
                .signature-title {
                    font-size: 14px;
                    color: #666;
                }
                
                .certificate-qr {
                    position: absolute;
                    bottom: 30px;
                    right: 30px;
                    width: 80px;
                    height: 80px;
                }
                
                .print-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #FFD700;
                    color: #333;
                    border: none;
                    padding: 12px 24px;
                    font-size: 16px;
                    font-weight: 600;
                    border-radius: 5px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .print-button:hover {
                    background: #FFC700;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                }
                
                @media print {
                    body {
                        background: white;
                        padding: 0;
                    }
                    
                    .certificate-container {
                        box-shadow: none;
                        max-width: 100%;
                    }
                    
                    .print-button {
                        display: none;
                    }
                    
                    .certificate {
                        padding: 40px;
                    }
                }
                
                @media (max-width: 768px) {
                    .certificate {
                        padding: 40px 20px;
                    }
                    
                    .certificate-title {
                        font-size: 32px;
                    }
                    
                    .certificate-recipient {
                        font-size: 28px;
                    }
                    
                    .certificate-course {
                        font-size: 22px;
                    }
                    
                    .certificate-signatures {
                        flex-direction: column;
                        gap: 30px;
                    }
                }
            </style>
        </head>
        <body>
            <button class="print-button" onclick="window.print()">
                <?php _e('📥 Télécharger PDF', 'tmm-dashboard'); ?>
            </button>
            
            <div class="certificate-container">
                <div class="certificate">
                    <img src="<?php echo esc_url(get_option('tmm_certificate_logo', TMM_DASHBOARD_URL . 'assets/img/logo.png')); ?>" 
                         alt="<?php echo esc_attr(get_bloginfo('name')); ?>" 
                         class="certificate-logo">
                    
                    <h1 class="certificate-title"><?php _e('CERTIFICAT DE RÉUSSITE', 'tmm-dashboard'); ?></h1>
                    
                    <p class="certificate-subtitle"><?php _e('Ce certificat est décerné à', 'tmm-dashboard'); ?></p>
                    
                    <h2 class="certificate-recipient"><?php echo esc_html($certificate['user_name']); ?></h2>
                    
                    <p class="certificate-description">
                        <?php _e('Pour avoir complété avec succès le parcours de formation', 'tmm-dashboard'); ?>
                    </p>
                    
                    <h3 class="certificate-course"><?php echo esc_html($certificate['parcours_name']); ?></h3>
                    
                    <?php if (!empty($certificate['skills'])): ?>
                    <p class="certificate-skills">
                        <strong><?php _e('Compétences acquises :', 'tmm-dashboard'); ?></strong><br>
                        <?php echo esc_html(implode(' • ', $certificate['skills'])); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="certificate-footer">
                        <div class="certificate-date">
                            <?php printf(__('Délivré le %s', 'tmm-dashboard'), date_i18n(get_option('date_format'), strtotime($certificate['date_completed']))); ?>
                        </div>
                        <div class="certificate-number">
                            <?php echo __('N° ', 'tmm-dashboard') . esc_html($certificate['certificate_number']); ?>
                        </div>
                    </div>
                    
                    <div class="certificate-signatures">
                        <div class="signature">
                            <div class="signature-line"></div>
                            <p class="signature-title"><?php _e('Directeur de formation', 'tmm-dashboard'); ?></p>
                        </div>
                        <div class="signature">
                            <div class="signature-line"></div>
                            <p class="signature-title"><?php _e('Responsable pédagogique', 'tmm-dashboard'); ?></p>
                        </div>
                    </div>
                    
                    <?php
                    $verification_url = add_query_arg([
                        'action' => 'verify',
                        'certificate' => $certificate['certificate_number']
                    ], home_url());
                    ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($verification_url); ?>" 
                         alt="QR Code" 
                         class="certificate-qr">
                </div>
            </div>
            
            <script>
                // Auto-print pour simuler le téléchargement PDF
                document.querySelector('.print-button').addEventListener('click', function() {
                    window.print();
                });
                
                // Ajuster la taille pour l'impression
                window.addEventListener('beforeprint', function() {
                    document.body.style.zoom = '0.7';
                });
                
                window.addEventListener('afterprint', function() {
                    document.body.style.zoom = '1';
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Régénérer un certificat (AJAX)
     */
    public static function regenerateCertificate() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'tmm_dashboard_nonce')) {
            wp_send_json_error(['message' => __('Requête non autorisée', 'tmm-dashboard')]);
        }
        
        $user_id = get_current_user_id();
        $parcours_id = sanitize_text_field($_POST['parcours_id'] ?? '');
        
        if (!$parcours_id) {
            wp_send_json_error(['message' => __('Parcours invalide', 'tmm-dashboard')]);
        }
        
        // Vérifier que le parcours est complété
        $progress = TMMDashboardParcours::getParcoursProgress($user_id, $parcours_id);
        if (!$progress['is_complete']) {
            wp_send_json_error(['message' => __('Parcours non complété', 'tmm-dashboard')]);
        }
        
        // Régénérer le certificat
        $url = self::generateCertificate($user_id, $parcours_id);
        
        if ($url) {
            wp_send_json_success([
                'message' => __('Certificat régénéré avec succès', 'tmm-dashboard'),
                'url' => $url
            ]);
        } else {
            wp_send_json_error(['message' => __('Erreur lors de la régénération', 'tmm-dashboard')]);
        }
    }
    
    /**
     * Shortcode pour afficher un certificat
     */
    public static function renderCertificateShortcode($atts) {
        $atts = shortcode_atts([
            'parcours_id' => '',
            'user_id' => get_current_user_id(),
            'style' => 'card' // card, button, link
        ], $atts);
        
        if (!$atts['parcours_id'] || !$atts['user_id']) {
            return '';
        }
        
        $certificates = get_user_meta($atts['user_id'], 'tmm_certificates', true) ?: [];
        $certificate = null;
        
        foreach ($certificates as $cert) {
            if ($cert['parcours_id'] === $atts['parcours_id']) {
                $certificate = $cert;
                break;
            }
        }
        
        if (!$certificate) {
            return '<p>' . __('Certificat non disponible', 'tmm-dashboard') . '</p>';
        }
        
        $url = self::getCertificateUrl($atts['user_id'], $atts['parcours_id']);
        
        switch ($atts['style']) {
            case 'button':
                return sprintf(
                    '<a href="%s" class="tmm-certificate-button ps-button ps-button-primary" target="_blank">%s</a>',
                    esc_url($url),
                    __('📄 Télécharger le certificat', 'tmm-dashboard')
                );
                
            case 'link':
                return sprintf(
                    '<a href="%s" class="tmm-certificate-link" target="_blank">%s</a>',
                    esc_url($url),
                    __('Télécharger le certificat', 'tmm-dashboard')
                );
                
            case 'card':
            default:
                ob_start();
                ?>
                <div class="tmm-certificate-card">
                    <div class="certificate-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="certificate-info">
                        <h4><?php echo esc_html($certificate['parcours_name']); ?></h4>
                        <p><?php printf(__('Délivré le %s', 'tmm-dashboard'), date_i18n(get_option('date_format'), strtotime($certificate['date_completed']))); ?></p>
                        <p class="certificate-number"><?php echo __('N° ', 'tmm-dashboard') . esc_html($certificate['certificate_number']); ?></p>
                    </div>
                    <div class="certificate-actions">
                        <a href="<?php echo esc_url($url); ?>" class="ps-button ps-button-primary" target="_blank">
                            <?php _e('📄 Télécharger', 'tmm-dashboard'); ?>
                        </a>
                    </div>
                </div>
                <?php
                return ob_get_clean();
        }
    }
    
    /**
     * Vérifier un certificat
     */
    public static function verifyCertificate($certificate_number) {
        global $wpdb;
        
        // Rechercher le certificat dans toutes les métadonnées utilisateur
        $users = $wpdb->get_col(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'tmm_certificates'"
        );
        
        foreach ($users as $user_id) {
            $certificates = get_user_meta($user_id, 'tmm_certificates', true) ?: [];
            
            foreach ($certificates as $cert) {
                if ($cert['certificate_number'] === $certificate_number) {
                    return [
                        'valid' => true,
                        'user_name' => $cert['user_name'],
                        'parcours_name' => $cert['parcours_name'],
                        'date_completed' => $cert['date_completed'],
                        'skills' => $cert['skills'] ?? []
                    ];
                }
            }
        }
        
        return ['valid' => false];
    }
    
    /**
     * Obtenir tous les certificats d'un utilisateur
     */
    public static function getUserCertificates($user_id) {
        return get_user_meta($user_id, 'tmm_certificates', true) ?: [];
    }
    
    /**
     * Compter les certificats d'un utilisateur
     */
    public static function getUserCertificatesCount($user_id) {
        $certificates = self::getUserCertificates($user_id);
        return count($certificates);
    }
}