<?php
/**
 * All contents (c)2016 Kazimer Corp.
 * Removal in part or in whole of this copyright notice is not allowed.
 * Selling this code or any derivative works is a violation of copyright law.
 * Kazimer Corp and its employees will be held harmless should any loss occur while using this code.
 * Use of this code constitutes full agreement with all these terms.
 *
 *  http://www.kazimer.com
 *
 *  @author     Kazimer Corp
 *  @copyright  (c) 2016 - Kazimer Corp
 *  @version    1.0.0
 *
 *  @package WordPress
 */
include_once dirname(__FILE__) . '/mindbody-api/MB_API.php';

/**
 * mbo_widget
 *
 * Define the widget
 */
class mbo_widget extends WP_Widget {

    const OPT_SOURCE_NAME = 'mbo_sourcename'; // option names
    const OPT_PASSWORD = 'mbo_password';
    const OPT_SITE_IDS = 'mbo_siteids';
    const TRANSIENT_NAME = 'mbo_widget_data'; // transient data name
    const TRANSIENT_TIMEOUT = 3600;
    const MIN_ITEMS = 1; // min and max schedule items to display
    const MAX_ITEMS = 12;
    const DEFAULT_ITEMS = 7;
    const DEFAULT_TIMESPAN = 'today + 7 days';

    private $_mbo_creds = array('SourceName' => '', 'Password' => '', 'SiteIDs' => array());
    private $_parms = array(); // SOAP call parameters

    /**
     * __construct
     *
     * Object contructor
     */

    function __construct() {
        parent::__construct(
                'mbo_widget', // Base ID of your widget
                __('MindBody Classes'/* , 'Your Text Domain Here' */), // Widget name will appear in UI
                array('description' => __('Displays upcoming classes from MindBody Online'/* , 'Your Text Domain Here' */)) // Widget description
        );

        // Set default SOAP call parameters
        $this->_parms = array(
            'StartDateTime' => \date('Y-m-d'),
            'EndDateTime' => \date('Y-m-d', \strtotime(self::DEFAULT_TIMESPAN)),
            'HideCanceledClasses' => false,
            'PageSize' => self::DEFAULT_ITEMS,
            'CurrentPageIndex' => 0,
            'XMLDetail' => 'Full'
        );

        // Read MindBody API credentials, if they exist
        $this->_mbo_creds['SourceName'] = \get_option(self::OPT_SOURCE_NAME, '');
        $this->_mbo_creds['Password'] = \get_option(self::OPT_PASSWORD, '');
        $this->_mbo_creds['SiteIDs'] = \get_option(self::OPT_SITE_IDS, array());

        // Make sure style sheet gets loaded
        \add_action('wp_enqueue_scripts', array(__CLASS__, 'queue_style'), 20);
    }

    /**
     * queue_style
     *
     * Extra step needed to make stylesheet load late
     */
    static function queue_style() {
        \wp_enqueue_style('mindbody-calendar', \trailingslashit(\get_stylesheet_directory_uri()) . 'style-mindbody.css');
    }

    /**
     * widget
     *
     * Create widget front-end
     * This is where the action happens
     *
     * @param array $args Widget arguments
     * @param array $instance Widget instance
     */
    public function widget($args, $instance) {
        $title = \apply_filters('widget_title', $instance['title']);

        // before and after widget arguments are defined by themes
        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        // Retrieve max number of classes to display
        $max_items = intval($instance['max_items']);
        if ($max_items < 1 || $max_items > 12) {
            $max_items = 7;
        }

        // Display the list of classes
        $this->display_classes($max_items);

        echo $args['after_widget'];
    }

