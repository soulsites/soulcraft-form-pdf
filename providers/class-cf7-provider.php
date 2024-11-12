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
                'post_type' => 'wpcf7_contact_form',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ];

            $cf7_forms = get_posts($args);
            $this->log('Found forms', count($cf7_forms));

            foreach ($cf7_forms as $form) {
                $form_id = $this->id_prefix . $form->ID;
                $forms[$form_id] = '[CF7] ' . $form->post_title;
                $this->log('Added form', [
                    'id' => $form_id,
                    'title' => $form->post_title
                ]);
            }
        } catch (Exception $e) {
            $this->log('Error fetching forms', $e->getMessage());
        }

        return $forms;
    }

    public function handle_form_submission($cf7) {
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
                'data' => $posted_data
            ]);

            $result = parent::handle_submission($pdf_data);
            $this->log('PDF generation result', $result ? 'success' : 'failed');

        } catch (Exception $e) {
            $this->log('Error processing submission', $e->getMessage());
        }
    }

    protected function prepare_submission_data($raw_data): array {
        $this->log('Preparing submission data');

        $cf7 = $raw_data['form'];
        $posted_data = $raw_data['data'];
        $form_tags = $cf7->scan_form_tags();

        $fields = [];
        foreach ($form_tags as $tag) {
            // Überspringe Submit-Buttons und versteckte Felder
            if (in_array($tag['type'], ['submit', 'hidden'])) {
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
                'type' => $field_type
            ];
        }

        return [
            'form_id' => $this->id_prefix . $cf7->id(),
            'title' => get_the_title($cf7->id()),
            'fields' => $fields,
            'metadata' => [
                'timestamp' => current_time('mysql'),
                'form_type' => 'contact-form-7',
                'site_url' => get_site_url()
            ]
        ];
    }
}