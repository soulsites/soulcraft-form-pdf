<?php
/**
 * Ninja Forms Provider
 */
class Ninja_Provider extends PDF_Base {
    private const CACHE_KEY = 'ninja_pdf_forms_cache';
    private const CACHE_EXPIRY = 3600; // 1 hour

    public function __construct($pdf_generator) {
        $this->provider_name = 'Ninja Forms';
        $this->id_prefix = 'ninja-forms_';

        parent::__construct($pdf_generator);
    }

    protected function init(): void {
        $this->log('Initializing provider');

        // Hook für PDF Generierung
        add_action('ninja_forms_after_submission', [$this, 'handle_form_submission']);

        // Cache leeren wenn Formular gespeichert wird
        add_action('ninja_forms_save_form', [$this, 'clear_cache']);
    }

    public function is_active(): bool {
        $is_active = function_exists('Ninja_Forms');
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

            $all_forms = Ninja_Forms()->form()->get_forms();
            $this->log('Found forms', count($all_forms));

            foreach ($all_forms as $form) {
                $id = $form->get_id();
                $title = $form->get_setting('title');

                if ($id && $title) {
                    $form_id = $this->id_prefix . $id;
                    $forms[$form_id] = '[Ninja] ' . $title;

                    $this->log('Added form', [
                        'id' => $form_id,
                        'title' => $title
                    ]);
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

    public function handle_form_submission($form_data) {
        $this->log('Processing form submission');

        try {
            if (empty($form_data) || !isset($form_data['form_id'])) {
                $this->log('Invalid form data or missing ID');
                return;
            }

            $form_id = $this->id_prefix . $form_data['form_id'];
            $this->log('Processing submission for form', $form_id);

            $pdf_data = $this->prepare_submission_data($form_data);
            $this->log('PDF data prepared', $pdf_data);

            $pdf_path = $this->pdf_generator->generate($pdf_data);
            if (!$pdf_path) {
                $this->log('PDF generation failed');
                return;
            }

            $this->log('PDF generated at: ' . $pdf_path);

            // E-Mail versenden
            $this->send_pdf_emails($pdf_path, $form_data, $pdf_data);

            return $pdf_path;

        } catch (Exception $e) {
            $this->log('Error processing submission', $e->getMessage());
            return false;
        }
    }

    protected function prepare_submission_data($form_data): array {
        $this->log('Preparing submission data');
        $fields = [];

        foreach ($form_data['fields'] as $field) {
            // Skip empty or submit fields
            if ($field['settings']['type'] === 'submit' || empty($field['value'])) {
                continue;
            }

            $field_value = $field['value'];

            // Handle array values (e.g., checkboxes)
            if (is_array($field_value)) {
                $field_value = implode(', ', array_filter($field_value, 'is_scalar'));
            }

            $fields[] = [
                'label' => $field['settings']['label'],
                'value' => $field_value,
                'type' => $field['settings']['type']
            ];
        }

        // Get PDF settings for this form
        $form_id = $this->id_prefix . $form_data['form_id'];
        $pdf_settings = $this->get_form_settings($form_id);

        if (!$pdf_settings) {
            $pdf_settings = $this->get_form_settings('all');  // Fallback auf "alle Formulare"
        }

        if (!$pdf_settings) {
            $pdf_settings = [  // Default settings falls keine gefunden
                'pdf_title' => $this->get_form_title($form_data['form_id']),
                'font' => 'helvetica',
                'font_size' => '10',
                'title_font_size' => '16',
                'line_spacing' => '1.5',
                'show_metadata' => true,
                'background_color' => '#FFFFFF',
                'header_background' => '#FFFFFF',
                'footer_background' => '#FFFFFF'
            ];
        }

        return [
            'form_id' => $form_id,
            'title' => $pdf_settings['pdf_title'] ?? $this->get_form_title($form_data['form_id']),
            'fields' => $fields,
            'settings' => $pdf_settings,  // Wichtig: PDF Settings mit übergeben
            'metadata' => [
                'timestamp' => current_time('mysql'),
                'form_type' => 'ninja-forms',
                'site_url' => get_site_url()
            ]
        ];
    }

    private function get_form_title($form_id): string {
        try {
            $form = Ninja_Forms()->form($form_id)->get();
            return $form->get_setting('title') ?? 'Ninja Form Submission';
        } catch (Exception $e) {
            $this->log('Error getting form title', $e->getMessage());
            return 'Ninja Form Submission';
        }
    }

    private function handle_email_recipients($pdf_path, $form_data, $pdf_data): void {
        $this->log('Handling email recipients');

        try {
            $settings = $this->get_form_settings($pdf_data['form_id']);
            if (!$settings) {
                $this->log('No PDF settings found for form');
                return;
            }

            $recipients = $this->get_recipients($form_data, $settings);
            if (empty($recipients)) {
                $this->log('No recipients found');
                return;
            }

            $this->send_pdf_emails($recipients, $pdf_path, $settings, $pdf_data);

        } catch (Exception $e) {
            $this->log('Error handling recipients', $e->getMessage());
        }
    }

    private function get_recipients($form_data, $settings): array {
        $recipients = [];

        // Form recipient (from form settings)
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_recipient', $settings['pdf_email_recipients'])) {
            if (!empty($form_data['actions']['email']['to'])) {
                $recipients[] = $form_data['actions']['email']['to'];
            }
        }

        // Email from form fields
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_email', $settings['pdf_email_recipients'])) {
            foreach ($form_data['fields'] as $field) {
                if ($field['settings']['type'] === 'email' && !empty($field['value'])) {
                    $recipients[] = $field['value'];
                    break;
                }
            }
        }

        // Custom email addresses
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('custom_email', $settings['pdf_email_recipients']) &&
            !empty($settings['pdf_custom_email'])) {
            $custom_emails = array_map('trim', explode(',', $settings['pdf_custom_email']));
            $recipients = array_merge($recipients, $custom_emails);
        }

        return array_unique(array_filter($recipients));
    }