    /**
     * form
     *
     * Widget Backend form
     *
     * @global \WP_User $current_user
     * @param array $instance Widget instance
     */
    public function form($instance) {
        if (isset($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = __('New title'/* , 'Your Text Domain Here' */);
        }
        $max_items = \intval($instance['max_items']);
        if ($max_items < 1 || $max_items > 10) {
            $max_items = 7;
        }
        $source_name = \get_option(self::OPT_SOURCE_NAME, '');
        $password = \get_option(self::OPT_PASSWORD, '');
        $site_IDs = \implode(',', \get_option(self::OPT_SITE_IDS, ''));

        // Widget admin form
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
            <label for="<?php echo $this->get_field_id('max_items'); ?>"><?php _e('Max Items:'/* , 'Your Text Domain Here' */); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('max_items'); ?>" name="<?php echo $this->get_field_name('max_items'); ?>" type="text" value="<?php echo esc_attr($max_items); ?>" />
            <?php
            // If current user is an administrator, show the API credential fields
            global $current_user;
            if (current_user_can('update_core')) {
                ?>
                <label for="<?php echo $this->get_field_id('source_name'); ?>"><?php _e('API Source Name:'/* , 'Your Text Domain Here' */); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('source_name'); ?>" name="<?php echo $this->get_field_name('source_name'); ?>" type="text" value="<?php echo esc_attr($source_name); ?>" />
                <label for="<?php echo $this->get_field_id('password'); ?>"><?php _e('API Password:'/* , 'Your Text Domain Here' */); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('password'); ?>" name="<?php echo $this->get_field_name('password'); ?>" type="text" value="<?php echo esc_attr($password); ?>" />
                <label for="<?php echo $this->get_field_id('site_IDs'); ?>"><?php _e('Site IDs:  (comma separated)'/* , 'Your Text Domain Here' */); ?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('site_IDs'); ?>" name="<?php echo $this->get_field_name('site_IDs'); ?>" type="text" value="<?php echo esc_attr($site_IDs); ?>" />
                <?php
            } else {
                // Tell a non-admin user if the API credentials are not setup
                if (empty($source_name) || empty($password) || empty($site_IDs)) {
                    echo '<label for="">' . __('Ask your web site adminstrator to setup Mindbody API credentials.'/* , 'Your Text Domain Here' */) . '</label>';
                }
            }
            ?>
        </p>
        <?php
    }

    /**
     * update
     *
     * Updating widget replacing old instances with new
     *
     * @param type $new_instance
     * @param type $old_instance
     * @return type
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (empty($new_instance['title'])) ? '' : \strip_tags($new_instance['title']);

        $max_items = \intval($new_instance['max_items']);
        if ($max_items < self::MIN_ITEMS || $max_items > self::MAX_ITEMS) {
            $max_items = self::DEFAULT_ITEMS;
        }
        $instance['max_items'] = $max_items;

        global $current_user;
        if (\current_user_can('update_core')) {
            // If admin cleared a setting then delete the option,
            // else set and save the option
            $source_name = $new_instance['source_name'];
            if (empty($source_name)) {
                $this->_mbo_creds['SourceName'] = '';
                \delete_option(self::OPT_SOURCE_NAME);
            } else {
                $this->_mbo_creds['SourceName'] = $source_name;
                \update_option(self::OPT_SOURCE_NAME, $source_name);
            }

            $password = $new_instance['password'];
            if (empty($password)) {
                $this->_mbo_creds['Password'] = '';
                \delete_option(self::OPT_PASSWORD);
            } else {
                $this->_mbo_creds['Password'] = $password;
                \update_option(self::OPT_PASSWORD, $password);
            }

            $site_ids = $new_instance['site_IDs'];
            if (empty($site_ids)) {
                $this->_mbo_creds['SiteIDs'] = '';
                \delete_option(self::OPT_SITE_IDS);
            } else {
                $site_ids = \explode(',', $site_ids);
                $this->_mbo_creds['SiteIDs'] = $site_ids;
                \update_option(self::OPT_SITE_IDS, $site_ids);
            }
        }

        \delete_transient(self::TRANSIENT_NAME);

        return $instance;
    }

    /**
     * get_classes
     * Check if cached results are available first, if not then get from MindBody
     *
     * @global array $mbo_creds API credentials for MindBody
     * @return array MindBody class data
     */
    private function get_classes() {

        $classes = \get_transient(self::TRANSIENT_NAME);
        if (false === $classes) {
            $mb = new \Mindbody\MB_API($this->_mbo_creds);
            $classes = array();
            $data = $mb->GetClasses($this->_parms);

            // If class data was returned, process it and save it
            if (!empty($data['GetClassesResult']['Classes']['Class'])) {
                $classes = $mb->makeNumericArray($data['GetClassesResult']['Classes']['Class']);
                $classes = $this->sortClassesByDate($classes);
                \set_transient(self::TRANSIENT_NAME, $classes, self::TRANSIENT_TIMEOUT); // One hour expiration time
            }
        }
        return $classes;
    }

    /**
     * display_classes
     *
     * Gets MindBody data, sorts it and returns it
     *
     * @param int $max_items Maximum number of items to return
     */
    private function display_classes($max_items) {
        $this->_parms['PageSize'] = $max_items;
        $classes = $this->get_classes();

        if (!empty($classes)) {
            echo '<ol>';
            foreach ($classes as $class) {
                $sTime = \strtotime($class['StartDateTime']);
                $eTime = \strtotime($class['EndDateTime']);
                $startDateTime = \esc_html(\date('F j \@ g:ia', $sTime));
                $sDateTime = \esc_attr(\date('c', $sTime));
                $endDateTime = \esc_html(\date('g:ia', $eTime));
                $eDateTime = \esc_attr(\date('c', $eTime));
                $class_id = \rawurlencode($class['ID']);
                $className = $class['ClassDescription']['Name'];
                $classUrlName = \rawurlencode(\strtolower($className));
                $className = \esc_html($className);
                $linkURL = \site_url("class/{$classUrlName}/{$class_id}/");
                $is_canceled = ($class['IsCanceled'] == true) ? ' canceled' : '';
                if (!empty($is_canceled)) {
                    $className .= ' - Canceled';
                }

                echo '<li class="h-event" itemtype="http://schema.org/Event" itemscope="" itemprop="event">';
                echo "<h4 class=\"entry-title{$is_canceled}\"><a rel=\"bookmark\" class=\"u-url\" itemprop=\"url\" href=\"{$linkURL}\">";
                echo "<span itemprop=\"name\">{$className}</span></a></h4>";
                echo"<span class=\"{$is_canceled}\"><span content=\"{$sDateTime}\" itemprop=\"startDate\">{$startDateTime}</span> - <span content=\"{$eDateTime}\" itemprop=\"endDate\">{$endDateTime}</span></span></li>";

                if (--$max_items === 0) {
                    break;
                }
            }
        }
        echo '</ol>';
    }

    /**
     * sortClassesByDate
     *
     * Simple sorting of classes by StartDateTime and Class ID
     *
     * @param array $classes  Array of classes
     * @return array Sorted array
     */
    private function sortClassesByDate($classes = array()) {
        $classesByDate = array();
        foreach ($classes as $class) {
            $classHash = \date('U', \strtotime($class['StartDateTime'])) . \sprintf('%08d', $class['ID']);
            $classesByDate[$classHash] = $class;
        }
        ksort($classesByDate);
        return $classesByDate;
    }

    /**
     * Register and load the widget
     */
    static function load_widget() {
        \register_widget(__CLASS__);
    }

}

\add_action('widgets_init', array('mbo_widget', 'load_widget'));

/**
 * mbo_class_page
 *
 * Display details of a single class/event
 *
 */
class mbo_class_page {

