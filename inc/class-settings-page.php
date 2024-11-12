<?php
/**
 * Settings Page Handler
 * Manages the admin interface using Carbon Fields
 */
use Carbon_Fields\Container;
use Carbon_Fields\Field;
class Settings_Page {
    private $registry;
    private $font_manager;

    public function __construct($registry) {
        pdf_debug('Settings Page constructor start');

        $this->registry = $registry;

        // Register fields right away
        $this->register_fields();

        // Admin scripts & styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX handlers
        add_action('wp_ajax_refresh_debug_log', [$this, 'ajax_refresh_debug_log']);
        add_action('wp_ajax_send_pdf_preview', [$this, 'ajax_send_pdf_preview']);
        add_action('wp_ajax_install_google_font', [$this, 'ajax_install_google_font']);

        pdf_debug('Settings Page constructor complete');
    }



    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'crb_carbon_fields_container_pdf_einstellungen') === false &&
            strpos($hook, 'crb_carbon_fields_container_google_fonts') === false) {
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

    private function register_fields() {
        pdf_debug('Registering settings fields');

        // Get all available forms from registry
        $available_forms = $this->registry->get_all_forms();
        pdf_debug('Forms for dropdown', $available_forms);

        // Get available fonts
        $available_fonts = $this->get_available_fonts();
        pdf_debug('Available fonts', $available_fonts);

        // Register containers
        $this->register_fields_init();
    }

    public function register_fields_init() {
        pdf_debug('Registering Carbon Fields');

        // Ensure providers are loaded
        if (!$this->registry) {
            pdf_debug('Registry not available for settings page');
            return;
        }

        // Get all available forms from registry
        $available_forms = $this->registry->get_all_forms();
        pdf_debug('Forms for dropdown', $available_forms);

        // Get available fonts
        $available_fonts = $this->get_available_fonts();
        pdf_debug('Available fonts', $available_fonts);

        // PDF Settings Container
        Container::make('theme_options', 'PDF Einstellungen')
            ->set_page_parent('options-general.php')
            ->add_fields([
                // Form PDF Settings
                Field::make('complex', 'pdf_form_settings', 'PDF Anhang für bestimmtes Formular einrichten')
                    ->set_collapsed(true)
                    ->add_fields([
                        // Form Selection
                        Field::make('select', 'form_id', 'Formular auswählen')
                            ->set_options($available_forms)
                            ->set_required(true)
                            ->set_width(50),

                        // Basic Settings
                        Field::make('text', 'pdf_title', 'PDF Titel')
                            ->set_required(true)
                            ->set_width(50)
                            ->set_help_text('Dieser Name wird als Dateiname verwendet'),

                        // Email Recipients
                        Field::make('set', 'pdf_email_recipients', 'PDF per E-Mail senden an')
                            ->set_options([
                                'form_recipient' => 'Formular-Empfänger (Standard)',
                                'form_email' => 'E-Mail aus Formular (falls vorhanden)',
                                'custom_email' => 'Benutzerdefinierte E-Mail-Adresse'
                            ])
                            ->set_default_value(['form_recipient'])
                            ->set_help_text('Wählen Sie aus, wer die PDF per E-Mail erhalten soll'),

                        Field::make('text', 'pdf_custom_email', 'Benutzerdefinierte E-Mail-Adresse')
                            ->set_conditional_logic([
                                [
                                    'field' => 'pdf_email_recipients',
                                    'value' => ['custom_email'],
                                    'compare' => 'INCLUDES'
                                ]
                            ])
                            ->set_help_text('Mehrere Adressen mit Komma trennen'),

                        // Header & Footer
                        Field::make('rich_text', 'header', 'Kopfzeile')
                            ->set_help_text('Wird oben auf jeder Seite angezeigt')
                            ->set_width(100),

                        Field::make('rich_text', 'footer', 'Fußzeile')
                            ->set_help_text('Wird unten auf jeder Seite angezeigt')
                            ->set_width(100),

                        // Font Settings
                        Field::make('select', 'font', 'Schriftart')
                            ->set_options($available_fonts)
                            ->set_default_value('helvetica')
                            ->set_width(25),

                        Field::make('select', 'font_size', 'Schriftgröße')
                            ->set_options([
                                '8' => '8pt',
                                '9' => '9pt',
                                '10' => '10pt',
                                '11' => '11pt',
                                '12' => '12pt',
                                '14' => '14pt',
                                '16' => '16pt'
                            ])
                            ->set_default_value('11')
                            ->set_width(25),

                        Field::make('select', 'title_font_size', 'Titelgröße')
                            ->set_options([
                                '14' => '14pt',
                                '16' => '16pt',
                                '18' => '18pt',
                                '20' => '20pt',
                                '24' => '24pt',
                                '28' => '28pt',
                                '32' => '32pt'
                            ])
                            ->set_default_value('18')
                            ->set_width(25),

                        Field::make('select', 'line_spacing', 'Zeilenabstand')
                            ->set_options([
                                '1' => 'Eng (1-zeilig)',
                                '1.15' => 'Kompakt (1.15-zeilig)',
                                '1.5' => 'Normal (1.5-zeilig)',
                                '2' => 'Weit (2-zeilig)'
                            ])
                            ->set_default_value('1.5')
                            ->set_width(25),

                        // Colors
                        Field::make('color', 'background_color', 'Hintergrundfarbe PDF')
                            ->set_default_value('#FFFFFF')
                            ->set_width(33.33),

                        Field::make('color', 'header_background', 'Hintergrundfarbe Header')
                            ->set_default_value('#FFFFFF')
                            ->set_width(33.33),

                        Field::make('color', 'footer_background', 'Hintergrundfarbe Footer')
                            ->set_default_value('#FFFFFF')
                            ->set_width(33.33),

                        Field::make('checkbox', 'show_metadata', 'Metadaten im Footer anzeigen')
                            ->set_option_value('yes')
                            ->set_default_value('yes')
                            ->set_help_text('Zeigt Datum, E-Mail und URL im Footer an'),

                        // Preview Section
                        Field::make('html', 'preview_section')
                            ->set_html('<h3>PDF Vorschau</h3>')
                            ->set_width(100),

                        Field::make('text', 'pdf_preview_email', 'Test E-Mail-Adresse')
                            ->set_width(80)
                            ->set_help_text('An diese Adresse wird die Test-PDF gesendet'),

                        Field::make('html', 'preview_button')
                            ->set_width(20)
                            ->set_html('
                                <button type="button" class="button button-secondary send-pdf-preview" style="margin-top: 22px;">
                                    PDF Vorschau senden
                                </button>
                            ')
                    ])
                    ->set_header_template('
                        <% if (form_id) { %>
                            Formular: <%- form_id == "all" ? "Alle Formulare" : (form_id == "" ? "Nicht ausgewählt" : form_id) %> - <%- pdf_title %>
                        <% } else { %>
                            Neues Formular-PDF
                        <% } %>
                    '),

                // Debug Section
                Field::make('html', 'debug_section')
                    ->set_html('<h3 style="margin-top: 30px;">Debug Einstellungen</h3>'),

                Field::make('checkbox', 'pdf_debug_enabled', 'Debug-Modus aktivieren')
                    ->set_option_value('yes')
                    ->set_help_text('Aktiviert detailliertes Logging für die PDF-Generierung')
            ]);

        // Google Fonts Container
        Container::make('theme_options', 'Google Fonts')
            ->set_page_parent('options-general.php')
            ->add_fields([
                Field::make('text', 'google_font_family', 'Google Font Familie')
                    ->set_help_text('z.B. "Open Sans" oder "Roboto"'),

                Field::make('html', 'install_button')
                    ->set_html('
                        <button type="button" class="button button-primary install-google-font">
                            Schriftart installieren
                        </button>
                    ')
            ]);
    }

    /**
     * Kombiniert Standard- und Google Fonts
     */
    private function get_available_fonts(): array {
        return [
            'helvetica' => 'Helvetica',
            'arial' => 'Arial',
            'times' => 'Times New Roman',
            'courier' => 'Courier',
            'symbol' => 'Symbol',
            'zapfdingbats' => 'ZapfDingbats'
        ];
    }

    /**
     * AJAX handler for debug log refresh
     */
    public function ajax_refresh_debug_log() {
        check_ajax_referer('soulcraft_pdf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $log_content = '';

        if (file_exists($debug_log_path)) {
            // Nur die letzten 1000 Zeilen laden
            $log_content = shell_exec('tail -n 1000 ' . escapeshellarg($debug_log_path));

            // Fallback wenn shell_exec nicht verfügbar
            if ($log_content === null) {
                $log_content = file_get_contents($debug_log_path);
            }

            // Auf PDF-relevante Einträge filtern
            $log_lines = explode("\n", $log_content);
            $pdf_logs = array_filter($log_lines, function($line) {
                return strpos($line, 'PDF Debug:') !== false;
            });
            $log_content = implode("\n", $pdf_logs);
        }

        wp_send_json_success(['content' => $log_content]);
    }

    /**
     * AJAX handler for PDF preview
     */
    public function ajax_send_pdf_preview() {
        check_ajax_referer('soulcraft_pdf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $form_id = $_POST['form_id'] ?? '';
        $preview_email = $_POST['preview_email'] ?? '';

        pdf_debug('Preview requested', [
            'form_id' => $form_id,
            'email' => $preview_email
        ]);

        try {
            // Lade die Form-Einstellungen
            $settings = $this->get_form_settings($form_id);
            if (!$settings) {
                pdf_debug('No settings found for form ID', $form_id);
                wp_send_json_error('Keine Einstellungen für dieses Formular gefunden');
                return;
            }

            // Erstelle Test-Daten
            $sample_data = $this->create_sample_data($form_id, $settings);
            pdf_debug('Sample data created', $sample_data);

            // Generiere PDF
            $pdf_generator = $this->registry->get_pdf_generator();
            if (!$pdf_generator) {
                pdf_debug('PDF Generator not available');
                throw new Exception('PDF Generator nicht verfügbar');
            }

            // Stelle sicher, dass der Upload-Ordner existiert
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/soulcraft-pdf';
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }

            pdf_debug('Generating PDF');
            $pdf_path = $pdf_generator->generate($sample_data);

            if (!$pdf_path || !file_exists($pdf_path)) {
                pdf_debug('PDF file not found at path', $pdf_path);
                throw new Exception('PDF konnte nicht generiert werden');
            }

            // Sende E-Mail
            $subject = 'PDF Vorschau: ' . $settings['pdf_title'];
            $message = 'Anbei finden Sie die PDF-Vorschau für das Formular.';
            $headers = array('Content-Type: text/html; charset=UTF-8');

            $mail_sent = wp_mail($preview_email, $subject, $message, $headers, [$pdf_path]);

            if (!$mail_sent) {
                pdf_debug('Email sending failed');
                throw new Exception('E-Mail konnte nicht gesendet werden');
            }

            pdf_debug('Preview email sent successfully');

            // Lösche temporäre PDF
            @unlink($pdf_path);

            wp_send_json_success('PDF wurde erfolgreich gesendet');

        } catch (Exception $e) {
            pdf_debug('Error in preview process', $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler for Google Font installation
     */
    public function ajax_install_google_font() {
        check_ajax_referer('soulcraft_pdf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $font_family = sanitize_text_field($_POST['font_family'] ?? '');
        if (empty($font_family)) {
            wp_send_json_error('Keine Schriftart angegeben');
            return;
        }

        pdf_debug('Installing Google Font', $font_family);

        try {
            $result = $this->font_manager->install_google_font($font_family);

            if ($result) {
                pdf_debug('Font installed successfully');
                wp_send_json_success([
                    'message' => sprintf('Schriftart "%s" wurde erfolgreich installiert', $font_family),
                    'fonts' => $this->get_available_fonts()
                ]);
            } else {
                throw new Exception('Fehler beim Installieren der Schriftart');
            }
        } catch (Exception $e) {
            pdf_debug('Font installation error', $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Creates sample data for PDF preview
     */
    private function create_sample_data($form_id, $settings): array {
        // Provider-Typ aus der Form-ID extrahieren
        $provider_type = explode('_', $form_id)[0] ?? '';

        // Basis-Testdaten
        return [
            'form_id' => $form_id,
            'title' => $settings['pdf_title'] ?? 'PDF Vorschau',
            'fields' => [
                [
                    'label' => 'Name',
                    'value' => 'Max Mustermann',
                    'type' => 'text'
                ],
                [
                    'label' => 'E-Mail',
                    'value' => 'max.mustermann@example.com',
                    'type' => 'email'
                ],
                [
                    'label' => 'Telefon',
                    'value' => '+49 123 456789',
                    'type' => 'tel'
                ],
                [
                    'label' => 'Betreff',
                    'value' => 'Test-Betreff für PDF-Vorschau',
                    'type' => 'text'
                ],
                [
                    'label' => 'Nachricht',
                    'value' => "Dies ist eine Test-Nachricht für die PDF-Vorschau.\n\nSie dient dazu, das Layout und die Formatierung der generierten PDF zu überprüfen.",
                    'type' => 'textarea'
                ],
                [
                    'label' => 'Datum',
                    'value' => date_i18n('d.m.Y H:i'),
                    'type' => 'date'
                ]
            ],
            'metadata' => [
                'is_preview' => true,
                'timestamp' => current_time('mysql'),
                'site_url' => get_site_url(),
                'form_type' => $provider_type
            ],
            'settings' => $settings
        ];
    }

    /**
     * Gets form settings for a specific form
     */
    private function get_form_settings($form_id): ?array {
        $all_settings = carbon_get_theme_option('pdf_form_settings');
        if (!is_array($all_settings)) {
            return null;
        }

        // Erst nach exaktem Match suchen
        foreach ($all_settings as $settings) {
            if ($settings['form_id'] === $form_id) {
                return $settings;
            }
        }

        // Wenn keine spezifischen Einstellungen gefunden wurden
        foreach ($all_settings as $settings) {
            if ($settings['form_id'] === 'all') {
                return $settings;
            }
        }

        return null;
    }
}