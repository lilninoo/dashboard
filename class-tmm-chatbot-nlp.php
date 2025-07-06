<?php
/**
 * Moteur NLP (Natural Language Processing) du chatbot
 */
class TMMChatbotNLP {
    
    /**
     * Analyseur de contexte avancé
     */
    public static function analyzeContext($user_id, $message, $conversation_history = []) {
        $context = [
            'user_profile' => self::getUserContext($user_id),
            'conversation_state' => self::getConversationState($conversation_history),
            'intent' => self::detectAdvancedIntent($message),
            'entities' => self::extractEntities($message),
            'sentiment' => self::analyzeSentiment($message),
            'urgency' => self::detectUrgency($message),
            'topic_continuity' => self::checkTopicContinuity($message, $conversation_history)
        ];
        
        return self::buildContextualResponse($context);
    }
    
    /**
     * Extraction d'entités nommées
     */
    private static function extractEntities($message) {
        $entities = [
            'courses' => [],
            'dates' => [],
            'numbers' => [],
            'actions' => [],
            'skills' => []
        ];
        
        // Reconnaissance des cours mentionnés
        $courses = self::findCourseMentions($message);
        if (!empty($courses)) {
            $entities['courses'] = $courses;
        }
        
        // Extraction des dates
        $date_patterns = [
            '/aujourd\'?hui/i' => 'today',
            '/demain/i' => 'tomorrow',
            '/hier/i' => 'yesterday',
            '/cette semaine/i' => 'this_week',
            '/le (\d{1,2}\/\d{1,2})/i' => 'specific_date'
        ];
        
        foreach ($date_patterns as $pattern => $type) {
            if (preg_match($pattern, $message, $matches)) {
                $entities['dates'][] = [
                    'type' => $type,
                    'value' => $matches[0],
                    'parsed' => self::parseDate($matches[0])
                ];
            }
        }
        
        // Extraction des nombres et pourcentages
        if (preg_match_all('/\d+%?/', $message, $matches)) {
            $entities['numbers'] = $matches[0];
        }
        
        // Actions demandées
        $action_keywords = [
            'montrer' => 'show',
            'afficher' => 'display',
            'calculer' => 'calculate',
            'analyser' => 'analyze',
            'comparer' => 'compare',
            'recommander' => 'recommend',
            'expliquer' => 'explain'
        ];
        
        foreach ($action_keywords as $keyword => $action) {
            if (stripos($message, $keyword) !== false) {
                $entities['actions'][] = $action;
            }
        }
        
        return $entities;
    }
    
    /**
     * Système de scoring d'intention avancé
     */
    private static function detectAdvancedIntent($message) {
        $intents = [
            'question_progress' => [
                'patterns' => [
                    '/où (en suis-je|j\'en suis)/i',
                    '/ma progression/i',
                    '/mon avancement/i',
                    '/combien.*complété/i'
                ],
                'score' => 0
            ],
            'need_help' => [
                'patterns' => [
                    '/aide/i',
                    '/comment faire/i',
                    '/je ne comprends pas/i',
                    '/expliquer/i',
                    '/difficultés?/i'
                ],
                'score' => 0
            ],
            'course_recommendation' => [
                'patterns' => [
                    '/quel cours/i',
                    '/recommand/i',
                    '/suggér/i',
                    '/que.*apprendre/i',
                    '/suivant/i'
                ],
                'score' => 0
            ],
            'achievement_query' => [
                'patterns' => [
                    '/badge/i',
                    '/certificat/i',
                    '/récompense/i',
                    '/accomplissement/i'
                ],
                'score' => 0
            ],
            'schedule_planning' => [
                'patterns' => [
                    '/quand/i',
                    '/planning/i',
                    '/calendrier/i',
                    '/temps.*reste/i',
                    '/deadline/i'
                ],
                'score' => 0
            ],
            'technical_issue' => [
                'patterns' => [
                    '/bug/i',
                    '/erreur/i',
                    '/ne fonctionne pas/i',
                    '/problème/i',
                    '/marche pas/i'
                ],
                'score' => 0
            ]
        ];
        
        // Calculer les scores pour chaque intention
        foreach ($intents as $intent => &$data) {
            foreach ($data['patterns'] as $pattern) {
                if (preg_match($pattern, $message)) {
                    $data['score'] += 10;
                }
            }
        }
        
        // Retourner l'intention avec le score le plus élevé
        $best_intent = array_reduce(array_keys($intents), function($carry, $key) use ($intents) {
            return (!$carry || $intents[$key]['score'] > $intents[$carry]['score']) ? $key : $carry;
        });
        
        return [
            'primary' => $best_intent,
            'confidence' => $intents[$best_intent]['score'],
            'all_scores' => $intents
        ];
    }
    