    const TRANSIENT_PREFIX = 'class';
    const TRANSIENT_TIMEOUT = 3600;

    private $_mbo_creds = array('SourceName' => '', 'Password' => '', 'SiteIDs' => array());
    private static $_class = array();

    /**
     * __construct
     *
     */
    function __construct() {
        // Read MindBody API credentials, if they exist
        $this->_mbo_creds['SourceName'] = \get_option(mbo_widget::OPT_SOURCE_NAME, '');
        $this->_mbo_creds['Password'] = \get_option(mbo_widget::OPT_PASSWORD, '');
        $this->_mbo_creds['SiteIDs'] = \get_option(mbo_widget::OPT_SITE_IDS, array());

        // Make sure style sheet gets loaded
        \add_action('wp_enqueue_scripts', array(__CLASS__, 'queue_style'), 20);
    }

    /**
     * queue_style
     *
     * Extra step needed to make stylesheet load late
     */
    static function queue_style() {
        \wp_enqueue_style('mindbody-calendar', \trailingslashit(\get_stylesheet_directory_uri()) . 'style-mindbody.css');
    }

    /**
     * get_class
     * Extract a single class from transient cache of MindBody data
     *
     * @param int $id Class id to extract
     * @return array
     */
    function get_class($id) {

        // First search for saved widget data
        $data = \get_transient(mbo_widget::TRANSIENT_NAME);
        if (false !== $data) {
            $class = $this->search_data($id, $data);
            if (!empty($class)) {
                return $class;  // Return class if found
            }
        }

        // Next, search for saved calendar data
        $transients = $this->get_calendar_transients();
        foreach ($transients as $name) {
            $name = \str_replace('_transient_', '', $name);
            $data = \get_transient($name);
            if (false !== $data) {
                $class = $this->search_data($id, $data);
                if (!empty($class)) {
                    return $class;  // Return class if found
                }
            }
        }

        // Next, check for saved individual class ID MindBody query
        $data = \get_transient(self::TRANSIENT_PREFIX . $id);
        if (!empty($data)) {
            return $data;
        }

        // Last, make a new MindBody query on the class ID
        // Setup SOAP call parameters
        // Class filter only seems to work with start and end datetime set
        // Using +/- one year as search range
        $_parms = array(
            'PageSize' => 10,
            'CurrentPageIndex' => 0,
            'XMLDetail' => 'Full',
            'ClassIDs' => array(\intval($id)),
            'StartDateTime' => \date('c', \strtotime('now - 1 year')),
            'EndDateTime' => \date('c', \strtotime('now + 1 year')),
            'HideCanceledClasses' => false,
        );

        $mb = new \Mindbody\MB_API($this->_mbo_creds);
        $data = $mb->GetClasses($_parms);
        if (!empty($data['GetClassesResult']['Classes']['Class'])) {
            $data = $data['GetClassesResult']['Classes']['Class'];
        }
        // If more than one class returned, search them all
        if (!isset($data['ID'])) {
            $data = $this->search_data($id, $data);
        }

        // If we got valid data, save it for later use
        if (!empty($data) && isset($data['ID'])) {
            \set_transient(self::TRANSIENT_PREFIX . $id, $data, self::TRANSIENT_TIMEOUT);
            return $data;
        }

        return array();
    }

