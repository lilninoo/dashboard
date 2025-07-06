<?php
$user_id = get_current_user_id();
$dashboard_data = TMMDashboardData::getDashboardData($user_id);
$current_badge = get_user_meta($user_id, 'tmm_badge_status', true) ?: 'curieux';
$badge_info = TMMDashboardBadges::getBadgeInfo($current_badge);
?>

<div class="ps-dashboard-container" id="tmm-dashboard">
    <!-- Loader -->
    <div id="ps-loader" class="ps-loader-overlay" style="display: none;">
        <div class="ps-loader-spinner"></div>
    </div>

    <!-- Sidebar -->
    <aside class="ps-dashboard-sidebar">
        <div class="ps-sidebar-header">
            <a href="<?php echo home_url(); ?>" class="ps-brand-logo">
                <img src="<?php echo TMM_DASHBOARD_URL; ?>assets/img/logo.png" alt="TeachMeMore">
            </a>
            <h1 class="ps-brand-title">TeachMeMore</h1>
            
            <div class="ps-user-profile">
                <div class="ps-user-avatar">
                    <?php echo get_avatar(get_current_user_id(), 60); ?>
                </div>
                <div class="ps-user-info">
                    <span class="ps-user-name"><?php echo wp_get_current_user()->display_name; ?></span>
                    <span class="ps-user-membership">
                        <?php echo $dashboard_data['stats']['membership']['level_name'] ?? 'Invité'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <nav class="ps-sidebar-nav">
            <ul>
                <li class="ps-nav-item active">
                    <a href="#dashboard" class="ps-nav-link">
                        <i class="fas fa-tachometer-alt ps-icon"></i>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li class="ps-nav-item">
                    <a href="#my-courses" class="ps-nav-link">
                        <i class="fas fa-book ps-icon"></i>
                        <span>Mes cours</span>
                    </a>
                </li>
                <li class="ps-nav-item">
                    <a href="#learning-path" class="ps-nav-link">
                        <i class="fas fa-route ps-icon"></i>
                        <span>Mon parcours</span>
                    </a>
                </li>
                <li class="ps-nav-item">
                    <a href="#analytics" class="ps-nav-link">
                        <i class="fas fa-chart-line ps-icon"></i>
                        <span>Progression</span>
                    </a>
                </li>
                <li class="ps-nav-item">
                    <a href="#certificates" class="ps-nav-link">
                        <i class="fas fa-certificate ps-icon"></i>
                        <span>Certificats</span>
                    </a>
                </li>
                <li class="ps-nav-item">
                    <a href="#badges" class="ps-nav-link">
                        <i class="fas fa-trophy ps-icon"></i>
                        <span>Badges</span>
                    </a>
                </li>
                <li class="ps-nav-item">
                    <a href="#settings" class="ps-nav-link">
                        <i class="fas fa-cog ps-icon"></i>
                        <span>Paramètres</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="ps-dashboard-main">
        <!-- Header -->
        <header class="ps-dashboard-header">
            <h1 class="ps-dashboard-title">Tableau de bord</h1>
            
            <div class="ps-header-actions">
                <div id="ps-search-box" class="ps-search-box" style="display: none;">
                    <input type="text" id="ps-course-search" placeholder="Rechercher un cours...">
                    <i class="fas fa-search"></i>
                </div>
                
                <div class="ps-notification-bell">
                    <i class="fas fa-bell"></i>
                    <span class="ps-notification-count" style="display: none;">0</span>
                </div>
            </div>
        </header>

        <!-- Dashboard Section -->
        <section id="dashboard" class="ps-dashboard-section active">
            <!-- Welcome Banner -->