    private function send_pdf_emails($pdf_path, $form_data, $pdf_data) {
        if (!file_exists($pdf_path)) {
            $this->log('PDF file not found for email sending');
            return;
        }

        // Get form settings
        $settings = $pdf_data['settings'] ?? null;
        if (!$settings) {
            $this->log('No settings found for email sending');
            return;
        }

        $this->log('PDF Email Settings', $settings);  // Debug-Log für Settings

        // Get recipients
        $recipients = [];

        // 1. Form recipient if enabled
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_recipient', $settings['pdf_email_recipients'])) {
            $this->log('Checking form recipient');
            try {
                $form = Ninja_Forms()->form($form_data['form_id'])->get();
                $admin_email = $form->get_setting('admin_email');
                $this->log('Admin email from form settings', $admin_email);
                if ($admin_email) {
                    $recipients[] = $admin_email;
                }

                // Alternative: Get email actions from form
                $actions = Ninja_Forms()->form($form_data['form_id'])->get_actions();
                foreach ($actions as $action) {
                    if ($action->get_setting('type') === 'email') {
                        $action_settings = $action->get_settings();
                        if (!empty($action_settings['to'])) {
                            $recipients[] = $action_settings['to'];
                            $this->log('Added email from action', $action_settings['to']);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->log('Error getting form settings', $e->getMessage());
            }
        } else {
            $this->log('Form recipient not enabled in settings');
        }

        // 2. Email from form field if enabled
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_email', $settings['pdf_email_recipients'])) {
            $this->log('Checking form fields for email');
            foreach ($form_data['fields'] as $field) {
                $this->log('Checking field', [
                    'type' => $field['settings']['type'],
                    'value' => $field['value'] ?? 'no value'
                ]);
                if ($field['settings']['type'] === 'email' && !empty($field['value'])) {
                    $recipients[] = $field['value'];
                    $this->log('Added email from form field', $field['value']);
                    break;
                }
            }
        } else {
            $this->log('Form email not enabled in settings');
        }

        // 3. Custom email if enabled
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('custom_email', $settings['pdf_email_recipients']) &&
            !empty($settings['pdf_custom_email'])) {
            $this->log('Adding custom emails', $settings['pdf_custom_email']);
            $custom_emails = array_map('trim', explode(',', $settings['pdf_custom_email']));
            $recipients = array_merge($recipients, $custom_emails);
        } else {
            $this->log('Custom email not enabled or empty');
        }

        // Remove duplicates and empty values
        $recipients = array_unique(array_filter($recipients));

        $this->log('Final recipients list', $recipients);

        if (empty($recipients)) {
            $this->log('No recipients found for PDF email');
            return;
        }

        // Build email
        $subject = sprintf('PDF Formular: %s', $settings['pdf_title'] ?? 'Formulareinreichung');

        // Create email body
        $message = '<h2>' . esc_html($settings['pdf_title'] ?? 'Formulareinreichung') . "</h2>\n\n";
        $message .= "<p>Anbei finden Sie die PDF-Version des ausgefüllten Formulars.</p>\n\n";

        // Add form data to email
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

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Send to each recipient
        foreach ($recipients as $to) {
            $mail_sent = wp_mail($to, $subject, $message, $headers, [$pdf_path]);
            $this->log('Mail sent to ' . $to, $mail_sent ? 'success' : 'failed');
        }
    }

    public function clear_cache(): void {
        parent::clear_cache();
        delete_transient(self::CACHE_KEY);
        $this->log('Cache cleared');
    }
}