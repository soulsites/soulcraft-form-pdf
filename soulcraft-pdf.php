<?php
/**
 * Plugin Name: Soulcraft PDF Generator
 * Description: Generiert PDF Dateien aus verschiedenen Form Buildern (Ninja Forms, Elementor, CF7))
 * Version: 1.0.0
 * Author: Christian Wedel
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('SOULCRAFT_PDF_VERSION', '1.0.0');
define('SOULCRAFT_PDF_FILE', __FILE__);
define('SOULCRAFT_PDF_PATH', plugin_dir_path(__FILE__));
define('SOULCRAFT_PDF_URL', plugin_dir_url(__FILE__));

// Composer Autoloader

require_once SOULCRAFT_PDF_PATH . 'vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Core files
require_once SOULCRAFT_PDF_PATH . 'inc/class-pdf-debug.php';
require_once SOULCRAFT_PDF_PATH . 'inc/class-pdf-base.php';
require_once SOULCRAFT_PDF_PATH . 'inc/class-form-registry.php';
require_once SOULCRAFT_PDF_PATH . 'inc/class-pdf-generator.php';
require_once SOULCRAFT_PDF_PATH . 'inc/class-settings-page.php';



class Soulcraft_PDF {
    private static $instance = null;
    private $registry;
    private $settings_page;

    private function __construct() {
        // Boot Carbon Fields
        add_action('after_setup_theme', function() {
            \Carbon_Fields\Carbon_Fields::boot();
        });

        // Warte auf Carbon Fields und initialisiere das Plugin
        add_action('carbon_fields_fields_registered', [$this, 'init']);
    }

    public function init() {
        pdf_debug('Initializing Soulcraft PDF');

        // Initialize Registry
        $this->registry = Form_Registry::get_instance();

        // Setup directory
        $this->setup_directories();

        // Load and register providers
        $this->load_providers();

        // Initialize settings page
        if (is_admin()) {
            $this->settings_page = new Settings_Page($this->registry);
        }

        pdf_debug('Soulcraft PDF initialization complete');
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot_carbon_fields() {
        pdf_debug('Booting Carbon Fields');
        \Carbon_Fields\Carbon_Fields::boot();
    }

    public function init_base() {
        if ($this->initialization_complete) {
            return;
        }

        if (is_admin()) {
            $this->init_settings();
        }

        pdf_debug('Starting base initialization');

        // Initialize Registry
        $this->registry = Form_Registry::get_instance();

        // Setup directory
        $this->setup_directories();

        // Load and register providers
        $this->load_providers();

        $this->initialization_complete = true;
        pdf_debug('Base initialization complete');
    }

    public function init_settings() {
        if (!is_admin() || isset($this->settings_page)) {
            return;
        }

        pdf_debug('Initializing settings page');

        // Initialize settings page
        $this->settings_page = new Settings_Page($this->registry);
        pdf_debug('Settings page initialized');
    }

    private function load_providers() {
        pdf_debug('Loading providers');

        $provider_dir = SOULCRAFT_PDF_PATH . 'providers/';
        pdf_debug('Provider directory', $provider_dir);

        if (!is_dir($provider_dir)) {
            pdf_debug('Provider directory not found!');
            return;
        }

        // Load all provider files
        $provider_files = glob($provider_dir . 'class-*.php');
        pdf_debug('Found provider files', $provider_files);

        foreach ($provider_files as $provider_file) {
            pdf_debug('Loading provider file', basename($provider_file));
            require_once $provider_file;
        }

        // Register active providers
        if (defined('WPCF7_VERSION')) {
            $this->registry->register_provider(
                new CF7_Provider($this->registry->get_pdf_generator())
            );
        }

        if (defined('ELEMENTOR_PRO_VERSION')) {
            $this->registry->register_provider(
                new Elementor_Provider($this->registry->get_pdf_generator())
            );
        }

        if (function_exists('Ninja_Forms')) {
            $this->registry->register_provider(
                new Ninja_Provider($this->registry->get_pdf_generator())
            );
        }

        pdf_debug('Providers loaded');
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'crb_carbon_fields_container_pdf_einstellungen') === false) {
            return;
        }

        wp_enqueue_style(
            'soulcraft-pdf-admin',
            SOULCRAFT_PDF_URL . 'assets/css/admin-style.css',
            [],
            SOULCRAFT_PDF_VERSION
        );

        wp_enqueue_script(
            'soulcraft-pdf-admin',
            SOULCRAFT_PDF_URL . 'assets/js/admin-script.js',
            ['jquery'],
            SOULCRAFT_PDF_VERSION,
            true
        );

        wp_localize_script('soulcraft-pdf-admin', 'soulcraftPdfData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('soulcraft_pdf_nonce')
        ]);
    }

    private function setup_directories() {
        pdf_debug('Setting up directories');

        // Create upload directory for PDFs
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/soulcraft-pdf';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
            pdf_debug('Created PDF directory', $pdf_dir);
        }

        // Secure the directory
        $htaccess_file = $pdf_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Order deny,allow\nDeny from all");
            pdf_debug('Created .htaccess file');
        }

        $index_file = $pdf_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
            pdf_debug('Created index.php file');
        }
    }

    public function get_registry() {
        return $this->registry;
    }
}

// Initialize plugin
function soulcraft_pdf() {
    return Soulcraft_PDF::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'soulcraft_pdf');

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/soulsites/soulcraft-form-pdf',
    __FILE__,
    'soulcraft-form-pdf'
);

$myUpdateChecker->setBranch('main');
$myUpdateChecker->debugMode = true;

// Für private Repositories
$myUpdateChecker->setAuthentication('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');