<!-- Welcome Banner -->
    <div class="ps-welcome-banner">
        <div class="ps-welcome-content">
            <h2>Bienvenue, <?php echo wp_get_current_user()->display_name; ?> !</h2>
            <p>Continuez votre parcours d'apprentissage et atteignez vos objectifs.</p>
            
            <?php 
            // Récupérer le dernier cours actif
            $last_activity = TMMDashboardData::getLastActiveCourse($user_id);
            $has_membership = $dashboard_data['stats']['membership']['has_membership'] ?? false;
            
            if ($has_membership && $last_activity) : ?>
                <div class="ps-continue-course">
                    <a href="<?php echo esc_url($last_activity['url']); ?>" class="ps-button ps-button-primary ps-button-large">
                        <i class="fas fa-play-circle"></i> 
                        Continuer : <?php echo esc_html($last_activity['name']); ?>
                    </a>
                    <div class="ps-course-progress-info">
                        <div class="ps-progress-bar ps-progress-bar-small">
                            <div class="ps-progress-fill" style="width: <?php echo $last_activity['progress']; ?>%"></div>
                        </div>
                        <span class="ps-progress-text"><?php echo $last_activity['progress']; ?>% complété</span>
                    </div>
                </div>
            <?php elseif ($has_membership) : ?>
                <div class="ps-explore-courses">
                    <a href="#my-courses" class="ps-button ps-button-primary ps-button-large ps-nav-link">
                        <i class="fas fa-book-open"></i> 
                        Explorer les formations
                    </a>
                    <p class="ps-subtitle">Découvrez notre catalogue complet de cours</p>
                </div>
            <?php else : ?>
                <div class="ps-get-access">
                    <a href="<?php echo esc_url(pmpro_url('levels')); ?>" class="ps-button ps-button-primary ps-button-large">
                        <i class="fas fa-crown"></i> 
                        Obtenir un accès illimité
                    </a>
                    <p class="ps-subtitle">Abonnez-vous pour accéder à tous nos cours</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ps-user-badge ps-badge-<?php echo esc_attr($current_badge); ?>" 
             style="color: <?php echo esc_attr($badge_info['color']); ?>;">
            <span class="ps-badge-ring"></span>
            <i class="fas <?php echo esc_attr($badge_info['icon']); ?>"></i>
            <span class="ps-badge-label"><?php echo esc_html($badge_info['name']); ?></span>
        </div>
    </div>

            <!-- Stats Grid -->
            <div class="ps-stats-grid">
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Cours terminés</h3>
                        <div class="ps-stat-number"><?php echo $dashboard_data['stats']['completed_count']; ?></div>
                    </div>
                </div>
                
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Progression</h3>
                        <div class="ps-stat-number"><?php echo $dashboard_data['stats']['completion_percentage']; ?>%</div>
                    </div>
                </div>
                
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Série</h3>
                        <div class="ps-stat-number"><?php echo $dashboard_data['stats']['learning_streak']; ?> jours</div>
                    </div>
                </div>
                
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Badges</h3>
                        <div class="ps-stat-number"><?php echo $dashboard_data['stats']['badges_count']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Progress Chart -->
            <div class="ps-card">
                <div class="ps-card-header">
                    <h3>Activité de la semaine</h3>
                    <select class="ps-time-selector">
                        <option value="7">7 jours</option>
                        <option value="30">30 jours</option>
                        <option value="90">90 jours</option>
                    </select>
                </div>
                <div class="ps-card-body">
                    <canvas id="progressChart" height="100"></canvas>
                </div>
            </div>

            <!-- Course Recommendations -->
            <?php if (!empty($dashboard_data['recommended_courses'])) : ?>
            <div class="ps-card">
                <div class="ps-card-header">
                    <h3>Cours recommandés</h3>
                </div>
                <div class="ps-card-body">
                    <div class="ps-course-grid">
                        <?php foreach (array_slice($dashboard_data['recommended_courses'], 0, 4) as $course) : ?>
                        <div class="ps-course-card">
                            <?php if ($course['is_new']) : ?>
                            <div class="ps-course-badge">Nouveau</div>
                            <?php elseif ($course['is_popular']) : ?>
                            <div class="ps-course-badge">Populaire</div>
                            <?php endif; ?>
                            
                            <div class="ps-course-thumbnail">
                                <img src="<?php echo esc_url($course['image']); ?>" alt="<?php echo esc_attr($course['name']); ?>">
                            </div>
                            
                            <div class="ps-course-content">
                                <h4><?php echo esc_html($course['name']); ?></h4>
                                <div class="ps-course-meta">
                                    <span class="ps-course-level"><?php echo esc_html($course['level']); ?></span>
                                    <span><?php echo esc_html($course['duration']); ?></span>
                                </div>
                                <a href="<?php echo esc_url($course['url']); ?>" class="ps-button ps-button-primary ps-button-small">
                                    Découvrir
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="ps-card">
                <div class="ps-card-header">
                    <h3>Activité récente</h3>
                </div>
                <div class="ps-card-body">
                    <?php if (!empty($dashboard_data['activity'])) : ?>
                    <ul class="ps-activity-list">
                        <?php foreach ($dashboard_data['activity'] as $activity) : ?>
                        <li class="ps-activity-item">
                            <div class="ps-activity-icon">
                                <i class="fas <?php echo esc_attr($activity['icon']); ?>"></i>
                            </div>
                            <div class="ps-activity-content">
                                <p><?php echo esc_html($activity['description']); ?></p>
                                <span class="ps-activity-time"><?php echo esc_html($activity['time_ago']); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                    <div class="ps-empty-state">
                        <p>Aucune activité récente</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Section Mes Cours -->
