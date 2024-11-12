<?php
/**
 * Font Manager Class
 * Handles font installation and management for the PDF Generator
 */
class Font_Manager {
    private const FONTS_DIR = SOULCRAFT_PDF_PATH . 'fonts/';
    private const GOOGLE_FONTS_DIR = self::FONTS_DIR . 'google/';

    private $installed_fonts = [];

    public function __construct() {
        $this->setup_directories();
        $this->scan_installed_fonts();
        error_log('PDF Debug: Font Manager initialized');
        error_log('PDF Debug: Google Fonts Directory: ' . self::GOOGLE_FONTS_DIR);
    }

    private function register_ttf_font($font_file) {
        $font_name = strtolower(pathinfo($font_file, PATHINFO_FILENAME));

        // Generiere die Font-Definition wenn sie nicht existiert
        $font_definition = dirname($font_file) . '/' . $font_name . '.php';
        if (!file_exists($font_definition)) {
            require_once SOULCRAFT_PDF_PATH . 'vendor/setasign/fpdf/makefont/makefont.php';
            MakeFont($font_file, $font_definition);
        }

        return [
            'font_file' => $font_file,
            'font_definition' => $font_definition,
            'name' => $font_name
        ];
    }

    private function setup_directories() {
        // Erstelle Verzeichnisse falls sie nicht existieren
        wp_mkdir_p(self::FONTS_DIR);
        wp_mkdir_p(self::GOOGLE_FONTS_DIR);

        // Sichere die Verzeichnisse
        $htaccess = self::FONTS_DIR . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all");
        }

        $index = self::FONTS_DIR . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }

    private function scan_installed_fonts() {
        if (!is_dir(self::GOOGLE_FONTS_DIR)) {
            error_log('PDF Debug: Google Fonts directory not found');
            return;
        }

        $files = glob(self::GOOGLE_FONTS_DIR . '*.{ttf,otf}', GLOB_BRACE);
        error_log('PDF Debug: Found font files: ' . print_r($files, true));

        foreach ($files as $file) {
            $font_info = $this->get_font_info($file);
            if ($font_info) {
                $family = strtolower($font_info['family']);
                if (!isset($this->installed_fonts[$family])) {
                    $this->installed_fonts[$family] = $font_info;
                    error_log('PDF Debug: Registered font: ' . $family);
                }
            }
        }

        error_log('PDF Debug: Installed fonts: ' . print_r($this->installed_fonts, true));
    }

    private function get_font_info($file_path) {
        try {
            $filename = basename($file_path);

            // Entferne Dateiendung und mögliche Stil-Bezeichnungen
            $base_name = preg_replace('/[-]?(Regular|Bold|Italic|Light|Medium)?\.(ttf|otf)$/i', '', $filename);

            // Bereinige den Namen
            $family = str_replace(['-', '_'], ' ', $base_name);
            // Konvertiere zu Title Case
            $family = ucwords($family);

            error_log('PDF Debug: Processing font: ' . $filename . ' as family: ' . $family);

            return [
                'family' => $family,
                'name' => $family,
                'file_path' => $file_path,
                'type' => 'TTF',
                'styles' => ['regular'], // Standardmäßig nur regular Style
                'variants' => [
                    'regular' => $file_path
                ]
            ];
        } catch (Exception $e) {
            error_log('PDF Debug: Error processing font file: ' . $e->getMessage());
            return false;
        }
    }

    public function get_available_fonts() {
        // Kombiniere Standard- und installierte Fonts
        $fonts = PDF_Generator::get_standard_fonts();

        foreach ($this->installed_fonts as $key => $font) {
            $fonts[$key] = $font['name'];
        }

        error_log('PDF Debug: Available fonts: ' . print_r($fonts, true));
        return $fonts;
    }

    public function get_font_path($family) {
        $family = strtolower($family);
        if (isset($this->installed_fonts[$family])) {
            return $this->installed_fonts[$family]['file_path'];
        }
        return false;
    }

    public function is_ttf_font($family) {
        $family = strtolower($family);
        return isset($this->installed_fonts[$family]) &&
            isset($this->installed_fonts[$family]['type']) &&
            $this->installed_fonts[$family]['type'] === 'TTF';
    }

    public function get_font_info_by_family($family) {
        $family = strtolower($family);
        if (isset($this->installed_fonts[$family])) {
            return $this->installed_fonts[$family];
        }
        return false;
    }

    public function install_google_font($font_family) {
        try {
            // Google Fonts API URL
            $api_url = sprintf(
                'https://fonts.googleapis.com/css2?family=%s:wght@400;700&display=swap',
                urlencode($font_family)
            );

            // Hole CSS mit Font-Face Definitionen
            $response = wp_remote_get($api_url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);

            if (is_wp_error($response)) {
                throw new Exception('Konnte Google Fonts CSS nicht laden: ' . $response->get_error_message());
            }

            $css = wp_remote_retrieve_body($response);

            // Extrahiere Font-URLs
            preg_match_all('/url\((.*?)\)/', $css, $matches);

            if (empty($matches[1])) {
                throw new Exception('Keine Font-URLs gefunden');
            }

            $installed_files = [];

            // Lade jede Font-Datei herunter
            foreach ($matches[1] as $font_url) {
                $font_data = wp_remote_get($font_url);
                if (is_wp_error($font_data)) {
                    continue;
                }

                $font_content = wp_remote_retrieve_body($font_data);
                $font_filename = basename($font_url);
                $font_path = self::GOOGLE_FONTS_DIR . sanitize_file_name($font_family . '-' . $font_filename);

                if (file_put_contents($font_path, $font_content)) {
                    $installed_files[] = $font_path;
                }
            }

            if (empty($installed_files)) {
                throw new Exception('Keine Font-Dateien konnten installiert werden');
            }

            // Aktualisiere installierte Fonts
            $this->scan_installed_fonts();

            return true;

        } catch (Exception $e) {
            error_log('PDF Debug: Font installation error - ' . $e->getMessage());
            return false;
        }
    }

    public function get_installed_fonts() {
        return $this->installed_fonts;
    }

    public function clear_cache() {
        $this->installed_fonts = [];
        $this->scan_installed_fonts();
    }

    public function get_font_styles($family) {
        $family = strtolower($family);
        if (isset($this->installed_fonts[$family]['styles'])) {
            return $this->installed_fonts[$family]['styles'];
        }
        return ['regular'];
    }

    public function get_font_variants($family) {
        $family = strtolower($family);
        if (isset($this->installed_fonts[$family]['variants'])) {
            return $this->installed_fonts[$family]['variants'];
        }
        return [];
    }
}