    /**
     * get_calendar_transients()
     * Get all calendar transients in the database
     *
     * @global \wpdb $wpdb
     */
    private function get_calendar_transients() {
        global $wpdb;
        $prefix = mbo_calendar::TRANSIENT_PREFIX;

        $sql = "SELECT `option_name` AS `name` FROM {$wpdb->options} WHERE `option_name` LIKE '_transient_{$prefix}%'";
        return $wpdb->get_col($sql);
    }

    /**
     * search_data
     *
     * @param array $data
     * @return array
     */
    private function search_data($id, $data) {
        // If data is a valid single class/event, make class array with one element
        if (isset($data['ID'])) {
            $data = array(0 => $data);
        }

        // Search data for matching class ID
        foreach ($data as $class) {
            if (!isset($class['ID'])) {
                break;
            }
            if ($class['ID'] == $id) {
                return $class;
            }
        }

        // Not found, return empty array
        return array();
    }

    /**
     * encodeURIComponent
     * PHP equivalent of Javascript encodeURIComponent()
     *
     * @param string $str
     * @return string
     */
    function encodeURIComponent($str) {
        $revert = array('%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')');
        return strtr(rawurlencode($str), $revert);
    }

    /**
     * display_class
     * Display a single class info page
     *
     */
    function display_class() {

        // Get class data loaded during redirect
        $class = self::$_class;
        if (empty($class)) {
            return;
        }

        // Extract and format data to be displayed
        $class_description = $class['ClassDescription'];
        $location = $class['Location'];

        $start = \strtotime($class['StartDateTime']);
        $end = \strtotime($class['EndDateTime']);

        $schedule = \esc_html(\date('g:ia', $start) . ' - ' . \date('g:ia', $end));

        $start_date = \esc_html(\date('F j', $start));
        $start_datetime = \esc_html(\date('F j \@ g:ia', $start));
        $start_cdate = \esc_attr(\date('Y-m-d', $start));
        $start_ctime = \esc_attr(\date('c', $start));

        $end_datetime = \date('g:ia', $end);
        $end_ctime = \esc_attr(\date('c', $end));

        $is_canceled = ($class['IsCanceled'] == true) ? ' canceled' : '';
        $class_name = \esc_html($class_description['Name']);
        if (!empty($is_canceled)) {
            $class_name .= ' - Canceled';
        }

        $loc_id = \esc_attr($location['ID']);
        $sTG = \esc_attr($class_description['Program']['ID']);
        $studioid = \esc_attr($location['SiteID']);
        $schedId = \esc_attr($class['ClassScheduleID']);
        $sType = -7;

        $linkURL = \esc_url("https://clients.mindbodyonline.com/ws.asp?sDate={$class['StartDateTime']}&sLoc={$loc_id}&sTG={$sTG}&sType={$sType}&sclassid={$schedId}&studioid={$studioid}");

        $street_address = \esc_html($location['Address']);
        $address_region = \esc_html("{$location['City']}, {$location['StateProvCode']} {$location['PostalCode']}");
        $gmap_link = 'https://maps.google.com/?q=' . $this->encodeURIComponent("{$street_address} {$address_region}");

        $phone = $location['Phone'];
        if (\preg_match('/^(\d{3})(\d{3})(\d{4})$/', $phone, $matches)) {
            $phone = "({$matches[1]}) {$matches[2]}-{$matches[3]}";
        }
        $phone = \esc_html($phone);

        // Container has schema.org Event data
        echo '<div class="h-event event-details" itemtype="http://schema.org/Event" itemscope="" itemprop="event">';

        // Title and time
        echo "<h1 class=\"entry-title{$is_canceled}\" itemprop=\"name\">{$class_name}</h1>";
        echo "<h2 class=\"event-schedule{$is_canceled}\">";
        echo "<span content=\"{$start_ctime}\" itemprop=\"startDate\">{$start_datetime}</span> - ";
        echo "<span content=\"{$end_ctime}\" itemprop=\"endDate\">{$end_datetime}</span></h2>";

        // Container for details
        echo '<div class="info-box">';

        // Event details
        echo '<div class="section details"><h3 class="section-title">Details</h3>';
        echo '<dl><dt>Date:</dt><dd><abbr class="event-dtstart" title="' . $start_cdate . '">' . $start_date . '</abbr></dd>';
        echo '<dt>Time:</dt><dd><abbr class="event-dtstart" title="' . $start_cdate . '">' . $schedule . '</abbr></dd>';
        echo '<dt>Event Type:</dt><dd>' . \esc_html($class_description['Program']['ScheduleType']) . '</dd>';
        echo '</dl></div>';

        // Venue details
        echo '<div class="section venue"  itemscope="" itemtype="http://schema.org/Corporation"><h3 class="section-title">Venue</h3>';
        echo '<dl><dd class="event-venue" itemprop="name">' . \esc_html($location['Name']) . '</dd>';
        echo '<dd class="venue-location"><address class="event-address" itemprop="address" itemscope="" itemtype="http://schema.org/PostalAddress" >';
        echo '<span class="street-address" itemprop="streetAddress">' . $street_address . '</span><br />';
        echo '<span class="address-region" itemprop="addressLocality">' . $address_region . '</span><br />';
        echo '<a class="event-map-link" href="' . $gmap_link . '" target="_blank" title="Click to view Google Map">+Google Map</a>';
        echo '</address></dd>';
        echo '<dt>Phone:</dt><dd itemprop="telephone">' . $phone . '</dd>';
        if (empty($is_canceled)) {
            echo '<dd><a href="' . $linkURL . '" target="_blank" title="Click to sign up at MindBody Online">Click to Sign-up with "MindBody Online"</a></dd>';
        }
        echo '</dl></div>';

        // Embedded Google Map
        $embed_address = "{$location['Name']} {$street_address} {$address_region}";
        echo '<div class="section venuemap">';
        echo '<iframe src="https://maps.google.com/maps?&q=' . $embed_address . '&output=embed" width="100%" height="100%" frameborder="0" style="border:4px solid #eee; border-radius:4px" allowfullscreen></iframe>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * add_query_vars
     *
     * Rewrite support for PayPal IPN
     *
     * @param array $query_vars
     * @return string
     */
    static function add_query_vars($query_vars) {
        $query_vars[] = 'class';
        $query_vars[] = 'id';
        return $query_vars;
    }

    /**
     * flush_rewrites
     * Flush our custom rewrite rules
     */
    static function flush_rewrites() {
        \flush_rewrite_rules(true);
    }

    /**
     * redirect
     * Redirect callback.
     *
     * @global WP_Query $wp_query
     * @return void|int Void if invalid redirect. Exits zero if valid redirect received
     */
    static function redirect() {

        // handle class info display
        $id = \get_query_var('id');
        if (!empty($id)) {
            // See if class exists
            $instance = new self();
            self::$_class = $instance->get_class($id);
            if (empty(self::$_class)) {
                // Throw 404, if not valid class ID
                global $wp_query;
                $wp_query->set_404();
                \status_header(404);
                \nocache_headers();
            } else {
                //redirect to class template
                \add_filter('template_include', array(__CLASS__, 'include_class_template'), 1);
            }
        }
    }

    /**
     * include_class_template
     * Replaces the default post template with our template
     *
     * @param string $template
     * @return string
     */
    static function include_class_template($template) {

        $new_template = \locate_template(array('page-class.php'));
        if (!empty($new_template)) {
            return $new_template;
        }

        return $template;
    }

    /**
     * add_rewrites
     * Add custom rewrite rules so plugin can receive IPN messages
     */
    static function add_rewrites() {
        \add_rewrite_rule('^class/([^/]*)/([^/]*)/?', 'index.php?id=$matches[2]', 'top');

        \add_action('query_vars', array(__CLASS__, 'add_query_vars'));
        \add_action('admin_init', array(__CLASS__, 'flush_rewrites'));
        \add_action('template_redirect', array(__CLASS__, 'redirect'));
    }

}

\add_action('init', array('mbo_class_page', 'add_rewrites'));

/**
 * mbo_calendar class
 *
 * Builds and displays calendar of MindBody event data
 *
 */
class mbo_calendar {

