/**
 * TMM Dashboard Pro - Admin JavaScript
 * Version: 1.0.0
 * Modern Admin Interface JavaScript for TeachMeMore Dashboard
 */

(function($) {
    'use strict';

    // =============================================================================
    // Global Variables & Configuration
    // =============================================================================
    
    const TMM_Admin = {
        // Configuration
        config: {
            ajaxUrl: tmm_admin_ajax?.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: tmm_admin_ajax?.nonce || '',
            debug: false,
            animationDuration: 300,
            toastDuration: 5000,
            chartColors: {
                primary: '#2563eb',
                secondary: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#06b6d4',
                gradient: ['#2563eb', '#3b82f6', '#60a5fa', '#93c5fd']
            }
        },

        // State management
        state: {
            activeModals: [],
            loadingStates: new Set(),
            charts: new Map(),
            timers: new Map()
        },

        // Cache for frequently used elements
        cache: {
            $window: $(window),
            $document: $(document),
            $body: $('body')
        }
    };

    // =============================================================================
    // Utility Functions
    // =============================================================================
    
    const Utils = {
        /**
         * Debounce function execution
         */
        debounce(func, wait, immediate = false) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    timeout = null;
                    if (!immediate) func.apply(this, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(this, args);
            };
        },

        /**
         * Throttle function execution
         */
        throttle(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Generate unique ID
         */
        generateId(prefix = 'tmm') {
            return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        },

        /**
         * Format numbers with localization
         */
        formatNumber(num, decimals = 0) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(num);
        },

        /**
         * Format file size
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Sanitize string for HTML
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Deep clone object
         */
        deepClone(obj) {
            return JSON.parse(JSON.stringify(obj));
        },

        /**
         * Check if element is in viewport
         */
        isInViewport($el) {
            if (!$el.length) return false;
            const rect = $el[0].getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= window.innerHeight &&
                rect.right <= window.innerWidth
            );
        }
    };

    // =============================================================================
    // Toast Notification System
    // =============================================================================
    
    const Toast = {
        container: null,

        init() {
            this.createContainer();
        },

        createContainer() {
            if (!this.container) {
                this.container = $('<div class="tmm-toast-container"></div>');
                TMM_Admin.cache.$body.append(this.container);
            }
        },

        show(message, type = 'info', options = {}) {
            const defaults = {
                duration: TMM_Admin.config.toastDuration,
                closable: true,
                persistent: false,
                onClick: null
            };
            
            const settings = { ...defaults, ...options };
            const toastId = Utils.generateId('toast');
            
            const $toast = $(`
                <div class="tmm-toast tmm-toast-${type}" data-toast-id="${toastId}">
                    <div class="tmm-toast-content">
                        <div class="tmm-toast-icon">
                            ${this.getIcon(type)}
                        </div>
                        <div class="tmm-toast-message">${Utils.escapeHtml(message)}</div>
                        ${settings.closable ? '<button class="tmm-toast-close" type="button">&times;</button>' : ''}
                    </div>
                </div>
            `);

            this.container.append($toast);

            // Animate in
            setTimeout(() => $toast.addClass('show'), 10);

            // Handle click events
            if (settings.onClick) {
                $toast.on('click', settings.onClick);
            }

            // Auto-hide unless persistent
            if (!settings.persistent && settings.duration > 0) {
                TMM_Admin.state.timers.set(toastId, setTimeout(() => {
                    this.hide(toastId);
                }, settings.duration));
            }

            // Close button
            $toast.find('.tmm-toast-close').on('click', (e) => {
                e.stopPropagation();
                this.hide(toastId);
            });

            return toastId;
        },

        hide(toastId) {
            const $toast = this.container.find(`[data-toast-id="${toastId}"]`);
            if ($toast.length) {
                $toast.removeClass('show');
                setTimeout(() => $toast.remove(), TMM_Admin.config.animationDuration);
                
                // Clear timer
                const timer = TMM_Admin.state.timers.get(toastId);
                if (timer) {
                    clearTimeout(timer);
                    TMM_Admin.state.timers.delete(toastId);
                }
            }
        },

        hideAll() {
            this.container.find('.tmm-toast').removeClass('show');
            setTimeout(() => {
                this.container.empty();
                TMM_Admin.state.timers.clear();
            }, TMM_Admin.config.animationDuration);
        },

        getIcon(type) {
            const icons = {
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-exclamation-circle"></i>',
                warning: '<i class="fas fa-exclamation-triangle"></i>',
                info: '<i class="fas fa-info-circle"></i>'
            };
            return icons[type] || icons.info;
        },

        // Convenience methods
        success(message, options = {}) {
            return this.show(message, 'success', options);
        },

        error(message, options = {}) {
            return this.show(message, 'error', { ...options, duration: 8000 });
        },

        warning(message, options = {}) {
            return this.show(message, 'warning', options);
        },

        info(message, options = {}) {
            return this.show(message, 'info', options);
        }
    };

    // =============================================================================
    // Loading State Management
    // =============================================================================
    
    const Loading = {
        show(target, options = {}) {
            const defaults = {
                type: 'spinner', // spinner, skeleton, overlay
                size: 'medium', // small, medium, large
                message: 'Chargement...',
                overlay: true
            };
            
            const settings = { ...defaults, ...options };
            const $target = $(target);
            const loadingId = Utils.generateId('loading');
            
            if (!$target.length) return null;
            
            // Store loading state
            TMM_Admin.state.loadingStates.add(loadingId);
            
            // Create loading element
            let $loading;
            
            if (settings.type === 'overlay') {
                $loading = $(`
                    <div class="tmm-loading-overlay" data-loading-id="${loadingId}">
                        <div class="tmm-loading tmm-loading-${settings.size}"></div>
                        ${settings.message ? `<div class="tmm-loading-message">${settings.message}</div>` : ''}
                    </div>
                `);
                
                $target.css('position', 'relative').append($loading);
            } else if (settings.type === 'spinner') {
                $loading = $(`<div class="tmm-loading tmm-loading-${settings.size}" data-loading-id="${loadingId}"></div>`);
                $target.append($loading);
            }
            
            // Store reference for cleanup
            $target.data('tmm-loading-id', loadingId);
            
            return loadingId;
        },

        hide(target) {
            const $target = $(target);
            const loadingId = $target.data('tmm-loading-id');
            
            if (loadingId) {
                $target.find(`[data-loading-id="${loadingId}"]`).remove();
                $target.removeData('tmm-loading-id');
                TMM_Admin.state.loadingStates.delete(loadingId);
            }
        },

        showButton($button, options = {}) {
            const originalText = $button.text();
            const originalHtml = $button.html();
            
            $button.prop('disabled', true)
                   .data('original-text', originalText)
                   .data('original-html', originalHtml)
                   .html(`<span class="tmm-loading tmm-loading-small"></span> ${options.text || 'Chargement...'}`);
        },

        hideButton($button) {
            const originalHtml = $button.data('original-html');
            if (originalHtml) {
                $button.prop('disabled', false)
                       .html(originalHtml)
                       .removeData('original-text')
                       .removeData('original-html');
            }
        }
    };

    // =============================================================================
    // Modal System
    // =============================================================================
    
    const Modal = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Close on backdrop click
            TMM_Admin.cache.$document.on('click', '.tmm-modal', function(e) {
                if (e.target === this) {
                    Modal.hide($(this));
                }
            });

            // Close on close button click
            TMM_Admin.cache.$document.on('click', '.tmm-modal-close', function(e) {
                e.preventDefault();
                const $modal = $(this).closest('.tmm-modal');
                Modal.hide($modal);
            });

            // ESC key to close
            TMM_Admin.cache.$document.on('keydown', function(e) {
                if (e.key === 'Escape' && TMM_Admin.state.activeModals.length > 0) {
                    const topModal = TMM_Admin.state.activeModals[TMM_Admin.state.activeModals.length - 1];
                    Modal.hide(topModal);
                }
            });
        },

        show($modal, options = {}) {
            if (!$modal.length) return;

            const defaults = {
                backdrop: true,
                keyboard: true,
                focus: true,
                onShow: null,
                onHide: null
            };

            const settings = { ...defaults, ...options };
            
            // Store modal reference
            TMM_Admin.state.activeModals.push($modal);
            $modal.data('modal-options', settings);

            // Show modal
            $modal.addClass('show').css('display', 'flex');
            TMM_Admin.cache.$body.addClass('tmm-modal-open');

            // Focus management
            if (settings.focus) {
                const $focusTarget = $modal.find('[autofocus]').first();
                if ($focusTarget.length) {
                    setTimeout(() => $focusTarget.focus(), TMM_Admin.config.animationDuration);
                }
            }

            // Callback
            if (typeof settings.onShow === 'function') {
                settings.onShow($modal);
            }

            return $modal;
        },

        hide($modal) {
            if (!$modal.length) return;

            const settings = $modal.data('modal-options') || {};

            // Remove from active modals
            const index = TMM_Admin.state.activeModals.indexOf($modal);
            if (index > -1) {
                TMM_Admin.state.activeModals.splice(index, 1);
            }

            // Hide modal
            $modal.removeClass('show');
            
            setTimeout(() => {
                $modal.css('display', 'none');
                if (TMM_Admin.state.activeModals.length === 0) {
                    TMM_Admin.cache.$body.removeClass('tmm-modal-open');
                }
            }, TMM_Admin.config.animationDuration);

            // Callback
            if (typeof settings.onHide === 'function') {
                settings.onHide($modal);
            }

            return $modal;
        },

        create(options = {}) {
            const defaults = {
                title: 'Modal',
                content: '',
                size: 'medium', // small, medium, large, xl
                closable: true,
                buttons: [],
                className: ''
            };

            const settings = { ...defaults, ...options };
            const modalId = Utils.generateId('modal');

            const $modal = $(`
                <div class="tmm-modal tmm-modal-${settings.size} ${settings.className}" id="${modalId}">
                    <div class="tmm-modal-content">
                        <div class="tmm-modal-header">
                            <h2 class="tmm-modal-title">${Utils.escapeHtml(settings.title)}</h2>
                            ${settings.closable ? '<button type="button" class="tmm-modal-close">&times;</button>' : ''}
                        </div>
                        <div class="tmm-modal-body">
                            ${settings.content}
                        </div>
                        ${settings.buttons.length > 0 ? this.createFooter(settings.buttons) : ''}
                    </div>
                </div>
            `);

            TMM_Admin.cache.$body.append($modal);
            return $modal;
        },

        createFooter(buttons) {
            const buttonHtml = buttons.map(button => {
                const classes = ['tmm-button', button.className || 'tmm-button-secondary'].join(' ');
                return `<button type="button" class="${classes}" data-action="${button.action || ''}">${button.text}</button>`;
            }).join('');

            return `<div class="tmm-modal-footer">${buttonHtml}</div>`;
        },

        confirm(message, options = {}) {
            const defaults = {
                title: 'Confirmation',
                confirmText: 'Confirmer',
                cancelText: 'Annuler',
                confirmClass: 'tmm-button-danger',
                onConfirm: null,
                onCancel: null
            };

            const settings = { ...defaults, ...options };

            return new Promise((resolve) => {
                const $modal = this.create({
                    title: settings.title,
                    content: `<p>${Utils.escapeHtml(message)}</p>`,
                    size: 'small',
                    buttons: [
                        {
                            text: settings.cancelText,
                            action: 'cancel',
                            className: 'tmm-button-secondary'
                        },
                        {
                            text: settings.confirmText,
                            action: 'confirm',
                            className: settings.confirmClass
                        }
                    ]
                });

                $modal.on('click', '[data-action]', function() {
                    const action = $(this).data('action');
                    const result = action === 'confirm';
                    
                    if (result && typeof settings.onConfirm === 'function') {
                        settings.onConfirm();
                    } else if (!result && typeof settings.onCancel === 'function') {
                        settings.onCancel();
                    }
                    
                    Modal.hide($modal);
                    setTimeout(() => $modal.remove(), TMM_Admin.config.animationDuration);
                    resolve(result);
                });

                this.show($modal);
            });
        }
    };

    // =============================================================================
    // Tab Navigation System
    // =============================================================================
    
    const Tabs = {
        init() {
            this.bindEvents();
            this.activateFromHash();
        },

        bindEvents() {
            TMM_Admin.cache.$document.on('click', '.nav-tab', function(e) {
                e.preventDefault();
                Tabs.activate($(this));
            });

            // Handle hash changes
            TMM_Admin.cache.$window.on('hashchange', () => {
                this.activateFromHash();
            });
        },

        activate($tab) {
            if (!$tab.length || $tab.hasClass('nav-tab-active')) return;

            const targetId = $tab.attr('href') || $tab.data('tab');
            if (!targetId) return;

            const $tabContainer = $tab.closest('.tmm-tabs, .tmm-settings-tabs');
            const $contentContainer = $tabContainer.siblings().find('.tmm-tab-content').first().parent();

            // Update tabs
            $tabContainer.find('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');

            // Update content
            $contentContainer.find('.tmm-tab-content').removeClass('tmm-tab-active');
            $contentContainer.find(targetId).addClass('tmm-tab-active');

            // Update URL hash
            if (targetId.startsWith('#')) {
                history.replaceState(null, null, targetId);
            }

            // Trigger custom event
            TMM_Admin.cache.$document.trigger('tmm:tab:activated', [$tab, targetId]);
        },

        activateFromHash() {
            const hash = window.location.hash;
            if (hash) {
                const $tab = $(`.nav-tab[href="${hash}"], .nav-tab[data-tab="${hash}"]`).first();
                if ($tab.length) {
                    this.activate($tab);
                }
            }
        }
    };

    // =============================================================================
    // AJAX System
    // =============================================================================
    
    const Ajax = {
        request(action, data = {}, options = {}) {
            const defaults = {
                method: 'POST',
                dataType: 'json',
                cache: false,
                timeout: 30000,
                beforeSend: null,
                success: null,
                error: null,
                complete: null
            };

            const settings = { ...defaults, ...options };

            const requestData = {
                action: action,
                _wpnonce: TMM_Admin.config.nonce,
                ...data
            };

            return $.ajax({
                url: TMM_Admin.config.ajaxUrl,
                method: settings.method,
                data: requestData,
                dataType: settings.dataType,
                cache: settings.cache,
                timeout: settings.timeout,
                beforeSend: settings.beforeSend,
                success: (response) => {
                    if (response && response.success === false) {
                        const message = response.data?.message || 'Une erreur est survenue';
                        Toast.error(message);
                        if (typeof settings.error === 'function') {
                            settings.error(response, 'error', message);
                        }
                    } else {
                        if (typeof settings.success === 'function') {
                            settings.success(response);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', { xhr, status, error });
                    
                    let message = 'Erreur de connexion';
                    if (status === 'timeout') {
                        message = 'Délai d\'attente dépassé';
                    } else if (status === 'parsererror') {
                        message = 'Erreur de format de réponse';
                    }
                    
                    Toast.error(message);
                    
                    if (typeof settings.error === 'function') {
                        settings.error(xhr, status, error);
                    }
                },
                complete: settings.complete
            });
        },

        // Convenience methods
        get(action, data = {}, options = {}) {
            return this.request(action, data, { ...options, method: 'GET' });
        },

        post(action, data = {}, options = {}) {
            return this.request(action, data, { ...options, method: 'POST' });
        }
    };

    // =============================================================================
    // Chart Management
    // =============================================================================
    
    const Charts = {
        init() {
            this.loadCharts();
        },

        loadCharts() {
            $('.tmm-chart-canvas').each((index, canvas) => {
                const $canvas = $(canvas);
                const chartType = $canvas.data('chart-type');
                const chartData = $canvas.data('chart-data');
                
                if (chartType && chartData) {
                    this.createChart(canvas, chartType, chartData);
                }
            });
        },

        createChart(canvas, type, data, options = {}) {
            const ctx = canvas.getContext('2d');
            const chartId = canvas.id || Utils.generateId('chart');
            
            // Destroy existing chart if it exists
            if (TMM_Admin.state.charts.has(chartId)) {
                TMM_Admin.state.charts.get(chartId).destroy();
            }

            const defaultOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: TMM_Admin.config.chartColors.primary,
                        borderWidth: 1
                    }
                }
            };

            const chartOptions = this.mergeOptions(defaultOptions, options, type);
            
            const chart = new Chart(ctx, {
                type: type,
                data: this.processChartData(data, type),
                options: chartOptions
            });

            TMM_Admin.state.charts.set(chartId, chart);
            return chart;
        },

        processChartData(data, type) {
            if (type === 'doughnut' || type === 'pie') {
                return {
                    ...data,
                    datasets: data.datasets.map(dataset => ({
                        ...dataset,
                        backgroundColor: dataset.backgroundColor || TMM_Admin.config.chartColors.gradient,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }))
                };
            }

            if (type === 'line') {
                return {
                    ...data,
                    datasets: data.datasets.map((dataset, index) => ({
                        ...dataset,
                        borderColor: dataset.borderColor || TMM_Admin.config.chartColors.gradient[index % TMM_Admin.config.chartColors.gradient.length],
                        backgroundColor: dataset.backgroundColor || TMM_Admin.config.chartColors.gradient[index % TMM_Admin.config.chartColors.gradient.length] + '20',
                        tension: 0.4,
                        fill: false
                    }))
                };
            }

            return data;
        },

        mergeOptions(defaultOptions, customOptions, type) {
            if (type === 'line') {
                defaultOptions.scales = {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                };
            }

            return $.extend(true, {}, defaultOptions, customOptions);
        },

        updateChart(chartId, newData) {
            const chart = TMM_Admin.state.charts.get(chartId);
            if (chart) {
                chart.data = newData;
                chart.update();
            }
        },

        destroyChart(chartId) {
            const chart = TMM_Admin.state.charts.get(chartId);
            if (chart) {
                chart.destroy();
                TMM_Admin.state.charts.delete(chartId);
            }
        }
    };

    // =============================================================================
    // Form Management
    // =============================================================================
    
    const Forms = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Auto-save forms
            TMM_Admin.cache.$document.on('change', '.tmm-auto-save', Utils.debounce(function() {
                Forms.autoSave($(this).closest('form'));
            }, 2000));

            // Form validation
            TMM_Admin.cache.$document.on('submit', '.tmm-form', function(e) {
                return Forms.validate($(this));
            });

            // File upload handling
            TMM_Admin.cache.$document.on('change', 'input[type="file"]', function() {
                Forms.handleFileUpload($(this));
            });
        },

        validate($form) {
            let isValid = true;
            const errors = [];

            // Clear previous errors
            $form.find('.tmm-field-error').removeClass('tmm-field-error');
            $form.find('.tmm-error-message').remove();

            // Required fields
            $form.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    isValid = false;
                    errors.push({
                        field: $field,
                        message: 'Ce champ est obligatoire'
                    });
                }
            });

            // Email validation
            $form.find('input[type="email"]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (value && !this.checkValidity()) {
                    isValid = false;
                    errors.push({
                        field: $field,
                        message: 'Format d\'email invalide'
                    });
                }
            });

            // URL validation
            $form.find('input[type="url"]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (value && !this.checkValidity()) {
                    isValid = false;
                    errors.push({
                        field: $field,
                        message: 'Format d\'URL invalide'
                    });
                }
            });

            // Password confirmation
            const $password = $form.find('input[name="password"]');
            const $passwordConfirm = $form.find('input[name="password_confirm"]');
            
            if ($password.length && $passwordConfirm.length) {
                if ($password.val() !== $passwordConfirm.val()) {
                    isValid = false;
                    errors.push({
                        field: $passwordConfirm,
                        message: 'Les mots de passe ne correspondent pas'
                    });
                }
            }

            // Display errors
            errors.forEach(error => {
                error.field.addClass('tmm-field-error');
                error.field.after(`<div class="tmm-error-message">${error.message}</div>`);
            });

            if (!isValid) {
                const firstError = errors[0];
                firstError.field.focus();
                Toast.error('Veuillez corriger les erreurs dans le formulaire');
            }

            return isValid;
        },

        autoSave($form) {
            if (!$form.length) return;

            const formData = new FormData($form[0]);
            const action = $form.data('auto-save-action') || 'tmm_auto_save_form';

            Ajax.post(action, Object.fromEntries(formData), {
                success: () => {
                    Toast.success('Sauvegarde automatique effectuée', { duration: 2000 });
                },
                error: () => {
                    Toast.warning('Erreur lors de la sauvegarde automatique');
                }
            });
        },

        handleFileUpload($input) {
            const file = $input[0].files[0];
            if (!file) return;

            // Validate file size (default 10MB)
            const maxSize = $input.data('max-size') || 10 * 1024 * 1024;
            if (file.size > maxSize) {
                Toast.error(`Le fichier est trop volumineux (max: ${Utils.formatFileSize(maxSize)})`);
                $input.val('');
                return;
            }

            // Validate file type
            const allowedTypes = $input.data('allowed-types');
            if (allowedTypes) {
                const types = allowedTypes.split(',').map(t => t.trim());
                if (!types.includes(file.type)) {
                    Toast.error('Type de fichier non autorisé');
                    $input.val('');
                    return;
                }
            }

            // Show preview for images
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const $preview = $input.siblings('.tmm-file-preview');
                    if ($preview.length) {
                        $preview.html(`<img src="${e.target.result}" alt="Aperçu" style="max-width: 200px; max-height: 200px;">`);
                    }
                };
                reader.readAsDataURL(file);
            }
        }
    };

    // =============================================================================
    // Table Management
    // =============================================================================
    
    const Tables = {
        init() {
            this.bindEvents();
            this.initializeTables();
        },

        bindEvents() {
            // Search functionality
            TMM_Admin.cache.$document.on('input', '.tmm-table-search', Utils.debounce(function() {
                Tables.search($(this));
            }, 300));

            // Sort functionality
            TMM_Admin.cache.$document.on('click', '.tmm-sortable th', function() {
                Tables.sort($(this));
            });

            // Pagination
            TMM_Admin.cache.$document.on('click', '.tmm-pagination a', function(e) {
                e.preventDefault();
                Tables.paginate($(this));
            });

            // Bulk actions
            TMM_Admin.cache.$document.on('change', '.tmm-bulk-action-select', function() {
                Tables.handleBulkAction($(this));
            });
        },

        initializeTables() {
            $('.tmm-data-table').each(function() {
                const $table = $(this);
                Tables.enhanceTable($table);
            });
        },

        enhanceTable($table) {
            // Add sorting indicators
            $table.find('th.sortable').each(function() {
                const $th = $(this);
                if (!$th.find('.sort-indicator').length) {
                    $th.append('<span class="sort-indicator"><i class="fas fa-sort"></i></span>');
                }
            });

            // Add row numbers
            $table.find('tbody tr').each(function(index) {
                if (!$(this).find('.row-number').length) {
                    $(this).prepend(`<td class="row-number">${index + 1}</td>`);
                }
            });
        },

        search($input) {
            const query = $input.val().toLowerCase();
            const $table = $input.closest('.tmm-table-container').find('table');
            
            $table.find('tbody tr').each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                
                if (text.includes(query)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });

            this.updateRowNumbers($table);
        },

        sort($th) {
            const $table = $th.closest('table');
            const columnIndex = $th.index();
            const currentSort = $th.data('sort') || 'none';
            const newSort = currentSort === 'asc' ? 'desc' : 'asc';
            
            // Clear other sort indicators
            $table.find('th').removeData('sort').find('.sort-indicator i')
                  .removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
            
            // Set new sort
            $th.data('sort', newSort);
            $th.find('.sort-indicator i')
               .removeClass('fa-sort fa-sort-up fa-sort-down')
               .addClass(newSort === 'asc' ? 'fa-sort-up' : 'fa-sort-down');

            // Sort rows
            const rows = $table.find('tbody tr').get();
            rows.sort((a, b) => {
                const aText = $(a).find('td').eq(columnIndex).text().trim();
                const bText = $(b).find('td').eq(columnIndex).text().trim();
                
                // Try to parse as numbers
                const aNum = parseFloat(aText.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bText.replace(/[^0-9.-]/g, ''));
                
                let comparison = 0;
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    comparison = aNum - bNum;
                } else {
                    comparison = aText.localeCompare(bText);
                }
                
                return newSort === 'asc' ? comparison : -comparison;
            });

            $table.find('tbody').empty().append(rows);
            this.updateRowNumbers($table);
        },

        updateRowNumbers($table) {
            $table.find('tbody tr:visible').each(function(index) {
                $(this).find('.row-number').text(index + 1);
            });
        },

        paginate($link) {
            const page = $link.data('page');
            const $container = $link.closest('.tmm-table-container');
            
            // Implementation depends on server-side pagination
            // This is a placeholder for AJAX pagination
            Ajax.get('tmm_paginate_table', {
                page: page,
                table: $container.data('table-id')
            }, {
                success: (response) => {
                    if (response.data && response.data.html) {
                        $container.find('table tbody').html(response.data.html);
                        $container.find('.tmm-pagination').html(response.data.pagination);
                    }
                }
            });
        },

        handleBulkAction($select) {
            const action = $select.val();
            const $table = $select.closest('.tmm-table-container').find('table');
            const selectedIds = [];
            
            $table.find('input[type="checkbox"]:checked').each(function() {
                const id = $(this).val();
                if (id !== 'all') {
                    selectedIds.push(id);
                }
            });

            if (action && selectedIds.length > 0) {
                const actionText = $select.find('option:selected').text();
                
                Modal.confirm(`Êtes-vous sûr de vouloir ${actionText.toLowerCase()} ${selectedIds.length} élément(s) ?`, {
                    onConfirm: () => {
                        Ajax.post('tmm_bulk_action', {
                            action: action,
                            ids: selectedIds
                        }, {
                            success: () => {
                                Toast.success('Action effectuée avec succès');
                                location.reload();
                            }
                        });
                    }
                });
            }
        }
    };

    // =============================================================================
    // Media Upload Integration
    // =============================================================================
    
    const MediaUpload = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            TMM_Admin.cache.$document.on('click', '.tmm-upload-button', function(e) {
                e.preventDefault();
                MediaUpload.openMediaLibrary($(this));
            });
        },

        openMediaLibrary($button) {
            const options = {
                title: $button.data('title') || 'Choisir un fichier',
                button: { text: $button.data('button-text') || 'Utiliser ce fichier' },
                multiple: $button.data('multiple') || false,
                library: { type: $button.data('type') || 'image' }
            };

            const frame = wp.media(options);

            frame.on('select', function() {
                const selection = frame.state().get('selection');
                const targetInput = $button.data('target');
                const previewContainer = $button.data('preview');

                if (options.multiple) {
                    const urls = [];
                    selection.each(function(attachment) {
                        urls.push(attachment.get('url'));
                    });
                    
                    if (targetInput) {
                        $(targetInput).val(urls.join(','));
                    }
                } else {
                    const attachment = selection.first();
                    const url = attachment.get('url');
                    
                    if (targetInput) {
                        $(targetInput).val(url);
                    }
                    
                    if (previewContainer) {
                        const isImage = attachment.get('type') === 'image';
                        const preview = isImage 
                            ? `<img src="${url}" alt="Aperçu" style="max-width: 200px; max-height: 200px;">`
                            : `<a href="${url}" target="_blank">${attachment.get('filename')}</a>`;
                        
                        $(previewContainer).html(preview);
                    }
                }

                // Trigger change event
                if (targetInput) {
                    $(targetInput).trigger('change');
                }
            });

            frame.open();
        }
    };

    // =============================================================================
    // Settings Management
    // =============================================================================
    
    const Settings = {
        init() {
            this.bindEvents();
            this.loadSettings();
        },

        bindEvents() {
            // Settings form submission
            TMM_Admin.cache.$document.on('submit', '.tmm-settings-form', function(e) {
                e.preventDefault();
                Settings.saveSettings($(this));
            });

            // Test email functionality
            TMM_Admin.cache.$document.on('click', '#send-test-email', function(e) {
                e.preventDefault();
                Settings.sendTestEmail($(this));
            });

            // Maintenance actions
            TMM_Admin.cache.$document.on('click', '#cleanup-analytics', () => Settings.cleanupAnalytics());
            TMM_Admin.cache.$document.on('click', '#cleanup-chatbot', () => Settings.cleanupChatbot());
            TMM_Admin.cache.$document.on('click', '#export-data', () => Settings.exportData());
            TMM_Admin.cache.$document.on('click', '#clear-cache', () => Settings.clearCache());
            TMM_Admin.cache.$document.on('click', '#reset-badges', () => Settings.resetBadges());

            // Advanced actions
            TMM_Admin.cache.$document.on('click', '#export-settings', () => Settings.exportSettings());
            TMM_Admin.cache.$document.on('change', '#import-settings', (e) => Settings.importSettings(e.target.files[0]));
        },

        loadSettings() {
            // Load any dynamic settings or perform initialization
            this.updateCertificatePreview();
        },

        saveSettings($form) {
            if (!Forms.validate($form)) return;

            const $submitButton = $form.find('input[type="submit"]');
            Loading.showButton($submitButton, { text: 'Sauvegarde...' });

            const formData = new FormData($form[0]);

            Ajax.post('tmm_save_settings', Object.fromEntries(formData), {
                success: (response) => {
                    Toast.success('Paramètres sauvegardés avec succès');
                    Settings.updateCertificatePreview();
                },
                complete: () => {
                    Loading.hideButton($submitButton);
                }
            });
        },

        sendTestEmail($button) {
            Loading.showButton($button, { text: 'Envoi...' });

            Ajax.post('tmm_test_email', {}, {
                success: () => {
                    Toast.success('Email de test envoyé avec succès');
                },
                complete: () => {
                    Loading.hideButton($button);
                }
            });
        },

        cleanupAnalytics() {
            Modal.confirm('Supprimer les anciennes données analytics ?', {
                title: 'Nettoyage Analytics',
                confirmText: 'Nettoyer',
                onConfirm: () => {
                    Ajax.post('tmm_cleanup_analytics', {}, {
                        success: (response) => {
                            const count = response.data?.deleted_count || 0;
                            Toast.success(`${count} enregistrements supprimés`);
                        }
                    });
                }
            });
        },

        cleanupChatbot() {
            Modal.confirm('Supprimer les anciens messages du chatbot ?', {
                title: 'Nettoyage Chatbot',
                confirmText: 'Nettoyer',
                onConfirm: () => {
                    Ajax.post('tmm_cleanup_chatbot', {}, {
                        success: (response) => {
                            const count = response.data?.deleted_count || 0;
                            Toast.success(`${count} messages supprimés`);
                        }
                    });
                }
            });
        },

        exportData() {
            Ajax.post('tmm_export_data', {}, {
                success: (response) => {
                    if (response.data?.download_url) {
                        window.open(response.data.download_url, '_blank');
                        Toast.success('Export généré avec succès');
                    }
                }
            });
        },

        clearCache() {
            Ajax.post('tmm_clear_cache', {}, {
                success: () => {
                    Toast.success('Cache vidé avec succès');
                }
            });
        },

        resetBadges() {
            Modal.confirm('Recalculer tous les badges utilisateur ? Cette opération peut prendre du temps.', {
                title: 'Recalcul des badges',
                confirmText: 'Recalculer',
                confirmClass: 'tmm-button-warning',
                onConfirm: () => {
                    const toastId = Toast.info('Recalcul en cours...', { persistent: true });
                    
                    Ajax.post('tmm_reset_badges', {}, {
                        success: (response) => {
                            Toast.hide(toastId);
                            const count = response.data?.updated_count || 0;
                            Toast.success(`${count} badges mis à jour`);
                        },
                        error: () => {
                            Toast.hide(toastId);
                        }
                    });
                }
            });
        },

        exportSettings() {
            Ajax.post('tmm_export_settings', {}, {
                success: (response) => {
                    if (response.data?.settings) {
                        const blob = new Blob([JSON.stringify(response.data.settings, null, 2)], {
                            type: 'application/json'
                        });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `tmm-settings-${new Date().toISOString().split('T')[0]}.json`;
                        a.click();
                        URL.revokeObjectURL(url);
                        Toast.success('Paramètres exportés');
                    }
                }
            });
        },

        importSettings(file) {
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    
                    Modal.confirm('Importer ces paramètres ? Les paramètres actuels seront remplacés.', {
                        title: 'Import des paramètres',
                        confirmText: 'Importer',
                        confirmClass: 'tmm-button-warning',
                        onConfirm: () => {
                            Ajax.post('tmm_import_settings', { settings }, {
                                success: () => {
                                    Toast.success('Paramètres importés avec succès');
                                    setTimeout(() => location.reload(), 2000);
                                }
                            });
                        }
                    });
                } catch (error) {
                    Toast.error('Fichier de paramètres invalide');
                }
            };
            reader.readAsText(file);
        },

        updateCertificatePreview() {
            const logoUrl = $('#tmm_certificate_logo').val();
            const directorName = $('#tmm_certificate_director_name').val();
            const managerName = $('#tmm_certificate_manager_name').val();

            if (logoUrl) {
                $('.tmm-cert-logo').attr('src', logoUrl);
                $('.tmm-logo-preview img').attr('src', logoUrl);
            }

            $('.tmm-cert-signatures p').each(function(index) {
                if (index === 0 && directorName) {
                    $(this).text(directorName);
                } else if (index === 1 && managerName) {
                    $(this).text(managerName);
                }
            });
        }
    };

    // =============================================================================
    // Dashboard Analytics
    // =============================================================================
    
    const Analytics = {
        init() {
            this.bindEvents();
            this.loadCharts();
        },

        bindEvents() {
            // Period selector
            TMM_Admin.cache.$document.on('change', '#analytics-period', function() {
                Analytics.updatePeriod($(this).val());
            });

            // Chart type selector
            TMM_Admin.cache.$document.on('change', '#chart-type', function() {
                Analytics.updateChartType($(this).val());
            });

            // Export analytics
            TMM_Admin.cache.$document.on('click', '#export-analytics', () => Analytics.exportAnalytics());

            // Refresh analytics
            TMM_Admin.cache.$document.on('click', '#refresh-analytics', () => Analytics.refreshAnalytics());
        },

        loadCharts() {
            // Daily activity chart
            this.createDailyActivityChart();
            
            // Events pie chart
            this.createEventsPieChart();
        },

        createDailyActivityChart() {
            const canvas = document.getElementById('daily-activity-chart');
            if (!canvas) return;

            const data = {
                labels: window.tmmAnalyticsData?.dailyLabels || [],
                datasets: [{
                    label: 'Activités',
                    data: window.tmmAnalyticsData?.dailyData || [],
                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }]
            };

            Charts.createChart(canvas, 'line', data, {
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            });
        },

        createEventsPieChart() {
            const canvas = document.getElementById('events-pie-chart');
            if (!canvas) return;

            const data = {
                labels: window.tmmAnalyticsData?.eventLabels || [],
                datasets: [{
                    data: window.tmmAnalyticsData?.eventData || [],
                    backgroundColor: TMM_Admin.config.chartColors.gradient
                }]
            };

            Charts.createChart(canvas, 'doughnut', data);
        },

        updatePeriod(period) {
            Ajax.get('tmm_analytics_period', { period }, {
                success: (response) => {
                    if (response.data) {
                        this.updateChartsData(response.data);
                        Toast.success('Données mises à jour');
                    }
                }
            });
        },

        updateChartType(type) {
            // Update the daily activity chart type
            const chartId = 'daily-activity-chart';
            const chart = TMM_Admin.state.charts.get(chartId);
            
            if (chart) {
                Ajax.get('tmm_analytics_chart_data', { type }, {
                    success: (response) => {
                        if (response.data) {
                            chart.data = response.data;
                            chart.update();
                        }
                    }
                });
            }
        },

        updateChartsData(data) {
            // Update all charts with new data
            TMM_Admin.state.charts.forEach((chart, chartId) => {
                if (data[chartId]) {
                    chart.data = data[chartId];
                    chart.update();
                }
            });
        },

        exportAnalytics() {
            const period = $('#analytics-period').val();
            
            Ajax.post('tmm_export_analytics', { period }, {
                success: (response) => {
                    if (response.data?.download_url) {
                        window.open(response.data.download_url, '_blank');
                        Toast.success('Export généré avec succès');
                    }
                }
            });
        },

        refreshAnalytics() {
            location.reload();
        }
    };

    // =============================================================================
    // Parcours Management
    // =============================================================================
    
    const Parcours = {
        init() {
            this.bindEvents();
            this.loadParcours();
        },

        bindEvents() {
            // Sync parcours
            TMM_Admin.cache.$document.on('click', '#sync-parcours', () => Parcours.syncParcours());

            // Add new parcours
            TMM_Admin.cache.$document.on('click', '#add-parcours', () => Parcours.addParcours());

            // Parcours actions
            TMM_Admin.cache.$document.on('click', '.tmm-parcours-action', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const parcoursId = $(this).data('parcours');
                Parcours.handleAction(action, parcoursId);
            });
        },

        loadParcours() {
            // Load enrollment chart if canvas exists
            const canvas = document.getElementById('enrollment-chart');
            if (canvas && window.tmmParcoursData) {
                Charts.createChart(canvas, 'line', window.tmmParcoursData.enrollmentData);
            }
        },

        syncParcours() {
            const $button = $('#sync-parcours');
            Loading.showButton($button, { text: 'Synchronisation...' });

            Ajax.post('tmm_sync_parcours', {}, {
                success: (response) => {
                    const count = response.data?.synced_count || 0;
                    Toast.success(`${count} parcours synchronisés`);
                },
                complete: () => {
                    Loading.hideButton($button);
                }
            });
        },

        addParcours() {
            const $modal = Modal.create({
                title: 'Nouveau parcours',
                content: this.getParcoursForm(),
                size: 'large',
                buttons: [
                    { text: 'Annuler', action: 'cancel' },
                    { text: 'Créer', action: 'create', className: 'tmm-button-primary' }
                ]
            });

            $modal.on('click', '[data-action="create"]', () => {
                const formData = new FormData($modal.find('form')[0]);
                
                Ajax.post('tmm_create_parcours', Object.fromEntries(formData), {
                    success: () => {
                        Toast.success('Parcours créé avec succès');
                        Modal.hide($modal);
                        setTimeout(() => location.reload(), 1000);
                    }
                });
            });

            Modal.show($modal);
        },

        handleAction(action, parcoursId) {
            switch (action) {
                case 'view':
                    this.viewParcours(parcoursId);
                    break;
                case 'edit':
                    this.editParcours(parcoursId);
                    break;
                case 'export':
                    this.exportParcours(parcoursId);
                    break;
                case 'delete':
                    this.deleteParcours(parcoursId);
                    break;
            }
        },

        viewParcours(parcoursId) {
            Ajax.get('tmm_get_parcours_details', { parcours_id: parcoursId }, {
                success: (response) => {
                    if (response.data?.html) {
                        const $modal = Modal.create({
                            title: 'Détails du parcours',
                            content: response.data.html,
                            size: 'xl'
                        });
                        Modal.show($modal);
                    }
                }
            });
        },

        editParcours(parcoursId) {
            Toast.info('Fonctionnalité en cours de développement');
        },

        exportParcours(parcoursId) {
            Ajax.post('tmm_export_parcours', { parcours_id: parcoursId }, {
                success: (response) => {
                    if (response.data?.download_url) {
                        window.open(response.data.download_url, '_blank');
                        Toast.success('Export généré');
                    }
                }
            });
        },

        deleteParcours(parcoursId) {
            Modal.confirm('Supprimer définitivement ce parcours ?', {
                title: 'Suppression du parcours',
                confirmText: 'Supprimer',
                confirmClass: 'tmm-button-danger',
                onConfirm: () => {
                    Ajax.post('tmm_delete_parcours', { parcours_id: parcoursId }, {
                        success: () => {
                            Toast.success('Parcours supprimé');
                            setTimeout(() => location.reload(), 1000);
                        }
                    });
                }
            });
        },

        getParcoursForm() {
            return `
                <form class="tmm-form">
                    <div class="tmm-form-group">
                        <label for="parcours_name">Nom du parcours</label>
                        <input type="text" id="parcours_name" name="name" required>
                    </div>
                    <div class="tmm-form-group">
                        <label for="parcours_description">Description</label>
                        <textarea id="parcours_description" name="description" rows="4"></textarea>
                    </div>
                    <div class="tmm-form-group">
                        <label for="parcours_level">Niveau</label>
                        <select id="parcours_level" name="level" required>
                            <option value="debutant">Débutant</option>
                            <option value="intermediaire">Intermédiaire</option>
                            <option value="avance">Avancé</option>
                        </select>
                    </div>
                    <div class="tmm-form-group">
                        <label for="parcours_duration">Durée estimée</label>
                        <input type="text" id="parcours_duration" name="duration" placeholder="ex: 3 mois">
                    </div>
                </form>
            `;
        }
    };

    // =============================================================================
    // Initialization
    // =============================================================================
    
    $(document).ready(function() {
        // Initialize all modules
        Toast.init();
        Modal.init();
        Tabs.init();
        Forms.init();
        Tables.init();
        Settings.init();

        // Initialize page-specific modules
        if ($('.tmm-analytics-page').length) {
            Analytics.init();
        }

        if ($('.tmm-parcours-page').length) {
            Parcours.init();
        }

        // Initialize media upload if wp.media is available
        if (typeof wp !== 'undefined' && wp.media) {
            MediaUpload.init();
        }

        // Initialize charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            Charts.init();
        }

        // Global error handler
        window.addEventListener('error', function(e) {
            if (TMM_Admin.config.debug) {
                console.error('Global error:', e.error);
            }
        });

        // Global unhandled promise rejection handler
        window.addEventListener('unhandledrejection', function(e) {
            if (TMM_Admin.config.debug) {
                console.error('Unhandled promise rejection:', e.reason);
            }
        });

        // Trigger ready event
        TMM_Admin.cache.$document.trigger('tmm:admin:ready');

        // Debug info
        if (TMM_Admin.config.debug) {
            console.log('TMM Admin initialized successfully', TMM_Admin);
        }
    });

    // =============================================================================
    // Global API
    // =============================================================================
    
    // Expose TMM_Admin to global scope for external access
    window.TMM_Admin = TMM_Admin;
    window.TMM_Utils = Utils;
    window.TMM_Toast = Toast;
    window.TMM_Modal = Modal;
    window.TMM_Loading = Loading;
    window.TMM_Ajax = Ajax;

})(jQuery);