<?php

/**
 * The report class
 *
 *
 * @since      1.0.16
 * @package    LiteSpeed_Cache
 * @subpackage LiteSpeed_Cache/admin
 * @author     LiteSpeed Technologies <info@litespeedtech.com>
 */
class LiteSpeed_Cache_Admin_Report extends LiteSpeed{
	protected static $_instance;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.16
	 */
	protected function __construct(){
	}


	/**
	 * Gathers the environment details and creates the report.
	 * Will write to the environment report file.
	 *
	 * @since 1.0.12
	 * @access public
	 * @param mixed $options Array of options to output. If null, will skip
	 * the options section.
	 * @return string The built report.
	 */
	public function generate_environment_report($options = null){
		global $wp_version, $_SERVER;
		$home = LiteSpeed_Cache_Admin_Rules::get_home_path();
		$site = LiteSpeed_Cache_Admin_Rules::get_site_path();
		$paths = array($home);
		if ($home != $site) {
			$paths[] = $site;
		}

		if (is_multisite()) {
			$active_plugins = get_site_option('active_sitewide_plugins');
			if (!empty($active_plugins)) {
				$active_plugins = array_keys($active_plugins);
			}
		}
		else {
			$active_plugins = get_option('active_plugins');
		}

		if (function_exists('wp_get_theme')) {
			$theme_obj = wp_get_theme();
			$active_theme = $theme_obj->get('Name');
		}
		else {
			$active_theme = get_current_theme();
		}

		$extras = array(
			'wordpress version' => $wp_version,
			'locale' => get_locale(),
			'active theme' => $active_theme,
			'active plugins' => $active_plugins,

		);
		if (is_null($options)) {
			$options = LiteSpeed_Cache_Config::get_instance()->get_options();
		}

		if (!is_null($options) && is_multisite()) {
			$blogs = LiteSpeed_Cache::get_network_ids();
			if (!empty($blogs)) {
				foreach ($blogs as $blog_id) {
					$opts = get_blog_option($blog_id, LiteSpeed_Cache_Config::OPTION_NAME, array());
					if (isset($opts[LiteSpeed_Cache_Config::OPID_ENABLED_RADIO])) {
						$options['blog ' . $blog_id . ' radio select'] = $opts[LiteSpeed_Cache_Config::OPID_ENABLED_RADIO];
					}
				}
			}
		}

		$report = $this->build_environment_report($_SERVER, $options, $extras, $paths);
		$this->write_environment_report($report);
		return $report;
	}

	/**
	 * Hooked to the update options/site options actions. Whenever our options
	 * are updated, update the environment report with the new options.
	 *
	 * @since 1.0.12
	 * @access public
	 * @param $unused
	 * @param mixed $options The updated options. May be array or string.
	 */
	public function update_environment_report($unused, $options){
		if (is_array($options)) {
			$this->generate_environment_report($options);
		}
	}

	/**
	 * Write the environment report to the report location.
	 *
	 * @since 1.0.12
	 * @access private
	 * @param string $content What to write to the environment report.
	 */
	private function write_environment_report($content){
		$content = "<"."?php die();?".">\n\n".$content;

		$ret = LiteSpeed_Cache_Admin_Rules::file_save($content, false, LSWCP_DIR . 'environment_report.php', false);
		if ($ret !== true && defined('LSCWP_LOG')) {
			LiteSpeed_Cache::debug_log('LSCache wordpress plugin attempted to write env report but did not have permissions.');
		}
	}

	/**
	 * Builds the environment report buffer with the given parameters
	 *
	 * @access private
	 * @param array $server - server variables
	 * @param array $options - cms options
	 * @param array $extras - cms specific attributes
	 * @param array $htaccess_paths - htaccess paths to check.
	 * @return string The Environment Report buffer.
	 */
	private function build_environment_report($server, $options, $extras = array(), $htaccess_paths = array()){
		$server_keys = array(
			'DOCUMENT_ROOT'=>'',
			'SERVER_SOFTWARE'=>'',
			'X-LSCACHE'=>'',
			'HTTP_X_LSCACHE'=>''
		);
		$server_vars = array_intersect_key($server, $server_keys);

		$buf = $this->format_report_section('Server Variables', $server_vars);

		$buf .= $this->format_report_section('LSCache Plugin Options', $options);

		$buf .= $this->format_report_section('Wordpress Specific Extras', $extras);

		if (empty($htaccess_paths)) {
			return $buf;
		}

		foreach ($htaccess_paths as $path) {
			if (!file_exists($path) || !is_readable($path)) {
				$buf .= $path . " does not exist or is not readable.\n";
				continue;
			}
			$content = file_get_contents($path);
			if ($content === false) {
				$buf .= $path . " returned false for file_get_contents.\n";
				continue;
			}
			$buf .= $path . " contents:\n" . $content . "\n\n";
		}
		return $buf;
	}

	/**
	 * Creates a part of the environment report based on a section header
	 * and an array for the section parameters.
	 *
	 * @since 1.0.12
	 * @access private
	 * @param string $section_header The section heading
	 * @param array $section An array of information to output
	 * @return string The created report block.
	 */
	private function format_report_section($section_header, $section){
		$tab = '    '; // four spaces
		$nl = "\n";

		if (empty($section)) {
			return 'No matching ' . $section_header . $nl . $nl;
		}
		$buf = $section_header;
		foreach ($section as $key=>$val) {
			$buf .= $nl . $tab;
			if (!is_numeric($key)) {
				$buf .= $key . ' = ';
			}
			if (!is_string($val)) {
				$buf .= print_r($val, true);
			}
			else {
				$buf .= $val;
			}
		}
		return $buf . $nl . $nl;
	}

}