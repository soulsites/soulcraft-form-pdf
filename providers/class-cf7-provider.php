<?php
/**
 * Contact Form 7 Provider
 */
class CF7_Provider extends PDF_Base {
    public function __construct($pdf_generator) {
        $this->provider_name = 'Contact Form 7';
        $this->id_prefix = 'contact-form-7_';

        parent::__construct($pdf_generator);
    }

    protected function init(): void {
        $this->log('Initializing provider');
        add_action('wpcf7_mail_sent', [$this, 'handle_form_submission']);
    }

    public function is_active(): bool {
        $is_active = defined('WPCF7_VERSION');
        $this->log('Checking if active', $is_active ? 'yes' : 'no');
        return $is_active;
    }

    protected function fetch_forms(): array {
        $this->log('Fetching forms');
        $forms = [];

        try {
            $args = [
                'post_type'      => 'wpcf7_contact_form',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ];

            $cf7_forms = get_posts($args);
            $this->log('Found forms', count($cf7_forms));

            foreach ($cf7_forms as $form) {
                $form_id = $this->id_prefix . $form->ID;
                $forms[$form_id] = '[CF7] ' . $form->post_title;
                $this->log('Added form', [
                    'id'    => $form_id,
                    'title' => $form->post_title,
                ]);
            }
        } catch (Exception $e) {
            $this->log('Error fetching forms', $e->getMessage());
        }

        return $forms;
    }

    public function handle_form_submission($cf7): void {
        $this->log('Processing form submission', $cf7->id());

        try {
            $submission = WPCF7_Submission::get_instance();

            if (!$submission) {
                $this->log('No submission instance found');
                return;
            }

            $posted_data = $submission->get_posted_data();
            if (empty($posted_data)) {
                $this->log('No posted data found');
                return;
            }

            $pdf_data = $this->prepare_submission_data([
                'form' => $cf7,
                'data' => $posted_data,
            ]);

            $pdf_path = $this->pdf_generator->generate($pdf_data);

            if (!$pdf_path || !file_exists($pdf_path)) {
                $this->log('PDF generation failed');
                return;
            }

            $this->log('PDF generated at: ' . $pdf_path);
            $this->send_pdf_emails($pdf_path, $pdf_data);

        } catch (Exception $e) {
            $this->log('Error processing submission', $e->getMessage());
        }
    }

    protected function prepare_submission_data($raw_data): array {
        $this->log('Preparing submission data');

        $cf7         = $raw_data['form'];
        $posted_data = $raw_data['data'];
        $form_tags   = $cf7->scan_form_tags();

        $fields = [];
        foreach ($form_tags as $tag) {
            // Überspringe Submit-Buttons und versteckte Felder
            if (in_array($tag['type'], ['submit', 'hidden'], true)) {
                continue;
            }

            $field_name = $tag['name'];
            if (empty($field_name) || !isset($posted_data[$field_name])) {
                continue;
            }

            $field_value = $posted_data[$field_name];

            // Behandle Arrays (z.B. Checkboxen, Multi-Select)
            if (is_array($field_value)) {
                $field_value = implode(', ', array_filter($field_value, 'is_scalar'));
            }

            // Bestimme den Feldtyp
            $field_type = 'text';
            if (strpos($tag['type'], 'email') !== false) {
                $field_type = 'email';
            } elseif (strpos($tag['type'], 'textarea') !== false) {
                $field_type = 'textarea';
            }

            $fields[] = [
                'label' => $tag['name'],
                'value' => $field_value,
                'type'  => $field_type,
            ];
        }

        $form_id     = $this->id_prefix . $cf7->id();
        $pdf_settings = $this->get_form_settings($form_id);

        return [
            'form_id'  => $form_id,
            'title'    => $pdf_settings['pdf_title'] ?? get_the_title($cf7->id()),
            'fields'   => $fields,
            'settings' => $pdf_settings ?? [],
            'metadata' => [
                'timestamp' => current_time('mysql'),
                'form_type' => 'contact-form-7',
                'site_url'  => get_site_url(),
            ],
        ];
    }

    private function send_pdf_emails(string $pdf_path, array $pdf_data): void {
        $settings = $pdf_data['settings'] ?? null;
        if (!$settings) {
            $this->log('No settings found for email sending');
            return;
        }

        $recipients = [];

        // Standard-Admin-E-Mail wenn "form_recipient" gewählt
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_recipient', $settings['pdf_email_recipients'], true)) {
            $recipients[] = get_option('admin_email');
        }

        // E-Mail aus Formularfeld
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('form_email', $settings['pdf_email_recipients'], true)) {
            foreach ($pdf_data['fields'] as $field) {
                if ($field['type'] === 'email' && !empty($field['value'])) {
                    $recipients[] = $field['value'];
                    break;
                }
            }
        }

        // Benutzerdefinierte E-Mail-Adressen
        if (!empty($settings['pdf_email_recipients']) &&
            in_array('custom_email', $settings['pdf_email_recipients'], true) &&
            !empty($settings['pdf_custom_email'])) {
            $custom_emails = array_map('trim', explode(',', $settings['pdf_custom_email']));
            $recipients    = array_merge($recipients, $custom_emails);
        }

        $recipients = array_unique(array_filter($recipients));

        $this->log('Final recipients list', $recipients);

        if (empty($recipients)) {
            $this->log('No recipients found for PDF email');
            return;
        }

        $subject = sprintf('PDF Formular: %s', $settings['pdf_title'] ?? 'Formulareinreichung');
        $message = '<h2>' . esc_html($settings['pdf_title'] ?? 'Formulareinreichung') . "</h2>\n\n";
        $message .= "<p>Anbei finden Sie die PDF-Version des ausgefüllten Formulars.</p>\n\n";
        $message .= "<h3>Eingegebene Daten:</h3>\n<ul>\n";

        foreach ($pdf_data['fields'] as $field) {
            if (!empty($field['value'])) {
                $message .= sprintf(
                    "<li><strong>%s:</strong> %s</li>\n",
                    esc_html($field['label']),
                    esc_html(is_array($field['value']) ? implode(', ', $field['value']) : $field['value'])
                );
            }
        }

        $message .= "</ul>\n";
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        foreach ($recipients as $to) {
            $mail_sent = wp_mail($to, $subject, $message, $headers, [$pdf_path]);
            $this->log('Mail sent to ' . $to, $mail_sent ? 'success' : 'failed');
        }
    }
}