<!-- Section Mes Cours -->
<section id="my-courses" class="ps-dashboard-section">
    <h2>Mes Cours</h2>
    <?php 
    $user_id = get_current_user_id();
    $membership = pmpro_getMembershipLevelForUser($user_id);
    $has_active_membership = $membership && $membership->id > 0;
    
    // Si l'utilisateur a un abonnement actif, récupérer TOUS les cours
    if ($has_active_membership) {
        // Récupérer tous les cours disponibles sur la plateforme
        $args = [
            'post_type' => 'lp_course',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $all_courses_query = new WP_Query($args);
        $all_courses = [];
        
        if ($all_courses_query->have_posts()) {
            while ($all_courses_query->have_posts()) {
                $all_courses_query->the_post();
                $course_id = get_the_ID();
                
                // Récupérer les données de progression pour ce cours
                $progress_data = TMMDashboardData::calculateCourseProgress($user_id, $course_id);
                $course_meta = TMMDashboardData::getCourseMetadata($course_id);
                
                // Déterminer le statut
                if ($progress_data['percentage'] >= 100) {
                    $status = 'completed';
                } elseif ($progress_data['percentage'] > 0) {
                    $status = 'in_progress';
                } else {
                    $status = 'not_started';
                }
                
                $all_courses[] = [
                    'id' => $course_id,
                    'name' => get_the_title(),
                    'url' => get_permalink(),
                    'image' => get_the_post_thumbnail_url($course_id, 'medium') ?: TMM_DASHBOARD_URL . 'assets/img/course-placeholder.jpg',
                    'excerpt' => get_the_excerpt(),
                    'duration' => $course_meta['duration'],
                    'level' => $course_meta['level'],
                    'progress' => $progress_data['percentage'],
                    'status' => $status,
                    'instructor' => $course_meta['instructor'],
                    'categories' => $course_meta['categories'],
                    'is_enrolled' => $progress_data['percentage'] > 0
                ];
            }
            wp_reset_postdata();
        }
    } else {
        // Si pas d'abonnement, récupérer seulement les cours où l'utilisateur est inscrit
        $courses_data = TMMDashboardData::getUserCourses($user_id);
        $all_courses = array_merge(
            $courses_data['enrolled'] ?? [],
            $courses_data['in_progress'] ?? [],
            $courses_data['completed'] ?? []
        );
    }
    
    // Organiser les cours par statut pour le filtrage
    $courses_by_status = [
        'all' => $all_courses,
        'not_started' => array_filter($all_courses, function($c) { return $c['status'] === 'not_started'; }),
        'in_progress' => array_filter($all_courses, function($c) { return $c['status'] === 'in_progress'; }),
        'completed' => array_filter($all_courses, function($c) { return $c['status'] === 'completed'; })
    ];
    ?>
    
    <!-- Message d'information pour les abonnés -->
    <?php if ($has_active_membership) : ?>
    <div class="ps-info-banner">
        <i class="fas fa-info-circle"></i>
        <p>Avec votre abonnement <strong><?php echo esc_html($membership->name); ?></strong>, vous avez accès à tous les cours de la plateforme !</p>
    </div>
    <?php endif; ?>
    
    <!-- Statistiques des cours -->
    <div class="ps-courses-stats">
        <div class="ps-stat-mini">
            <span class="ps-stat-value"><?php echo count($all_courses); ?></span>
            <span class="ps-stat-label">Cours <?php echo $has_active_membership ? 'disponibles' : 'inscrits'; ?></span>
        </div>
        <div class="ps-stat-mini">
            <span class="ps-stat-value"><?php echo count($courses_by_status['in_progress']); ?></span>
            <span class="ps-stat-label">En cours</span>
        </div>
        <div class="ps-stat-mini">
            <span class="ps-stat-value"><?php echo count($courses_by_status['completed']); ?></span>
            <span class="ps-stat-label">Terminés</span>
        </div>
        <?php if ($has_active_membership) : ?>
        <div class="ps-stat-mini">
            <span class="ps-stat-value"><?php echo count($courses_by_status['not_started']); ?></span>
            <span class="ps-stat-label">À découvrir</span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Filtres -->
    <div class="ps-courses-filter">
        <button class="ps-filter-btn active" data-filter="all">
            Tous (<?php echo count($all_courses); ?>)
        </button>
        <button class="ps-filter-btn" data-filter="in_progress">
            En cours (<?php echo count($courses_by_status['in_progress']); ?>)
        </button>
        <button class="ps-filter-btn" data-filter="completed">
            Terminés (<?php echo count($courses_by_status['completed']); ?>)
        </button>
        <?php if ($has_active_membership) : ?>
        <button class="ps-filter-btn" data-filter="not_started">
            Non commencés (<?php echo count($courses_by_status['not_started']); ?>)
        </button>
        <?php else : ?>
        <button class="ps-filter-btn" data-filter="enrolled">
            Inscrits (<?php echo count($courses_by_status['all']); ?>)
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Barre de recherche -->
    <div class="ps-courses-search">
        <input type="text" id="ps-courses-search-input" placeholder="Rechercher un cours..." class="ps-search-input">
        <i class="fas fa-search"></i>
    </div>
    
    <!-- Grille des cours -->
    <div class="ps-courses-grid">
        <?php foreach ($all_courses as $course) : ?>
        <div class="ps-course-card" data-status="<?php echo esc_attr($course['status']); ?>" data-name="<?php echo esc_attr(strtolower($course['name'])); ?>">
            <?php if ($course['status'] === 'completed') : ?>
            <div class="ps-course-badge ps-badge-completed">
                <i class="fas fa-check"></i> Terminé
            </div>
            <?php elseif ($course['status'] === 'in_progress') : ?>
            <div class="ps-course-badge ps-badge-progress">
                <i class="fas fa-spinner"></i> En cours
            </div>
            <?php elseif ($has_active_membership && !$course['is_enrolled']) : ?>
            <div class="ps-course-badge ps-badge-new">
                <i class="fas fa-star"></i> Nouveau
            </div>
            <?php endif; ?>
            
            <div class="ps-course-thumbnail">
                <img src="<?php echo esc_url($course['image']); ?>" alt="<?php echo esc_attr($course['name']); ?>">
                <?php if ($course['progress'] > 0) : ?>
                <div class="ps-course-progress-overlay">
                    <span><?php echo $course['progress']; ?>%</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="ps-course-content">
                <h4><?php echo esc_html($course['name']); ?></h4>
                
                <?php if (!empty($course['excerpt'])) : ?>
                <p class="ps-course-excerpt"><?php echo wp_trim_words($course['excerpt'], 15); ?></p>
                <?php endif; ?>
                
                <div class="ps-course-meta">
                    <span><i class="fas fa-clock"></i> <?php echo esc_html($course['duration']); ?></span>
                    <span><i class="fas fa-layer-group"></i> <?php echo esc_html($course['level']); ?></span>
                </div>
                
                <?php if (!empty($course['categories'])) : ?>
                <div class="ps-course-categories">
                    <?php foreach (array_slice($course['categories'], 0, 2) as $category) : ?>
                    <span class="ps-category-tag"><?php echo esc_html($category); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($course['progress'] > 0) : ?>
                <div class="ps-progress-bar">
                    <div class="ps-progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                </div>
                <?php endif; ?>
                
                <div class="ps-course-actions">
                    <?php if ($has_active_membership || $course['is_enrolled']) : ?>
                        <a href="<?php echo esc_url($course['url']); ?>" class="ps-button ps-button-primary ps-button-small">
                            <?php 
                            if ($course['status'] === 'completed') {
                                echo '<i class="fas fa-redo"></i> Revoir';
                            } elseif ($course['status'] === 'in_progress') {
                                echo '<i class="fas fa-play"></i> Continuer';
                            } else {
                                echo '<i class="fas fa-play-circle"></i> Commencer';
                            }
                            ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($course['url']); ?>" class="ps-button ps-button-secondary ps-button-small">
                            <i class="fas fa-eye"></i> Voir détails
                        </a>
                        <a href="<?php echo esc_url(pmpro_url('levels')); ?>" class="ps-button ps-button-primary ps-button-small">
                            <i class="fas fa-lock"></i> S'abonner
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($all_courses)) : ?>
    <div class="ps-empty-state">
        <i class="fas fa-book-open"></i>
        <?php if ($has_active_membership) : ?>
            <p>Aucun cours n'est disponible pour le moment.</p>
        <?php else : ?>
            <p>Vous n'êtes inscrit à aucun cours pour le moment.</p>
            <a href="<?php echo esc_url(pmpro_url('levels')); ?>" class="ps-button ps-button-primary">
                <i class="fas fa-crown"></i> Obtenir un abonnement pour accéder à tous les cours
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$has_active_membership && count($all_courses) > 0) : ?>
    <div class="ps-cta-banner">
        <div class="ps-cta-content">
            <h3>Débloquez l'accès à tous nos cours</h3>
            <p>Avec un abonnement TeachMeMore, accédez à l'intégralité de notre catalogue de formation.</p>
        </div>
        <a href="<?php echo esc_url(pmpro_url('levels')); ?>" class="ps-button ps-button-primary ps-button-large">
            <i class="fas fa-unlock"></i> Voir les abonnements
        </a>
    </div>
    <?php endif; ?>