    // ===================================
// À ajouter dans class-tmm-chatbot-nlp.php
// ===================================

/**
 * Construire une réponse contextuelle
 */
private static function buildContextualResponse($context) {
    $response = [
        'text' => '',
        'type' => 'text',
        'data' => [],
        'actions' => [],
        'confidence' => 0
    ];
    
    // Logique de construction basée sur le contexte
    if ($context['urgency'] === 'high') {
        $response['type'] = 'priority';
        $response['text'] = 'Je comprends que c\'est urgent. ';
    }
    
    // Ajouter la réponse principale basée sur l'intention
    switch ($context['intent']['primary']) {
        case 'question_progress':
            $response['text'] .= 'Voici votre progression actuelle.';
            $response['data'] = ['show_progress' => true];
            break;
            
        case 'need_help':
            $response['text'] .= 'Je suis là pour vous aider. Que souhaitez-vous savoir ?';
            $response['actions'] = [
                ['label' => 'Aide sur les cours', 'value' => 'help_courses'],
                ['label' => 'Problème technique', 'value' => 'technical_issue'],
                ['label' => 'Question sur mon parcours', 'value' => 'parcours_question']
            ];
            break;
            
        default:
            $response['text'] = 'Je suis là pour vous accompagner dans votre apprentissage.';
    }
    
    $response['confidence'] = $context['intent']['confidence'] / 10;
    
    return $response;
}

/**
 * Obtenir le contexte utilisateur
 */
private static function getUserContext($user_id) {
    return [
        'is_premium' => pmpro_hasMembershipLevel(null, $user_id),
        'current_courses' => count(TMMDashboardData::getUserCourses($user_id)['in_progress'] ?? []),
        'last_activity' => get_user_meta($user_id, '_lp_last_activity', true),
        'preferred_language' => get_user_meta($user_id, 'locale', true) ?: 'fr_FR'
    ];
}

/**
 * Obtenir l'état de la conversation
 */
private static function getConversationState($history) {
    if (empty($history)) {
        return 'new';
    }
    
    $last_message = end($history);
    $time_since_last = time() - strtotime($last_message->created_at);
    
    if ($time_since_last > 3600) { // Plus d'une heure
        return 'resumed';
    }
    
    return 'ongoing';
}

/**
 * Détecter l'urgence
 */
private static function detectUrgency($message) {
    $urgent_keywords = [
        'urgent', 'vite', 'rapidement', 'maintenant', 'tout de suite',
        'immédiatement', 'bloqué', 'help!', 'au secours', 'svp', 's\'il vous plaît'
    ];
    
    foreach ($urgent_keywords as $keyword) {
        if (stripos($message, $keyword) !== false) {
            return 'high';
        }
    }
    
    return 'normal';
}

/**
 * Vérifier la continuité du sujet
 */
private static function checkTopicContinuity($message, $history) {
    if (empty($history)) {
        return 'new_topic';
    }
    
    // Logique simple : vérifier si le message contient des mots de l'historique récent
    $recent_messages = array_slice($history, -3);
    $historical_words = [];
    
    foreach ($recent_messages as $msg) {
        $words = str_word_count(strtolower($msg->message), 1);
        $historical_words = array_merge($historical_words, $words);
    }
    
    $current_words = str_word_count(strtolower($message), 1);
    $common_words = array_intersect($current_words, $historical_words);
    
    if (count($common_words) > 2) {
        return 'continued';
    }
    
    return 'new_topic';
}

/**
 * Trouver les mentions de cours
 */
private static function findCourseMentions($message) {
    global $wpdb;
    
    // Rechercher des titres de cours dans le message
    $courses = $wpdb->get_results(
        "SELECT ID, post_title 
         FROM {$wpdb->posts} 
         WHERE post_type = 'lp_course' 
         AND post_status = 'publish'
         LIMIT 100"
    );
    
    $mentioned_courses = [];
    
    foreach ($courses as $course) {
        if (stripos($message, $course->post_title) !== false) {
            $mentioned_courses[] = [
                'id' => $course->ID,
                'title' => $course->post_title
            ];
        }
    }
    
    return $mentioned_courses;
}

/**
 * Parser une date
 */
private static function parseDate($date_string) {
    $date_map = [
        'aujourd\'hui' => 'today',
        'demain' => 'tomorrow',
        'hier' => 'yesterday',
        'cette semaine' => 'this week'
    ];
    
    if (isset($date_map[$date_string])) {
        return date('Y-m-d', strtotime($date_map[$date_string]));
    }
    
    // Essayer de parser d'autres formats
    $timestamp = strtotime($date_string);
    if ($timestamp) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}
}