<div class="wrap">
    <h1>TMM Dashboard - Tableau de bord Admin</h1>
    
    <div class="tmm-admin-stats">
        <div class="tmm-stat-box">
            <h3>Utilisateurs actifs</h3>
            <p class="tmm-big-number"><?php echo wp_count_posts('user'); ?></p>
        </div>
        
        <div class="tmm-stat-box">
            <h3>Badges distribués</h3>
            <p class="tmm-big-number"><?php 
                global $wpdb;
                echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_user_badges");
            ?></p>
        </div>
        
        <div class="tmm-stat-box">
            <h3>Événements trackés</h3>
            <p class="tmm-big-number"><?php 
                echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tmm_analytics");
            ?></p>
        </div>
    </div>
    
    <h2>Actions rapides</h2>
    <p>
        <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-settings'); ?>" class="button button-primary">
            Paramètres
        </a>
        <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-analytics'); ?>" class="button">
            Analytics
        </a>
        <a href="<?php echo admin_url('admin.php?page=tmm-dashboard-parcours'); ?>" class="button">
            Gérer les parcours
        </a>
    </p>
</div>