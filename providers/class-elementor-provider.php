<?php
/**
 * Elementor Forms Provider
 */
class Elementor_Provider extends PDF_Base {
    private const CACHE_KEY = 'elementor_pdf_forms_cache';
    private const CACHE_EXPIRY = 3600; // 1 hour

    public function __construct($pdf_generator) {
        $this->provider_name = 'Elementor Forms';
        $this->id_prefix = 'elementor-forms_';

        parent::__construct($pdf_generator);
    }

    protected function init(): void {
        $this->log('Initializing provider');

        // Form Submission Hooks - verschiedene Varianten für verschiedene Elementor Versionen
        add_action('elementor/forms/form_submitted', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor_pro/forms/form_submitted', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor-pro/forms/form_submitted', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor/forms/new_record', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor_pro/forms/new_record', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor-pro/forms/new_record', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor-pro/forms/submit', [$this, 'handle_form_submission'], 10, 2);
        add_action('elementor_pro/forms/submit', [$this, 'handle_form_submission'], 10, 2);

        // Zusätzliche Debug-Hooks
        add_action('elementor/forms/pre_process', function($form_record, $ajax_handler) {
            $this->log('Form pre_process triggered', [
                'form_id' => $form_record->get_form_settings('id'),
                'has_handler' => $ajax_handler ? 'yes' : 'no'
            ]);
        }, 10, 2);

        add_action('elementor/forms/process', function($form_record, $ajax_handler) {
            $this->log('Form process triggered', [
                'form_id' => $form_record->get_form_settings('id'),
                'has_handler' => $ajax_handler ? 'yes' : 'no'
            ]);
        }, 10, 2);

        add_action('elementor_pro/forms/validation', function($record, $ajax_handler) {
            $this->log('Form validation triggered', [
                'form_id' => $record->get_form_settings('id'),
                'has_handler' => $ajax_handler ? 'yes' : 'no'
            ]);
        }, 10, 2);

        // Cache leeren wenn Elementor aktualisiert wird
        add_action('elementor/editor/after_save', [$this, 'clear_cache']);
        add_action('elementor/update_screenshots/after_save', [$this, 'clear_cache']);

        $this->log('Provider initialized with hooks');
    }

    public function is_active(): bool {
        $is_active = defined('ELEMENTOR_PRO_VERSION');
        $this->log('Checking if active', $is_active ? 'yes' : 'no');
        return $is_active;
    }

    protected function fetch_forms(): array {
        $this->log('Fetching forms');
        $forms = [];

        try {
            // Prüfe transient cache
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false && !WP_DEBUG) {
                $this->log('Returning from transient cache');
                return $cached;
            }

            global $wpdb;

            // Hole alle Posts mit Elementor form widgets
            $results = $wpdb->get_results(
                "SELECT DISTINCT p.ID, p.post_title, m.meta_value 
                FROM {$wpdb->postmeta} m 
                JOIN {$wpdb->posts} p ON p.ID = m.post_id 
                WHERE m.meta_key = '_elementor_data' 
                AND p.post_status = 'publish'"
            );

            $this->log('Found posts with Elementor data', count($results));

            foreach ($results as $result) {
                $elementor_data = json_decode($result->meta_value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->extract_forms($elementor_data, $forms, $result);
                } else {
                    $this->log('JSON decode error for post', $result->ID);
                }
            }

            if (!empty($forms)) {
                set_transient(self::CACHE_KEY, $forms, self::CACHE_EXPIRY);
                $this->log('Forms cached in transient');
            }

        } catch (Exception $e) {
            $this->log('Error fetching forms', $e->getMessage());
        }