    const TRANSIENT_TIMEOUT = 7200; // Two hour expiration time
    const TRANSIENT_PREFIX = 'cal'; // Used to create transient name
    const NONCE_HANDLE = 'cal_nonce'; // Used for calendar navigation
    const NONCE_NAME = '_nonce'; // Element name used to submit nonce
    const ACTION_HANDLE = 'mbocalendar'; //AJAX action handle

    // Mindbody API credentials

    private $_mbo_creds = array('SourceName' => '', 'Password' => '', 'SiteIDs' => array());

    /**
     * __construct
     * Object constructor
     *
     */
    function __construct() {
        // Read MindBody API credentials from widget, if they exist
        $this->_mbo_creds['SourceName'] = \get_option(mbo_widget::OPT_SOURCE_NAME, '');
        $this->_mbo_creds['Password'] = \get_option(mbo_widget::OPT_PASSWORD, '');
        $this->_mbo_creds['SiteIDs'] = \get_option(mbo_widget::OPT_SITE_IDS, array());

        // Make sure style sheet gets loaded, load other dependancies in footer
        \add_action('get_footer', array(__CLASS__, 'queue_actions'), 20);
    }

    /**
     * queue_actions
     *
     */
    static function queue_actions() {
        // Queue CSS for calendar and widget
        \wp_enqueue_style('mbo-calendar', \trailingslashit(\get_stylesheet_directory_uri()) . 'style-mindbody.css');

        // Put required javascript in footer
        \wp_enqueue_script('mbo-calendar-js', \trailingslashit(\get_stylesheet_directory_uri()) . 'mindbody-calendar.js', array('jquery'), false, true);
        \wp_localize_script('mbo-calendar-js', 'cal_ajax', array('ajaxurl' => admin_url('admin-ajax.php'), 'action' => self::ACTION_HANDLE));
    }

