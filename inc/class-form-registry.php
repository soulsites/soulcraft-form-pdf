<?php
/**
 * Registry for form providers
 */
class Form_Registry {
    private static $instance = null;
    private $providers = [];
    private $pdf_generator;

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // PDF Generator wird erst bei Bedarf initialisiert
        $this->pdf_generator = null;
    }

    /**
     * Lazy loading für PDF Generator
     */
    public function get_pdf_generator(): PDF_Generator {
        if ($this->pdf_generator === null) {
            $this->pdf_generator = new PDF_Generator();
        }
        return $this->pdf_generator;
    }

    /**
     * Registriert einen neuen Provider
     */
    public function register_provider(PDF_Base $provider): void {
        $this->providers[$provider->get_name()] = $provider;
        pdf_debug('Registered provider: ' . $provider->get_name());
    }

    /**
     * Gibt alle verfügbaren Formulare zurück
     */
    public function get_all_forms(): array {
        $forms = [
            '' => '-- Bitte auswählen --',
            'all' => 'Alle Formulare'
        ];

        foreach ($this->providers as $provider) {
            if ($provider->is_active()) {
                $provider_forms = $provider->get_forms();
                if (!empty($provider_forms)) {
                    pdf_debug('Got forms from ' . $provider->get_name(), $provider_forms);
                    $forms = array_merge($forms, $provider_forms);
                }
            }
        }

        return $forms;
    }

    /**
     * Leert den Cache aller Provider
     */
    public function clear_cache(): void {
        foreach ($this->providers as $provider) {
            $provider->clear_cache();
        }
        pdf_debug('Cleared cache for all providers');
    }

    /**
     * Gibt einen spezifischen Provider zurück
     */
    public function get_provider(string $name): ?PDF_Base {
        return $this->providers[$name] ?? null;
    }

    /**
     * Verarbeitet eine Formular-Einreichung
     */
    public function handle_submission(string $form_id, $data) {
        foreach ($this->providers as $provider) {
            if (strpos($form_id, $provider->get_id_prefix()) === 0) {
                return $provider->handle_submission($data);
            }
        }
        return false;
    }
}