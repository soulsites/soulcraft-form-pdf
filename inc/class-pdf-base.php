<?php
/**
 * Base class for PDF providers
 */
abstract class PDF_Base {
    /**
     * @var string Der eindeutige Provider-Name
     */
    protected $provider_name;

    /**
     * @var string Das Provider-Präfix für Form-IDs
     */
    protected $id_prefix;

    /**
     * @var PDF_Generator Der PDF Generator
     */
    protected $pdf_generator;

    /**
     * @var array Cache für Formulare
     */
    protected $forms_cache = null;

    public function __construct($pdf_generator) {
        $this->pdf_generator = $pdf_generator;

        if ($this->is_active()) {
            $this->init();
        }
    }

    /**
     * Initialisiert den Provider
     * Kann von Kindklassen überschrieben werden
     */
    protected function init(): void {
        // Optional von Kindklassen zu implementieren
    }

    /**
     * Gibt zurück, ob der Provider aktiv ist
     */
    abstract public function is_active(): bool;

    /**
     * Holt die Formulare aus der jeweiligen Quelle
     */
    abstract protected function fetch_forms(): array;

    /**
     * Bereitet die Formulardaten für die PDF-Generierung vor
     */
    protected function prepare_submission_data($raw_data): array {
        return [];
    }

    /**
     * Gibt den Provider-Namen zurück
     */
    public function get_name(): string {
        return $this->provider_name;
    }

    /**
     * Gibt das ID-Präfix zurück
     */
    public function get_id_prefix(): string {
        return $this->id_prefix;
    }

    /**
     * Holt alle verfügbaren Formulare
     */
    public function get_forms(): array {
        if ($this->forms_cache !== null) {
            return $this->forms_cache;
        }

        if (!$this->is_active()) {
            return [];
        }

        $this->forms_cache = $this->fetch_forms();
        return $this->forms_cache;
    }

    /**
     * Verarbeitet eine Formular-Einreichung
     */
    public function handle_submission($data) {
        if (!$this->is_active()) {
            return false;
        }

        $pdf_data = $this->prepare_submission_data($data);
        return $this->pdf_generator->generate($pdf_data);
    }

    /**
     * Leert den Formular-Cache
     */
    public function clear_cache(): void {
        $this->forms_cache = null;
    }

    /**
     * Gibt die PDF-Einstellungen für ein bestimmtes Formular zurück.
     * Sucht zuerst nach einem exakten Match, dann nach "alle Formulare".
     */
    protected function get_form_settings(string $form_id): ?array {
        $all_settings = carbon_get_theme_option('pdf_form_settings');
        if (!is_array($all_settings)) {
            return null;
        }

        foreach ($all_settings as $settings) {
            if ($settings['form_id'] === $form_id) {
                $this->log('Found specific form settings for: ' . $form_id);
                return $settings;
            }
        }

        foreach ($all_settings as $settings) {
            if ($settings['form_id'] === 'all') {
                $this->log('Using "all" form settings for: ' . $form_id);
                return $settings;
            }
        }

        $this->log('No settings found for form: ' . $form_id);
        return null;
    }

    /**
     * Logging-Hilfsmethode für Provider
     */
    protected function log($message, $data = null): void {
        pdf_debug($this->provider_name . ' Provider - ' . $message, $data);
    }
}