    /**
     * add_actions
     *
     * Add ajax actions
     *
     */
    static function add_actions() {
        // add function that handles the AJAX request for priv and non-priv users
        if (defined('DOING_AJAX') && DOING_AJAX) {
            add_action('wp_ajax_' . self::ACTION_HANDLE, array(__CLASS__, 'do_ajax'));
            add_action('wp_ajax_nopriv_' . self::ACTION_HANDLE, array(__CLASS__, 'do_ajax'));
        }
    }

    /**
     * do_ajax()
     *
     * Handle ajax calls for calendar navigation
     */
    static function do_ajax() {
        // Look for ajax data
        if (isset($_REQUEST['timstamp']) && isset($_REQUEST[self::NONCE_NAME])) {
            // Verify nonce
            \check_ajax_referer(self::NONCE_HANDLE, self::NONCE_NAME, true);

            // Get month and year from date data
            $timestamp = \intval($_REQUEST['timstamp']);
            $month = \date('F', $timestamp);
            $year = \date('Y', $timestamp);

            // Return calendar for requested month/year
            $instance = new mbo_calendar();
            echo $instance->create_calendar($month, $year);
        }
    }

    /**
     * get_header
     * Returns weekday names as table header
     *
     * @return string
     */
    function get_header() {
        $days = array(
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday'
        );
        $week_day = \get_option('start_of_week');
        $header = '<tr>';

        for ($day_number = 0; $day_number < 7; $day_number++) {
            $header .= "<th title=\"{$days[$week_day]}\">{$days[$week_day++]}</th>";
            if ($week_day === 7) {
                $week_day = 0;
            }
        }
        $header .= '</tr>';

        return $header;
    }

