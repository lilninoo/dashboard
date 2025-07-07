<?php
/**
 * Moteur NLP (Natural Language Processing) du chatbot
 * 
 * @package TMMDashboardPro
 * @subpackage Chatbot/NLP
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

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
     * Analyser le contexte conversationnel avancé
     */
    public static function analyzeAdvancedContext($user_id, $message, $conversation_history = []) {
        $context = [
            'user_profile' => self::getUserDetailedContext($user_id),
            'conversation_state' => self::getDetailedConversationState($conversation_history),
            'intent' => self::detectAdvancedIntent($message),
            'entities' => self::extractAdvancedEntities($message),
            'sentiment' => self::analyzeSentiment($message),
            'urgency' => self::detectUrgencyLevel($message),
            'topic_continuity' => self::analyzeTopicContinuity($message, $conversation_history),
            'user_mood' => self::assessUserMood($message, $conversation_history),
            'contextual_relevance' => self::calculateContextualRelevance($message, $user_id),
            'follow_up_potential' => self::predictFollowUpQuestions($message, $conversation_history)
        ];
        
        return self::buildIntelligentResponse($context);
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
     * Extraction d'entités nommées améliorée
     */
    private static function extractAdvancedEntities($message) {
        $entities = [
            'courses' => [],
            'skills' => [],
            'dates' => [],
            'times' => [],
            'numbers' => [],
            'actions' => [],
            'technologies' => [],
            'levels' => [],
            'durations' => []
        ];
        
        // 1. Extraction de compétences techniques
        $tech_skills = [
            'programming' => ['php', 'javascript', 'python', 'java', 'html', 'css', 'react', 'vue', 'angular'],
            'databases' => ['mysql', 'postgresql', 'mongodb', 'redis', 'sqlite'],
            'devops' => ['docker', 'kubernetes', 'aws', 'azure', 'jenkins', 'git'],
            'design' => ['photoshop', 'figma', 'sketch', 'illustrator', 'ux', 'ui']
        ];
        
        $message_lower = strtolower($message);
        foreach ($tech_skills as $category => $skills) {
            foreach ($skills as $skill) {
                if (strpos($message_lower, $skill) !== false) {
                    $entities['technologies'][] = [
                        'skill' => $skill,
                        'category' => $category,
                        'confidence' => 0.8
                    ];
                }
            }
        }
        
        // 2. Extraction de niveaux de difficulté
        $level_patterns = [
            'beginner' => ['débutant', 'novice', 'facile', 'simple', 'basique'],
            'intermediate' => ['intermédiaire', 'moyen', 'normal'],
            'advanced' => ['avancé', 'expert', 'difficile', 'complexe', 'professionnel']
        ];
        
        foreach ($level_patterns as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($message_lower, $keyword) !== false) {
                    $entities['levels'][] = [
                        'level' => $level,
                        'keyword' => $keyword,
                        'confidence' => 0.7
                    ];
                }
            }
        }
        
        // 3. Extraction de durées
        $duration_patterns = [
            '/(\d+)\s*(heure?s?|h)\b/i' => 'hours',
            '/(\d+)\s*(minute?s?|min)\b/i' => 'minutes',
            '/(\d+)\s*(jour?s?|j)\b/i' => 'days',
            '/(\d+)\s*(semaine?s?)\b/i' => 'weeks',
            '/(\d+)\s*(mois)\b/i' => 'months'
        ];
        
        foreach ($duration_patterns as $pattern => $unit) {
            if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $entities['durations'][] = [
                        'value' => intval($match[1]),
                        'unit' => $unit,
                        'text' => $match[0],
                        'confidence' => 0.9
                    ];
                }
            }
        }
        
        // 4. Extraction de dates relatives améliorée
        $date_patterns = [
            '/aujourd\'?hui/i' => ['type' => 'relative', 'value' => 'today'],
            '/demain/i' => ['type' => 'relative', 'value' => 'tomorrow'],
            '/hier/i' => ['type' => 'relative', 'value' => 'yesterday'],
            '/cette semaine/i' => ['type' => 'relative', 'value' => 'this_week'],
            '/la semaine prochaine/i' => ['type' => 'relative', 'value' => 'next_week'],
            '/ce mois/i' => ['type' => 'relative', 'value' => 'this_month'],
            '/(\d{1,2})\/(\d{1,2})\/(\d{4})/i' => ['type' => 'absolute', 'format' => 'dd/mm/yyyy'],
            '/(\d{1,2})\s+(janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)/i' => ['type' => 'absolute', 'format' => 'dd month']
        ];
        
        foreach ($date_patterns as $pattern => $info) {
            if (preg_match($pattern, $message, $matches)) {
                $entity = [
                    'type' => $info['type'],
                    'text' => $matches[0],
                    'confidence' => 0.8
                ];
                
                if ($info['type'] === 'relative') {
                    $entity['value'] = $info['value'];
                    $entity['parsed_date'] = self::parseRelativeDate($info['value']);
                } else {
                    $entity['format'] = $info['format'];
                    $entity['parsed_date'] = self::parseAbsoluteDate($matches[0], $info['format']);
                }
                
                $entities['dates'][] = $entity;
            }
        }
        
        // 5. Extraction d'actions et intentions
        $action_patterns = [
            'start' => ['commencer', 'débuter', 'lancer', 'initier', 'entamer'],
            'continue' => ['continuer', 'reprendre', 'poursuivre'],
            'finish' => ['terminer', 'finir', 'achever', 'compléter'],
            'learn' => ['apprendre', 'étudier', 'découvrir', 'maîtriser'],
            'practice' => ['pratiquer', 'exercer', 's\'entraîner', 'réviser'],
            'help' => ['aider', 'assister', 'conseiller', 'guider'],
            'show' => ['montrer', 'afficher', 'présenter', 'voir'],
            'explain' => ['expliquer', 'clarifier', 'détailler', 'préciser']
        ];
        
        foreach ($action_patterns as $action => $verbs) {
            foreach ($verbs as $verb) {
                if (strpos($message_lower, $verb) !== false) {
                    $entities['actions'][] = [
                        'action' => $action,
                        'verb' => $verb,
                        'confidence' => 0.7
                    ];
                }
            }
        }
        
        // 6. Extraction de nombres et pourcentages améliorée
        $number_patterns = [
            '/(\d+)%/' => ['type' => 'percentage', 'unit' => '%'],
            '/(\d+)\s*points?/' => ['type' => 'score', 'unit' => 'points'],
            '/note\s+de\s+(\d+)/' => ['type' => 'grade', 'unit' => 'grade'],
            '/(\d+)\s*€/' => ['type' => 'price', 'unit' => 'euros'],
            '/(\d+)/' => ['type' => 'number', 'unit' => null]
        ];
        
        foreach ($number_patterns as $pattern => $info) {
            if (preg_match_all($pattern, $message, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $entities['numbers'][] = [
                        'value' => intval($match[1]),
                        'type' => $info['type'],
                        'unit' => $info['unit'],
                        'text' => $match[0],
                        'confidence' => 0.9
                    ];
                }
            }
        }
        
        // Ajouter les cours mentionnés
        $courses = self::findCourseMentions($message);
        if (!empty($courses)) {
            $entities['courses'] = $courses;
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
            'confidence' => $intents[$best_intent]['score'] / 10,
            'all_scores' => $intents
        ];
    }
    
    /**
     * Analyse de sentiment améliorée
     */
    private static function analyzeSentiment($message) {
        // Dictionnaires de mots pour l'analyse de sentiment en français
        $positive_words = [
            'excellent', 'super', 'génial', 'fantastique', 'parfait', 'merveilleux',
            'content', 'heureux', 'ravi', 'satisfait', 'motivé', 'impressionnant',
            'utile', 'bien', 'bon', 'formidable', 'extraordinaire', 'magnifique',
            'j\'aime', 'j\'adore', 'bravo', 'félicitations', 'merci', 'cool'
        ];
        
        $negative_words = [
            'mauvais', 'nul', 'horrible', 'terrible', 'décevant', 'frustrant',
            'difficile', 'compliqué', 'confus', 'ennuyeux', 'lent', 'bug',
            'erreur', 'problème', 'cassé', 'inutile', 'perte', 'échec',
            'abandonner', 'arrêter', 'déteste', 'pas capable', 'impossible'
        ];
        
        $intensifiers = [
            'très' => 1.5, 'vraiment' => 1.3, 'extrêmement' => 2.0,
            'assez' => 1.2, 'plutôt' => 1.1, 'un peu' => 0.5,
            'pas' => -1, 'ne' => -0.8, 'jamais' => -1.2
        ];
        
        // Normaliser le message
        $message = strtolower($message);
        $words = preg_split('/\s+/', $message);
        
        $sentiment_score = 0;
        $emotion_intensity = 1;
        
        for ($i = 0; $i < count($words); $i++) {
            $word = trim($words[$i], '.,!?;:');
            
            // Vérifier les intensificateurs
            if (isset($intensifiers[$word])) {
                $emotion_intensity = $intensifiers[$word];
                continue;
            }
            
            // Analyser le sentiment
            if (in_array($word, $positive_words)) {
                $sentiment_score += 1 * $emotion_intensity;
            } elseif (in_array($word, $negative_words)) {
                $sentiment_score -= 1 * $emotion_intensity;
            }
            
            // Réinitialiser l'intensité après utilisation
            if ($emotion_intensity != 1) {
                $emotion_intensity = 1;
            }
        }
        
        // Analyser les emoticons et émojis
        $emoji_patterns = [
            '/😀|😃|😄|😁|😊|🙂|😉|😍|🥰|😘/' => 2,  // Très positif
            '/😐|😑|🤔|😕/' => 0,                          // Neutre
            '/😞|😢|😭|😠|😡|🤬|😤/' => -2,               // Très négatif
            '/👍|👏|💪|🎉|✅/' => 1,                      // Positif
            '/👎|❌|💔|😷/' => -1                          // Négatif
        ];
        
        foreach ($emoji_patterns as $pattern => $score) {
            if (preg_match($pattern, $message)) {
                $sentiment_score += $score;
            }
        }
        
        // Normaliser le score final
        $normalized_score = tanh($sentiment_score / 3); // Entre -1 et 1
        
        // Déterminer le sentiment
        if ($normalized_score > 0.3) {
            $sentiment = 'positive';
        } elseif ($normalized_score < -0.3) {
            $sentiment = 'negative';
        } else {
            $sentiment = 'neutral';
        }
        
        return [
            'sentiment' => $sentiment,
            'score' => $normalized_score,
            'confidence' => min(1, abs($normalized_score) + 0.3),
            'emotions' => self::detectEmotions($message, $sentiment_score)
        ];
    }
    
    /**
     * Détection d'émotions spécifiques
     */
    private static function detectEmotions($message, $base_score) {
        $emotion_patterns = [
            'joy' => [
                'keywords' => ['heureux', 'content', 'joie', 'ravi', 'enchanté'],
                'patterns' => ['/\bha+h+a+\b/i', '/\blol\b/i', '/\b😂\b/'],
                'score' => 0
            ],
            'excitement' => [
                'keywords' => ['excité', 'impatient', 'génial', 'fantastique'],
                'patterns' => ['/!{2,}/', '/\b(wow|waouh)\b/i'],
                'score' => 0
            ],
            'frustration' => [
                'keywords' => ['frustré', 'énervé', 'agacé', 'relou'],
                'patterns' => ['/\bgrr+\b/i', '/\bargh+\b/i'],
                'score' => 0
            ],
            'confusion' => [
                'keywords' => ['confus', 'perdu', 'comprends pas', 'comment'],
                'patterns' => ['/\?\?+/', '/euh/', '/hein/'],
                'score' => 0
            ],
            'motivation' => [
                'keywords' => ['motivé', 'déterminé', 'go', 'allez-y'],
                'patterns' => ['/let\'s go/i', '/c\'est parti/i'],
                'score' => 0
            ],
            'fatigue' => [
                'keywords' => ['fatigué', 'crevé', 'épuisé', 'marre'],
                'patterns' => ['/zzz/i', '/ouf/i'],
                'score' => 0
            ]
        ];
        
        $detected_emotions = [];
        $message_lower = strtolower($message);
        
        foreach ($emotion_patterns as $emotion => $data) {
            $score = 0;
            
            // Vérifier les mots-clés
            foreach ($data['keywords'] as $keyword) {
                if (strpos($message_lower, $keyword) !== false) {
                    $score += 1;
                }
            }
            
            // Vérifier les patterns regex
            foreach ($data['patterns'] as $pattern) {
                if (preg_match($pattern, $message)) {
                    $score += 1;
                }
            }
            
            if ($score > 0) {
                $detected_emotions[$emotion] = min(1, $score / 2); // Normaliser
            }
        }
        
        return $detected_emotions;
    }
    
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
                
            case 'course_recommendation':
                $response['text'] .= 'Je vais vous recommander des cours adaptés à votre niveau.';
                $response['data'] = ['show_recommendations' => true];
                break;
                
            case 'achievement_query':
                $response['text'] .= 'Voici vos badges et accomplissements.';
                $response['data'] = ['show_achievements' => true];
                break;
                
            case 'schedule_planning':
                $response['text'] .= 'Analysons votre planning d\'apprentissage.';
                $response['data'] = ['show_schedule' => true];
                break;
                
            case 'technical_issue':
                $response['text'] .= 'Je comprends que vous rencontrez un problème technique. Pouvez-vous me donner plus de détails ?';
                $response['type'] = 'support';
                break;
                
            default:
                $response['text'] = 'Je suis là pour vous accompagner dans votre apprentissage.';
        }
        
        $response['confidence'] = $context['intent']['confidence'];
        
        return $response;
    }
    
    /**
     * Construire une réponse intelligente
     */
    private static function buildIntelligentResponse($context) {
        $response = [
            'text' => '',
            'type' => 'text',
            'data' => [],
            'actions' => [],
            'confidence' => 0,
            'personalization' => [],
            'follow_up_suggestions' => []
        ];
        
        // Adapter le ton selon l'humeur de l'utilisateur
        $tone = self::determineTone($context['sentiment'], $context['urgency']);
        
        // Personnaliser selon le contexte utilisateur
        $personalization = self::generatePersonalization($context['user_profile']);
        
        // Construire la réponse principale
        $response = self::constructMainResponse($context, $tone);
        
        // Ajouter des suggestions de suivi
        $response['follow_up_suggestions'] = $context['follow_up_potential'];
        
        // Ajouter la personnalisation
        $response['personalization'] = $personalization;
        
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
     * Obtenir le contexte utilisateur détaillé
     */
    private static function getUserDetailedContext($user_id) {
        $base_context = self::getUserContext($user_id);
        
        // Ajouter des informations avancées
        $detailed_context = array_merge($base_context, [
            'learning_style' => get_user_meta($user_id, 'tmm_learning_style', true) ?: 'mixed',
            'skill_level' => self::assessUserSkillLevel($user_id),
            'interaction_patterns' => self::getUserInteractionPatterns($user_id),
            'preferred_communication' => self::getPreferredCommunicationStyle($user_id),
            'recent_struggles' => self::identifyRecentStruggles($user_id),
            'motivation_level' => self::assessMotivationLevel($user_id),
            'time_preferences' => self::getUserTimePreferences($user_id)
        ]);
        
        return $detailed_context;
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
     * Obtenir l'état détaillé de la conversation
     */
    private static function getDetailedConversationState($history) {
        $basic_state = self::getConversationState($history);
        
        return [
            'state' => $basic_state,
            'message_count' => count($history),
            'duration' => self::getConversationDuration($history),
            'topic_changes' => self::countTopicChanges($history),
            'average_response_time' => self::calculateAverageResponseTime($history)
        ];
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
     * Détecter le niveau d'urgence avancé
     */
    private static function detectUrgencyLevel($message) {
        $urgency_indicators = [
            'critical' => [
                'keywords' => ['urgent', 'critique', 'bloqué', 'bloquant', 'immédiat'],
                'patterns' => ['/!{3,}/', '/URGENT/i', '/AIDE!/i'],
                'score' => 4
            ],
            'high' => [
                'keywords' => ['vite', 'rapidement', 'maintenant', 'today', 'svp'],
                'patterns' => ['/!{2}/', '/asap/i', '/au plus vite/i'],
                'score' => 3
            ],
            'medium' => [
                'keywords' => ['bientôt', 'prochain', 'demain'],
                'patterns' => ['/!{1}/', '/quand possible/i'],
                'score' => 2
            ],
            'low' => [
                'keywords' => ['éventuellement', 'plus tard', 'quand vous pouvez'],
                'patterns' => [],
                'score' => 1
            ]
        ];
        
        $message_lower = strtolower($message);
        $urgency_score = 0;
        $detected_level = 'normal';
        
        foreach ($urgency_indicators as $level => $indicators) {
            $level_score = 0;
            
            // Vérifier les mots-clés
            foreach ($indicators['keywords'] as $keyword) {
                if (strpos($message_lower, $keyword) !== false) {
                    $level_score += $indicators['score'];
                }
            }
            
            // Vérifier les patterns
            foreach ($indicators['patterns'] as $pattern) {
                if (preg_match($pattern, $message)) {
                    $level_score += $indicators['score'];
                }
            }
            
            if ($level_score > $urgency_score) {
                $urgency_score = $level_score;
                $detected_level = $level;
            }
        }
        
        return [
            'level' => $detected_level,
            'score' => $urgency_score,
            'confidence' => min(1, $urgency_score / 4)
        ];
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
     * Analyser la continuité du sujet de manière avancée
     */
    private static function analyzeTopicContinuity($message, $history) {
        $basic_continuity = self::checkTopicContinuity($message, $history);
        
        return [
            'continuity' => $basic_continuity,
            'topic_similarity' => self::calculateTopicSimilarity($message, $history),
            'context_switch' => self::detectContextSwitch($message, $history),
            'reference_to_previous' => self::hasReferenceToPrivious($message)
        ];
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
    
    /**
     * Parser une date relative
     */
    private static function parseRelativeDate($relative_value) {
        $date_map = [
            'today' => 'today',
            'tomorrow' => '+1 day',
            'yesterday' => '-1 day',
            'this_week' => 'this week',
            'next_week' => 'next week',
            'this_month' => 'this month'
        ];
        
        if (isset($date_map[$relative_value])) {
            return date('Y-m-d', strtotime($date_map[$relative_value]));
        }
        
        return null;
    }
    
    /**
     * Parser une date absolue
     */
    private static function parseAbsoluteDate($date_string, $format) {
        switch ($format) {
            case 'dd/mm/yyyy':
                $timestamp = DateTime::createFromFormat('d/m/Y', $date_string);
                return $timestamp ? $timestamp->format('Y-m-d') : null;
                
            case 'dd month':
                $timestamp = strtotime($date_string);
                return $timestamp ? date('Y-m-d', $timestamp) : null;
                
            default:
                $timestamp = strtotime($date_string);
                return $timestamp ? date('Y-m-d', $timestamp) : null;
        }
    }
    
    /**
     * Évaluer le niveau de compétence de l'utilisateur
     */
    private static function assessUserSkillLevel($user_id) {
        global $wpdb;
        
        // Analyser les scores de quiz récents
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
            return 'unknown';
        }
        
        $avg_score = array_sum($recent_scores) / count($recent_scores);
        
        if ($avg_score >= 85) return 'advanced';
        if ($avg_score >= 70) return 'intermediate';
        if ($avg_score >= 50) return 'beginner';
        return 'struggling';
    }
    
    /**
     * Analyser les patterns d'interaction de l'utilisateur
     */
    private static function getUserInteractionPatterns($user_id) {
        global $wpdb;
        
        // Analyser les types de questions posées
        $question_types = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(response, '$.intent.primary') as intent,
                COUNT(*) as count
             FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE user_id = %d 
             AND message_type = 'user'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY JSON_EXTRACT(response, '$.intent.primary')",
            $user_id
        ));
        
        $patterns = [
            'dominant_intent' => null,
            'question_frequency' => 0,
            'help_seeking_behavior' => 'normal',
            'interaction_depth' => 'surface'
        ];
        
        if (!empty($question_types)) {
            // Trouver l'intention dominante
            $max_count = 0;
            foreach ($question_types as $type) {
                if ($type->count > $max_count) {
                    $max_count = $type->count;
                    $patterns['dominant_intent'] = $type->intent;
                }
            }
            
            $patterns['question_frequency'] = array_sum(array_column($question_types, 'count'));
            
            // Déterminer le comportement de recherche d'aide
            $help_requests = array_filter($question_types, function($type) {
                return in_array($type->intent, ['need_help', 'technical_issue', 'confusion']);
            });
            
            $help_ratio = array_sum(array_column($help_requests, 'count')) / $patterns['question_frequency'];
            
            if ($help_ratio > 0.4) {
                $patterns['help_seeking_behavior'] = 'frequent';
            } elseif ($help_ratio < 0.1) {
                $patterns['help_seeking_behavior'] = 'independent';
            }
        }
        
        return $patterns;
    }
    
    /**
     * Méthodes helper pour les fonctionnalités avancées
     */
    
    private static function getPreferredCommunicationStyle($user_id) {
        // Analyser le style de communication préféré basé sur l'historique
        return get_user_meta($user_id, 'tmm_communication_style', true) ?: 'balanced';
    }
    
    private static function identifyRecentStruggles($user_id) {
        // Identifier les difficultés récentes basées sur les échecs ou abandons
        global $wpdb;
        
        $struggles = $wpdb->get_results($wpdb->prepare(
            "SELECT item_id, item_type, status
             FROM {$wpdb->prefix}learnpress_user_items
             WHERE user_id = %d 
             AND status IN ('failed', 'started')
             AND start_time > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));
        
        return $struggles;
    }
    
    private static function assessMotivationLevel($user_id) {
        // Évaluer le niveau de motivation basé sur l'activité récente
        $recent_activity = TMMDashboardBadges::getRecentActivityCount($user_id, 7);
        
        if ($recent_activity > 10) return 'high';
        if ($recent_activity > 5) return 'medium';
        if ($recent_activity > 0) return 'low';
        return 'very_low';
    }
    
    private static function getUserTimePreferences($user_id) {
        // Analyser les préférences temporelles de l'utilisateur
        return [
            'preferred_study_time' => get_user_meta($user_id, 'tmm_preferred_study_time', true) ?: 'evening',
            'average_session_duration' => get_user_meta($user_id, 'tmm_avg_session_duration', true) ?: 30
        ];
    }
    
    private static function assessUserMood($message, $conversation_history) {
        // Évaluer l'humeur globale de l'utilisateur
        $sentiment = self::analyzeSentiment($message);
        
        if (count($conversation_history) > 2) {
            // Analyser la tendance sur les derniers messages
            $mood_trend = 0;
            foreach (array_slice($conversation_history, -3) as $msg) {
                $msg_sentiment = self::analyzeSentiment($msg->message);
                $mood_trend += $msg_sentiment['score'];
            }
            $mood_trend = $mood_trend / 3;
            
            return [
                'current' => $sentiment['sentiment'],
                'trend' => $mood_trend > 0 ? 'improving' : 'declining'
            ];
        }
        
        return [
            'current' => $sentiment['sentiment'],
            'trend' => 'stable'
        ];
    }
    
    private static function calculateContextualRelevance($message, $user_id) {
        // Calculer la pertinence contextuelle du message
        $user_context = self::getUserContext($user_id);
        $relevance_score = 0;
        
        // Vérifier si le message concerne les cours en cours
        if ($user_context['current_courses'] > 0) {
            $courses = self::findCourseMentions($message);
            if (!empty($courses)) {
                $relevance_score += 0.5;
            }
        }
        
        return min(1, $relevance_score);
    }
    
    private static function predictFollowUpQuestions($message, $conversation_history) {
        // Prédire les questions de suivi potentielles
        $intent = self::detectAdvancedIntent($message);
        $suggestions = [];
        
        switch ($intent['primary']) {
            case 'question_progress':
                $suggestions = [
                    'Voir le détail par cours',
                    'Comparer avec la moyenne',
                    'Définir des objectifs'
                ];
                break;
                
            case 'need_help':
                $suggestions = [
                    'Ressources supplémentaires',
                    'Contacter un tuteur',
                    'FAQ similaires'
                ];
                break;
                
            case 'course_recommendation':
                $suggestions = [
                    'Filtrer par durée',
                    'Voir les prérequis',
                    'Avis des étudiants'
                ];
                break;
        }
        
        return $suggestions;
    }
    
    private static function getConversationDuration($history) {
        if (count($history) < 2) {
            return 0;
        }
        
        $first = reset($history);
        $last = end($history);
        
        return strtotime($last->created_at) - strtotime($first->created_at);
    }
    
    private static function countTopicChanges($history) {
        // Compter les changements de sujet dans la conversation
        $topic_changes = 0;
        $previous_intent = null;
        
        foreach ($history as $msg) {
            if ($msg->message_type === 'user') {
                $intent = self::detectAdvancedIntent($msg->message);
                if ($previous_intent && $intent['primary'] !== $previous_intent) {
                    $topic_changes++;
                }
                $previous_intent = $intent['primary'];
            }
        }
        
        return $topic_changes;
    }
    
    private static function calculateAverageResponseTime($history) {
        // Calculer le temps de réponse moyen
        $response_times = [];
        
        for ($i = 1; $i < count($history); $i++) {
            if ($history[$i]->message_type === 'bot' && $history[$i-1]->message_type === 'user') {
                $time_diff = strtotime($history[$i]->created_at) - strtotime($history[$i-1]->created_at);
                $response_times[] = $time_diff;
            }
        }
        
        return empty($response_times) ? 0 : array_sum($response_times) / count($response_times);
    }
    
    private static function calculateTopicSimilarity($message, $history) {
        // Calculer la similarité du sujet avec l'historique
        // Implémentation simplifiée
        return 0.5;
    }
    
    private static function detectContextSwitch($message, $history) {
        // Détecter un changement de contexte
        if (empty($history)) {
            return false;
        }
        
        $current_intent = self::detectAdvancedIntent($message);
        $last_message = end($history);
        $last_intent = self::detectAdvancedIntent($last_message->message);
        
        return $current_intent['primary'] !== $last_intent['primary'];
    }
    
    private static function hasReferenceToPrivious($message) {
        // Vérifier si le message fait référence à un message précédent
        $reference_patterns = [
            '/comme je disais/i',
            '/comme mentionné/i',
            '/voir ci-dessus/i',
            '/précédemment/i',
            '/tout à l\'heure/i'
        ];
        
        foreach ($reference_patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function determineTone($sentiment, $urgency) {
        // Déterminer le ton approprié pour la réponse
        if ($urgency['level'] === 'critical') {
            return 'direct';
        }
        
        if ($sentiment['sentiment'] === 'negative') {
            return 'empathetic';
        }
        
        if ($sentiment['sentiment'] === 'positive') {
            return 'enthusiastic';
        }
        
        return 'professional';
    }
    
    private static function generatePersonalization($user_profile) {
        // Générer des éléments de personnalisation
        $personalization = [];
        
        if ($user_profile['skill_level'] === 'beginner') {
            $personalization[] = 'use_simple_language';
        }
        
        if ($user_profile['learning_style'] === 'visual') {
            $personalization[] = 'include_visuals';
        }
        
        if ($user_profile['motivation_level'] === 'low') {
            $personalization[] = 'add_encouragement';
        }
        
        return $personalization;
    }
    
    private static function constructMainResponse($context, $tone) {
        // Construire la réponse principale basée sur le contexte et le ton
        $response = self::buildContextualResponse($context);
        
        // Adapter le ton
        switch ($tone) {
            case 'empathetic':
                $response['text'] = 'Je comprends vos difficultés. ' . $response['text'];
                break;
                
            case 'enthusiastic':
                $response['text'] = 'C\'est fantastique ! ' . $response['text'];
                break;
                
            case 'direct':
                // Garder le texte direct et concis
                break;
        }
        
        return $response;
    }
}
