/**
 * TeachMeMore Dashboard Pro - Scripts principaux
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Objet principal du dashboard
    const TMMDashboard = {
        
        // Configuration
        config: {
            animationSpeed: 300,
            chartUpdateInterval: 5000,
            notificationCheckInterval: 60000,
            debug: false
        },

        // État du dashboard
        state: {
            currentSection: 'dashboard',
            isLoading: false,
            charts: {},
            notifications: [],
            searchTimeout: null,
            chatbotLoaded: false
        },

        // Initialisation
        init: function() {
            this.log('Initialisation du dashboard...');
            
            // Initialiser les composants
            this.initNavigation();
            this.initTabs();
            this.initCharts();
            this.initForms();
            this.initSearch();
            this.initCourseFilters();
            this.initNotifications();
            this.initTooltips();
            this.initModals();
            this.initParcours();
            this.initChatbot();
            
            // Restaurer l'état sauvegardé
            this.restoreState();
            
            // Événements globaux
            this.bindGlobalEvents();
            
            this.log('Dashboard initialisé');
        },

        // Logger pour debug
        log: function(message, data) {
            if (this.config.debug) {
                console.log('[TMM Dashboard] ' + message, data || '');
            }
        },

        // Navigation entre sections
        initNavigation: function() {
            const self = this;
            
            $('.ps-nav-link').on('click', function(e) {
                e.preventDefault();
                
                const targetId = $(this).attr('href').substring(1);
                self.navigateToSection(targetId);
            });
        },

        navigateToSection: function(sectionId) {
            // Mettre à jour la navigation
            $('.ps-nav-item').removeClass('active');
            $('.ps-nav-link[href="#' + sectionId + '"]').parent().addClass('active');
            
            // Afficher la section
            $('.ps-dashboard-section').removeClass('active').fadeOut(this.config.animationSpeed);
            $('#' + sectionId).addClass('active').fadeIn(this.config.animationSpeed);
            
            // Mettre à jour le titre
            const title = $('.ps-nav-link[href="#' + sectionId + '"] span').text();
            $('.ps-dashboard-title').text(title);
            
            // Sauvegarder l'état
            this.state.currentSection = sectionId;
            this.saveState();
            
            // Déclencher un événement
            $(document).trigger('tmm:section:changed', [sectionId]);
            
            // Actions spécifiques par section
            this.handleSectionChange(sectionId);
        },

        handleSectionChange: function(sectionId) {
            switch(sectionId) {
                case 'dashboard':
                    this.updateCharts();
                    break;
                case 'my-courses':
                    this.updateSearchVisibility('show');
                    break;
                default:
                    this.updateSearchVisibility('hide');
            }
        },

        // Gestion des onglets
        initTabs: function() {
            $('.ps-tab-button').on('click', function() {
                const tabId = $(this).data('tab');
                const container = $(this).closest('.ps-dashboard-section');
                
                // Mettre à jour les boutons
                container.find('.ps-tab-button').removeClass('active');
                $(this).addClass('active');
                
                // Afficher le contenu
                container.find('.ps-tab-content').removeClass('active');
                container.find('#' + tabId).addClass('active');
            });
        },

        // Initialisation des graphiques
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                this.log('Chart.js non chargé');
                return;
            }

            this.initProgressChart();
            this.initAnalyticsChart();
        },

        initProgressChart: function() {
            const canvas = document.getElementById('progressChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            this.state.charts.progress = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: window.tmmDashboardData?.weeklyLabels || [],
                    datasets: [{
                        label: 'Activités complétées',
                        data: window.tmmDashboardData?.weeklyData?.completed || [],
                        backgroundColor: 'rgba(255, 215, 0, 0.8)',
                        borderColor: 'rgba(255, 215, 0, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' activités';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#999'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#999'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        initAnalyticsChart: function() {
            const canvas = document.getElementById('analyticsChart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            
            // Données initiales
            const analyticsData = window.tmmDashboardData?.analytics || {};
            const labels = analyticsData.activity_by_day?.map(d => d.day) || [];
            const data = analyticsData.activity_by_day?.map(d => d.count) || [];

            this.state.charts.analytics = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Activité',
                        data: data,
                        borderColor: 'rgba(255, 215, 0, 1)',
                        backgroundColor: 'rgba(255, 215, 0, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#999'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#999'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        updateCharts: function() {
            // Mettre à jour les graphiques si nécessaire
            if (this.state.charts.progress) {
                this.state.charts.progress.update();
            }
            if (this.state.charts.analytics) {
                this.state.charts.analytics.update();
            }
        },

        // Gestion des formulaires
        initForms: function() {
            this.initProfileForm();
            this.initPasswordForm();
            this.initNotificationsForm();
            this.initPreferencesForm();
        },

        initProfileForm: function() {
            const self = this;
            
            $('#ps-profile-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const formData = new FormData(this);
                formData.append('action', 'tmm_update_user_profile');
                formData.append('_wpnonce', tmm_ajax.nonce);
                
                self.showLoader();
                
                $.ajax({
                    url: tmm_ajax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        self.hideLoader();
                        
                        if (response.success) {
                            self.showMessage('#ps-profile-message', response.data.message, 'success');
                            
                            // Mettre à jour l'interface
                            if (response.data.user) {
                                $('.ps-user-name').text(response.data.user.display_name);
                                if (response.data.user.avatar) {
                                    $('.ps-user-avatar img').attr('src', response.data.user.avatar);
                                }
                            }
                        } else {
                            self.showMessage('#ps-profile-message', response.data.message, 'error');
                        }
                    },
                    error: function() {
                        self.hideLoader();
                        self.showMessage('#ps-profile-message', tmm_ajax.strings.error, 'error');
                    }
                });
            });

            // Prévisualisation de l'avatar
            $('#avatar').on('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        $('.ps-avatar-preview img').attr('src', event.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            });
        },

        initPasswordForm: function() {
            const self = this;
            
            $('#ps-password-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const newPassword = $('#ps-new-password').val();
                const confirmPassword = $('#ps-confirm-password').val();
                
                // Validation
                if (newPassword !== confirmPassword) {
                    self.showMessage('#ps-password-message', 'Les mots de passe ne correspondent pas', 'error');
                    return;
                }
                
                if (newPassword.length < 8) {
                    self.showMessage('#ps-password-message', 'Le mot de passe doit contenir au moins 8 caractères', 'error');
                    return;
                }
                
                const formData = form.serialize() + '&action=tmm_change_user_password&_wpnonce=' + tmm_ajax.nonce;
                
                self.showLoader();
                
                $.post(tmm_ajax.ajaxurl, formData, function(response) {
                    self.hideLoader();
                    
                    if (response.success) {
                        self.showMessage('#ps-password-message', response.data.message, 'success');
                        form[0].reset();
                        
                        // Redirection si nécessaire
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 2000);
                        }
                    } else {
                        self.showMessage('#ps-password-message', response.data.message, 'error');
                    }
                }).fail(function() {
                    self.hideLoader();
                    self.showMessage('#ps-password-message', tmm_ajax.strings.error, 'error');
                });
            });
        },

        initNotificationsForm: function() {
            const self = this;
            
            $('#ps-notifications-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const preferences = {};
                
                form.find('input[type="checkbox"]').each(function() {
                    preferences[$(this).attr('name')] = $(this).is(':checked') ? '1' : '0';
                });
                
                self.showLoader();
                
                $.post(tmm_ajax.ajaxurl, {
                    action: 'tmm_update_user_preferences',
                    preferences: preferences,
                    _wpnonce: tmm_ajax.nonce
                }, function(response) {
                    self.hideLoader();
                    
                    if (response.success) {
                        self.showMessage('#ps-notifications-message', response.data.message, 'success');
                    } else {
                        self.showMessage('#ps-notifications-message', response.data.message || tmm_ajax.strings.error, 'error');
                    }
                }).fail(function() {
                    self.hideLoader();
                    self.showMessage('#ps-notifications-message', tmm_ajax.strings.error, 'error');
                });
            });
        },

        initPreferencesForm: function() {
            const self = this;
            
            $('#ps-preferences-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const preferences = {};
                
                form.find('select').each(function() {
                    preferences[$(this).attr('name')] = $(this).val();
                });
                
                self.showLoader();
                
                $.post(tmm_ajax.ajaxurl, {
                    action: 'tmm_update_user_preferences',
                    preferences: preferences,
                    _wpnonce: tmm_ajax.nonce
                }, function(response) {
                    self.hideLoader();
                    
                    if (response.success) {
                        self.showMessage('#ps-preferences-message', response.data.message, 'success');
                    } else {
                        self.showMessage('#ps-preferences-message', response.data.message || tmm_ajax.strings.error, 'error');
                    }
                }).fail(function() {
                    self.hideLoader();
                    self.showMessage('#ps-preferences-message', tmm_ajax.strings.error, 'error');
                });
            });
        },

        // Recherche de cours
        initSearch: function() {
            const self = this;
            
            $('#ps-course-search').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                
                clearTimeout(self.state.searchTimeout);
                
                if (searchTerm.length < 2) {
                    self.resetSearch();
                    return;
                }
                
                self.state.searchTimeout = setTimeout(function() {
                    self.performSearch(searchTerm);
                }, 300);
            });
        },

        performSearch: function(searchTerm) {
            const self = this;
            
            // Recherche locale d'abord
            $('.ps-course-row').each(function() {
                const courseName = $(this).find('h4').text().toLowerCase();
                if (courseName.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Recherche serveur pour plus de résultats
            $.post(tmm_ajax.ajaxurl, {
                action: 'tmm_search_courses',
                query: searchTerm,
                _wpnonce: tmm_ajax.nonce
            }, function(response) {
                if (response.success && response.data.results.length > 0) {
                    self.displaySearchResults(response.data.results);
                }
            });
        },

        resetSearch: function() {
            $('.ps-course-row').show();
            $('.ps-search-results').remove();
        },

        displaySearchResults: function(results) {
            // Implémenter l'affichage des résultats de recherche
            this.log('Résultats de recherche:', results);
        },

        updateSearchVisibility: function(action) {
            const searchBox = $('#ps-search-box');
            if (action === 'show') {
                searchBox.fadeIn();
            } else {
                searchBox.fadeOut();
            }
        },

        // Filtres de cours
        initCourseFilters: function() {
            const self = this;
            
            // Gestion des filtres
            $(document).on('click', '.ps-filter-btn', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const filter = $btn.data('filter');
                
                // Mettre à jour l'état actif
                $('.ps-filter-btn').removeClass('active');
                $btn.addClass('active');
                
                // Supprimer les messages précédents
                $('.ps-no-filter-results').remove();
                
                // Filtrer les cours
                let visibleCount = 0;
                
                $('.ps-course-card').each(function() {
                    const $card = $(this);
                    const status = $card.data('status');
                    
                    if (filter === 'all') {
                        $card.show();
                        visibleCount++;
                    } else if (filter === 'enrolled') {
                        // Pour le filtre "enrolled", afficher tous les cours où l'utilisateur est inscrit
                        if (status === 'enrolled' || status === 'in_progress' || status === 'completed') {
                            $card.show();
                            visibleCount++;
                        } else {
                            $card.hide();
                        }
                    } else if (status === filter) {
                        $card.show();
                        visibleCount++;
                    } else {
                        $card.hide();
                    }
                });
                
                // Afficher un message si aucun cours
                if (visibleCount === 0) {
                    const message = self.getEmptyFilterMessage(filter);
                    $('.ps-courses-grid').after(
                        '<div class="ps-no-filter-results ps-empty-state">' +
                        '<i class="fas fa-search"></i>' +
                        '<p>' + message + '</p>' +
                        '</div>'
                    );
                }
                
                // Réinitialiser la recherche
                $('#ps-courses-search-input').val('').trigger('input');
            });
            
            // Recherche dans les cours
            $(document).on('input', '#ps-courses-search-input', function() {
                const searchTerm = $(this).val().toLowerCase();
                const activeFilter = $('.ps-filter-btn.active').data('filter') || 'all';
                
                $('.ps-no-search-results').remove();
                let visibleCount = 0;
                
                $('.ps-course-card').each(function() {
                    const $card = $(this);
                    const courseName = $card.data('name') || '';
                    const status = $card.data('status');
                    
                    // Vérifier d'abord le filtre
                    let matchesFilter = false;
                    if (activeFilter === 'all') {
                        matchesFilter = true;
                    } else if (activeFilter === 'enrolled') {
                        matchesFilter = (status === 'enrolled' || status === 'in_progress' || status === 'completed');
                    } else {
                        matchesFilter = (status === activeFilter);
                    }
                    
                    // Ensuite vérifier la recherche
                    const matchesSearch = searchTerm === '' || courseName.includes(searchTerm);
                    
                    if (matchesFilter && matchesSearch) {
                        $card.show();
                        visibleCount++;
                    } else {
                        $card.hide();
                    }
                });
                
                // Message si aucun résultat
                if (visibleCount === 0 && searchTerm !== '') {
                    $('.ps-courses-grid').after(
                        '<div class="ps-no-search-results ps-empty-state">' +
                        '<i class="fas fa-search"></i>' +
                        '<p>Aucun cours ne correspond à votre recherche.</p>' +
                        '</div>'
                    );
                }
            });
        },

        getEmptyFilterMessage: function(filter) {
            const messages = {
                'in_progress': 'Aucun cours en progression.',
                'completed': 'Aucun cours terminé.',
                'not_started': 'Tous vos cours ont été commencés !',
                'enrolled': 'Vous n\'êtes inscrit à aucun cours.',
                'all': 'Aucun cours disponible.'
            };
            
            return messages[filter] || 'Aucun cours trouvé.';
        },

        // Notifications
        initNotifications: function() {
            const self = this;
            
            // Vérifier les notifications au chargement
            this.checkNotifications();
            
            // Vérifier périodiquement
            setInterval(function() {
                self.checkNotifications();
            }, this.config.notificationCheckInterval);
            
            // Clic sur la cloche
            $('.ps-notification-bell').on('click', function() {
                self.toggleNotificationPanel();
            });
        },

        checkNotifications: function() {
            const self = this;
            
            $.post(tmm_ajax.ajaxurl, {
                action: 'tmm_get_notifications',
                _wpnonce: tmm_ajax.nonce
            }, function(response) {
                if (response.success) {
                    self.updateNotificationCount(response.data.unread_count);
                    self.state.notifications = response.data.notifications;
                }
            });
        },

        updateNotificationCount: function(count) {
            const badge = $('.ps-notification-count');
            
            if (count > 0) {
                badge.text(count).show();
            } else {
                badge.hide();
            }
        },

        toggleNotificationPanel: function() {
            // Implémenter le panneau de notifications
            this.log('Toggle notifications panel');
        },

        // Parcours d'apprentissage
        initParcours: function() {
            const self = this;
            
            // Gestion des checkboxes de semaines
            $(document).on('change', '.week-checkbox', function() {
                const checkbox = $(this);
                const parcours = checkbox.data('parcours');
                const month = checkbox.data('month');
                const week = checkbox.val();
                const isChecked = checkbox.is(':checked');
                
                // Mettre à jour l'UI immédiatement
                self.updateParcoursProgress(checkbox);
                
                // Sauvegarder sur le serveur
                $.post(tmm_ajax.ajaxurl, {
                    action: 'tmm_save_week_progress',
                    parcours: parcours,
                    month: month,
                    value: week,
                    checked: isChecked ? '1' : '0',
                    _wpnonce: tmm_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        self.showToast('Progression sauvegardée');
                        
                        // Mettre à jour les badges si nécessaire
                        if (response.data.progress && response.data.progress.is_complete) {
                            self.showCertificateModal(parcours);
                        }
                    } else {
                        // Annuler le changement en cas d'erreur
                        checkbox.prop('checked', !isChecked);
                        self.updateParcoursProgress(checkbox);
                        self.showToast('Erreur lors de la sauvegarde', 'error');
                    }
                });
            });
            
            // Info-bulles
            this.initCourseTooltips();
        },

        updateParcoursProgress: function(checkbox) {
            const container = checkbox.closest('.timeline-weeks-enhanced');
            const checkedCount = container.find('.week-checkbox:checked').length;
            const totalCount = container.find('.week-checkbox').length;
            const percent = (checkedCount / totalCount) * 100;
            
            // Mettre à jour la barre de progression du mois
            const monthProgress = container.nextAll('.month-progress-container').first();
            monthProgress.find('.month-progress-bar').css('width', percent + '%');
            monthProgress.find('.month-progress-text').text(Math.round(percent) + '% complété');
            
            // Afficher le badge si mois complété
            if (percent === 100) {
                container.nextAll('.month-badge').first().fadeIn();
            }
            
            // Mettre à jour la progression globale
            this.updateGlobalProgress();
        },

        updateGlobalProgress: function() {
            const totalChecked = $('.week-checkbox:checked').length;
            const totalPossible = $('.week-checkbox').length;
            const globalPercent = totalPossible > 0 ? (totalChecked / totalPossible) * 100 : 0;
            
            $('.global-progress-bar').css('width', globalPercent + '%');
            $('.global-progress-text').text(Math.round(globalPercent) + '% du parcours complété');
            
            if (globalPercent === 100) {
                $('.global-badge').fadeIn();
            }
        },

        initCourseTooltips: function() {
            $('.info-icon').on('mouseenter', function() {
                const $this = $(this);
                const tooltipText = $this.data('reco');
                if (!tooltipText) return;
                
                const tooltip = $('<div class="custom-tooltip"></div>').text(tooltipText);
                $('body').append(tooltip);
                
                const rect = this.getBoundingClientRect();
                const tooltipRect = tooltip[0].getBoundingClientRect();
                const top = rect.top + window.scrollY - tooltipRect.height - 12;
                const left = rect.left + window.scrollX + (rect.width / 2) - (tooltipRect.width / 2);
                
                tooltip.css({
                    top: top + 'px',
                    left: left + 'px',
                    opacity: 1
                });
            }).on('mouseleave', function() {
                $('.custom-tooltip').remove();
            });
        },

        // Chatbot
        initChatbot: function() {
            const self = this;
            
            // Toggle chatbot
            $('.tmm-chatbot-toggle').on('click', function() {
                $('.tmm-chatbot-container').toggleClass('active');
                
                // Charger l'historique au premier affichage
                if ($('.tmm-chatbot-container').hasClass('active') && !self.state.chatbotLoaded) {
                    self.loadChatHistory();
                    self.state.chatbotLoaded = true;
                }
            });
            
            // Fermer le chatbot
            $('.tmm-chatbot-close').on('click', function() {
                $('.tmm-chatbot-container').removeClass('active');
            });
            
            // Envoyer un message
            $('.tmm-chatbot-input').on('submit', function(e) {
                e.preventDefault();
                
                const input = $(this).find('input[type="text"]');
                const message = input.val().trim();
                
                if (message) {
                    self.sendChatMessage(message);
                    input.val('');
                }
            });
            
            // Suggestions rapides
            $(document).on('click', '.tmm-chat-suggestion', function() {
                const value = $(this).data('value');
                self.sendChatMessage(value);
            });
        },

        sendChatMessage: function(message) {
            const self = this;
            
            // Ajouter le message de l'utilisateur
            self.appendChatMessage(message, 'user');
            
            // Afficher l'indicateur de frappe
            self.showTypingIndicator();
            
            $.post(tmm_ajax.ajaxurl, {
                action: 'tmm_chatbot_message',
                message: message,
                _wpnonce: tmm_ajax.nonce
            }, function(response) {
                self.hideTypingIndicator();
                
                if (response.success) {
                    self.appendChatMessage(response.data.response.text, 'bot', response.data.response);
                    
                    // Afficher les suggestions si disponibles
                    if (response.data.suggestions && response.data.suggestions.length > 0) {
                        self.showChatSuggestions(response.data.suggestions);
                    }
                } else {
                    self.appendChatMessage('Désolé, une erreur est survenue.', 'bot');
                }
            }).fail(function() {
                self.hideTypingIndicator();
                self.appendChatMessage('Erreur de connexion. Veuillez réessayer.', 'bot');
            });
        },

        appendChatMessage: function(message, sender, data) {
            const time = new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
            const messageHtml = `
                <div class="tmm-chat-message tmm-chat-${sender}">
                    <div class="tmm-chat-avatar">
                        ${sender === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>'}
                    </div>
                    <div class="tmm-chat-content">
                        <div class="tmm-chat-text">${this.formatChatMessage(message, data)}</div>
                        <div class="tmm-chat-time">${time}</div>
                    </div>
                </div>
            `;
            
            $('.tmm-chatbot-messages').append(messageHtml);
            this.scrollChatToBottom();
        },

        formatChatMessage: function(message, data) {
            // Convertir le markdown simple en HTML
            let formatted = message
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
            
            // Si c'est un type spécial de message
            if (data && data.type === 'cards') {
                formatted += '<div class="tmm-chat-cards">';
                data.data.forEach(card => {
                    formatted += `
                        <div class="tmm-chat-card">
                            ${card.image ? `<img src="${card.image}" alt="${card.title}">` : ''}
                            <h5>${card.title}</h5>
                            <p>${card.subtitle}</p>
                            <a href="${card.url}" class="tmm-chat-card-link">Voir</a>
                        </div>
                    `;
                });
                formatted += '</div>';
            }
            
            // Ajouter les boutons d'action si disponibles
            if (data && data.actions && data.actions.length > 0) {
                formatted += '<div class="tmm-chat-actions">';
                data.actions.forEach(action => {
                    formatted += `<button class="tmm-chat-suggestion" data-value="${action.value}">${action.label}</button>`;
                });
                formatted += '</div>';
            }
            
            return formatted;
        },

        showChatSuggestions: function(suggestions) {
            let html = '<div class="tmm-chat-suggestions">';
            suggestions.forEach(suggestion => {
                html += `<button class="tmm-chat-suggestion" data-value="${suggestion.value}">${suggestion.label}</button>`;
            });
            html += '</div>';
            
            $('.tmm-chatbot-messages').append(html);
            this.scrollChatToBottom();
        },

        showTypingIndicator: function() {
            const indicator = `
                <div class="tmm-chat-typing">
                    <div class="tmm-chat-avatar"><i class="fas fa-robot"></i></div>
                    <div class="tmm-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            $('.tmm-chatbot-messages').append(indicator);
            this.scrollChatToBottom();
        },

        hideTypingIndicator: function() {
            $('.tmm-chat-typing').remove();
        },

        scrollChatToBottom: function() {
            const messages = $('.tmm-chatbot-messages')[0];
            if (messages) {
                messages.scrollTop = messages.scrollHeight;
            }
        },

        loadChatHistory: function() {
            // Charger l'historique des conversations si nécessaire
            // Pour l'instant, afficher un message de bienvenue
            this.appendChatMessage(
                'Bonjour ! Je suis votre assistant d\'apprentissage. Comment puis-je vous aider aujourd\'hui ?',
                'bot',
                {
                    actions: [
                        {label: '📚 Mes cours', value: 'cours'},
                        {label: '📊 Ma progression', value: 'progress'},
                        {label: '🏆 Mes badges', value: 'badges'},
                        {label: '❓ Aide', value: 'help'}
                    ]
                }
            );
        },

        // Tooltips généraux
        initTooltips: function() {
            // Initialisation des tooltips Bootstrap ou custom
            $('[data-tooltip]').each(function() {
                // Tooltip custom déjà géré par CSS
            });
        },

        // Modales
        initModals: function() {
            const self = this;
            
            // Annulation d'abonnement
            $('#ps-cancel-membership').on('click', function() {
                if (confirm(tmm_ajax.strings.confirm_cancel)) {
                    self.cancelMembership();
                }
            });
            
            // Changement d'abonnement
            $('#ps-upgrade-membership').on('click', function() {
                window.location.href = $(this).data('url') || '/abonnement/';
            });
            
            // Partage de certificat
            $(document).on('click', '.ps-share-certificate', function() {
                const url = $(this).data('url');
                self.shareCertificate(url);
            });
        },

        cancelMembership: function() {
            const self = this;
            
            self.showLoader();
            
            $.post(tmm_ajax.ajaxurl, {
                action: 'tmm_cancel_membership',
                _wpnonce: tmm_ajax.nonce
            }, function(response) {
                self.hideLoader();
                
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    alert('Erreur : ' + response.data.message);
                }
            }).fail(function() {
                self.hideLoader();
                alert(tmm_ajax.strings.error);
            });
        },

        shareCertificate: function(url) {
            if (navigator.share) {
                navigator.share({
                    title: 'Mon certificat TeachMeMore',
                    text: 'J\'ai obtenu mon certificat sur TeachMeMore !',
                    url: url
                }).catch(err => console.log('Erreur de partage:', err));
            } else {
                // Fallback : copier dans le presse-papier
                this.copyToClipboard(url);
                this.showToast('Lien copié dans le presse-papier !');
            }
        },

        copyToClipboard: function(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        },

        showCertificateModal: function(parcoursId) {
            // Implémenter la modale de certificat
            alert('Félicitations ! Vous avez complété le parcours. Votre certificat est maintenant disponible.');
            
            // Recharger la section des certificats
            this.navigateToSection('certificates');
        },

        // Utilitaires
        showLoader: function() {
            $('#ps-loader').fadeIn();
            this.state.isLoading = true;
        },

        hideLoader: function() {
            $('#ps-loader').fadeOut();
            this.state.isLoading = false;
        },

        showMessage: function(selector, message, type) {
            const element = $(selector);
            element
                .removeClass('success error')
                .addClass(type)
                .text(message)
                .fadeIn();
            
            setTimeout(function() {
                element.fadeOut();
            }, 5000);
        },

        showToast: function(message, type = 'success') {
            // Supprimer les toasts existants
            $('.tmm-toast').remove();
            
            const toast = $('<div class="tmm-toast"></div>')
                .addClass('tmm-toast-' + type)
                .text(message);
            
            $('body').append(toast);
            
            setTimeout(function() {
                toast.addClass('show');
            }, 100);
            
            setTimeout(function() {
                toast.removeClass('show');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        },

        // Sauvegarde et restauration de l'état
        saveState: function() {
            localStorage.setItem('tmm_dashboard_state', JSON.stringify({
                currentSection: this.state.currentSection,
                timestamp: Date.now()
            }));
        },

        restoreState: function() {
            const savedState = localStorage.getItem('tmm_dashboard_state');
            
            if (savedState) {
                try {
                    const state = JSON.parse(savedState);
                    
                    // Vérifier que l'état n'est pas trop ancien (24h)
                    if (Date.now() - state.timestamp < 86400000) {
                        if (state.currentSection && $('#' + state.currentSection).length) {
                            this.navigateToSection(state.currentSection);
                        }
                    }
                } catch (e) {
                    this.log('Erreur lors de la restauration de l\'état', e);
                }
            }
        },

        // Événements globaux
        bindGlobalEvents: function() {
            const self = this;
            
            // Gestion du redimensionnement
            let resizeTimeout;
            $(window).on('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    self.handleResize();
                }, 250);
            });
            
            // Gestion des touches clavier
            $(document).on('keydown', function(e) {
                self.handleKeyboard(e);
            });
            
            // Changement de période pour les graphiques
            $('.ps-time-selector').on('change', function() {
                const range = $(this).val();
                self.updateChartData(range);
            });
            
            // Sélecteur de graphiques analytics
            $('.ps-chart-selector').on('change', function() {
                const type = $(this).val();
                self.updateAnalyticsChart(type);
            });
        },

        handleResize: function() {
            // Mettre à jour les graphiques
            if (this.state.charts.progress) {
                this.state.charts.progress.resize();
            }
            if (this.state.charts.analytics) {
                this.state.charts.analytics.resize();
            }
            
            // Ajuster la navigation mobile si nécessaire
            if ($(window).width() < 768) {
                this.initMobileNavigation();
            }
        },

        handleKeyboard: function(e) {
            // Raccourcis clavier
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 's':
                        e.preventDefault();
                        $('.ps-form').filter(':visible').first().submit();
                        break;
                    case '/':
                        e.preventDefault();
                        $('#ps-course-search, #ps-courses-search-input').first().focus();
                        break;
                }
            }
            
            // Échap pour fermer le chatbot
            if (e.key === 'Escape' && $('.tmm-chatbot-container').hasClass('active')) {
                $('.tmm-chatbot-container').removeClass('active');
            }
        },

        updateChartData: function(range) {
            const self = this;
            
            $.post(tmm_ajax.ajaxurl, {
                action: 'tmm_get_progress_data',
                range: range,
                _wpnonce: tmm_ajax.nonce
            }, function(response) {
                if (response.success && self.state.charts.progress) {
                    self.state.charts.progress.data.labels = response.data.labels;
                    self.state.charts.progress.data.datasets[0].data = response.data.data.completed;
                    self.state.charts.progress.update();
                }
            });
        },

        updateAnalyticsChart: function(type) {
            const self = this;
            
            $.post(tmm_ajax.ajaxurl, {
                action: 'tmm_get_analytics_data',
                type: type,
                range: 30,
                _wpnonce: tmm_ajax.nonce
            }, function(response) {
                if (response.success && self.state.charts.analytics) {
                    // Mettre à jour les données selon le type
                    let newData = {
                        labels: [],
                        datasets: [{
                            label: '',
                            data: [],
                            borderColor: 'rgba(255, 215, 0, 1)',
                            backgroundColor: 'rgba(255, 215, 0, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    };
                    
                    switch(type) {
                        case 'activity':
                            newData.labels = response.data.activity_by_day?.map(d => d.day) || [];
                            newData.datasets[0].data = response.data.activity_by_day?.map(d => d.count) || [];
                            newData.datasets[0].label = 'Activité';
                            break;
                        case 'performance':
                            newData.labels = response.data.score_evolution?.map(d => d.date) || [];
                            newData.datasets[0].data = response.data.score_evolution?.map(d => d.average_score) || [];
                            newData.datasets[0].label = 'Score moyen';
                            break;
                        case 'time':
                            newData.labels = response.data.labels || [];
                            newData.datasets[0].data = response.data.values || [];
                            newData.datasets[0].label = 'Heures d\'apprentissage';
                            break;
                    }
                    
                    self.state.charts.analytics.data = newData;
                    self.state.charts.analytics.update();
                }
            });
        },

        initMobileNavigation: function() {
            // Ajouter un bouton hamburger si nécessaire
            if (!$('.ps-mobile-toggle').length) {
                const toggleBtn = $('<button class="ps-mobile-toggle"><i class="fas fa-bars"></i></button>');
                $('.ps-dashboard-header').prepend(toggleBtn);
                
                toggleBtn.on('click', function() {
                    $('.ps-dashboard-sidebar').toggleClass('mobile-open');
                    $('body').toggleClass('mobile-nav-open');
                });
                
                // Fermer le menu au clic sur un lien
                $('.ps-nav-link').on('click', function() {
                    if ($(window).width() < 768) {
                        $('.ps-dashboard-sidebar').removeClass('mobile-open');
                        $('body').removeClass('mobile-nav-open');
                    }
                });
            }
        }
    };

    // Initialiser quand le DOM est prêt
    $(document).ready(function() {
        TMMDashboard.init();
        
        // Exposer l'objet globalement pour debug
        window.TMMDashboard = TMMDashboard;
    });
    
    // Extension du chatbot avec fonctionnalités avancées
const TMMChatbotAdvanced = {
    
    // Rendu des réponses riches
    renderRichResponse: function(response) {
        const container = $('.tmm-chatbot-messages');
        
        switch(response.type) {
            case 'rich_progress':
                this.renderProgressCard(response.data, container);
                break;
                
            case 'smart_recommendations':
                this.renderRecommendationCarousel(response.data, container);
                break;
                
            case 'interactive_quiz':
                this.renderInteractiveQuiz(response.data, container);
                break;
                
            case 'learning_path':
                this.renderLearningPath(response.data, container);
                break;
                
            case 'achievement_showcase':
                this.renderAchievementShowcase(response.data, container);
                break;
        }
    },
    
    // Carte de progression interactive
    renderProgressCard: function(data, container) {
        const card = $(`
            <div class="tmm-chat-progress-card">
                <div class="tmm-progress-header">
                    <h4>${data.summary.title}</h4>
                    <span class="tmm-progress-badge">${data.summary.percentage}%</span>
                </div>
                
                <div class="tmm-progress-chart-container">
                    <canvas id="tmm-chat-progress-${Date.now()}"></canvas>
                </div>
                
                <div class="tmm-progress-insights">
                    ${data.insights.map(insight => `
                        <div class="tmm-insight-item ${insight.type}">
                            <i class="fas fa-${insight.icon}"></i>
                            <span>${insight.text}</span>
                        </div>
                    `).join('')}
                </div>
                
                <div class="tmm-progress-actions">
                    ${data.recommendations.map(rec => `
                        <button class="tmm-action-btn" data-action="${rec.action}">
                            ${rec.label}
                        </button>
                    `).join('')}
                </div>
            </div>
        `);
        
        container.append(card);
        
        // Initialiser le graphique
        this.initProgressChart(card.find('canvas')[0], data.chart);
    },
    
    // Carrousel de recommandations
    renderRecommendationCarousel: function(data, container) {
        const carousel = $(`
            <div class="tmm-chat-carousel">
                <div class="tmm-carousel-header">
                    <h4>Cours recommandés pour vous</h4>
                    <div class="tmm-carousel-controls">
                        <button class="tmm-carousel-prev"><i class="fas fa-chevron-left"></i></button>
                        <button class="tmm-carousel-next"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
                
                <div class="tmm-carousel-track">
                    <div class="tmm-carousel-inner">
                        ${[data.primary_recommendation, ...data.alternatives].map((course, index) => `
                            <div class="tmm-recommendation-card ${index === 0 ? 'primary' : ''}">
                                <div class="tmm-rec-image">
                                    <img src="${course.thumbnail}" alt="${course.name}">
                                    <span class="tmm-relevance-score">${course.relevance_score}% pertinent</span>
                                </div>
                                <div class="tmm-rec-content">
                                    <h5>${course.name}</h5>
                                    <p class="tmm-rec-reason">${course.reasoning}</p>
                                    <div class="tmm-rec-meta">
                                        <span><i class="fas fa-clock"></i> ${course.estimated_duration}</span>
                                        <span><i class="fas fa-signal"></i> ${course.level}</span>
                                    </div>
                                    <button class="tmm-rec-action" data-course-id="${course.id}">
                                        Voir le cours
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `);
        
        container.append(carousel);
        this.initCarousel(carousel);
    },
    
    // Quiz interactif dans le chat
    renderInteractiveQuiz: function(data, container) {
        const quiz = $(`
            <div class="tmm-chat-quiz">
                <div class="tmm-quiz-header">
                    <h4>${data.title}</h4>
                    <div class="tmm-quiz-progress">
                        <div class="tmm-quiz-progress-bar" style="width: 0%"></div>
                    </div>
                </div>
                
                <div class="tmm-quiz-content">
                    <div class="tmm-question-container">
                        <!-- Questions dynamiques -->
                    </div>
                </div>
                
                <div class="tmm-quiz-footer">
                    <button class="tmm-quiz-prev" style="display: none;">Précédent</button>
                    <button class="tmm-quiz-next">Suivant</button>
                    <button class="tmm-quiz-submit" style="display: none;">Terminer</button>
                </div>
            </div>
        `);
        
        container.append(quiz);
        this.initQuiz(quiz, data.questions);
    }
};

// Support vocal pour le chatbot
const TMMVoiceChat = {
    recognition: null,
    synthesis: window.speechSynthesis,
    
    init: function() {
        if ('webkitSpeechRecognition' in window) {
            this.recognition = new webkitSpeechRecognition();
            this.setupRecognition();
            this.addVoiceButton();
        }
    },
    
    setupRecognition: function() {
        this.recognition.continuous = false;
        this.recognition.interimResults = true;
        this.recognition.lang = 'fr-FR';
        
        this.recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            $('.tmm-chatbot-text').val(transcript);
            
            if (event.results[0].isFinal) {
                $('.tmm-chatbot-input').submit();
            }
        };
    },
    
    speak: function(text) {
        // Nettoyer le texte des balises
        const cleanText = text.replace(/\*\*/g, '').replace(/<[^>]*>/g, '');
        
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.lang = 'fr-FR';
        utterance.rate = 0.9;
        
        this.synthesis.speak(utterance);
    }
};

})(jQuery);