    /**
     * get_calendar_days
     * Set up parameters needed for calendar display of requested month & year
     *
     * @param string $month Name of month to get calendar paramters for
     * @param int $year Year of month to get calendar parameters for (valid only 2000 to 2100)
     */
    function get_calendar_days($month = NULL, $year = NULL) {
        // Get timestamp for first day of requested month,
        // or current month if no valid month and year are specified
        $date_query = "first day of {$month} {$year}";
        $date_query = \strtotime($date_query);
        if ($date_query === false) {
            $date_query = \strtotime();
        }
        // Get number of days in month
        $days_in_month = \date('t', $date_query);

        // Use WordPress function find start and end days of first week in calendar
        $week = \get_weekstartend(\date('c', $date_query));
        $start_date = \date('m/d/Y', $week['start']);
        $start_day = \intval(\date('j', $week['start']));
        // Get number of days in month shown at start of first calendar week.
        // May or may not be previous month
        $days_in_start_month = \intval(\date('t', $week['start']));

        // Build array of weeks
        $weeks = array();
        // If calendar start day is not 1,
        // then calendar shows part of previous month.
        // Fill in displayed days of previous month
        if ($start_day != 1) {
            $weekdays = array();
            $is_current = 0;
            for ($day = 0; $day < 7; $day++) {
                $weekdays[] = array('day' => $start_day,
                    'date' => \date('Y-m-d 00:00:00.00', \strtotime("{$start_date} + " . $day . ' days')),
                    'events' => array(),
                    'is_current' => $is_current);  // Allows CSS to fade previous month display

                $start_day++;

                if ($start_day > $days_in_start_month) {
                    $start_day = 1;
                    $is_current = 1;
                }
            }
            $weeks[] = $weekdays;
        }
        // Fill in weekdays of each week in requested calendar month
        while ($start_day <= $days_in_month) {
            $weekdays = array();
            for ($day = 0; $day < 7; $day++) {
                $weekdays[] = array('day' => $start_day,
                    'date' => \date('Y-m-d 00:00:00.00', \strtotime("{$month} {$start_day} {$year}")),
                    'events' => array(),
                    'is_current' => 1);

                $start_day++;

                // If $start_day > $days_in_month then start of next month is
                // displayed on calendar.  Fill in those days until end of current week
                if ($start_day > $days_in_month) {
                    $start_day = 1;
                    $day++;
                    while ($day < 7) {
                        $weekdays[] = array('day' => $start_day,
                            'date' => \date('Y-m-d 00:00:00.00', \strtotime("{$month} {$days_in_month} {$year} + {$start_day} days")),
                            'events' => array(),
                            'is_current' => 0);  // Mark these days as not requested month
                        $start_day++;
                        $day++;
                    }
                    $start_day = $days_in_month + 1;  // Make sure we break out of outer loop
                    break;
                }
            }
            $weeks[] = $weekdays;  // Save weekday data to weeks array
        }

        return $weeks;
    }

    /**
     * fill_calendar_days
     *
     * @param array $weeks  Passed by reference and modified
     */
    function fill_calendar_days(&$weeks) {
        // Get start and end times of calendar
        $start_datetime = $weeks[0][0]['date'];
        $end_datetime = \str_replace('00:00:00.00', '23:59:59.99', $weeks[\count($weeks) - 1][6]['date']);

        $classes = $this->get_classes($start_datetime, $end_datetime);

        // Go through each week
        foreach ($weeks as $week_key => $week) {
            // Go through each day in week
            foreach ($week as $day_key => $day) {
                $timestamp = \date('Y-m-d', \strtotime($day['date']));
                // Search class data for all classes happening on this day
                foreach ($classes as $class_key => $class) {
                    if ($class['StartDate'] === $timestamp) {
                        $weeks[$week_key][$day_key]['events'][] = $class;  // Save class data in this day's data
                        unset($classes[$class_key]); // Unset this data so we don't search through it again
                    }
                }
            }
        }
    }

