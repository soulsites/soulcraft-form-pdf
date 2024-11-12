<?php
/**
 * PDF Generator Class
 * Handles PDF generation with FPDF
 */

class PDF_Generator extends FPDF {
    private $current_settings;
    private $font_manager;
    private $last_pdf_path;

    const MARGIN_LEFT = 15;
    const MARGIN_RIGHT = 15;
    const MARGIN_TOP = 35;
    const MARGIN_BOTTOM = 35;
    const CONTENT_PADDING = 10;

    public function __construct() {
        parent::__construct();

        $this->SetMargins(self::MARGIN_LEFT, self::MARGIN_TOP, self::MARGIN_RIGHT);
        $this->SetAutoPageBreak(true, self::MARGIN_BOTTOM);

        pdf_debug('PDF Generator initialized');
    }

    public function generate($form_data) {
        try {
            pdf_debug('Starting PDF generation', $form_data);

            if (!$this->validate_form_data($form_data)) {
                throw new Exception('Invalid form data structure');
            }

            // Load settings
            $this->load_settings($form_data);

            // Reset PDF object state
            $this->SetAutoPageBreak(true, self::MARGIN_BOTTOM);
            $this->SetMargins(self::MARGIN_LEFT, self::MARGIN_TOP, self::MARGIN_RIGHT);
            $this->SetCompression(true);

            // Generate PDF content
            $this->AddPage();
            $this->generate_content($form_data);

            // Save PDF
            $pdf_path = $this->save_pdf();
            pdf_debug('Generated PDF at path', $pdf_path);

            return $pdf_path;

        } catch (Exception $e) {
            pdf_debug('PDF generation error', $e->getMessage());
            return false;
        }
    }

    private function validate_form_data($form_data): bool {
        $required_keys = ['form_id', 'title', 'fields'];

        foreach ($required_keys as $key) {
            if (!isset($form_data[$key])) {
                pdf_debug('Missing required form data key', $key);
                return false;
            }
        }

        if (!is_array($form_data['fields'])) {
            pdf_debug('Fields must be an array');
            return false;
        }

        return true;
    }

    private function load_settings($form_data) {
        pdf_debug('Loading settings for form', $form_data['form_id']);

        // Default settings
        $this->current_settings = [
            'pdf_title' => 'Formular-Einreichung',
            'font' => 'helvetica',
            'font_size' => '11',
            'title_font_size' => '18',
            'line_spacing' => '1.5',
            'show_metadata' => 'yes',
            'background_color' => '#FFFFFF',
            'header_background' => '#FFFFFF',
            'footer_background' => '#FFFFFF',
            'header' => '',
            'footer' => ''
        ];

        // Load settings from form data if provided
        if (!empty($form_data['settings'])) {
            $this->current_settings = array_merge(
                $this->current_settings,
                $form_data['settings']
            );
        }

        pdf_debug('Loaded settings', $this->current_settings);
    }

    protected function normalize_text($text) {
        try {
            if (mb_detect_encoding($text, 'UTF-8', true)) {
                return iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
            }
            return $text;
        } catch (Exception $e) {
            pdf_debug('Text normalization error', $e->getMessage());
            return $text;
        }
    }