        return $forms;
    }

    private function extract_forms($elements, &$forms, $post) {
        foreach ($elements as $element) {
            if (!empty($element['widgetType']) && $element['widgetType'] === 'form') {
                $form_id = $element['id'];
                $form_name = !empty($element['settings']['form_name'])
                    ? $element['settings']['form_name']
                    : sprintf('Form auf %s', $post->post_title);

                $unique_id = $this->id_prefix . $form_id;
                $forms[$unique_id] = '[Elementor] ' . $form_name;

                $this->log('Found form', [
                    'id' => $unique_id,
                    'name' => $form_name,
                    'post' => $post->post_title
                ]);
            }

            if (!empty($element['elements'])) {
                $this->extract_forms($element['elements'], $forms, $post);
            }
        }
    }

    public function handle_form_submission($record, $ajax_handler) {
        $this->log('Form submission started', [
            'record_type' => get_class($record),
            'has_ajax_handler' => isset($ajax_handler) ? 'yes' : 'no'
        ]);

        try {
            if (!$record) {
                $this->log('Invalid form record');
                return;
            }

            $form_settings = $record->get('form_settings');
            if (!$form_settings) {
                $this->log('No form settings found in record');
                return;
            }

            $form_id = $this->id_prefix . $form_settings['id'];
            $this->log('Processing form submission', [
                'form_id' => $form_id,
                'form_settings' => $form_settings
            ]);

            // Get form fields
            $raw_fields = $record->get('fields');
            $this->log('Form fields received', $raw_fields);

            // Prepare PDF data
            $pdf_data = $this->prepare_submission_data([
                'record' => $record,
                'form_settings' => $form_settings
            ]);

            $this->log('PDF data prepared', $pdf_data);

            // Get PDF settings
            $settings = $this->get_form_settings($form_id);
            if (!$settings) {
                $this->log('No PDF settings found for form', [
                    'form_id' => $form_id,
                    'all_settings' => carbon_get_theme_option('pdf_form_settings')
                ]);
                return;
            }
            $this->log('PDF settings found', $settings);

            // Generate PDF
            try {
                $this->log('Starting PDF generation');
                $pdf_path = $this->pdf_generator->generate($pdf_data);

                if (!$pdf_path || !file_exists($pdf_path)) {
                    $this->log('PDF generation failed - no file created');
                    if ($ajax_handler) {
                        $ajax_handler->add_error_message('PDF konnte nicht erstellt werden.');
                    }
                    return;
                }

                $this->log('PDF generated successfully at', $pdf_path);

                // Send emails
                $recipients = $this->get_email_recipients($record, $settings);
                $this->log('Found recipients', $recipients);

                if (empty($recipients)) {
                    $this->log('No recipients found for PDF delivery');
                    return;
                }

                // Email sending
                $subject = sprintf('PDF Formular: %s', $settings['pdf_title']);
                $message = $this->build_email_message($pdf_data);
                $headers = ['Content-Type: text/html; charset=UTF-8'];

                foreach ($recipients as $to) {
                    $this->log('Sending email to', $to);
                    $mail_sent = wp_mail($to, $subject, $message, $headers, [$pdf_path]);
                    $this->log('Mail sending result', $mail_sent ? 'success' : 'failed');
                }

                $this->log('Form submission process completed successfully');

            } catch (Exception $e) {
                $this->log('Error during PDF processing', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                if ($ajax_handler) {
                    $ajax_handler->add_error_message('PDF-Verarbeitung fehlgeschlagen: ' . $e->getMessage());
                }
            }

        } catch (Exception $e) {
            $this->log('Error in form submission handler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            if ($ajax_handler) {
                $ajax_handler->add_error_message('Ein Fehler ist aufgetreten: ' . $e->getMessage());
            }
        }
    }

    protected function prepare_submission_data($raw_data): array {
        $record = $raw_data['record'];
        $form_settings = $raw_data['form_settings'];
        $fields = [];
        $raw_fields = $record->get('fields');

        foreach ($raw_fields as $id => $field) {
            if (empty($field['value'])) {
                continue;
            }

            $value = $field['value'];
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }

            $fields[] = [
                'label' => $field['title'] ?? $id,
                'value' => $value,
                'type' => $field['type'] ?? 'text'
            ];
        }

        // Hole die PDF-Einstellungen für dieses Formular
        $form_id = $this->id_prefix . $form_settings['id'];
        $pdf_settings = $this->get_form_settings($form_id);

        $this->log('Using PDF settings', $pdf_settings);

        return [
            'form_id' => $form_id,
            'title' => $pdf_settings['pdf_title'] ?? $form_settings['form_name'] ?? 'Formular-Einreichung',
            'fields' => $fields,
            'settings' => $pdf_settings, // Hier die kompletten Einstellungen übergeben
            'metadata' => [
                'timestamp' => current_time('mysql'),
                'form_type' => 'elementor-forms',
                'site_url' => get_site_url()
            ]
        ];
    }

    private function handle_email_sending($pdf_path, $record, $settings, $pdf_data) {
        $this->log('Handling email sending');

        try {
            $recipients = $this->get_email_recipients($record, $settings);
            if (empty($recipients)) {
                $this->log('No recipients found');
                return;
            }

            $subject = sprintf('PDF Formular: %s', $settings['pdf_title']);
            $message = $this->build_email_message($pdf_data);
            $headers = ['Content-Type: text/html; charset=UTF-8'];

            foreach ($recipients as $to) {
                $this->log('Sending email to', $to);
                $mail_sent = wp_mail($to, $subject, $message, $headers, [$pdf_path]);
                $this->log('Mail sent status', $mail_sent ? 'success' : 'failed');
            }

        } catch (Exception $e) {
            $this->log('Error sending emails', $e->getMessage());
            throw $e;
        }
    }

    private function get_email_recipients($record, $settings): array {
        $recipients = [];

        // Form recipient
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_recipient', $settings['pdf_email_recipients'])) {
            $form_settings = $record->get('form_settings');
            if (!empty($form_settings['email_to'])) {
                $recipients[] = $form_settings['email_to'];
            }
        }

        // Email from form
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_email', $settings['pdf_email_recipients'])) {
            $fields = $record->get('fields');
            foreach ($fields as $field) {
                if ($field['type'] === 'email' && !empty($field['value'])) {
                    $recipients[] = $field['value'];
                    break;
                }
            }
        }

        // Custom email
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('custom_email', $settings['pdf_email_recipients']) &&
            !empty($settings['pdf_custom_email'])) {
            $custom_emails = array_map('trim', explode(',', $settings['pdf_custom_email']));
            $recipients = array_merge($recipients, $custom_emails);
        }

        return array_unique(array_filter($recipients));
    }

    private function build_email_message($pdf_data): string {
        $message = '<h2>' . esc_html($pdf_data['title']) . "</h2>\n\n";
        $message .= "<p>Anbei finden Sie die PDF-Version des ausgefüllten Formulars.</p>\n\n";
        $message .= "<h3>Eingegebene Daten:</h3>\n<ul>\n";

        foreach ($pdf_data['fields'] as $field) {
            if (!empty($field['value'])) {
                $message .= sprintf(
                    "<li><strong>%s:</strong> %s</li>\n",
                    esc_html($field['label']),
                    esc_html($field['value'])
                );
            }
        }

        $message .= "</ul>\n";
        return $message;
    }

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

        // Dann nach "all" suchen
        foreach ($all_settings as $settings) {
            if ($settings['form_id'] === 'all') {
                return $settings;
            }
        }

        return null;
    }
}

