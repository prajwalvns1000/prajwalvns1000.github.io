<?php
/**
 * Plugin Name: Portfolio CMS - Headless Engine
 * Description: Registers Custom Post Types for Projects and Work Experience, exposes custom fields via the REST API, and configures CORS headers for headless frontend consumption.
 * Version: 1.0.0
 * Author: Prajwal N
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Portfolio_CMS_Plugin {

    public function __construct() {
        // Register Post Types
        add_action('init', [$this, 'register_post_types']);

        // Register Custom Meta Fields & REST Fields
        add_action('init', [$this, 'register_meta_fields']);
        add_action('rest_api_init', [$this, 'register_rest_fields']);

        // Admin Meta Boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);

        // Configure CORS for REST API
        add_action('rest_api_init', [$this, 'configure_cors'], 15);
        add_action('init', [$this, 'handle_preflight']);

        // Automatic content seeding on initialization
        add_action('init', [$this, 'seed_initial_content'], 20);
        add_action('init', [$this, 'remove_legacy_seed_content'], 21);

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function activate() {
        $this->register_post_types();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function remove_legacy_seed_content() {
        if (get_option('portfolio_cms_legacy_seed_cleaned_v1')) {
            return;
        }

        $legacy_posts = get_posts([
            'post_type' => 'experience',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'title' => 'Instrumentation Engineering Intern at Jubilant Biosys Limited'
        ]);

        foreach ($legacy_posts as $legacy_post) {
            wp_delete_post($legacy_post->ID, true);
        }

        update_option('portfolio_cms_legacy_seed_cleaned_v1', 1);
    }

    /**
     * 1. Register Custom Post Types: Projects and Work Experience
     */
    public function register_post_types() {
        // Projects CPT
        $project_labels = [
            'name'                  => 'Projects',
            'singular_name'         => 'Project',
            'menu_name'             => 'Projects',
            'add_new'               => 'Add New',
            'add_new_item'          => 'Add New Project',
            'edit_item'             => 'Edit Project',
            'new_item'              => 'New Project',
            'view_item'             => 'View Project',
            'search_items'          => 'Search Projects',
            'not_found'             => 'No projects found',
            'not_found_in_trash'    => 'No projects found in trash'
        ];

        register_post_type('projects', [
            'labels'             => $project_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'projects'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-portfolio',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'show_in_rest'       => true,
            'rest_base'          => 'projects',
            'rest_controller_class' => 'WP_REST_Posts_Controller'
        ]);

        // Work Experience CPT
        $experience_labels = [
            'name'                  => 'Work Experience',
            'singular_name'         => 'Work Experience',
            'menu_name'             => 'Work Experience',
            'add_new'               => 'Add New',
            'add_new_item'          => 'Add New Experience',
            'edit_item'             => 'Edit Experience',
            'new_item'              => 'New Experience',
            'view_item'             => 'View Experience',
            'search_items'          => 'Search Experience',
            'not_found'             => 'No experience found',
            'not_found_in_trash'    => 'No experience found in trash'
        ];

        register_post_type('experience', [
            'labels'             => $experience_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'experience'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-id-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'show_in_rest'       => true,
            'rest_base'          => 'experience',
            'rest_controller_class' => 'WP_REST_Posts_Controller'
        ]);
    }

    /**
     * 2. Register Post Meta
     */
    public function register_meta_fields() {
        // Project Meta
        $project_metas = [
            'project_summary'   => 'string',
            'technologies'      => 'string',
            'tech_tags'         => 'string',
            'key_concepts'      => 'string',
            'github_link'       => 'string',
            'live_demo'         => 'string',
            'project_date'      => 'string',
        ];

        foreach ($project_metas as $key => $type) {
            register_post_meta('projects', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => $type,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() { return current_user_can('edit_posts'); }
            ]);
        }

        // Work Experience Meta
        $experience_metas = [
            'company_name'  => 'string',
            'job_title'     => 'string',
            'start_date'    => 'string',
            'end_date'      => 'string',
            'location'      => 'string',
            'technologies'  => 'string',
        ];

        foreach ($experience_metas as $key => $type) {
            register_post_meta('experience', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => $type,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() { return current_user_can('edit_posts'); }
            ]);
        }
    }

    /**
     * 3. Expose Custom Clean Fields in REST API with Get and Update Callbacks
     */
    public function register_rest_fields() {
        // Project: summary
        register_rest_field('projects', 'summary', [
            'get_callback' => function($post) {
                $val = get_post_meta($post['id'], 'project_summary', true);
                return !empty($val) ? $val : wp_strip_all_tags($post['excerpt']['rendered'] ?? '');
            },
            'update_callback' => function($value, $post) {
                update_post_meta($post->ID, 'project_summary', sanitize_textarea_field($value));
            }
        ]);

        // Project: technologies
        register_rest_field('projects', 'technologies', [
            'get_callback' => function($post) {
                return get_post_meta($post['id'], 'technologies', true);
            },
            'update_callback' => function($value, $post) {
                update_post_meta($post->ID, 'technologies', sanitize_text_field($value));
            }
        ]);

        // Project: tech_tags
        register_rest_field('projects', 'tech_tags', [
            'get_callback' => function($post) {
                $val = get_post_meta($post['id'], 'tech_tags', true);
                return !empty($val) ? array_map('trim', explode(',', $val)) : [];
            },
            'update_callback' => function($value, $post) {
                $val_str = is_array($value) ? implode(', ', $value) : $value;
                update_post_meta($post->ID, 'tech_tags', sanitize_text_field($val_str));
            }
        ]);

        // Project: key_concepts
        register_rest_field('projects', 'key_concepts', [
            'get_callback' => function($post) {
                $concepts_str = get_post_meta($post['id'], 'key_concepts', true);
                if (empty($concepts_str)) return [];
                $lines = explode("\n", str_replace("\r", "", $concepts_str));
                $concepts = [];
                foreach ($lines as $line) {
                    $trimmed = trim($line, " \t\n\r\0\x0B-•*");
                    if (!empty($trimmed)) $concepts[] = $trimmed;
                }
                return $concepts;
            },
            'update_callback' => function($value, $post) {
                $val_str = is_array($value) ? implode("\n", $value) : $value;
                update_post_meta($post->ID, 'key_concepts', sanitize_textarea_field($val_str));
            }
        ]);

        // Project: links & date
        register_rest_field('projects', 'github_link', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'github_link', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'github_link', esc_url_raw($value)); }
        ]);

        register_rest_field('projects', 'live_demo', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'live_demo', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'live_demo', esc_url_raw($value)); }
        ]);

        register_rest_field('projects', 'project_date', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'project_date', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'project_date', sanitize_text_field($value)); }
        ]);

        register_rest_field('projects', 'featured_image_url', [
            'get_callback' => function($post) {
                return has_post_thumbnail($post['id']) ? get_the_post_thumbnail_url($post['id'], 'large') : '';
            }
        ]);

        // Experience: company_name
        register_rest_field('experience', 'company_name', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'company_name', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'company_name', sanitize_text_field($value)); }
        ]);

        // Experience: job_title
        register_rest_field('experience', 'job_title', [
            'get_callback' => function($post) {
                $title = get_post_meta($post['id'], 'job_title', true);
                return !empty($title) ? $title : html_entity_decode($post['title']['rendered'] ?? '');
            },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'job_title', sanitize_text_field($value)); }
        ]);

        // Experience: dates
        register_rest_field('experience', 'date_range', [
            'get_callback' => function($post) {
                $start = get_post_meta($post['id'], 'start_date', true);
                $end = get_post_meta($post['id'], 'end_date', true);
                if (empty($start) && empty($end)) return '';
                if ($start === $end || empty($end)) return $start;
                return trim($start . ' – ' . $end);
            }
        ]);

        register_rest_field('experience', 'start_date', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'start_date', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'start_date', sanitize_text_field($value)); }
        ]);

        register_rest_field('experience', 'end_date', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'end_date', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'end_date', sanitize_text_field($value)); }
        ]);

        // Experience: location & technologies
        register_rest_field('experience', 'location', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'location', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'location', sanitize_text_field($value)); }
        ]);

        register_rest_field('experience', 'technologies', [
            'get_callback' => function($post) { return get_post_meta($post['id'], 'technologies', true); },
            'update_callback' => function($value, $post) { update_post_meta($post->ID, 'technologies', sanitize_text_field($value)); }
        ]);
    }

    /**
     * 4. Admin Meta Boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'portfolio_project_details',
            'Project Details (Headless REST API)',
            [$this, 'render_project_meta_box'],
            'projects',
            'normal',
            'high'
        );

        add_meta_box(
            'portfolio_experience_details',
            'Work Experience Details (Headless REST API)',
            [$this, 'render_experience_meta_box'],
            'experience',
            'normal',
            'high'
        );
    }

    public function render_project_meta_box($post) {
        wp_nonce_field('portfolio_meta_box_nonce', 'portfolio_meta_nonce');

        $summary      = get_post_meta($post->ID, 'project_summary', true);
        $technologies = get_post_meta($post->ID, 'technologies', true);
        $tech_tags    = get_post_meta($post->ID, 'tech_tags', true);
        $key_concepts = get_post_meta($post->ID, 'key_concepts', true);
        $github_link  = get_post_meta($post->ID, 'github_link', true);
        $live_demo    = get_post_meta($post->ID, 'live_demo', true);
        $project_date = get_post_meta($post->ID, 'project_date', true);
        ?>
        <style>
            .portfolio-field-row { margin-bottom: 15px; }
            .portfolio-field-row label { display: block; font-weight: 600; margin-bottom: 5px; }
            .portfolio-field-row input[type="text"], .portfolio-field-row textarea { width: 100%; }
            .portfolio-field-row .description { color: #666; font-size: 12px; margin-top: 3px; }
        </style>
        <div class="portfolio-meta-wrapper">
            <div class="portfolio-field-row">
                <label for="project_summary">Card Short Summary:</label>
                <textarea id="project_summary" name="project_summary" rows="2" placeholder="Brief summary displayed on project card preview..."><?php echo esc_textarea($summary); ?></textarea>
            </div>
            <div class="portfolio-field-row">
                <label for="technologies">Technologies Subtitle:</label>
                <input type="text" id="technologies" name="technologies" value="<?php echo esc_attr($technologies); ?>" placeholder="e.g. ESP32-S3 | INMP441 | DSP" />
                <p class="description">Displayed in the header of the card and modal popup.</p>
            </div>
            <div class="portfolio-field-row">
                <label for="tech_tags">Tech Tags (comma separated):</label>
                <input type="text" id="tech_tags" name="tech_tags" value="<?php echo esc_attr($tech_tags); ?>" placeholder="e.g. ESP32-S3, INMP441 Microphone, FFT, MFCC, OLED" />
                <p class="description">Renders as badge pills on the card.</p>
            </div>
            <div class="portfolio-field-row">
                <label for="key_concepts">Key Technical Concepts (one per line):</label>
                <textarea id="key_concepts" name="key_concepts" rows="3" placeholder="Band-pass filtering & acoustic cross-correlation&#10;Sound classification & direction of arrival"><?php echo esc_textarea($key_concepts); ?></textarea>
                <p class="description">Bullet points displayed under 'Key Technical Concepts'.</p>
            </div>
            <div class="portfolio-field-row">
                <label for="github_link">GitHub Link (optional):</label>
                <input type="text" id="github_link" name="github_link" value="<?php echo esc_url($github_link); ?>" placeholder="https://github.com/..." />
            </div>
            <div class="portfolio-field-row">
                <label for="live_demo">Live Demo Link (optional):</label>
                <input type="text" id="live_demo" name="live_demo" value="<?php echo esc_url($live_demo); ?>" placeholder="https://..." />
            </div>
            <div class="portfolio-field-row">
                <label for="project_date">Project Date / Year (optional):</label>
                <input type="text" id="project_date" name="project_date" value="<?php echo esc_attr($project_date); ?>" placeholder="e.g. 2026" />
            </div>
        </div>
        <?php
    }

    public function render_experience_meta_box($post) {
        wp_nonce_field('portfolio_meta_box_nonce', 'portfolio_meta_nonce');

        $company_name = get_post_meta($post->ID, 'company_name', true);
        $job_title    = get_post_meta($post->ID, 'job_title', true);
        $start_date   = get_post_meta($post->ID, 'start_date', true);
        $end_date     = get_post_meta($post->ID, 'end_date', true);
        $location     = get_post_meta($post->ID, 'location', true);
        $technologies = get_post_meta($post->ID, 'technologies', true);
        ?>
        <style>
            .portfolio-field-row { margin-bottom: 15px; }
            .portfolio-field-row label { display: block; font-weight: 600; margin-bottom: 5px; }
            .portfolio-field-row input[type="text"] { width: 100%; }
            .portfolio-field-row .description { color: #666; font-size: 12px; margin-top: 3px; }
        </style>
        <div class="portfolio-meta-wrapper">
            <div class="portfolio-field-row">
                <label for="company_name">Company / Organization Name:</label>
                <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($company_name); ?>" placeholder="e.g. 1Xsoft Private Limited" />
            </div>
            <div class="portfolio-field-row">
                <label for="job_title">Job / Internship Title:</label>
                <input type="text" id="job_title" name="job_title" value="<?php echo esc_attr($job_title); ?>" placeholder="e.g. Software Engineer - Intern" />
            </div>
            <div style="display: flex; gap: 15px;">
                <div class="portfolio-field-row" style="flex: 1;">
                    <label for="start_date">Start Date:</label>
                    <input type="text" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" placeholder="e.g. August 2026" />
                </div>
                <div class="portfolio-field-row" style="flex: 1;">
                    <label for="end_date">End Date:</label>
                    <input type="text" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" placeholder="e.g. Present" />
                </div>
            </div>
            <div class="portfolio-field-row">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" value="<?php echo esc_attr($location); ?>" placeholder="e.g. Mangalore, Karnataka / Hybrid" />
            </div>
            <div class="portfolio-field-row">
                <label for="technologies">Skills / Technologies Utilized:</label>
                <input type="text" id="technologies" name="technologies" value="<?php echo esc_attr($technologies); ?>" placeholder="e.g. Embedded C, Python, DSP, Firmware" />
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!in_array(get_post_type($post_id), ['projects', 'experience'], true)) {
            return;
        }

        if (!isset($_POST['portfolio_meta_nonce']) || !wp_verify_nonce($_POST['portfolio_meta_nonce'], 'portfolio_meta_box_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'project_summary'   => 'sanitize_textarea_field',
            'technologies'      => 'sanitize_text_field',
            'tech_tags'         => 'sanitize_text_field',
            'key_concepts'      => 'sanitize_textarea_field',
            'github_link'       => 'esc_url_raw',
            'live_demo'         => 'esc_url_raw',
            'project_date'      => 'sanitize_text_field',
            'company_name'      => 'sanitize_text_field',
            'job_title'         => 'sanitize_text_field',
            'start_date'        => 'sanitize_text_field',
            'end_date'          => 'sanitize_text_field',
            'location'          => 'sanitize_text_field',
        ];

        foreach ($fields as $field => $sanitizer) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, call_user_func($sanitizer, $_POST[$field]));
            }
        }
    }

    /**
     * 5. CORS Headers for REST API
     */
    public function configure_cors() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

        add_filter('rest_pre_serve_request', function($value) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Wpnonce');
            return $value;
        });
    }

    public function handle_preflight() {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Wpnonce');
            status_header(200);
            exit();
        }
    }

    /**
     * 6. Initial Seed Content (Runs automatically if no projects exist)
     */
    public function seed_initial_content() {
        if (get_option('portfolio_cms_initial_seed_v1')) {
            return; // Already seeded
        }

        $existing = get_posts(['post_type' => 'projects', 'posts_per_page' => 1, 'post_status' => 'any']);
        if (!empty($existing)) {
            update_option('portfolio_cms_initial_seed_v1', 1);
            return;
        }

        // Projects to Seed
        $projects = [
            [
                'title'   => 'AI-Based Drone Detection and Direction Estimation System',
                'summary' => 'Developed a portable embedded system for real-time drone detection using acoustic signal processing.',
                'meta'    => 'ESP32-S3 | INMP441 | DSP',
                'tags'    => 'ESP32-S3, INMP441 Microphone, FFT, MFCC, OLED',
                'concepts'=> "Band-pass filtering & acoustic cross-correlation\nSound classification & direction of arrival",
                'content' => "<h3>Project Overview</h3><p>Developed a portable, low-power embedded surveillance system designed for real-time detection and direction estimation of commercial drones. The device captures environmental acoustics and isolates the specific frequencies generated by high-RPM drone propellers and motors.</p><h3>Key Implementations</h3><ul><li><strong>Acoustic Front-End:</strong> Integrated an INMP441 omnidirectional I2S digital microphone with an ESP32-S3 microcontroller to stream high-quality audio data.</li><li><strong>Signal Processing & DSP:</strong> Implemented band-pass filtering to isolate propeller sounds (typically 200Hz to 8kHz), followed by Fast Fourier Transform (FFT) analysis to generate spectral metrics.</li><li><strong>Feature Extraction:</strong> Extracted Mel-Frequency Cepstral Coefficients (MFCC) to construct acoustic templates suitable for lightweight classification.</li><li><strong>Direction of Arrival (DoA):</strong> Employed Cross-Correlation algorithms across audio channels to determine the time difference of arrival (TDOA) and estimate heading direction.</li><li><strong>Alert System:</strong> Configured a local OLED display (SSD1306) to show detection states, a piezo buzzer for physical proximity warnings, and Wi-Fi modules to broadcast alerts to central dashboards.</li></ul><h3>Application Scope</h3><p>Tailored specifically for real-time security, site perimeter surveillance, and localized airspace warning applications where radar or optical cameras are impractical or too expensive.</p>",
                'date'    => '2026',
            ],
            [
                'title'   => 'Adaptive Eating Assistance System',
                'summary' => 'Developed an embedded assistive robotic feeding system for elderly and physically challenged individuals.',
                'meta'    => 'ESP32 | MPU6050 | Sensors | Servos',
                'tags'    => 'ESP32, MPU6050 Gyro, IR Sensor, Servo Motors',
                'concepts'=> "Real-time multi-sensor acquisition\nHands-free automated motion control & feeding",
                'content' => "<h3>Project Overview</h3><p>Designed and built an assistive robotic feeding arm to support hands-free eating, restoring autonomy to elderly, motor-impaired, and physically challenged individuals.</p><h3>Key Implementations</h3><ul><li><strong>Microcontroller Core:</strong> Programmed an ESP32 to coordinate multi-joint coordinate calculations and sensor polling cycles.</li><li><strong>Gesture Tracking:</strong> Integrated an MPU6050 accelerometer and gyroscope (head-mounted or strap-mounted) to record natural gesture tilts, allowing the user to guide the mechanical arm.</li><li><strong>Intent Triggering:</strong> Used an IR eye-blink sensor as a binary switch. The firmware filters out natural blinks, utilizing long/deliberate blinks to trigger scooping and feeding cycles.</li><li><strong>Motion Profiling:</strong> Controlled high-torque servo motors to drive the physical robotic linkages smoothly, reducing acceleration spikes and ensuring food remains stable.</li></ul><h3>Safety Features</h3><p>Programmed motion boundary clamps and safety override thresholds. If the IR sensor detects an obstruction or the MPU6050 registers rapid movements, the controller instantly halts the servo power supply.</p>",
                'date'    => '2025',
            ],
            [
                'title'   => 'Vibration and Tilt Measurement using MPU6050',
                'summary' => 'Developed a real-time vibration and tilt monitoring system using the MPU6050 accelerometer and gyroscope.',
                'meta'    => 'ESP32 | Embedded Systems',
                'tags'    => 'ESP32, MPU6050, Accelerometer, Gyroscope',
                'concepts'=> "Sensor signal processing and filtering\nOrientation estimation & vibration monitoring",
                'content' => "<h3>Project Overview</h3><p>Developed a high-precision real-time telemetry node to measure mechanical vibrations and angular orientation (tilt) for structural safety and motor diagnostics.</p><h3>Key Implementations</h3><ul><li><strong>Sensor Integration:</strong> Interfaced an MPU6050 inertial measurement unit with an ESP32 over the I2C serial protocol.</li><li><strong>Drift Elimination:</strong> Wrote firmware filters combining raw gyroscope rates and accelerometer values to estimate tilt angles, neutralizing the cumulative drift inherent in gyros.</li><li><strong>Vibration Tracking:</strong> Sampled accelerometer registers at high frequencies to extract peak-to-peak displacement, velocity, and acceleration indices.</li><li><strong>Telemetry:</strong> Broadcasted variables via serial channels and local Wi-Fi, plotting metrics on HMI panels or graphing utilities in real-time.</li></ul><h3>Industrial Value</h3><p>Serves as a cost-effective sensor node for heavy machinery alignment checks, tilt warning safety triggers on transport equipment, and structural health monitoring.</p>",
                'date'    => '2025',
            ],
            [
                'title'   => 'PLC-Based Predictive Maintenance System',
                'summary' => 'Designed a predictive maintenance system for industrial motors by monitoring vibration and temperature.',
                'meta'    => 'PLC | Industrial Automation',
                'tags'    => 'PLC Programming, HMI Design, Predictive Maintenance, Modbus/Telemetry',
                'concepts'=> "Real-time fault detection algorithms\nAutomated alarm generation & HMI monitoring",
                'content' => "<h3>Project Overview</h3><p>Designed and programmed an industrial predictive maintenance system aimed at maximizing rotating motor uptime and avoiding catastrophic machinery failures.</p><h3>Key Implementations</h3><ul><li><strong>PLC logic:</strong> Programmed Programmable Logic Controller routines to monitor industrial sensor feeds (vibration transducers and RTD thermal sensors).</li><li><strong>Condition Monitoring:</strong> Implemented fault detection thresholds comparing operational parameters against ISO standards for motor health.</li><li><strong>Alarm Infrastructure:</strong> Formulated ladder logic routines to generate hierarchical alerts (Warning & Critical Shutdown) based on temperature gradients and velocity levels.</li><li><strong>Operator Interface:</strong> Designed a descriptive Human Machine Interface (HMI) page showing machine health indices, historical graphs, and troubleshooting procedures.</li></ul><h3>Project Outcomes</h3><p>Provides early warning indicators for common faults like bearing wear, shaft misalignment, and stator overheating, enabling scheduled repairs and mitigating costly unplanned factory line downtime.</p>",
                'date'    => '2024',
            ]
        ];

        foreach ($projects as $p) {
            $pid = wp_insert_post([
                'post_title'   => $p['title'],
                'post_content' => $p['content'],
                'post_excerpt' => $p['summary'],
                'post_status'  => 'publish',
                'post_type'    => 'projects'
            ]);

            if ($pid && !is_wp_error($pid)) {
                update_post_meta($pid, 'project_summary', $p['summary']);
                update_post_meta($pid, 'technologies', $p['meta']);
                update_post_meta($pid, 'tech_tags', $p['tags']);
                update_post_meta($pid, 'key_concepts', $p['concepts']);
                update_post_meta($pid, 'project_date', $p['date']);
            }
        }

        // Work Experiences to Seed
        $experiences = [
            [
                'company' => '1Xsoft Private Limited',
                'title'   => 'Software Engineer - Intern',
                'start'   => 'August 2026',
                'end'     => 'Present',
                'location'=> 'Mangalore, Karnataka / Hybrid',
                'tech'    => 'Embedded C, Python, Product Engineering, QA & Testing',
                'content' => 'Contributing to software engineering pipelines, embedded system development, and firmware testing routines.'
            ],
            [
                'company' => 'InternPE',
                'title'   => 'Embedded Systems Intern',
                'start'   => '2026',
                'end'     => '2026',
                'location'=> 'Remote',
                'tech'    => 'Arduino Uno, LM35 Temperature Sensor, Signal Conditioning, Serial Telemetry',
                'content' => 'Developed digital temperature monitoring solutions, calibrated analog sensors, and implemented serial communication protocols.'
            ]
        ];

        foreach ($experiences as $e) {
            $eid = wp_insert_post([
                'post_title'   => $e['title'] . ' at ' . $e['company'],
                'post_content' => $e['content'],
                'post_status'  => 'publish',
                'post_type'    => 'experience'
            ]);

            if ($eid && !is_wp_error($eid)) {
                update_post_meta($eid, 'company_name', $e['company']);
                update_post_meta($eid, 'job_title', $e['title']);
                update_post_meta($eid, 'start_date', $e['start']);
                update_post_meta($eid, 'end_date', $e['end']);
                update_post_meta($eid, 'location', $e['location']);
                update_post_meta($eid, 'technologies', $e['tech']);
            }
        }

        update_option('portfolio_cms_initial_seed_v1', 1);
    }
}

new Portfolio_CMS_Plugin();
