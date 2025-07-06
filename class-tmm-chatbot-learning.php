<?php
/**
 * Système d'apprentissage du chatbot
 */
class TMMChatbotLearning {
    
    /**
     * Apprendre des interactions utilisateur
     */
    public static function learnFromInteraction($user_id, $message, $response, $feedback = null) {
        global $wpdb;
        
        // Enregistrer l'interaction
        $interaction_data = [
            'user_id' => $user_id,
            'message' => $message,
            'intent_detected' => $response['intent'] ?? 'unknown',
            'response_type' => $response['type'] ?? 'text',
            'confidence_score' => $response['confidence'] ?? 0,
            'user_satisfaction' => $feedback['satisfaction'] ?? null,
            'response_helpful' => $feedback['helpful'] ?? null,
            'timestamp' => current_time('mysql')
        ];
        
        $wpdb->insert(
            $wpdb->prefix . 'tmm_chatbot_learning',
            $interaction_data
        );
        
        // Mettre à jour les patterns de succès
        if ($feedback && $feedback['helpful'] === true) {
            self::updateSuccessfulPatterns($message, $response['intent']);
        }
        
        // Ajuster les poids du modèle
        self::adjustModelWeights($user_id, $interaction_data);
    }
    
    /**
     * Créer un profil d'apprentissage personnalisé
     */
    public static function buildUserLearningProfile($user_id) {
        global $wpdb;
        
        // Analyser l'historique complet
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmm_chatbot_messages
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT 1000",
            $user_id
        ));
        
        $profile = [
            'communication_style' => self::analyzeCommunicationStyle($history),
            'preferred_response_types' => self::analyzePreferredResponses($history),
            'common_queries' => self::extractCommonQueries($history),
            'interaction_patterns' => self::analyzeInteractionPatterns($history),
            'vocabulary_level' => self::assessVocabularyLevel($history),
            'technical_proficiency' => self::assessTechnicalLevel($history)
        ];
        
        // Sauvegarder le profil
        update_user_meta($user_id, 'tmm_chatbot_learning_profile', $profile);
        
        return $profile;
    }
    
    /**
     * Système de prédiction des besoins
     */
    public static function predictUserNeeds($user_id) {
        $profile = get_user_meta($user_id, 'tmm_chatbot_learning_profile', true);
        $current_context = self::getCurrentUserContext($user_id);
        
        $predictions = [];
        
        // Prédire le prochain cours probable
        if ($current_context['courses_in_progress'] > 0) {
            $next_likely_action = self::predictNextAction($user_id, $current_context);
            $predictions[] = [
                'type' => 'next_action',
                'prediction' => $next_likely_action,
                'confidence' => $next_likely_action['confidence']
            ];
        }
        
        // Prédire les questions probables
        $likely_questions = self::predictLikelyQuestions($user_id, $current_context);
        $predictions[] = [
            'type' => 'likely_questions',
            'predictions' => $likely_questions
        ];
        
        // Prédire les difficultés potentielles
        $potential_issues = self::predictPotentialIssues($user_id);
        if (!empty($potential_issues)) {
            $predictions[] = [
                'type' => 'potential_issues',
                'predictions' => $potential_issues
            ];
        }
        
        return $predictions;
    }
}