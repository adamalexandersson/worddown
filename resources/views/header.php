<div class="worddown-plugin-header">
    <div class="worddown-header-bar">
        <div class="worddown-header-logo-bar">
            <div class="worddown-header-logo">
                <?php echo wp_kses(icon('worddown-alt'), allowed_svg_tags()); ?>
            </div>

            <h4 class="worddown-header-title"><?php echo esc_attr(config('app.name')) ?></h4>
        </div>

        <nav class="worddown-header-submenu">
            <?php foreach ($worddown_submenus as $submenu): ?>
                <a href="<?php echo esc_url($submenu['url']); ?>" class="worddown-header-submenu-link<?php echo $current_page === $submenu['menu_slug'] ? ' active' : ''; ?>">
                    <?php echo wp_kses(icon($submenu['icon']), allowed_svg_tags()); ?>
                    <span><?php echo esc_html($submenu['menu_title']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<div class="worddown-header-heading-bar">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>
</div>