    /**
     * create_calendar
     * Create a calendar table for the requested month and year,
     * or current month if none is specified.
     *
     * @param string $month
     * @param string $month Name of month
     * @param int $year Year of month (accepts only 2010 to 2050)
     * @return string HTML for calendar
     */
    function create_calendar($month = NULL, $year = NULL) {
        // Make sure we have month and year set
        $now = \strtotime(\current_time('mysql'));
        if (empty($month)) {
            $month = \date('F', $now);
        }
        if (empty($year) || $year != \intval($year) || $year < 2010 || $year > 2050) {
            $year = \intval(\date('Y', $now));
        }

        $prev_monthyear = \strtotime("{$month} 7, {$year} - 1 month");
        $prev_month = date('F', $prev_monthyear);

        $next_monthyear = \strtotime("{$month} 7, {$year} + 1 month");
        $next_month = date('F', $next_monthyear);

        $nonce = (string) \wp_create_nonce(self::NONCE_HANDLE);

        // Start building calendar HTML
        $calendar = '<div id="calendar-content">';
        $calendar .= "<h2>Calendar of Events for {$month} {$year}</h2>";
        $calendar .= "<div class=\"month prev\"><a data=\"{$prev_monthyear}_{$nonce}\"><<< {$prev_month}</a></div>";
        $calendar .= "<div class=\"month next\"><a data=\"{$next_monthyear}_{$nonce}\">{$next_month} >>></a></div>";
        $calendar .= '<table class="calendar"><thead>';
        $calendar .= $this->get_header();
        $calendar .= '</thead><tbody>';

        // Build array of calendar days to be displayed and fill with class data
        $weeks = $this->get_calendar_days($month, $year);
        $this->fill_calendar_days($weeks);

        // Loop through each week
        foreach ($weeks as $week) {
            $calendar .= '<tr>';
            // Loop through each day of the week
            foreach ($week as $day) {
                // Set a CSS class for this day to indicate if it is outside
                // the request month (revious month, or next month)
                $css = ($day['is_current']) ? '' : ' class="outside"';
                $calendar .= "<td><div{$css}>{$day['day']}</div>";

                // Loop through all events on this day
                $events = $day['events'];
                foreach ($events as $event) {
                    $class_id = \rawurlencode($event['ID']);
                    $className = \esc_html($event['ClassDescription']['Name']);
                    $classUrlName = \rawurlencode(\strtolower($className));
                    $className = \esc_html($className);
                    $linkURL = \site_url("class/{$classUrlName}/{$class_id}/");
                    $is_canceled = ($event['IsCanceled'] == true) ? ' canceled' : '';
                    if (!empty($is_canceled)) {
                        $className .= ' - Canceled';
                    }

                    $calendar .= "<div class=\"event{$is_canceled}\"><h3{$css}><a href=\"{$linkURL}\"><span class=\"time\">" . \date('g:ia', \strtotime($event['StartDateTime'])) . '</span><br />';
                    $calendar .= $className . '</a></h3></div>';
                }
                $calendar .= '</td>';
            }
            $calendar .= '</tr>';
        }

        $calendar .= '</tbody></table></div>';
        return $calendar;
    }

    /**
     * get_classes
     * Get class data for all days displayed on calendar
     *
     * @param string $start
     * @param string $end
     * @return array
     */
    private function get_classes($start, $end) {
        // Make transient name from start and end dates (remove time data)
        $_trans_name = \str_replace(array(' ', '-', '00', '23', '59', '99', ':', '.'), '', self::TRANSIENT_PREFIX . $start . $end);

        $data = \get_transient($_trans_name);
        if (false === $data) {
            // Setup SOAP call parameters
            $_parms = array(
                'StartDateTime' => \date('c', \strtotime($start)),
                'EndDateTime' => \date('c', \strtotime($end)),
                'HideCanceledClasses' => false,
                'PageSize' => 0, // get everything all at once
                'CurrentPageIndex' => 0,
                'XMLDetail' => 'Full'
            );

            $mb = new \Mindbody\MB_API($this->_mbo_creds);
            $data = $mb->GetClasses($_parms);

            if (!empty($data['GetClassesResult']['Classes']['Class'])) {
                $data = $data['GetClassesResult']['Classes']['Class'];

                // Sort the class data by StartDateTime stamp + Class ID
                $classesByDate = array();
                foreach ($data as $class) {
                    $timestamp = \strtotime($class['StartDateTime']);
                    $classHash = \date('U', $timestamp) . \sprintf('%08d', $class['ID']);
                    $class['StartDate'] = \date('Y-m-d', $timestamp);
                    $classesByDate[$classHash] = $class;
                }
                ksort($classesByDate);
                $data = $classesByDate;

                // Save the sorted data as a transient
                \set_transient($_trans_name, $data, self::TRANSIENT_TIMEOUT);
            }
        }

        return $data;
    }

}

add_action('init', array('mbo_calendar', 'add_actions'));

