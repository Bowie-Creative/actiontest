<?php
function_exists('add_action') or die;

class AutoUpdater_WP_Admin
{
    protected static $instance = null;
    protected $menu_slug = 'autoupdater';
    protected $display_settings_page = false;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!is_null(static::$instance)) {
            return static::$instance;
        }

        static::$instance = new AutoUpdater_WP_Admin();

        return static::$instance;
    }

    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        if (AutoUpdater_Filemanager::getInstance()->exists(AUTOUPDATER_WP_PLUGIN_PATH . 'app/Settings.php')) {
            require_once AUTOUPDATER_WP_PLUGIN_PATH . 'app/Settings.php';
            $this->display_settings_page = true;
        }

        if (defined('DOING_AJAX') && $this->display_settings_page) {
            add_action('wp_ajax_autoupdater_save', 'AutoUpdater_WP_Settings::ajaxSaveConfiguration');
            return;
        }

        global $pagenow;
        if ($pagenow === 'update-core.php') {
            AutoUpdater_Log::traceHooks();
        }

        $whitelabelling = AutoUpdater_WP_Whitelabelling::getInstance();
        $this->menu_slug = $whitelabelling->getWhiteLabeledSlug();
        if (!$whitelabelling->isPluginHidden()) {
            add_action('admin_init', array($this, 'maintenanceOff'));
            add_action('admin_init', array($this, 'redirectToConfigurationPage'));
            add_action('admin_menu', array($this, 'addMenuEntry'));
            if ($this->display_settings_page) {
                add_action('admin_init', 'AutoUpdater_WP_Settings::addMediaFiles');
            }
        }
    }

    /**
     * @return string
     */
    public function getMenuSlug()
    {
        return $this->menu_slug;
    }

    /**
     * Add menu entry with plug-in settings page.
     */
    public function addMenuEntry()
    {
        $name = AutoUpdater_WP_Whitelabelling::getInstance()
            ->getWhiteLabeledName(AUTOUPDATER_WP_PLUGIN_NAME);

        add_management_page(
            $name,
            $name,
            'manage_options',
            $this->menu_slug,
            array($this, 'displayConfigurationPage')
        );

        if ($this->menu_slug != 'autoupdater') {
            add_submenu_page(
                null,
                $name,
                $name,
                'manage_options',
                'autoupdater',
                array($this, 'displayConfigurationPage')
            );
        };

        // Display "Turn off maintenance" in admin menu if it is enabled for longer than 15 minutes or for an unknown time
        if (!AutoUpdater_Maintenance::getInstance()->isEnabled()) {
            return;
        }

        $date = AutoUpdater_Config::get('maintenance_started_at');
        if ($date) {
            $date = new DateTime($date);
            if ((time() - $date->getTimestamp()) / 60 < 15) {
                return;
            }
        }

        add_menu_page(
            'Turn off maintenance',
            'Turn off maintenance',
            'manage_options',
            admin_url('tools.php?page=autoupdater-maintenance-off'),
            '',
            'dashicons-admin-site',
            0
        );

        add_submenu_page(
            null,
            'Turning off maintenance',
            'Turning off maintenance',
            'manage_options',
            'autoupdater-maintenance-off'
        );
    }

    public function maintenanceOff()
    {
        global $pagenow;

        if ($pagenow != 'tools.php' || AutoUpdater_Request::getQueryVar('page') != 'autoupdater-maintenance-off') {
            return;
        }

        AutoUpdater_Maintenance::getInstance()->disable();

        wp_safe_redirect(admin_url(), 302);
        exit;
    }

    /**
     * Redirects wp-admin/tools.php?page=autoupdater to the configuration page with the white labelled menu slug
     */
    public function redirectToConfigurationPage()
    {
        global $pagenow;

        if (
            $pagenow != 'tools.php' || AutoUpdater_Request::getQueryVar('page') == $this->menu_slug ||
            AutoUpdater_Request::getQueryVar('page') != 'autoupdater'
        ) {
            return;
        }

        wp_safe_redirect(menu_page_url($this->menu_slug, false), 301);
        exit;
    }

    public function displayConfigurationPage()
    {
        if (!$this->display_settings_page) {
            // External redirect
            wp_redirect('https://my.wpengine.com/products/smart_plugin_manager', 301); // phpcs:ignore
            exit;
        }

        AutoUpdater_WP_Settings::displayConfigurationPage();
    }
}
