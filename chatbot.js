/**
 * TeachMeMore Dashboard Pro - Chatbot Scripts
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    const TMMChatbot = {
        
        // Configuration
        config: {
            typingDelay: 1000,
            messageDelay: 500,
            maxMessages: 50,
            autoSuggestions: true,
            soundEnabled: true
        },

        // État
        state: {
            isOpen: false,
            isTyping: false,
            messageCount: 0,
            conversationId: null,
            lastMessageTime: null
        },

        // Initialisation
        init: function() {
            this.bindEvents();
            this.loadState();
            this.checkUnreadMessages();
            
            // Message de bienvenue après un délai
            setTimeout(() => {
                this.showWelcomeMessage();
            }, 3000);
        },

        // Événements
        bindEvents: function() {
            const self = this;

            // Toggle chatbot
            $('.tmm-chatbot-toggle').on('click', function() {
                self.toggle();
            });

            // Fermer le chatbot
            $('.tmm-chatbot-close').on('click', function() {
                self.close();
            });

            // Soumettre un message
            $('.tmm-chatbot-input').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Suggestions
            $(document).on('click', '.tmm-suggestion-chip', function() {
                const message = $(this).text();
                self.sendMessage(message);
            });

            // Actions rapides
            $(document).on('click', '.tmm-quick-action', function() {
                const action = $(this).data('action');
                self.handleQuickAction(action);
            });

            // Feedback
            $(document).on('click', '.tmm-feedback-btn', function() {
                const messageId = $(this).closest('.tmm-chatbot-message').data('message-id');
                const helpful = $(this).hasClass('positive');
                self.sendFeedback(messageId, helpful);
            });

            // Touche Entrée pour envoyer
            $('.tmm-chatbot-text').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Fermer avec Escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.state.isOpen) {
                    self.close();
                }
            });

            // Clic en dehors pour fermer
            $(document).on('click', function(e) {
                if (self.state.isOpen && 
                    !$(e.target).closest('.tmm-chatbot-widget').length) {
                    self.close();
                }
            });
        },

        // Toggle ouverture/fermeture
        toggle: function() {
            if (this.state.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        // Ouvrir le chatbot
        open: function() {
            $('.tmm-chatbot-window').fadeIn(300);
            $('.tmm-chatbot-toggle').addClass('active');
            this.state.isOpen = true;
            
            // Focus sur l'input
            setTimeout(() => {
                $('.tmm-chatbot-text').focus();
            }, 300);
            
            // Marquer les messages comme lus
            this.markMessagesAsRead();
            
            // Scroll vers le bas
            this.scrollToBottom();
            
            // Sauvegarder l'état
            this.saveState();
            
            // Analytics
            this.trackEvent('chatbot_opened');
        },

        // Fermer le chatbot
        close: function() {
            $('.tmm-chatbot-window').fadeOut(300);
            $('.tmm-chatbot-toggle').removeClass('active');
            this.state.isOpen = false;
            
            // Sauvegarder l'état
            this.saveState();
            
            // Analytics
            this.trackEvent('chatbot_closed');
        },

        // Envoyer un message
        sendMessage: function(message) {
            const input = $('.tmm-chatbot-text');
            message = message || input.val().trim();
            
            if (!message) return;
            
            // Désactiver l'input
            input.prop('disabled', true);
            
            // Ajouter le message utilisateur
            this.addMessage(message, 'user');
            
            // Réinitialiser l'input
            input.val('').prop('disabled', false).focus();
            
            // Afficher l'indicateur de frappe
            this.showTypingIndicator();
            
            // Envoyer au serveur
            $.ajax({
                url: tmm_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmm_chatbot_message',
                    message: message,
                    conversation_id: this.state.conversationId,
                    _wpnonce: tmm_ajax.nonce
                },
                success: (response) => {
                    this.hideTypingIndicator();
                    
                    if (response.success) {
                        this.handleResponse(response.data);
                    } else {
                        this.addMessage(
                            response.data.message || 'Désolé, une erreur est survenue.',
                            'bot',
                            'error'
                        );
                    }
                },
                error: () => {
                    this.hideTypingIndicator();
                    this.addMessage(
                        'Désolé, je ne peux pas répondre pour le moment. Veuillez réessayer.',
                        'bot',
                        'error'
                    );
                }
            });
        },

        // Gérer la réponse du serveur
        handleResponse: function(data) {
            // Ajouter la réponse
            this.addMessage(data.message, 'bot', 'response', data.intent);
            
            // Mettre à jour les suggestions
            if (data.suggestions && data.suggestions.length > 0) {
                this.updateSuggestions(data.suggestions);
            }
            
            // Exécuter une action si nécessaire
            if (data.action) {
                this.executeAction(data.action);
            }
            
            // Jouer un son
            if (this.config.soundEnabled) {
                this.playSound('message');
            }
        },

        // Ajouter un message dans la conversation
        addMessage: function(message, sender, type = 'message', metadata = {}) {
            const messageId = 'msg-' + Date.now();
            const messageHtml = this.createMessageHtml(message, sender, messageId, metadata);
            
            $('.tmm-chatbot-messages').append(messageHtml);
            
            // Animation
            setTimeout(() => {
                $('#' + messageId).addClass('show');
            }, 10);
            
            // Scroll vers le bas
            this.scrollToBottom();
            
            // Incrémenter le compteur
            this.state.messageCount++;
            
            // Limiter le nombre de messages
            this.limitMessages();
            
            // Mettre à jour le timestamp
            this.state.lastMessageTime = Date.now();
            
            return messageId;
        },

        // Créer le HTML d'un message
        createMessageHtml: function(message, sender, messageId, metadata) {
            const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const avatarIcon = sender === 'bot' ? 'fa-robot' : 'fa-user';
            
            let html = `
                <div id="${messageId}" class="tmm-chatbot-message ${sender}" data-message-id="${messageId}">
                    <div class="tmm-message-avatar">
                        <i class="fas ${avatarIcon}"></i>
                    </div>
                    <div class="tmm-message-content">
                        <p>${this.escapeHtml(message)}</p>
                        <span class="tmm-message-time">${time}</span>
            `;
            
            // Ajouter les boutons de feedback pour les messages du bot
            if (sender === 'bot' && metadata.intent !== 'greeting') {
                html += `
                        <div class="tmm-message-feedback">
                            <button class="tmm-feedback-btn positive" title="Utile">
                                <i class="fas fa-thumbs-up"></i>
                            </button>
                            <button class="tmm-feedback-btn negative" title="Pas utile">
                                <i class="fas fa-thumbs-down"></i>
                            </button>
                        </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            return html;
        },

        // Afficher l'indicateur de frappe
        showTypingIndicator: function() {
            if (this.state.isTyping) return;
            
            const typingHtml = `
                <div class="tmm-chatbot-message bot tmm-typing-message">
                    <div class="tmm-message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="tmm-message-content">
                        <div class="tmm-typing-indicator">
                            <span class="tmm-typing-dot"></span>
                            <span class="tmm-typing-dot"></span>
                            <span class="tmm-typing-dot"></span>
                        </div>
                    </div>
                </div>
            `;
            
            $('.tmm-chatbot-messages').append(typingHtml);
            this.state.isTyping = true;
            this.scrollToBottom();
        },

        // Masquer l'indicateur de frappe
        hideTypingIndicator: function() {
            $('.tmm-typing-message').remove();
            this.state.isTyping = false;
        },

        // Mettre à jour les suggestions
        updateSuggestions: function(suggestions) {
            const container = $('.tmm-chatbot-suggestions');
            container.empty();
            
            suggestions.forEach(suggestion => {
                const chip = $('<button>')
                    .addClass('tmm-suggestion-chip')
                    .text(suggestion);
                container.append(chip);
            });
            
            // Animation
            container.find('.tmm-suggestion-chip').each(function(index) {
                $(this).css('animation-delay', (index * 0.1) + 's');
            });
        },

        // Exécuter une action
        executeAction: function(action) {
            switch (action.type) {
                case 'navigate':
                    if (window.TMMDashboard) {
                        window.TMMDashboard.navigateToSection(action.target.substring(1));
                        this.close();
                    }
                    break;
                    
                case 'redirect':
                    window.location.href = action.url;
                    break;
                    
                case 'open_course':
                    window.open(action.url, '_blank');
                    break;
                    
                case 'show_modal':
                    // Implémenter l'affichage de modal
                    break;
                    
                default:
                    console.log('Action non reconnue:', action);
            }
        },

        // Gérer les actions rapides
        handleQuickAction: function(action) {
            switch (action) {
                case 'help':
                    this.sendMessage('Aide');
                    break;
                    
                case 'courses':
                    this.sendMessage('Voir mes cours');
                    break;
                    
                case 'progress':
                    this.sendMessage('Ma progression');
                    break;
                    
                case 'clear':
                    this.clearConversation();
                    break;
            }
        },

        // Envoyer un feedback
        sendFeedback: function(messageId, helpful) {
            const button = $(`.tmm-chatbot-message[data-message-id="${messageId}"] .tmm-feedback-btn`);
            
            // Désactiver les boutons
            button.prop('disabled', true);
            
            $.ajax({
                url: tmm_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'tmm_chatbot_feedback',
                    message_id: messageId,
                    helpful: helpful,
                    _wpnonce: tmm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Marquer visuellement le feedback
                        if (helpful) {
                            button.filter('.positive').addClass('selected');
                        } else {
                            button.filter('.negative').addClass('selected');
                        }
                        
                        this.showToast('Merci pour votre retour !');
                    }
                }
            });
        },

        // Effacer la conversation
        clearConversation: function() {
            if (confirm('Êtes-vous sûr de vouloir effacer la conversation ?')) {
                $('.tmm-chatbot-messages').empty();
                this.state.messageCount = 0;
                this.showWelcomeMessage();
            }
        },

        // Message de bienvenue
        showWelcomeMessage: function() {
            const messages = [
                'Bonjour ! Je suis votre assistant d\'apprentissage. Comment puis-je vous aider aujourd\'hui ?',
                'Salut ! Prêt à apprendre quelque chose de nouveau ? Je suis là pour vous guider.',
                'Bienvenue ! Je peux vous aider avec vos cours, votre progression, ou répondre à vos questions.'
            ];
            
            const randomMessage = messages[Math.floor(Math.random() * messages.length)];
            this.addMessage(randomMessage, 'bot', 'greeting', {intent: 'greeting'});
            
            // Suggestions initiales
            this.updateSuggestions([
                'Voir mes cours',
                'Ma progression',
                'Aide',
                'Cours recommandés'
            ]);
        },

        // Vérifier les messages non lus
        checkUnreadMessages: function() {
            // Implémenter la vérification des messages non lus
            // et mettre à jour le badge si nécessaire
        },

        // Marquer les messages comme lus
        markMessagesAsRead: function() {
            $('.tmm-chatbot-badge').fadeOut();
        },

        // Scroll vers le bas
        scrollToBottom: function() {
            const container = $('.tmm-chatbot-messages');
            container.animate({
                scrollTop: container[0].scrollHeight
            }, 300);
        },

        // Limiter le nombre de messages
        limitMessages: function() {
            const messages = $('.tmm-chatbot-message');
            if (messages.length > this.config.maxMessages) {
                messages.slice(0, messages.length - this.config.maxMessages).remove();
            }
        },

        // Jouer un son
        playSound: function(type) {
            // Implémenter la lecture de sons
            // const audio = new Audio('/path/to/sound.mp3');
            // audio.play();
        },

        // Afficher un toast
        showToast: function(message, type = 'success') {
            const toast = $('<div>')
                .addClass('tmm-toast tmm-toast-' + type)
                .text(message);
            
            $('body').append(toast);
            
            setTimeout(() => {
                toast.addClass('show');
            }, 100);
            
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        },

        // Échapper le HTML
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        // Sauvegarder l'état
        saveState: function() {
            localStorage.setItem('tmm_chatbot_state', JSON.stringify({
                isOpen: this.state.isOpen,
                timestamp: Date.now()
            }));
        },

        // Charger l'état
        loadState: function() {
            const savedState = localStorage.getItem('tmm_chatbot_state');
            
            if (savedState) {
                try {
                    const state = JSON.parse(savedState);
                    
                    // Ouvrir automatiquement si c'était ouvert récemment
                    if (state.isOpen && Date.now() - state.timestamp < 1800000) { // 30 minutes
                        this.open();
                    }
                } catch (e) {
                    console.error('Erreur lors du chargement de l\'état du chatbot', e);
                }
            }
        },

        // Tracking d'événements
        trackEvent: function(event, data = {}) {
            // Implémenter le tracking Analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', event, {
                    event_category: 'Chatbot',
                    ...data
                });
            }
        }
    };

    // Initialisation
    $(document).ready(function() {
        if ($('.tmm-chatbot-widget').length) {
            TMMChatbot.init();
            
            // Exposer globalement pour debug
            window.TMMChatbot = TMMChatbot;
        }
    });

})(jQuery);