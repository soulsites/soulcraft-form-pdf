<?php
/**
 * Debug Logger Helper
 */
class PDF_Debug {
    private static $instance = null;
    private $debug_enabled = null;
    private $fields_registered = false;

    private function __construct() {
        // Höre auf Carbon Fields Registrierung
        add_action('carbon_fields_fields_registered', [$this, 'on_fields_registered']);
    }

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function on_fields_registered() {
        $this->fields_registered = true;
        $this->debug_enabled = null; // Reset cache
    }

    /**
     * Prüft, ob Debug-Modus aktiv ist
     */
    public function is_debug_enabled(): bool {
        // Wenn Carbon Fields bereit ist
        if ($this->fields_registered && $this->debug_enabled === null) {
            $this->debug_enabled = carbon_get_theme_option('pdf_debug_enabled') === 'yes';
        }

        // Fallback auf WP_DEBUG
        if ($this->debug_enabled === null) {
            $this->debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        }

        return $this->debug_enabled;
    }

    /**
     * Loggt eine Nachricht, wenn Debug aktiviert ist
     */
    public function log($message, $data = null): void {
        // Immer loggen während der Initialisierung
        $always_log = !$this->fields_registered;

        if (!$always_log && !$this->is_debug_enabled()) {
            return;
        }

        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $message .= ' ' . print_r($data, true);
            } else {
                $message .= ' ' . $data;
            }
        }

        error_log('PDF Debug: ' . $message);
    }
}

// Globale Hilfsfunktion für einfacheren Zugriff
function pdf_debug($message, $data = null): void {
    PDF_Debug::get_instance()->log($message, $data);
}