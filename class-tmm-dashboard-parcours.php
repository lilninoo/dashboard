<?php
/**
 * Classe de gestion des parcours d'apprentissage
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardParcours {
    
    
    
    
    
    /**
     * Définition des parcours disponibles
     */
    private static $parcours = [
        'sysops' => [
            'id' => 'sysops',
            'name' => 'SysOps',
            'membership_id' => 29,
            'description' => 'Parcours d\'administration système et automatisation avec Ansible',
            'duration' => '3 mois',
            'level' => 'Intermédiaire',
            'skills' => ['Linux', 'Windows Server', 'Ansible', 'Docker', 'Kubernetes'],
            'certification' => true,
            'courses' => [
                1 => [ // Mois 1 – Fondation infrastructure
                    [
                        'id' => 14658, 
                        'reco' => "Révise le chapitre 1 au chapitre 9. Après le bootcamp tu peux réviser les autres chapitres pour te perfectionner",
                        'required' => true
                    ],
                    [
                        'id' => 10269, 
                        'reco' => "Ce cours est Optionnel. Si tu sais créer des machines virtuelles avec VBox ou Vmware Workstation pas besoin de suivre ce cours",
                        'required' => false
                    ],
                    [
                        'id' => 8061, 
                        'reco' => "Le Workshop 1 et 2 sont suffisants pour le bootcamp. Après le bootcamp, je te conseille de suivre l'intégralité du cours",
                        'required' => true
                    ],
                    [
                        'id' => 22582, 
                        'reco' => 'Révise tout le cours',
                        'required' => true
                    ],
                    [
                        'id' => 19941, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 23661, 
                        'reco' => "Révise le chapitre 1 jusqu'au chapitre 7",
                        'required' => true
                    ]
                ],
                2 => [ // Mois 2 – Automatisation
                    [
                        'id' => 20279, 
                        'reco' => "Cours optionnel. Réservé uniquement si tu suis un parcours NetOps ou si tu es SysAdmin & Réseau",
                        'required' => false
                    ],
                    [
                        'id' => 45863, 
                        'reco' => "Uniquement si tu es SysAdmin. Révise tous les cours",
                        'required' => true
                    ],
                    [
                        'id' => 35714, 
                        'reco' => "Révise tous les cours",
                        'required' => true
                    ],
                    [
                        'id' => 9179,  
                        'reco' => "Cours optionnel. réservé uniquement pour les personnes qui suivent un parcours NetOps",
                        'required' => false
                    ]
                ],
                3 => [ // Mois 3 – Sécurisation, stabilisation, soutenance
                    [
                        'id' => 37688, 
                        'reco' => "Révise tous les cours. Très utile pour apprendre à dépanner un cluster kubernetes",
                        'required' => true
                    ],
                    [
                        'id' => 45863, 
                        'reco' => 'Approfondir les workflows Ansible et leur sécurité (TLS, AD)',
                        'required' => true
                    ]
                ]
            ],
            'milestones' => [
                1 => [
                    1 => "Installer Proxmox, comprendre la virtualisation, configurer le réseau bridge",
                    2 => "Créer les VMs Linux et Windows. Gérer le stockage LVM, snapshots",
                    3 => "Administrer Ubuntu en ligne de commande. Installer et configurer Active Directory",
                    4 => "Installer Docker. Déployer Gitea via Docker Compose. Versionner les playbooks Ansible"
                ],
                2 => [
                    1 => "Découvrir Ansible et créer les premiers playbooks. Installer AWX sur Kubernetes",
                    2 => "Créer l'inventaire dynamique, connecter Gitea à AWX, gérer les accès utilisateurs",
                    3 => "Automatiser le patch management Linux et Windows. Tester les playbooks",
                    4 => "Commencer la gestion réseau avec Ansible + GNS3. Tests et rollback"
                ],
                3 => [
                    1 => "Approfondir les rôles, créer des workflows robustes. Mettre en place la supervision et la journalisation",
                    2 => "Sécuriser les communications (Traefik, HTTPS), intégrer l'authentification AD dans AWX",
                    3 => "Consolider tous les playbooks et déploiements. Rédiger la documentation technique",
                    4 => "Soutenance du projet. Démonstration live et validation finale"
                ]
            ]
        ],
        'devops' => [
            'id' => 'devops',
            'name' => 'Bootcamp DevOps',
            'membership_id' => 30,
            'description' => 'Formation complète DevOps avec conteneurisation, CI/CD et cloud',
            'duration' => '3 mois',
            'level' => 'Intermédiaire à Avancé',
            'skills' => ['Docker', 'Kubernetes', 'CI/CD', 'Terraform', 'AWS', 'GitOps'],
            'certification' => true,
            'courses' => [
                1 => [ // Mois 1 – Conteneurisation & GitOps local
                    [
                        'id' => 19941, 
                        'reco' => "Révise le chapitre 1 jusqu'au chapitre 13",
                        'required' => true
                    ],
                    [
                        'id' => 23661, 
                        'reco' => "Révise le Chapitre 1 jusqu'au chapitre 7",
                        'required' => true
                    ],
                    [
                        'id' => 22582, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 35714, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 37200, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ]
                ],
                2 => [ // Mois 2 – DevOps Cloud & Infrastructure as Code
                    [
                        'id' => 38399, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 29810, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 72342, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 23170, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 24380, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ]
                ],
                3 => [ // Mois 3 – Observabilité, sécurité, soutenance
                    [
                        'id' => 52614, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 47166, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 48876, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ],
                    [
                        'id' => 46369, 
                        'reco' => "Révise tout le cours",
                        'required' => true
                    ]
                ]
            ],
            'milestones' => [
                1 => [
                    1 => "Installer Docker, créer des conteneurs pour une stack applicative. Introduction au DevOps",
                    2 => "Maîtriser Git et GitHub. Automatiser les builds avec des hooks et gérer les branches",
                    3 => "Apprendre les bases de l'administration Linux. Scripts bash, gestion d'utilisateurs",
                    4 => "Installer Kubernetes localement. Déployer une app avec kubectl et YAML"
                ],
                2 => [
                    1 => "Découvrir Terraform, écrire des fichiers .tf, provisionner des ressources cloud",
                    2 => "Déployer une infrastructure AWS complète avec Terraform. Sécurité cloud",
                    3 => "Mettre en place GitLab CI/CD. Gérer les runners et les pipelines",
                    4 => "Superviser avec Prometheus et visualiser avec Grafana"
                ],
                3 => [
                    1 => "Intégrer la sécurité dans le pipeline CI/CD. Introduction DevSecOps",
                    2 => "Centraliser les logs avec ELK. Analyser les erreurs d'applications",
                    3 => "Automatiser le déploiement continu avec ArgoCD. Gérer le drift",
                    4 => "Déployer une app serverless. Finalisation et soutenance"
                ]
            ]
        ]
    ];

    /**
     * Obtenir les informations d'un parcours
     */
    public static function getParcours($parcours_id) {
        return self::$parcours[$parcours_id] ?? null;
    }

    /**
     * Obtenir tous les parcours
     */
    public static function getAllParcours() {
        return self::$parcours;
    }

    /**
     * Obtenir le parcours d'un utilisateur basé sur son membership
     */
    public static function getUserParcours($user_id) {
        $membership = pmpro_getMembershipLevelForUser($user_id);
        if (!$membership) return null;

        foreach (self::$parcours as $parcours_id => $parcours_data) {
            if ($parcours_data['membership_id'] == $membership->id) {
                return $parcours_data;
            }
        }

        return null;
    }

    /**
     * Sauvegarder la progression d'une semaine
     */
    public static function saveWeekProgress($user_id, $parcours_id, $month, $week, $checked) {
        $key = self::getTimelineKey($parcours_id, $month);
        $saved_weeks = get_user_meta($user_id, $key, true) ?: [];

        if ($checked) {
            if (!in_array($week, $saved_weeks)) {
                $saved_weeks[] = $week;
            }
        } else {
            $saved_weeks = array_diff($saved_weeks, [$week]);
        }

        update_user_meta($user_id, $key, array_values($saved_weeks));
        update_user_meta($user_id, $key . '_last_update', current_time('timestamp'));

        // Vérifier si le parcours est complété
        self::checkParcoursCompletion($user_id, $parcours_id);

        // Enregistrer l'événement
        TMMDashboardAnalytics::trackEvent($user_id, 'week_progress', [
            'parcours' => $parcours_id,
            'month' => $month,
            'week' => $week,
            'checked' => $checked
        ]);

        return true;
    }

    /**
     * Obtenir la clé de timeline
     */
    private static function getTimelineKey($parcours_id, $month) {
        return 'timeline_' . sanitize_title($parcours_id) . '_mois_' . $month;
    }

    /**
     * Vérifier si un parcours est complété
     */
    private static function checkParcoursCompletion($user_id, $parcours_id) {
        $parcours = self::getParcours($parcours_id);
        if (!$parcours) return false;

        $total_checked = 0;
        $total_possible = 0;

        foreach ($parcours['courses'] as $month => $courses) {
            $key = self::getTimelineKey($parcours_id, $month);
            $weeks = get_user_meta($user_id, $key, true) ?: [];
            $total_checked += count($weeks);
            $total_possible += 4; // 4 semaines par mois
        }

        $completion_percentage = $total_possible > 0 ? ($total_checked / $total_possible) * 100 : 0;
        $is_complete = $completion_percentage >= 100;

        if ($is_complete) {
            update_user_meta($user_id, 'tmm_parcours_complet', true);
            update_user_meta($user_id, 'tmm_parcours_completed_' . $parcours_id, current_time('mysql'));
            
            // Créer un certificat
            self::generateCertificate($user_id, $parcours_id);
            
            // Mettre à jour les badges
            TMMDashboardBadges::updateBadgeStatus($user_id);
            
            // Notification
            self::notifyParcoursCompletion($user_id, $parcours_id);
        }

        return $is_complete;
    }

    /**
     * Générer un certificat pour un parcours complété
     */
    private static function generateCertificate($user_id, $parcours_id) {
        $parcours = self::getParcours($parcours_id);
        if (!$parcours) return;

        $user = get_user_by('id', $user_id);
        if (!$user) return;

        $certificate_data = [
            'id' => wp_generate_uuid4(),
            'user_id' => $user_id,
            'parcours_id' => $parcours_id,
            'title' => sprintf(__('Certificat %s', 'tmm-dashboard'), $parcours['name']),
            'date' => current_time('mysql'),
            'url' => self::generateCertificateUrl($user_id, $parcours_id)
        ];

        // Récupérer les certificats existants
        $certificates = get_user_meta($user_id, 'tmm_certificates', true) ?: [];
        $certificates[] = $certificate_data;
        update_user_meta($user_id, 'tmm_certificates', $certificates);

        return $certificate_data;
    }

    /**
     * Générer l'URL du certificat
     */
    private static function generateCertificateUrl($user_id, $parcours_id) {
        $certificate_id = md5($user_id . $parcours_id . NONCE_SALT);
        return add_query_arg([
            'tmm_certificate' => $certificate_id,
            'user' => $user_id,
            'parcours' => $parcours_id
        ], home_url('/certificate/'));
    }

    /**
     * Notifier la complétion d'un parcours
     */
    private static function notifyParcoursCompletion($user_id, $parcours_id) {
        $parcours = self::getParcours($parcours_id);
        $user = get_user_by('id', $user_id);
        
        if (!$parcours || !$user) return;

        // Notification interne
        $notification = [
            'user_id' => $user_id,
            'type' => 'parcours_completed',
            'parcours' => $parcours_id,
            'message' => sprintf(
                __('Félicitations ! Vous avez complété le parcours "%s". Votre certificat est disponible.', 'tmm-dashboard'),
                $parcours['name']
            ),
            'date' => current_time('mysql'),
            'read' => false
        ];

        $notifications = get_user_meta($user_id, 'tmm_notifications', true) ?: [];
        array_unshift($notifications, $notification);
        update_user_meta($user_id, 'tmm_notifications', array_slice($notifications, 0, 50));

        // Email
        $subject = sprintf(__('Félicitations ! Parcours %s complété', 'tmm-dashboard'), $parcours['name']);
        $message = sprintf(
            __("Bonjour %s,\n\nFélicitations ! Vous avez complété avec succès le parcours %s.\n\nVotre certificat est maintenant disponible dans votre tableau de bord.\n\nCordialement,\nL'équipe %s", 'tmm-dashboard'),
            $user->display_name,
            $parcours['name'],
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Obtenir la progression d'un parcours
     */
    public static function getParcoursProgress($user_id, $parcours_id) {
        $parcours = self::getParcours($parcours_id);
        if (!$parcours) return null;

        $progress = [
            'parcours_id' => $parcours_id,
            'months' => [],
            'total_weeks_checked' => 0,
            'total_weeks' => 0,
            'percentage' => 0,
            'is_complete' => false,
            'courses_progress' => []
        ];

        foreach ($parcours['courses'] as $month => $courses) {
            $key = self::getTimelineKey($parcours_id, $month);
            $weeks_checked = get_user_meta($user_id, $key, true) ?: [];
            
            $progress['months'][$month] = [
                'weeks_checked' => $weeks_checked,
                'total_weeks' => 4,
                'percentage' => count($weeks_checked) * 25,
                'is_complete' => count($weeks_checked) === 4
            ];

            $progress['total_weeks_checked'] += count($weeks_checked);
            $progress['total_weeks'] += 4;

            // Progression des cours du mois
            foreach ($courses as $course_data) {
                $course_progress = self::getCourseProgress($user_id, $course_data['id']);
                $progress['courses_progress'][$course_data['id']] = $course_progress;
            }
        }

        $progress['percentage'] = $progress['total_weeks'] > 0 
            ? round(($progress['total_weeks_checked'] / $progress['total_weeks']) * 100) 
            : 0;
        $progress['is_complete'] = $progress['percentage'] >= 100;

        return $progress;
    }

    /**
     * Obtenir la progression d'un cours
     */
    private static function getCourseProgress($user_id, $course_id) {
        if (!function_exists('learn_press_get_user_course_progress')) {
            return ['percentage' => 0, 'is_enrolled' => false];
        }

        $progress = learn_press_get_user_course_progress($user_id, $course_id);
        $is_enrolled = $progress !== false && $progress !== null;

        return [
            'course_id' => $course_id,
            'percentage' => $is_enrolled ? intval($progress) : 0,
            'is_enrolled' => $is_enrolled,
            'status' => $is_enrolled ? ($progress >= 100 ? 'completed' : 'in_progress') : 'not_started'
        ];
    }

    /**
     * Obtenir la prochaine semaine non cochée
     */
    public static function getNextUncheckedWeek($user_id, $parcours_id) {
        $parcours = self::getParcours($parcours_id);
        if (!$parcours) return null;

        foreach ($parcours['courses'] as $month => $courses) {
            $key = self::getTimelineKey($parcours_id, $month);
            $checked_weeks = get_user_meta($user_id, $key, true) ?: [];
            
            for ($week = 1; $week <= 4; $week++) {
                if (!in_array($week, $checked_weeks)) {
                    return [
                        'month' => $month,
                        'week' => $week,
                        'label' => sprintf(__('Mois %d - Semaine %d', 'tmm-dashboard'), $month, $week)
                    ];
                }
            }
        }

        return null; // Toutes les semaines sont cochées
    }

    /**
     * Obtenir les outils du parcours
     */
    public static function getParcoursTools($parcours_id) {
        $tools = [
            'sysops' => [
                'project' => 'https://github.com/Teachmemore-Ressources/projet-patchmanagement/wiki/Projet‐devops',
                'pdf' => 'https://github.com/Teachmemore-Ressources/projet-patchmanagement/blob/main/Cahier%20des%20Charges.%20TEACHMEMORE%20BOOTACAMPdocx.pdf',
                'resources' => 'https://github.com/angando/DEVOPS',
                'library' => 'https://github.com/angando/biblio'
            ],
            'devops' => [
                'project' => 'https://github.com/Teachmemore-Ressources/projet-patchmanagement/wiki/Bootcamps-devops',
                'pdf' => 'https://github.com/Teachmemore-Ressources/projet-devops-dev/raw/main/Cahier_des_charges_BOOTCAMP_DEVOPS.pdf',
                'resources' => 'https://github.com/angando/DEVOPS',
                'library' => 'https://github.com/angando/biblio'
            ]
        ];

        return $tools[$parcours_id] ?? [
            'project' => '#',
            'pdf' => '#',
            'resources' => '#',
            'library' => '#'
        ];
    }

    /**
     * Rendre le HTML d'un parcours
     */
    public static function renderParcours($user_id) {
        $user_parcours = self::getUserParcours($user_id);
        
        if (!$user_parcours) {
            return '<div class="ps-no-parcours">' . 
                   '<p>' . __('Vous devez avoir un abonnement actif pour accéder aux parcours.', 'tmm-dashboard') . '</p>' .
                   '<a href="' . esc_url(pmpro_url('levels')) . '" class="ps-button ps-button-primary">' . 
                   __('Voir les abonnements', 'tmm-dashboard') . '</a>' .
                   '</div>';
        }

        $parcours_id = $user_parcours['id'];
        $progress = self::getParcoursProgress($user_id, $parcours_id);
        $tools = self::getParcoursTools($parcours_id);
        $next_week = self::getNextUncheckedWeek($user_id, $parcours_id);

        // Vérifier l'inactivité
        $last_activity = self::getLastParcoursActivity($user_id, $parcours_id);
        $days_inactive = $last_activity ? floor((current_time('timestamp') - $last_activity) / DAY_IN_SECONDS) : 0;

        ob_start();
        ?>
        <div class="ps-parcours-container" data-parcours="<?php echo esc_attr($parcours_id); ?>">
            
            <?php if ($days_inactive > 14) : ?>
            <div class="ps-warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <?php printf(__('🚨 Inactif depuis %d jours. Pensez à reprendre votre progression !', 'tmm-dashboard'), $days_inactive); ?>
            </div>
            <?php endif; ?>

            <!-- Outils rapides -->
            <div class="student-tools-box-horizontal">
                <h3 class="tools-title"><?php _e('Outils rapides', 'tmm-dashboard'); ?></h3>
                <div class="tools-list-horizontal">
                    <a href="<?php echo esc_url($tools['project']); ?>" class="tool-item" data-tooltip="<?php esc_attr_e('Voir le résumé du projet', 'tmm-dashboard'); ?>">
                        <i class="fas fa-file-alt tool-icon"></i>
                        <span><?php _e('Projet', 'tmm-dashboard'); ?></span>
                    </a>
                    <a href="<?php echo esc_url($tools['pdf']); ?>" class="tool-item" data-tooltip="<?php esc_attr_e('Cahier de projet', 'tmm-dashboard'); ?>" target="_blank">
                        <i class="fas fa-file-pdf tool-icon"></i>
                        <span><?php _e('Cahier', 'tmm-dashboard'); ?></span>
                    </a>
                    <a href="https://koalendar.com/e/reserve-ton-accompagnement-personnalise" class="tool-item" data-tooltip="<?php esc_attr_e('Contacter le coach', 'tmm-dashboard'); ?>">
                        <i class="fas fa-envelope tool-icon"></i>
                        <span><?php _e('Coach', 'tmm-dashboard'); ?></span>
                    </a>
                    <a href="<?php echo esc_url($tools['resources']); ?>" class="tool-item" data-tooltip="<?php esc_attr_e('Voir les ressources', 'tmm-dashboard'); ?>">
                        <i class="fab fa-github tool-icon"></i>
                        <span><?php _e('Ressources', 'tmm-dashboard'); ?></span>
                    </a>
                    <a href="<?php echo esc_url($tools['library']); ?>" class="tool-item" data-tooltip="<?php esc_attr_e('Bibliothèque de fichiers', 'tmm-dashboard'); ?>">
                        <i class="fas fa-folder-open tool-icon"></i>
                        <span><?php _e('Bibliothèque', 'tmm-dashboard'); ?></span>
                    </a>
                </div>
            </div>

            <!-- Parcours -->
            <div class="learning-path">
                <h3 class="learning-section-title">
                    <?php echo esc_html(sprintf(__('Parcours : %s', 'tmm-dashboard'), $user_parcours['name'])); ?>
                </h3>

                <!-- Progression globale -->
                <div class="global-progress-container">
                    <div class="global-progress-bar" style="width:<?php echo esc_attr($progress['percentage']); ?>%;"></div>
                    <p class="global-progress-text">
                        <?php echo esc_html(sprintf(__('%d%% du parcours complété', 'tmm-dashboard'), $progress['percentage'])); ?>
                    </p>
                    
                    <?php if ($progress['is_complete']) : ?>
                    <div class="global-badge">
                        🎉 <?php _e('Parcours complété ! Vous pouvez maintenant demander votre certificat !', 'tmm-dashboard'); ?>
                    </div>
                    <a href="<?php echo esc_url(TMMDashboardCertificates::getCertificateUrl($user_id, $parcours_id)); ?>" 
                       class="lp-enroll-btn" target="_blank">
                        📄 <?php _e('Télécharger mon certificat PDF', 'tmm-dashboard'); ?>
                    </a>
                    <?php endif; ?>
                </div>

                <?php 
                // Afficher chaque mois
                foreach ($user_parcours['courses'] as $month => $courses) : 
                    $month_progress = $progress['months'][$month];
                ?>
                <h4 class="learning-month-title">
                    <?php echo esc_html(sprintf(__('Mois %d', 'tmm-dashboard'), $month)); ?>
                </h4>

                <!-- Cours du mois -->
                <?php foreach ($courses as $course_data) : 
                    $course = get_post($course_data['id']);
                    if (!$course || $course->post_status !== 'publish') continue;
                    
                    $course_url = get_permalink($course_data['id']);
                    $thumbnail = get_the_post_thumbnail_url($course_data['id'], 'medium');
                    $course_progress = $progress['courses_progress'][$course_data['id']] ?? ['percentage' => 0, 'is_enrolled' => false];
                ?>
                <div class="learning-course-card <?php echo $course_data['required'] ? 'required' : 'optional'; ?>">
                    <a href="<?php echo esc_url($course_url); ?>" class="learning-course-link">
                        <?php if ($thumbnail) : ?>
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($course->post_title); ?>" class="course-thumbnail">
                        <?php endif; ?>
                        <h5 class="learning-course-title"><?php echo esc_html($course->post_title); ?></h5>
                    </a>
                    
                    <div class="learning-progress-bar-container">
                        <div class="learning-progress-bar" style="width:<?php echo esc_attr($course_progress['percentage']); ?>%;"></div>
                    </div>
                    
                    <p class="learning-course-status">
                        <?php 
                        if ($course_progress['percentage'] > 0) {
                            echo esc_html(sprintf(__('%d%% complété', 'tmm-dashboard'), $course_progress['percentage']));
                        } else {
                            _e('Non débuté', 'tmm-dashboard');
                        }
                        ?>
                    </p>
                    
                    <span class="info-icon" data-reco="<?php echo esc_attr($course_data['reco']); ?>">
                        <i class="fas fa-info-circle"></i>
                    </span>
                    
                    <?php if (!$course_progress['is_enrolled']) : ?>
                    <a href="<?php echo esc_url($course_url); ?>" class="lp-enroll-btn lp-start-btn">
                        <i class="fas fa-play-circle"></i> <?php _e('Démarrer', 'tmm-dashboard'); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <!-- Timeline des semaines -->
                <div class="timeline-weeks-enhanced">
                    <h5 class="timeline-label">
                        <i class="fas fa-clock"></i> <?php _e('Progression hebdomadaire', 'tmm-dashboard'); ?>
                    </h5>
                    <div class="timeline-week-list">
                        <?php 
                        for ($week = 1; $week <= 4; $week++) : 
                            $is_checked = in_array($week, $month_progress['weeks_checked']);
                            $milestone = $user_parcours['milestones'][$month][$week] ?? __('Objectif à venir', 'tmm-dashboard');
                        ?>
                        <label class="timeline-week-box <?php echo $is_checked ? 'checked' : ''; ?>">
                            <input type="checkbox" 
                                   class="week-checkbox" 
                                   data-parcours="<?php echo esc_attr($parcours_id); ?>"
                                   data-month="<?php echo esc_attr($month); ?>"
                                   value="<?php echo esc_attr($week); ?>" 
                                   <?php checked($is_checked); ?>>
                            <span class="week-title">
                                <?php echo esc_html(sprintf(__('Semaine %d', 'tmm-dashboard'), $week)); ?>
                            </span>
                            <span class="week-desc"><?php echo esc_html($milestone); ?></span>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Progression du mois -->
                <div class="month-progress-container">
                    <div class="month-progress-bar" style="width:<?php echo esc_attr($month_progress['percentage']); ?>%;"></div>
                    <p class="month-progress-text">
                        <?php echo esc_html(sprintf(__('%d%% complété', 'tmm-dashboard'), $month_progress['percentage'])); ?>
                    </p>
                </div>
                
                <?php if ($month_progress['is_complete']) : ?>
                <div class="month-badge">
                    🏅 <?php _e('Mois terminé ! Continuez sur votre lancée !', 'tmm-dashboard'); ?>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtenir la dernière activité sur un parcours
     */
    private static function getLastParcoursActivity($user_id, $parcours_id) {
        $last_activity = 0;
        $parcours = self::getParcours($parcours_id);
        
        if (!$parcours) return 0;

        foreach ($parcours['courses'] as $month => $courses) {
            $key = self::getTimelineKey($parcours_id, $month);
            $update_time = get_user_meta($user_id, $key . '_last_update', true);
            
            if ($update_time && $update_time > $last_activity) {
                $last_activity = $update_time;
            }
        }

        return $last_activity;
    }

    /**
     * Obtenir le nombre d'utilisateurs d'un parcours
     * @param string $parcours_id ID du parcours
     * @return int Nombre d'utilisateurs
     */
    public static function getParcoursUserCount($parcours_id) {
        global $wpdb;
        
        $parcours = self::getParcours($parcours_id);
        if (!$parcours) return 0;
        
        // Vérifier d'abord si la table PMPro existe
        $table_name = $wpdb->prefix . 'pmpro_memberships_users';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Si la table n'existe pas, compter via user meta
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) 
                 FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'tmm_user_parcours' 
                 AND meta_value = %s",
                $parcours_id
            ));
            return intval($count);
        }
        
        // Sinon, compter les utilisateurs ayant le membership associé
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->prefix}pmpro_memberships_users 
             WHERE membership_id = %d 
             AND status = 'active'",
            $parcours['membership_id']
        ));
        
        return intval($count);
    }

    /**
     * Vérifier les alertes de progression
     * @param int $user_id ID de l'utilisateur
     * @param string $parcours_id ID du parcours
     * @return array Liste des alertes
     */
    public static function checkProgressAlerts($user_id, $parcours_id) {
        $alerts = [];
        
        // Vérifier l'inactivité
        $last_activity = self::getLastParcoursActivity($user_id, $parcours_id);
        if ($last_activity) {
            $days_inactive = (time() - $last_activity) / (60 * 60 * 24);
            
            if ($days_inactive > 7 && $days_inactive <= 14) {
                $alerts[] = [
                    'type' => 'inactivity',
                    'level' => 'warning',
                    'message' => sprintf(
                        'Vous n\'avez pas étudié depuis %d jours. N\'abandonnez pas maintenant !',
                        intval($days_inactive)
                    )
                ];
            } elseif ($days_inactive > 14) {
                $alerts[] = [
                    'type' => 'inactivity',
                    'level' => 'danger',
                    'message' => sprintf(
                        '🚨 Attention ! %d jours d\'inactivité. Votre progression est en danger.',
                        intval($days_inactive)
                    )
                ];
            }
        }
        
        // Vérifier si proche d'un milestone
        $progress = self::getParcoursProgress($user_id, $parcours_id);
        $next_week = self::getNextUncheckedWeek($user_id, $parcours_id);
        
        if ($next_week && $progress['percentage'] > 0) {
            $parcours = self::getParcours($parcours_id);
            if ($parcours && isset($parcours['milestones'][$next_week['month']][$next_week['week']])) {
                $milestone = $parcours['milestones'][$next_week['month']][$next_week['week']];
                $alerts[] = [
                    'type' => 'milestone',
                    'level' => 'info',
                    'message' => sprintf(
                        'Prochaine étape : %s - %s',
                        $next_week['label'],
                        $milestone
                    )
                ];
            }
        }
        
        // Vérifier la progression du parcours
        if ($progress['percentage'] >= 80 && $progress['percentage'] < 100) {
            $alerts[] = [
                'type' => 'achievement',
                'level' => 'success',
                'message' => sprintf(
                    '🎯 Vous êtes à %d%% ! Plus que %d semaines pour terminer votre parcours !',
                    $progress['percentage'],
                    $progress['total_weeks'] - $progress['total_weeks_checked']
                )
            ];
        }
        
        // Vérifier les badges proches (seulement si la classe existe et la méthode est publique)
        if (class_exists('TMMDashboardBadges') && method_exists('TMMDashboardBadges', 'getUserStats')) {
            try {
                $stats = TMMDashboardBadges::getUserStats($user_id);
                $completed_courses = $stats['completed_courses'] ?? 0;
                
                if ($completed_courses == 2 || $completed_courses == 4 || $completed_courses == 9) {
                    $alerts[] = [
                        'type' => 'badge',
                        'level' => 'info',
                        'message' => 'Vous êtes proche d\'obtenir un nouveau badge ! Encore un cours à terminer.'
                    ];
                }
            } catch (Exception $e) {
                // Ignorer l'erreur si la méthode n'est pas accessible
            }
        }
        
        // Vérifier les cours non commencés depuis longtemps
        if (isset($progress['courses_progress']) && is_array($progress['courses_progress'])) {
            foreach ($progress['courses_progress'] as $course_id => $course_progress) {
                if ($course_progress['status'] === 'not_started') {
                    $course = get_post($course_id);
                    if ($course) {
                        $alerts[] = [
                            'type' => 'course_reminder',
                            'level' => 'info',
                            'message' => sprintf(
                                'N\'oubliez pas de commencer le cours "%s"',
                                $course->post_title
                            )
                        ];
                        break; // Un seul rappel de cours
                    }
                }
            }
        }
        
        return $alerts;
    }

}