</section>

        <!-- Section Parcours -->
        <section id="learning-path" class="ps-dashboard-section">
            <?php echo TMMDashboardParcours::renderParcours(get_current_user_id()); ?>
        </section>

        <!-- Section Analytics -->
        <section id="analytics" class="ps-dashboard-section">
            <h2>Analytics & Progression</h2>
            <?php 
            $analytics = TMMDashboardAnalytics::getUserAnalytics(get_current_user_id(), 'overview', 30);
            $performance = TMMDashboardAnalytics::getUserAnalytics(get_current_user_id(), 'performance', 30);
            ?>
            
            <div class="ps-analytics-summary">
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Temps total d'apprentissage</h3>
                        <p class="ps-stat-number"><?php echo $analytics['summary']['total_time_hours']; ?>h</p>
                        <span class="ps-stat-subtitle"><?php echo round($analytics['summary']['total_time_minutes'] / 60 / 24, 1); ?> jours</span>
                    </div>
                </div>
                
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Score moyen aux quiz</h3>
                        <p class="ps-stat-number"><?php echo $analytics['summary']['average_quiz_score']; ?>%</p>
                        <span class="ps-stat-subtitle"><?php echo $analytics['summary']['quizzes_completed']; ?> quiz complétés</span>
                    </div>
                </div>
                
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Taux de complétion</h3>
                        <p class="ps-stat-number"><?php echo $analytics['summary']['completion_rate']; ?>%</p>
                        <span class="ps-stat-subtitle"><?php echo $analytics['summary']['courses_completed']; ?> / <?php echo $analytics['summary']['courses_started']; ?> cours</span>
                    </div>
                </div>
                
                <div class="ps-stat-card">
                    <div class="ps-stat-icon">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="ps-stat-info">
                        <h3>Préférence d'apprentissage</h3>
                        <p class="ps-stat-number"><?php echo $analytics['learning_preference']; ?></p>
                        <span class="ps-stat-subtitle">Moment optimal</span>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques -->
            <div class="ps-analytics-charts">
                <div class="ps-card">
                    <div class="ps-card-header">
                        <h3>Évolution de votre progression</h3>
                        <select class="ps-chart-selector" data-chart="analytics">
                            <option value="activity">Activité</option>
                            <option value="performance">Performance</option>
                            <option value="time">Temps passé</option>
                        </select>
                    </div>
                    <div class="ps-card-body">
                        <canvas id="analyticsChart" height="100"></canvas>
                    </div>
                </div>
                
                <div class="ps-card">
                    <div class="ps-card-header">
                        <h3>Comparaison avec la moyenne</h3>
                    </div>
                    <div class="ps-card-body">
                        <?php if (isset($performance['comparison'])) : ?>
                        <div class="ps-comparison-stats">
                            <div class="ps-comparison-item">
                                <span>Votre score moyen</span>
                                <strong><?php echo $performance['comparison']['user_average']; ?>%</strong>
                            </div>
                            <div class="ps-comparison-item">
                                <span>Moyenne globale</span>
                                <strong><?php echo $performance['comparison']['global_average']; ?>%</strong>
                            </div>
                            <div class="ps-comparison-item">
                                <span>Différence</span>
                                <strong class="<?php echo $performance['comparison']['difference'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo $performance['comparison']['difference'] > 0 ? '+' : ''; ?><?php echo $performance['comparison']['difference']; ?>%
                                </strong>
                            </div>
                            <div class="ps-comparison-item">
                                <span>Votre rang</span>
                                <strong>Top <?php echo 100 - $performance['comparison']['percentile']; ?>%</strong>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recommandations d'amélioration -->
            <?php if (!empty($performance['improvement_suggestions'])) : ?>
            <div class="ps-card">
                <div class="ps-card-header">
                    <h3>Recommandations pour progresser</h3>
                </div>
                <div class="ps-card-body">
                    <ul class="ps-suggestions-list">
                        <?php foreach ($performance['improvement_suggestions'] as $suggestion) : ?>
                        <li>
                            <i class="fas fa-lightbulb"></i>
                            <?php echo esc_html($suggestion); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Section Certificats -->
        <section id="certificates" class="ps-dashboard-section">
            <h2>Mes Certificats</h2>
            <?php 
            $certificates = TMMDashboardCertificates::getUserCertificates(get_current_user_id());
            ?>
            
            <?php if (!empty($certificates)) : ?>
            <div class="ps-certificates-grid">
                <?php foreach ($certificates as $certificate) : ?>
                <div class="ps-certificate-card">
                    <div class="ps-certificate-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="ps-certificate-content">
                        <h4><?php echo esc_html($certificate['parcours_name']); ?></h4>
                        <p class="ps-certificate-date">
                            <i class="fas fa-calendar"></i>
                            Délivré le <?php echo date_i18n(get_option('date_format'), strtotime($certificate['date_completed'])); ?>
                        </p>
                        <p class="ps-certificate-number">
                            <i class="fas fa-hashtag"></i>
                            N° <?php echo esc_html($certificate['certificate_number']); ?>
                        </p>
                        <?php if (!empty($certificate['skills'])) : ?>
                        <div class="ps-certificate-skills">
                            <p><strong>Compétences validées :</strong></p>
                            <div class="ps-skills-tags">
                                <?php foreach ($certificate['skills'] as $skill) : ?>
                                <span class="ps-skill-tag"><?php echo esc_html($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="ps-certificate-actions">
                        <a href="<?php echo esc_url(TMMDashboardCertificates::getCertificateUrl(get_current_user_id(), $certificate['parcours_id'])); ?>" 
                           class="ps-button ps-button-primary" target="_blank">
                            <i class="fas fa-download"></i> Télécharger PDF
                        </a>
                        <button class="ps-button ps-button-secondary ps-share-certificate" 
                                data-url="<?php echo esc_url(TMMDashboardCertificates::getCertificateUrl(get_current_user_id(), $certificate['parcours_id'])); ?>">
                            <i class="fas fa-share"></i> Partager
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="ps-empty-state">
                <i class="fas fa-certificate"></i>
                <p>Vous n'avez pas encore de certificat.</p>
                <p>Terminez un parcours complet pour obtenir votre premier certificat !</p>
                <a href="#learning-path" class="ps-button ps-button-primary ps-nav-link">
                    Voir mon parcours
                </a>
            </div>
            <?php endif; ?>
        </section>

        <!-- Section Badges -->
        <section id="badges" class="ps-dashboard-section">
            <?php echo TMMDashboardBadges::renderUserBadges(get_current_user_id()); ?>
        </section>

        <!-- Section Paramètres -->
        <section id="settings" class="ps-dashboard-section">
            <h2>Paramètres</h2>
            
            <div class="ps-settings-tabs">
                <button class="ps-tab-button active" data-tab="profile">
                    <i class="fas fa-user"></i> Profil
                </button>
                <button class="ps-tab-button" data-tab="notifications">
                    <i class="fas fa-bell"></i> Notifications
                </button>
                <button class="ps-tab-button" data-tab="security">
                    <i class="fas fa-lock"></i> Sécurité
                </button>
                <button class="ps-tab-button" data-tab="preferences">
                    <i class="fas fa-sliders-h"></i> Préférences
                </button>
                <button class="ps-tab-button" data-tab="membership">
                    <i class="fas fa-credit-card"></i> Abonnement
                </button>
            </div>
            
            <!-- Tab Profil -->
            <div class="ps-tab-content active" id="profile">
                <form id="ps-profile-form" class="ps-form">
                    <?php wp_nonce_field('update_profile_nonce'); ?>
                    
                    <div class="ps-form-row">
                        <div class="ps-form-group">
                            <label for="display_name"><?php _e('Nom d\'affichage', 'tmm-dashboard'); ?></label>
                            <input type="text" id="display_name" name="display_name" 
                                   value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>">
                        </div>
                        
                        <div class="ps-form-group">
                            <label for="user_email"><?php _e('Email', 'tmm-dashboard'); ?></label>
                            <input type="email" id="user_email" name="user_email" 
                                   value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>">
                        </div>
                    </div>
                    
                    <div class="ps-form-row">
                        <div class="ps-form-group">
                            <label for="first_name"><?php _e('Prénom', 'tmm-dashboard'); ?></label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'first_name', true)); ?>">
                        </div>
                        
                        <div class="ps-form-group">
                            <label for="last_name"><?php _e('Nom', 'tmm-dashboard'); ?></label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo esc_attr(get_user_meta(get_current_user_id(), 'last_name', true)); ?>">
                        </div>
                    </div>
                    
                    <div class="ps-form-group">
                        <label for="description"><?php _e('Bio', 'tmm-dashboard'); ?></label>
                        <textarea id="description" name="description" rows="4"><?php 
                            echo esc_textarea(get_user_meta(get_current_user_id(), 'description', true)); 
                        ?></textarea>
                    </div>
                    
                    <div class="ps-form-group">
                        <label for="avatar"><?php _e('Photo de profil', 'tmm-dashboard'); ?></label>
                        <div class="ps-avatar-upload">
                            <div class="ps-avatar-preview">
                                <?php echo get_avatar(get_current_user_id(), 100); ?>
                            </div>
                            <div class="ps-avatar-actions">
                                <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;">
                                <button type="button" class="ps-button ps-button-secondary" onclick="document.getElementById('avatar').click()">
                                    <i class="fas fa-camera"></i> Changer la photo
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="ps-profile-message" class="ps-message" style="display: none;"></div>
                    
                    <button type="submit" class="ps-button ps-button-primary">
                        <i class="fas fa-save"></i> <?php _e('Sauvegarder les modifications', 'tmm-dashboard'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Tab Notifications -->
            <div class="ps-tab-content" id="notifications">
                <form id="ps-notifications-form" class="ps-form">
                    <h3>Préférences de notification</h3>
                    
                    <div class="ps-form-group">
                        <label class="ps-switch">
                            <input type="checkbox" name="email_notifications" value="1" 
                                   <?php checked(get_user_meta(get_current_user_id(), 'tmm_email_notifications', true), '1'); ?>>
                            <span class="ps-switch-slider"></span>
                        </label>
                        <span><?php _e('Recevoir des notifications par email', 'tmm-dashboard'); ?></span>
                    </div>
                    
                    <div class="ps-form-group">
                        <label class="ps-switch">
                            <input type="checkbox" name="weekly_report" value="1" 
                                   <?php checked(get_user_meta(get_current_user_id(), 'tmm_pref_weekly_report', true), '1'); ?>>
                            <span class="ps-switch-slider"></span>
                        </label>
                        <span><?php _e('Recevoir le rapport hebdomadaire', 'tmm-dashboard'); ?></span>
                    </div>
                    
                    <div class="ps-form-group">
                        <label class="ps-switch">
                            <input type="checkbox" name="course_recommendations" value="1" 
                                   <?php checked(get_user_meta(get_current_user_id(), 'tmm_pref_course_recommendations', true), '1'); ?>>
                            <span class="ps-switch-slider"></span>
                        </label>
                        <span><?php _e('Recevoir des recommandations de cours', 'tmm-dashboard'); ?></span>
                    </div>
                    
                    <div class="ps-form-group">
                        <label class="ps-switch">
                            <input type="checkbox" name="achievement_alerts" value="1" 
                                   <?php checked(get_user_meta(get_current_user_id(), 'tmm_pref_achievement_alerts', true), '1'); ?>>
                            <span class="ps-switch-slider"></span>
                        </label>
                        <span><?php _e('Alertes pour les nouveaux badges et certificats', 'tmm-dashboard'); ?></span>
                    </div>
                    
                    <div id="ps-notifications-message" class="ps-message" style="display: none;"></div>
                    
                    <button type="submit" class="ps-button ps-button-primary">
                        <i class="fas fa-save"></i> <?php _e('Sauvegarder', 'tmm-dashboard'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Tab Sécurité -->
            <div class="ps-tab-content" id="security">
                <form id="ps-password-form" class="ps-form">
                    <?php wp_nonce_field('change_password_nonce'); ?>
                    
                    <h3>Changer le mot de passe</h3>
                    
                    <div class="ps-form-group">
                        <label for="current_password"><?php _e('Mot de passe actuel', 'tmm-dashboard'); ?></label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="ps-form-group">
                        <label for="ps-new-password"><?php _e('Nouveau mot de passe', 'tmm-dashboard'); ?></label>
                        <input type="password" id="ps-new-password" name="new_password" required>
                        <div class="ps-password-strength"></div>
                    </div>
                    
                    <div class="ps-form-group">
                        <label for="ps-confirm-password"><?php _e('Confirmer le nouveau mot de passe', 'tmm-dashboard'); ?></label>
                        <input type="password" id="ps-confirm-password" name="confirm_password" required>
                    </div>
                    
                    <div id="ps-password-message" class="ps-message" style="display: none;"></div>
                    
                    <button type="submit" class="ps-button ps-button-primary">
                        <i class="fas fa-key"></i> <?php _e('Changer le mot de passe', 'tmm-dashboard'); ?>
                    </button>
                </form>
                
                <div class="ps-security-info">
                    <h3>Sessions actives</h3>
                    <p>Dernière connexion : <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime(get_user_meta(get_current_user_id(), 'last_login', true))); ?></p>
                </div>
            </div>
            
            <!-- Tab Préférences -->
            <div class="ps-tab-content" id="preferences">
                <form id="ps-preferences-form" class="ps-form">
                    <h3>Préférences d'affichage</h3>
                    
                    <div class="ps-form-group">
                        <label for="theme_mode"><?php _e('Thème', 'tmm-dashboard'); ?></label>
                        <select id="theme_mode" name="theme_mode">
                            <option value="light" <?php selected(get_user_meta(get_current_user_id(), 'tmm_pref_theme_mode', true), 'light'); ?>>Clair</option>
                            <option value="dark" <?php selected(get_user_meta(get_current_user_id(), 'tmm_pref_theme_mode', true), 'dark'); ?>>Sombre</option>
                            <option value="auto" <?php selected(get_user_meta(get_current_user_id(), 'tmm_pref_theme_mode', true), 'auto'); ?>>Automatique</option>
                        </select>
                    </div>
                    
                    <div class="ps-form-group">
                        <label for="language"><?php _e('Langue', 'tmm-dashboard'); ?></label>
                        <select id="language" name="language">
                            <option value="fr_FR" <?php selected(get_user_meta(get_current_user_id(), 'tmm_pref_language', true), 'fr_FR'); ?>>Français</option>
                            <option value="en_US" <?php selected(get_user_meta(get_current_user_id(), 'tmm_pref_language', true), 'en_US'); ?>>English</option>
                        </select>
                    </div>
                    
                    <div class="ps-form-group">
                        <label for="timezone"><?php _e('Fuseau horaire', 'tmm-dashboard'); ?></label>
                        <select id="timezone" name="timezone">
                            <?php 
                            $selected_timezone = get_user_meta(get_current_user_id(), 'tmm_pref_timezone', true) ?: 'Europe/Paris';
                            echo wp_timezone_choice($selected_timezone); 
                            ?>
                        </select>
                    </div>
                    
                    <div id="ps-preferences-message" class="ps-message" style="display: none;"></div>
                    
                    <button type="submit" class="ps-button ps-button-primary">
                        <i class="fas fa-save"></i> <?php _e('Sauvegarder', 'tmm-dashboard'); ?>
                    </button>
                </form>
            </div>
            
            <!-- Tab Abonnement -->
            <div class="ps-tab-content" id="membership">
                <?php 
                $membership = $dashboard_data['stats']['membership'];
                ?>
                
                <div class="ps-membership-info">
                    <h3>Mon abonnement actuel</h3>
                    
                    <?php if ($membership['has_membership']) : ?>
                    <div class="ps-membership-card">
                        <div class="ps-membership-header">
                            <h4><?php echo esc_html($membership['level_name']); ?></h4>
                            <span class="ps-membership-status ps-status-<?php echo esc_attr($membership['status']); ?>">
                                <?php 
                                switch ($membership['status']) {
                                    case 'active':
                                        echo 'Actif';
                                        break;
                                    case 'expiring_soon':
                                        echo 'Expire bientôt';
                                        break;
                                    case 'expired':
                                        echo 'Expiré';
                                        break;
                                    case 'lifetime':
                                        echo 'À vie';
                                        break;
                                }
                                ?>
                            </span>
                        </div>
                        
                        <div class="ps-membership-details">
                            <?php if ($membership['description']) : ?>
                            <p><?php echo esc_html($membership['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="ps-membership-meta">
                                <?php if ($membership['start_date']) : ?>
                                <div class="ps-meta-item">
                                    <span>Date de début :</span>
                                    <strong><?php echo date_i18n(get_option('date_format'), strtotime($membership['start_date'])); ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($membership['end_date'] && $membership['status'] !== 'lifetime') : ?>
                                <div class="ps-meta-item">
                                    <span>Date de fin :</span>
                                    <strong><?php echo date_i18n(get_option('date_format'), strtotime($membership['end_date'])); ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($membership['days_remaining'] !== null) : ?>
                                <div class="ps-meta-item">
                                    <span>Jours restants :</span>
                                    <strong><?php echo $membership['days_remaining']; ?> jours</strong>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($membership['billing_amount'] > 0) : ?>
                                <div class="ps-meta-item">
                                    <span>Montant :</span>
                                    <strong><?php echo $membership['billing_amount']; ?> € / <?php 
                                        echo $membership['cycle_number'] . ' ';
                                        switch ($membership['cycle_period']) {
                                            case 'Day':
                                                echo 'jour(s)';
                                                break;
                                            case 'Week':
                                                echo 'semaine(s)';
                                                break;
                                            case 'Month':
                                                echo 'mois';
                                                break;
                                            case 'Year':
                                                echo 'an(s)';
                                                break;
                                        }
                                    ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="ps-membership-actions">
                            <?php if ($membership['status'] === 'active' || $membership['status'] === 'expiring_soon') : ?>
                            <button id="ps-cancel-membership" class="ps-button ps-button-danger">
                                <i class="fas fa-times"></i> Annuler l'abonnement
                            </button>
                            <?php endif; ?>
                            
                            <a href="<?php echo esc_url(pmpro_url('account')); ?>" class="ps-button ps-button-secondary">
                                <i class="fas fa-file-invoice"></i> Historique des factures
                            </a>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="ps-empty-state">
                        <i class="fas fa-credit-card"></i>
                        <p>Vous n'avez pas d'abonnement actif.</p>
                        <a href="<?php echo esc_url(pmpro_url('levels')); ?>" class="ps-button ps-button-primary">
                            Voir les abonnements
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($membership['has_membership'] && ($membership['status'] === 'active' || $membership['status'] === 'expiring_soon')) : ?>
                <div class="ps-upgrade-section">
                    <h3>Changer d'abonnement</h3>
                    <p>Découvrez nos autres formules d'abonnement.</p>
                    <a href="<?php echo esc_url(pmpro_url('levels')); ?>" id="ps-upgrade-membership" class="ps-button ps-button-primary">
                        <i class="fas fa-arrow-up"></i> Voir les options
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Chatbot Widget -->
    <?php if (get_option('tmm_enable_chatbot', true)) : ?>
    <div class="tmm-chatbot-widget">
        <button class="tmm-chatbot-toggle">
            <i class="fas fa-comments"></i>
            <span class="tmm-chatbot-badge" style="display: none;">1</span>
        </button>
        
        <div class="tmm-chatbot-window">
            <div class="tmm-chatbot-header">
                <div class="tmm-chatbot-title">
                    <i class="fas fa-robot"></i>
                    <h4>Assistant TeachMeMore</h4>
                </div>
                <button class="tmm-chatbot-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="tmm-chatbot-messages">
                <!-- Les messages seront ajoutés ici dynamiquement -->
            </div>
            
            <div class="tmm-chatbot-suggestions">
                <!-- Les suggestions rapides seront ajoutées ici -->
            </div>
            
            <form class="tmm-chatbot-input">
                <input type="text" placeholder="Tapez votre message..." autocomplete="off">
                <button type="submit">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Données pour les graphiques
window.tmmDashboardData = <?php echo json_encode([
    'weeklyLabels' => $dashboard_data['stats']['weekly_progress']['labels'] ?? [],
    'weeklyData' => $dashboard_data['stats']['weekly_progress']['data'] ?? [],
    'userId' => get_current_user_id(),
    'analytics' => $analytics ?? [],
    'performance' => $performance ?? []
]); ?>;
</script>