<?php
/**
 * Classe de gestion du chatbot assistant
 * 
 * @package TMMDashboardPro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMMDashboardChatbot {
    
    /**
     * Commandes disponibles
     */
    private static $commands = [
        'help' => 'showHelp',
        'aide' => 'showHelp',
        'cours' => 'showCourses',
        'courses' => 'showCourses',
        'progress' => 'showProgress',
        'progression' => 'showProgress',
        'badge' => 'showBadges',
        'badges' => 'showBadges',
        'certificat' => 'showCertificates',
        'certificate' => 'showCertificates',
        'parcours' => 'showParcours',
        'path' => 'showParcours',
        'stats' => 'showStats',
        'statistiques' => 'showStats',
        'support' => 'contactSupport',
        'contact' => 'contactSupport'
    ];
    
    /**
     * Réponses prédéfinies
     */
    private static $responses = [
        'greeting' => [
            'Bonjour ! Je suis votre assistant d\'apprentissage. Comment puis-je vous aider aujourd\'hui ?',
            'Salut ! Prêt à apprendre ? Que puis-je faire pour vous ?',
            'Bienvenue ! Je suis là pour vous accompagner dans votre parcours. Que souhaitez-vous savoir ?'
        ],
        'not_understood' => [
            'Je n\'ai pas compris votre demande. Tapez "aide" pour voir ce que je peux faire.',
            'Désolé, je ne comprends pas. Essayez "help" pour voir les commandes disponibles.',
            'Hmm, pouvez-vous reformuler ? Ou tapez "aide" pour voir mes capacités.'
        ],
        'encouragement' => [
            'Continuez comme ça, vous progressez bien !',
            'Excellent travail ! Continuez sur cette lancée.',
            'Vous êtes sur la bonne voie ! Ne lâchez rien.',
            'Bravo pour votre persévérance !',
            'C\'est en forgeant qu\'on devient forgeron. Continuez !'
        ]
    ];
    
    /**
     * Traiter un message
     */
    public static function processMessage($user_id, $message) {
        $message = trim(strtolower($message));
        
        // Log pour debug
        error_log("TMM Chatbot processMessage: user_id=$user_id, message='$message'");
        
        try {
            // Enregistrer le message
            self::saveMessage($user_id, $message, 'user');
            
            // Détecter l'intention
            $intent = self::detectIntent($message);
            error_log("TMM Chatbot intent détecté: " . print_r($intent, true));
            
            // Générer la réponse
            $response = self::generateResponse($user_id, $intent, $message);
            error_log("TMM Chatbot réponse générée: " . print_r($response, true));
            
            // Enregistrer la réponse
            self::saveMessage($user_id, $response['text'], 'bot', $response);
            
            // Analyser le sentiment pour personnaliser
            try {
                $sentiment = self::analyzeSentiment($message);
                if ($sentiment === 'negative') {
                    $response['text'] .= "\n\n" . self::getRandomResponse('encouragement');
                }
            } catch (Exception $e) {
                error_log("TMM Chatbot: Erreur analyse sentiment: " . $e->getMessage());
                // Continue sans analyse de sentiment
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log("TMM Chatbot: Erreur dans processMessage: " . $e->getMessage());
            
            // Retourner une réponse d'erreur mais fonctionnelle
            return [
                'text' => "Je rencontre une difficulté technique. Pouvez-vous reformuler votre question ?",
                'type' => 'error',
                'data' => [],
                'actions' => self::getQuickReplies()
            ];
        }
    }

    
    /**
     * Détecter l'intention du message
     */
    private static function detectIntent($message) {
        // Vérifier les commandes directes
        foreach (self::$commands as $command => $method) {
            if (strpos($message, $command) !== false) {
                return ['type' => 'command', 'command' => $command, 'method' => $method];
            }
        }
        
        // Analyse par mots-clés
        $keywords = [
            'progress' => ['progression', 'avancement', 'où j\'en suis', 'status', 'statut'],
            'courses' => ['cours', 'formation', 'apprendre', 'étudier', 'leçon'],
            'help' => ['aide', 'help', 'comment', 'quoi faire', 'perdu'],
            'badges' => ['badge', 'récompense', 'achievement', 'trophée'],
            'certificate' => ['certificat', 'diplôme', 'attestation'],
            'problem' => ['problème', 'bug', 'erreur', 'marche pas', 'fonctionne pas'],
            'motivation' => ['motivé', 'motivation', 'découragement', 'difficile', 'dur']
        ];
        
        foreach ($keywords as $intent => $words) {
            foreach ($words as $word) {
                if (strpos($message, $word) !== false) {
                    return ['type' => 'keyword', 'intent' => $intent];
                }
            }
        }
        
        // Salutations
        $greetings = ['bonjour', 'salut', 'hello', 'hi', 'bonsoir', 'hey'];
        foreach ($greetings as $greeting) {
            if (strpos($message, $greeting) !== false) {
                return ['type' => 'greeting'];
            }
        }
        
        // Questions fréquentes
        if (strpos($message, '?') !== false) {
            return ['type' => 'question', 'content' => $message];
        }
        
        return ['type' => 'unknown'];
    }
    
    /**
     * Générer une réponse
     */
    private static function generateResponse($user_id, $intent, $original_message) {
        $response = [
            'text' => '',
            'data' => [],
            'actions' => [],
            'type' => 'text'
        ];
        
        try {
            switch ($intent['type']) {
                case 'command':
                    if (method_exists(__CLASS__, $intent['method'])) {
                        $response = call_user_func([__CLASS__, $intent['method']], $user_id);
                    } else {
                        error_log("TMM Chatbot: Méthode {$intent['method']} non trouvée");
                        $response['text'] = self::getRandomResponse('not_understood');
                        $response['actions'] = self::getQuickReplies();
                    }
                    break;
                    
                case 'keyword':
                    $response = self::handleKeywordIntent($user_id, $intent['intent']);
                    break;
                    
                case 'greeting':
                    $response['text'] = self::getRandomResponse('greeting');
                    $response['actions'] = self::getQuickReplies();
                    break;
                    
                case 'question':
                    $response = self::handleQuestion($user_id, $original_message);
                    break;
                    
                default:
                    $response['text'] = self::getRandomResponse('not_understood');
                    $response['actions'] = self::getQuickReplies();
            }
            
        } catch (Exception $e) {
            error_log("TMM Chatbot: Erreur génération réponse: " . $e->getMessage());
            $response['text'] = "Je suis désolé, je rencontre une difficulté. Que puis-je faire d'autre pour vous aider ?";
            $response['actions'] = self::getQuickReplies();
        }
        
        return $response;
    }

    /**
     * Afficher l'aide
     */
    private static function showHelp($user_id) {
        $commands_list = [
            '📚 **cours** - Voir vos cours actuels',
            '📊 **progression** - Consulter votre avancement',
            '🏆 **badges** - Afficher vos badges et récompenses',
            '📜 **certificat** - Voir vos certificats',
            '🛤️ **parcours** - Détails de votre parcours',
            '📈 **stats** - Vos statistiques d\'apprentissage',
            '💬 **support** - Contacter le support'
        ];
        
        return [
            'text' => "Voici ce que je peux faire pour vous :\n\n" . implode("\n", $commands_list),
            'type' => 'text',
            'actions' => self::getQuickReplies()
        ];
    }
    
    /**
     * Afficher les cours
     */
     private static function showCourses($user_id) {
        try {
            if (!class_exists('TMMDashboardData')) {
                return [
                    'text' => "Je ne peux pas accéder aux informations de cours pour le moment. Rendez-vous dans votre tableau de bord pour voir vos cours.",
                    'type' => 'text',
                    'actions' => [
                        ['label' => 'Aller au tableau de bord', 'value' => 'dashboard']
                    ]
                ];
            }
    
            $courses_data = TMMDashboardData::getUserCourses($user_id);
            $in_progress = $courses_data['in_progress'] ?? [];
            
            if (empty($in_progress)) {
                return [
                    'text' => "Vous n'avez aucun cours en cours actuellement. Voulez-vous voir les cours recommandés ?",
                    'type' => 'text',
                    'actions' => [
                        ['label' => 'Voir les recommandations', 'value' => 'recommendations'],
                        ['label' => 'Parcourir tous les cours', 'value' => 'browse_courses']
                    ]
                ];
            }
            
            $courses_list = [];
            foreach (array_slice($in_progress, 0, 3) as $course) {
                $courses_list[] = [
                    'title' => $course['name'],
                    'subtitle' => sprintf('%d%% complété', $course['progress']),
                    'url' => $course['url'],
                    'image' => $course['image']
                ];
            }
            
            return [
                'text' => sprintf("Vous avez %d cours en progression :", count($in_progress)),
                'type' => 'cards',
                'data' => $courses_list,
                'actions' => [
                    ['label' => 'Voir tous mes cours', 'value' => 'all_courses']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("TMM Chatbot showCourses error: " . $e->getMessage());
            return [
                'text' => "Je ne peux pas récupérer vos cours actuellement. Veuillez consulter votre tableau de bord.",
                'type' => 'text',
                'actions' => [['label' => 'Tableau de bord', 'value' => 'dashboard']]
            ];
        }
    }
    
    /**
     * Afficher la progression
     */
    private static function showProgress($user_id) {
        try {
            if (!class_exists('TMMDashboardData')) {
                return [
                    'text' => "Les informations de progression ne sont pas disponibles actuellement.",
                    'type' => 'text'
                ];
            }
    
            $dashboard_data = TMMDashboardData::getDashboardData($user_id);
            $stats = $dashboard_data['stats'];
            
            $progress_text = sprintf(
                "📊 **Votre progression**\n\n" .
                "✅ Cours terminés : %d\n" .
                "🔄 En cours : %d\n" .
                "📚 Non commencés : %d\n" .
                "📈 Progression globale : %d%%\n" .
                "🔥 Série actuelle : %d jours",
                $stats['completed_count'],
                $stats['in_progress_count'],
                $stats['enrolled_count'],
                $stats['completion_percentage'],
                $stats['learning_streak']
            );
            
            return [
                'text' => $progress_text,
                'type' => 'text',
                'actions' => [
                    ['label' => 'Détails par cours', 'value' => 'course_details'],
                    ['label' => 'Statistiques complètes', 'value' => 'full_stats']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("TMM Chatbot showProgress error: " . $e->getMessage());
            return [
                'text' => "Impossible de récupérer votre progression actuellement. Consultez votre tableau de bord pour plus d'informations.",
                'type' => 'text'
            ];
        }
    }
    
    /**
     * Afficher les badges
     */
    private static function showBadges($user_id) {
        $current_badge = get_user_meta($user_id, 'tmm_badge_status', true) ?: 'curieux';
        $earned_badges = get_user_meta($user_id, 'tmm_earned_badges', true) ?: [];
        $badge_info = TMMDashboardBadges::getBadgeInfo($current_badge);
        
        $badges_text = sprintf(
            "🏆 **Vos badges**\n\n" .
            "Badge actuel : **%s**\n" .
            "%s\n\n" .
            "Badges débloqués : %d",
            $badge_info['name'],
            $badge_info['description'],
            count($earned_badges) + 1
        );
        
        if (!empty($earned_badges)) {
            $badges_text .= "\n\n**Badges spéciaux :**";
            foreach ($earned_badges as $badge) {
                $info = TMMDashboardBadges::getBadgeInfo($badge);
                if ($info) {
                    $badges_text .= sprintf("\n• %s", $info['name']);
                }
            }
        }
        
        return [
            'text' => $badges_text,
            'type' => 'text',
            'actions' => [
                ['label' => 'Comment gagner plus de badges', 'value' => 'badge_tips'],
                ['label' => 'Voir ma progression', 'value' => 'progress']
            ]
        ];
    }
    
    /**
     * Afficher les certificats
     */
    private static function showCertificates($user_id) {
        $certificates = TMMDashboardCertificates::getUserCertificates($user_id);
        
        if (empty($certificates)) {
            return [
                'text' => "Vous n'avez pas encore de certificat. Terminez un parcours complet pour obtenir votre premier certificat ! 🎓",
                'type' => 'text',
                'actions' => [
                    ['label' => 'Voir mon parcours', 'value' => 'parcours'],
                    ['label' => 'Comment obtenir un certificat', 'value' => 'certificate_info']
                ]
            ];
        }
        
        $cert_list = [];
        foreach ($certificates as $cert) {
            $cert_list[] = sprintf(
                "📜 **%s**\nDélivré le %s\nN° %s",
                $cert['parcours_name'],
                date_i18n(get_option('date_format'), strtotime($cert['date_completed'])),
                $cert['certificate_number']
            );
        }
        
        return [
            'text' => sprintf("Vous avez %d certificat(s) :\n\n%s", count($certificates), implode("\n\n", $cert_list)),
            'type' => 'text',
            'actions' => [
                ['label' => 'Télécharger mes certificats', 'value' => 'download_certificates']
            ]
        ];
    }
    
    /**
     * Afficher le parcours
     */
    private static function showParcours($user_id) {
        $user_parcours = TMMDashboardParcours::getUserParcours($user_id);
        
        if (!$user_parcours) {
            return [
                'text' => "Vous n'avez pas de parcours actif. Un abonnement est nécessaire pour accéder aux parcours personnalisés.",
                'type' => 'text',
                'actions' => [
                    ['label' => 'Voir les abonnements', 'value' => 'view_memberships']
                ]
            ];
        }
        
        $progress = TMMDashboardParcours::getParcoursProgress($user_id, $user_parcours['id']);
        $next_week = TMMDashboardParcours::getNextUncheckedWeek($user_id, $user_parcours['id']);
        
        $parcours_text = sprintf(
            "🛤️ **Parcours : %s**\n\n" .
            "Durée : %s\n" .
            "Niveau : %s\n" .
            "Progression : %d%%\n\n",
            $user_parcours['name'],
            $user_parcours['duration'],
            $user_parcours['level'],
            $progress['percentage']
        );
        
        if ($next_week) {
            $parcours_text .= sprintf(
                "📍 Prochaine étape : %s\n\n" .
                "Ne perdez pas votre élan, continuez votre progression !",
                $next_week['label']
            );
        } else if ($progress['is_complete']) {
            $parcours_text .= "🎉 Félicitations ! Vous avez terminé ce parcours !";
        }
        
        return [
            'text' => $parcours_text,
            'type' => 'text',
            'actions' => [
                ['label' => 'Voir le détail du parcours', 'value' => 'parcours_details'],
                ['label' => 'Mes cours actuels', 'value' => 'courses']
            ]
        ];
    }
    
    /**
     * Afficher les statistiques
     */
    private static function showStats($user_id) {
        $analytics = TMMDashboardAnalytics::getUserAnalytics($user_id, 'overview', 30);
        $summary = $analytics['summary'];
        
        $stats_text = sprintf(
            "📈 **Vos statistiques (30 derniers jours)**\n\n" .
            "⏱️ Temps total : %s heures\n" .
            "📚 Cours commencés : %d\n" .
            "✅ Cours terminés : %d\n" .
            "📝 Quiz complétés : %d\n" .
            "💯 Score moyen : %s%%\n" .
            "🎯 Taux de complétion : %s%%\n\n" .
            "Préférence d'apprentissage : %s",
            $summary['total_time_hours'],
            $summary['courses_started'],
            $summary['courses_completed'],
            $summary['quizzes_completed'],
            $summary['average_quiz_score'],
            $summary['completion_rate'],
            $analytics['learning_preference']
        );
        
        return [
            'text' => $stats_text,
            'type' => 'text',
            'data' => [
                'type' => 'stats_summary',
                'analytics' => $analytics
            ],
            'actions' => [
                ['label' => 'Rapport détaillé', 'value' => 'detailed_report'],
                ['label' => 'Comparer avec le mois dernier', 'value' => 'compare_stats']
            ]
        ];
    }
    
    /**
     * Contacter le support
     */
    private static function contactSupport($user_id) {
        return [
            'text' => "💬 **Contacter le support**\n\n" .
                     "Vous pouvez nous contacter de plusieurs façons :\n\n" .
                     "📧 Email : support@teachmemore.com\n" .
                     "💬 Chat en direct : Disponible du lundi au vendredi, 9h-18h\n" .
                     "📞 Téléphone : +33 1 23 45 67 89\n\n" .
                     "Ou décrivez votre problème ici et je transmettrai votre message.",
            'type' => 'text',
            'actions' => [
                ['label' => 'Signaler un bug', 'value' => 'report_bug'],
                ['label' => 'Question sur un cours', 'value' => 'course_question'],
                ['label' => 'Problème de compte', 'value' => 'account_issue']
            ]
        ];
    }
    
    /**
     * Gérer les intentions par mots-clés
     */
    private static function handleKeywordIntent($user_id, $intent) {
        switch ($intent) {
            case 'progress':
                return self::showProgress($user_id);
                
            case 'courses':
                return self::showCourses($user_id);
                
            case 'help':
                return self::showHelp($user_id);
                
            case 'badges':
                return self::showBadges($user_id);
                
            case 'certificate':
                return self::showCertificates($user_id);
                
            case 'problem':
                return [
                    'text' => "Je comprends que vous rencontrez un problème. Pouvez-vous me donner plus de détails ?\n\n" .
                             "Par exemple :\n" .
                             "- Le cours concerné\n" .
                             "- Le message d'erreur exact\n" .
                             "- Ce que vous essayiez de faire",
                    'type' => 'text',
                    'actions' => [
                        ['label' => 'Contacter le support', 'value' => 'support']
                    ]
                ];
                
            case 'motivation':
                // Version sécurisée qui ne dépend pas de classes externes
                $encouragement = "🌟 Chaque expert a commencé par être débutant. Votre parcours commence maintenant !\n\n";
                $encouragement .= self::getRandomResponse('encouragement');
                
                return [
                    'text' => $encouragement,
                    'type' => 'text',
                    'actions' => [
                        ['label' => 'Voir mes accomplissements', 'value' => 'achievements'],
                        ['label' => 'Définir un objectif', 'value' => 'set_goal']
                    ]
                ];
                
            default:
                return [
                    'text' => self::getRandomResponse('not_understood'),
                    'type' => 'text',
                    'actions' => self::getQuickReplies()
                ];
        }
    }

    /**
     * Gérer les questions
     */
    private static function handleQuestion($user_id, $question) {
        // Base de connaissances simple
        $faq = [
            'comment télécharger certificat' => "Pour télécharger votre certificat, terminez d'abord un parcours complet. Ensuite, allez dans la section 'Mes certificats' de votre tableau de bord.",
            'comment gagner badge' => "Les badges se gagnent en progressant : terminez des cours, connectez-vous régulièrement, obtenez de bons scores aux quiz.",
            'combien coûte' => "Les tarifs dépendent de l'abonnement choisi. Consultez la page des abonnements pour voir toutes les options.",
            'accès cours' => "Avec un abonnement actif, vous avez accès à tous les cours de votre parcours. Sans abonnement, l'accès est limité.",
            'durée formation' => "La durée dépend de votre rythme. En moyenne, un parcours se complète en 3 mois à raison de 2-3h par semaine."
        ];
        
        // Chercher une correspondance
        foreach ($faq as $key => $answer) {
            if (strpos($question, $key) !== false) {
                return [
                    'text' => $answer,
                    'type' => 'text',
                    'actions' => [
                        ['label' => 'Autre question', 'value' => 'help']
                    ]
                ];
            }
        }
        
        // Si pas de correspondance
        return [
            'text' => "Je n'ai pas trouvé de réponse exacte à votre question. Voulez-vous que je contacte le support pour vous ?",
            'type' => 'text',
            'actions' => [
                ['label' => 'Oui, contacter le support', 'value' => 'forward_to_support'],
                ['label' => 'Non, voir l\'aide', 'value' => 'help']
            ]
        ];
    }
    
    /**
     * Obtenir des suggestions de réponses rapides
     */
public static function getSuggestions($user_id) {
    $suggestions = [];
    
    try {
        // Suggestions par défaut qui ne dépendent pas d'autres classes
        $suggestions = [
            ['label' => '📚 Mes cours', 'value' => 'cours'],
            ['label' => '📊 Ma progression', 'value' => 'progress'],
            ['label' => '🏆 Mes badges', 'value' => 'badges'],
            ['label' => '❓ Aide', 'value' => 'help']
        ];

        // Suggestions contextuelles si les classes sont disponibles
        if (class_exists('TMMDashboardData')) {
            try {
                $dashboard_data = TMMDashboardData::getDashboardData($user_id);
                
                // Si des cours sont en progression
                if (!empty($dashboard_data['courses']['in_progress'])) {
                    array_unshift($suggestions, ['label' => '📚 Continuer mes cours', 'value' => 'courses']);
                }
            } catch (Exception $e) {
                error_log("TMM Chatbot: Erreur récupération suggestions contextuelles: " . $e->getMessage());
                // Continue avec suggestions par défaut
            }
        }
        
    } catch (Exception $e) {
        error_log("TMM Chatbot: Erreur génération suggestions: " . $e->getMessage());
        // Retourner des suggestions de base en cas d'erreur
        $suggestions = [
            ['label' => '❓ Aide', 'value' => 'help'],
            ['label' => '📞 Support', 'value' => 'support']
        ];
    }
    
    return array_slice($suggestions, 0, 4); // Maximum 4 suggestions
}
    
    /**
     * Obtenir une réponse aléatoire
     */
    private static function getRandomResponse($type) {
        if (isset(self::$responses[$type])) {
            $responses = self::$responses[$type];
            return $responses[array_rand($responses)];
        }
        return '';
    }
    
    /**
     * Obtenir des réponses rapides
     */
    private static function getQuickReplies() {
        return [
            ['label' => '📚 Mes cours', 'value' => 'cours'],
            ['label' => '📊 Progression', 'value' => 'progress'],
            ['label' => '🏆 Badges', 'value' => 'badges'],
            ['label' => '❓ Aide', 'value' => 'help']
        ];
    }
    
    /**
     * Analyser le sentiment
     */
    private static function analyzeSentiment($message) {
        $negative_words = ['difficile', 'dur', 'compliqué', 'découragement', 'abandonner', 'arrêter', 'nul', 'pas capable'];
        $positive_words = ['super', 'génial', 'bien', 'content', 'heureux', 'motivé', 'réussi', 'terminé'];
        
        $negative_count = 0;
        $positive_count = 0;
        
        foreach ($negative_words as $word) {
            if (strpos($message, $word) !== false) $negative_count++;
        }
        
        foreach ($positive_words as $word) {
            if (strpos($message, $word) !== false) $positive_count++;
        }
        
        if ($negative_count > $positive_count) return 'negative';
        if ($positive_count > $negative_count) return 'positive';
        return 'neutral';
    }
    
    /**
     * Obtenir un encouragement personnalisé
     */
    private static function getPersonalizedEncouragement($stats) {
        $messages = [];
        
        if ($stats['learning_streak'] > 0) {
            $messages[] = sprintf(
                "🔥 Vous êtes sur une série de %d jours ! C'est impressionnant, continuez comme ça !",
                $stats['learning_streak']
            );
        }
        
        if ($stats['completed_courses'] > 0) {
            $messages[] = sprintf(
                "💪 Vous avez déjà terminé %d cours. Chaque cours terminé vous rapproche de vos objectifs !",
                $stats['completed_courses']
            );
        }
        
        if ($stats['recent_activity'] > 5) {
            $messages[] = "⚡ Votre activité récente est excellente ! Votre régularité est la clé du succès.";
        }
        
        if (empty($messages)) {
            $messages[] = "🌟 Chaque expert a commencé par être débutant. Votre parcours commence maintenant !";
        }
        
        $messages[] = "\n\n" . self::getRandomResponse('encouragement');
        
        return implode("\n\n", $messages);
    }
    
    /**
     * Sauvegarder un message
     */
    private static function saveMessage($user_id, $message, $type, $data = []) {
        try {
            global $wpdb;
            
            // Vérifier que la table existe
            $table_name = $wpdb->prefix . 'tmm_chatbot_messages';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                error_log("TMM Chatbot: Table $table_name n'existe pas");
                return false;
            }
            
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'message_type' => $type,
                    'message' => $message,
                    'response' => json_encode($data),
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
            
            if ($result === false) {
                error_log("TMM Chatbot: Erreur sauvegarde message: " . $wpdb->last_error);
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("TMM Chatbot: Exception sauvegarde message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir l'historique des conversations
     */
    public static function getChatHistory($user_id, $limit = 50) {
        global $wpdb;
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id, $limit
        ));
        
        return array_reverse($messages);
    }
    
    /**
     * Nettoyer l'historique ancien
     */
    public static function cleanOldHistory($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Obtenir des insights sur les conversations
     */
    public static function getChatbotInsights() {
        global $wpdb;
        
        // Questions les plus fréquentes
        $frequent_messages = $wpdb->get_results(
            "SELECT message, COUNT(*) as count
             FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE message_type = 'user'
             GROUP BY message
             ORDER BY count DESC
             LIMIT 10"
        );
        
        // Utilisateurs les plus actifs
        $active_users = $wpdb->get_results(
            "SELECT user_id, COUNT(*) as message_count
             FROM {$wpdb->prefix}tmm_chatbot_messages
             GROUP BY user_id
             ORDER BY message_count DESC
             LIMIT 10"
        );
        
        // Taux de résolution (messages sans réponse "not_understood")
        $total_messages = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE message_type = 'bot'"
        );
        
        $not_understood = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE message_type = 'bot' 
             AND message LIKE '%pas compris%'"
        );
        
        $resolution_rate = $total_messages > 0 
            ? round((($total_messages - $not_understood) / $total_messages) * 100, 1)
            : 0;
        
        return [
            'frequent_messages' => $frequent_messages,
            'active_users' => $active_users,
            'resolution_rate' => $resolution_rate,
            'total_conversations' => $total_messages
        ];
    }
}