    public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        parent::Cell($w, $h, $this->normalize_text($txt), $border, $ln, $align, $fill, $link);
    }

    public function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false) {
        parent::MultiCell($w, $h, $this->normalize_text($txt), $border, $align, $fill);
    }

    public function Write($h, $txt, $link='') {
        parent::Write($h, $this->normalize_text($txt), $link);
    }

    public function SetFont($family, $style='', $size=0) {
        pdf_debug('Setting font', [
            'family' => $family,
            'style' => $style,
            'size' => $size
        ]);
        parent::SetFont($family, $style, $size);
    }

    private function generate_content($form_data) {
        // Set background color
        $this->set_background_color($this->current_settings['background_color']);

        // Title
        $title = $form_data['settings']['pdf_title'] ?? $form_data['title'] ?? 'Formular-Einreichung';

        $this->Ln(20);
        // Direkt Font setzen ohne TTF-Check
        $this->SetFont(
            $this->current_settings['font'],
            'B',
            $this->current_settings['title_font_size']
        );
        $this->Cell(0, 10, $title, 0, 1, 'L');
        $this->Ln(15);

        // Generate fields
        if (!empty($form_data['fields'])) {
            $this->generate_fields($form_data['fields']);
        }

        // Add metadata if enabled
        if (!empty($this->current_settings['show_metadata'])) {
            $this->add_metadata($form_data);
        }
    }

    private function generate_fields($fields) {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont($this->current_settings['font'], '', $this->current_settings['font_size']);
        $line_spacing = floatval($this->current_settings['line_spacing']);

        $label_width = 60;
        $content_width = $this->GetPageWidth() - $this->lMargin - $this->rMargin - $label_width;

        foreach ($fields as $field) {
            if (empty($field['value'])) continue;

            $value = is_array($field['value']) ? implode(', ', $field['value']) : $field['value'];

            // Position für diese Zeile merken
            $start_y = $this->GetY();

            // Label in fett
            $this->SetFont($this->current_settings['font'], 'B', $this->current_settings['font_size']);
            $this->Cell($label_width, 6, $field['label'] . ':', 0, 0); // Höhe auf 6 reduziert

            // Value normal
            $this->SetFont($this->current_settings['font'], '', $this->current_settings['font_size']);
            $this->SetX($this->lMargin + $label_width);

            // MultiCell für den Wert
            $this->MultiCell(
                $content_width,
                6,  // Höhe auf 6 reduziert
                $value,
                0,
                'L'
            );

            // Berechne die tatsächliche Höhe des Texts
            $lines = $this->getNumLines($value, $content_width);
            $actual_height = max(1, $lines) * 6 * $line_spacing;

            // Setze Y-Position für nächstes Feld
            $this->SetY($start_y + $actual_height);

            // Füge zusätzlichen Abstand zwischen den Feldern hinzu
            $this->Ln(2);
        }
    }

    public function Header() {
        if (!empty($this->current_settings['header'])) {
            pdf_debug('Rendering header', $this->current_settings['header']); // Geändert von log() zu pdf_debug()
            $currentY = $this->GetY();

            // Header-Bereich
            $this->SetY(0);
            $this->set_background_color($this->current_settings['header_background'] ?? '#FFFFFF');
            $this->Rect(0, 0, $this->GetPageWidth(), self::MARGIN_TOP, 'F');

            // Header-Inhalt
            $this->SetY(self::CONTENT_PADDING);
            $this->SetX(self::MARGIN_LEFT);
            $this->SetFont($this->current_settings['font'], '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->write_html($this->current_settings['header']);

            // Position wiederherstellen
            $this->SetY(max($currentY, self::MARGIN_TOP));
            $this->set_background_color($this->current_settings['background_color'] ?? '#FFFFFF');
        }
    }

    public function Footer() {
        if (!empty($this->current_settings['footer'])) {
            pdf_debug('Rendering footer', $this->current_settings['footer']);
            $footerTop = $this->GetPageHeight() - self::MARGIN_BOTTOM;

            // Footer-Bereich
            $this->set_background_color($this->current_settings['footer_background'] ?? '#FFFFFF');
            $this->Rect(0, $footerTop, $this->GetPageWidth(), self::MARGIN_BOTTOM, 'F');

            // Footer-Inhalt
            $this->SetY($footerTop + self::CONTENT_PADDING);
            $this->SetX(self::MARGIN_LEFT);
            $this->SetFont($this->current_settings['font'], '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->write_html($this->current_settings['footer']);
        }
    }

    private function add_metadata($form_data) {
        pdf_debug('Adding metadata');

        $this->Ln(10);

        // Separator line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(
            self::MARGIN_LEFT,
            $this->GetY(),
            $this->GetPageWidth() - self::MARGIN_RIGHT,
            $this->GetY()
        );

        $this->Ln(5);

        // Metadata style
        $this->SetFont($this->current_settings['font'], 'I', 8);
        $this->SetTextColor(100, 100, 100);

        // Basic metadata
        $this->Cell(0, 5, 'Erstellt am: ' . date_i18n('d.m.Y H:i'), 0, 1, 'L');
        $this->Cell(0, 5, 'URL: ' . get_site_url(), 0, 1, 'L');

        // Find and add submitter email if available
        foreach ($form_data['fields'] as $field) {
            if ($field['type'] === 'email' && !empty($field['value'])) {
                $this->Cell(0, 5, 'Absender: ' . $field['value'], 0, 1, 'L');
                break;
            }
        }

        // Add custom metadata if provided
        if (!empty($form_data['metadata'])) {
            foreach ($form_data['metadata'] as $key => $value) {
                if ($key === 'timestamp' || $key === 'site_url') continue;
                $this->Cell(0, 5, ucfirst($key) . ': ' . $value, 0, 1, 'L');
            }
        }
    }

    private function save_pdf() {
        pdf_debug('Saving PDF');

        try {
            $upload_dir = wp_upload_dir();
            $year_month = date('Y/m');
            $pdf_dir = $upload_dir['basedir'] . '/soulcraft-pdf/' . $year_month;

            // Ensure directory exists
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
                $this->secure_directory($pdf_dir);
            }

            $filename = sanitize_file_name(
                $this->current_settings['pdf_title'] . '_' . time() . '.pdf'
            );
            $pdf_path = $pdf_dir . '/' . $filename;

            pdf_debug('Attempting to save PDF to', $pdf_path);

            $this->Output('F', $pdf_path);

            if (!file_exists($pdf_path)) {
                throw new Exception('PDF file was not created');
            }

            // Store path for later access
            $this->last_pdf_path = $pdf_path;

            pdf_debug('PDF saved successfully');
            return $pdf_path;

        } catch (Exception $e) {
            pdf_debug('Save error', $e->getMessage());
            throw $e;
        }
    }

    private function secure_directory($dir) {
        $htaccess = $dir . '/.htaccess';
        $index = $dir . '/index.php';

        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all");
        }

        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }

    private function set_background_color($hex) {
        $rgb = $this->hex2rgb($hex);
        $this->SetFillColor($rgb['r'], $rgb['g'], $rgb['b']);
    }

    private function hex2rgb($hex) {
        $hex = str_replace('#', '', $hex);

        if(strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    private function write_html($html) {
        pdf_debug('Writing HTML content');

        try {
            // Bilderkennung und Ersetzen von <img>-Tags
            preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $images);

            foreach ($images[1] as $imgSrc) {
                // Bild einfügen
                $this->Image($imgSrc, $this->GetX(), $this->GetY(), 20); // Größe anpassen

                // Platz für Text neben oder unter dem Bild
                if ($this->GetX() + 30 < $this->GetPageWidth()) {
                    // Setze Text daneben, falls genug Platz auf der Seite ist
                    $this->SetX($this->GetX() + 25); // Verschiebe X-Position um Bildbreite
                } else {
                    // Andernfalls springe zur nächsten Zeile
                    $this->Ln(25); // Springt 25 Einheiten nach unten für neuen Abschnitt
                }
            }

            // Entferne <img>-Tags aus HTML
            $html = preg_replace('/<img[^>]+>/', '', $html);

            // HTML-Formatierung beibehalten
            $html = strip_tags($html, '<b><i><u><br>');
            $html = str_replace(['<br>', '<br />'], "\n", $html);
            $html = preg_replace('/<b>(.*?)<\/b>/i', "$1", $html);

            // Schrift und Textfarbe setzen
            $this->SetFont($this->current_settings['font'], '', 10);
            $this->Write(5, $html);

        } catch (Exception $e) {
            pdf_debug('HTML writing error', $e->getMessage());
        }
    }


    private function getNumLines($text, $width) {
        $this->SetFont($this->current_settings['font'], '', $this->current_settings['font_size']);

        $chars = str_split($text);
        $currentWidth = 0;
        $lines = 1;

        foreach ($chars as $char) {
            // Get character width
            $charWidth = $this->GetStringWidth($char);

            if ($char === "\n") {
                $lines++;
                $currentWidth = 0;
                continue;
            }

            $currentWidth += $charWidth;

            if ($currentWidth > $width) {
                $lines++;
                $currentWidth = $charWidth;
            }
        }

        return $lines;
    }

    public function get_last_path() {
        return $this->last_pdf_path;
    }

    public static function get_standard_fonts() {
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
     * Cleanup old PDFs
     * Optional: Kann regelmäßig per Cron aufgerufen werden
     */
    public function cleanup_old_pdfs($days = 30) {
        pdf_debug('Starting PDF cleanup');

        try {
            $upload_dir = wp_upload_dir();
            $pdf_base_dir = $upload_dir['basedir'] . '/soulcraft-pdf';

            if (!is_dir($pdf_base_dir)) {
                return;
            }

            $cutoff = time() - ($days * 86400);

            $this->cleanup_directory($pdf_base_dir, $cutoff);
            pdf_debug('PDF cleanup completed');

        } catch (Exception $e) {
            pdf_debug('Cleanup error', $e->getMessage());
        }
    }

    private function cleanup_directory($dir, $cutoff) {
        $files = glob($dir . '/*');

        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->cleanup_directory($file, $cutoff);
                // Lösche leere Verzeichnisse
                if (count(glob("$file/*")) === 0) {
                    rmdir($file);
                }
            } else if (is_file($file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($ext === 'pdf' && filemtime($file) < $cutoff) {
                    unlink($file);
                }
            }
        }
    }
}