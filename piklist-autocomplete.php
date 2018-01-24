<?php
/*
Plugin Name: Piklist Autocomplete
Description: Adds autocomplete field to piklist, with and without ajax support
Version: 0.0.2
Author: Manuel Abadía
Plugin Type: Piklist
Text Domain: piklist-autocomplete
License: GPL2
*/

// if accessed directly, exit
if (!defined('ABSPATH')) {
	exit;
}

/**
 * The Piklist Autocomplete Plugin class
 */
class Piklist_Autocomplete_Plugin {
	private static $inst = null;

	/**
	 * Returns the one and only instance of this class
	 *
	 * @since 0.0.2
	 */
	public static function Instance()
    {
        if (self::$inst === null) {
			self::$inst = new self();

			// piklist plugin check
			add_action('init', array(self::$inst, 'check_for_piklist'));

			// scripts/styles registration
			add_filter('piklist_field_assets', array(self::$inst, 'field_assets'));

			// autocomplete behaviour
			add_filter('piklist_field_alias', array(self::$inst, 'field_alias'));
			add_filter('piklist_field_list_types', array(self::$inst, 'field_list_types'));
			add_filter("piklist_request_field", array(self::$inst, 'request_field'));
			add_filter("piklist_pre_render_field", array(self::$inst, 'pre_render_field'));
        }

        return self::$inst;
    }

	/**
	 * Private Constructor
	 *
	 * @since 0.0.2
	 */
	private function __construct() {
	}

	/**
	 * Checks that piklist is installed
	 *
	 * @return void
	 * @since 0.0.1
	 */
	function check_for_piklist(){
		if(is_admin()){
			include_once(plugin_dir_path( __FILE__ ) . 'class-piklist-checker.php');
	
			if (!piklist_checker::check(__FILE__)){
				return;
			}
		}
	}

	/**
	 * Sets the callback to register the resources for the autocomplete type
	 *
	 * @param array $field_assets The fields with its corresponding assets
	 * @return array The updated array
	 * @since 0.0.1
	 */
	function field_assets($field_assets) {
		$field_assets['autocomplete'] = array('callback' => array(self::$inst, 'render_field_assets'));

		return $field_assets;
	}

	/**
	 * Registers the CSS and JS files required for autocompletion to work properly
	 *
	 * @param string $type The field type
	 * @return void
	 * @since 0.0.1
	 */
	function render_field_assets($type) {
		wp_enqueue_style('piklist-autocomplete-select2', plugins_url('lib/css/select2.min.css', __FILE__));

		wp_enqueue_script('piklist-autocomplete-select2', plugins_url('lib/js/select2/select2.min.js', __FILE__), array('jquery'), false, true);
		wp_enqueue_script('piklist-autocomplete-setup', plugins_url('parts/js/select2-setup.js', __FILE__), array('piklist-autocomplete-select2'), false, true);

		/**
		* Notifies that is time to add additional assets related to the autocomplete field
		*
		* @since 0.0.2
		*/
		do_action('piklist_autocomplete_field_assets');
	}

	/**
	 * Add an alias from autocomplete to select
	 *
	 * @return void
	 * @since 0.0.2
	 */
	function field_alias($alias){
		$alias['autocomplete'] = 'select';

		return $alias;
	}

	/**
	 * Adds the autocomplete field to the field list types
	 *
	 * @param array $field_list_types The registered field types
	 * @return array The updated array
	 * @since 0.0.1
	 */
	function field_list_types($field_list_types) {
		if (!isset($field_list_types['multiple_value'])) {
			$field_list_types['multiple_value'] = array();
		}
		array_push($field_list_types['multiple_value'], 'autocomplete');

		return $field_list_types;
	}

	/**
	 * Performs the initialization for the autocomplete field
	 *
	 * @param array $field The settings for the field
	 * @return array The updated field
	 * @since 0.0.1
	 */
	function request_field($field) {
		if ($field['type'] == 'autocomplete') {
			if (!isset($field['autocomplete'])) {
				$field['autocomplete'] = array(
					'config' => array(),
					'query' => array()
				);
			} else {
				foreach(array('config', 'query') as $section) {
					if (!isset($field['autocomplete'][$section])) {
						$field['autocomplete'][$section] = array();
					}
				}
			}

			// sets the default config settings for non initialized entries
			static $default_config = array(
				'enable_ajax_loading' => null,		// enable ajax calls to get partial data
				'minimum_input_length' => 0,		// minimum input length to generate an ajax call
				'delay_between_calls' => 250,		// minimum delay between ajax calls
				'items_per_page' => 20,				// number of items returned per call (max 100)
				'url' => null,						// ajax url to call to get the associated data (if null we use the default REST API url)
				'language' => null,					// languaje used for the different messages displayed (if null we use the current one)
				'display_field_name' => null,		// field to show in the options
			);

			/**
			* Filters the default config options
			*
			* @param array $default_config The default config parameters
			* @param array $field The settings for the field
			*
			* @since 0.0.1
			*/
			$config_options = apply_filters('piklist_autocomplete_default_query_options', $default_config, $field);

			$field['autocomplete']['config'] = wp_parse_args($field['autocomplete']['config'], $config_options);

			// sets the default query settings for non initialized entries
			static $default_query = array(
				'order' => null,					// order sort attribute
				'orderby' => null,					// sort collection by object attribute
				'include' => null,					// limit result set to specific IDs
				'exclude' => null,					// ensure result set excludes specific IDs
				'before' => null,					// limit response to posts/comments published before a given ISO8601 compliant date
				'after' => null,					// limit response to posts/comments published after a given ISO8601 compliant date
				'slug' => null,						// limit result set to users with one or more specific slugs
				'status' => null,					// limit result set to posts assigned one or more statuses
				'type' => null,						// type to query
			);

			/**
			* Filters the default query options
			*
			* @param array $default_query The default query parameters
			* @param array $field The settings for the field
			*
			* @since 0.0.1
			*/
			$query_options = apply_filters('piklist_autocomplete_default_query_options', $default_query, $field);

			$field['autocomplete']['query'] = wp_parse_args($field['autocomplete']['query'], $query_options);
		}

		return $field;
	}

	/**
	 * The main functionality of the autocomplete field is here
	 *
	 * @param array $field The settings for the field
	 * @return array The updated field
	 * @since 0.0.1
	 */
	function pre_render_field($field) {
		if ($field['type'] == 'autocomplete') {

			$attributes =& $field['attributes'];
			$autocomplete =& $field['autocomplete'];

			$query_url = '/wp/v2/';
			$query_entity = 'posts';
			$display_field_name = 'title.rendered';

			// resolves ajax url if not set
			if (!isset($autocomplete['config']['url'])) {
				if (isset($field['relate']['scope'])) {
					switch ($field['relate']['scope']) {
						case 'user':
						case 'user_meta':
							$query_entity = 'users';
							$display_field_name = 'name';
							break;
						case 'comment':
						case 'comment_meta':
							$query_entity = 'comments';
							$display_field_name = 'content.rendered';
							break;
					}
				}

				if (isset($autocomplete['query']['type'])) {
					$query_entity = $autocomplete['query']['type'];
					unset($autocomplete['query']['type']);
				}

				$query_url = $query_url . $query_entity;
				$autocomplete['config']['url'] = get_home_url(null, '/wp-json') . $query_url;
			} else {
				$query_url = $autocomplete['config']['url'];
			}

			// sets the display field name if not set by the user
			if (!isset($autocomplete['config']['display_field_name'])) {
				$autocomplete['config']['display_field_name'] = $display_field_name;
			}

			// if not set by the user, enables ajax loading if there are choices set
			if (!isset($autocomplete['config']['enable_ajax_loading'])) {
				$autocomplete['config']['enable_ajax_loading'] = !(isset($field['choices']) && is_array($field['choices']));
			}

			if ($autocomplete['config']['enable_ajax_loading']) {
				// for a field with saved values, retrieves only those values
				if (!isset($field['value']) || empty($field['value'])) {
					$field['choices'] = array();
				} else {
					// otherwise performs a REST request with the required values
					$rest_request = new WP_REST_Request('GET', $query_url);
					$rest_request->set_query_params(array(
						'include' => $field['value'],
						'per_page' => 100
					));

					/**
					* Filters the REST request to fetch the selected values
					*
					* @param WP_REST_Request $rest_request The REST request
					* @param array $field The settings for the field
					*
					* @since 0.0.1
					*/
					$rest_request = apply_filters('piklist_autocomplete_value_rest_request', $rest_request, $field);

					global $post;
					$current_post = $post;

					$rest_response = rest_do_request($rest_request);
		
					if ($rest_response->is_error()) {
						$error = $rest_response->as_error();
						echo(printf('<p>An error occurred: %s (%d) - code %s</p>', 
							$error->get_error_message(), $error->get_error_data(), $error->get_error_code()));
					}

					/**
					* Filters the REST response to fetch the selected values
					*
					* @param WP_REST_Response $rest_response The REST response
					* @param array $field The settings for the field
					*
					* @since 0.0.1
					*/

					$rest_response = apply_filters('piklist_autocomplete_rest_response', $rest_response, $field);
		
					$field['choices'] = piklist($rest_response->get_data(), array('id', $autocomplete['config']['display_field_name']));

					$GLOBALS['post'] = $current_post;
					setup_postdata($current_post);
				}
			}

			$query_parameters = $autocomplete['query'];

			/**
			* Filters the parameters that will be passed to the REST request
			*
			* @param array $query_paramters The parameters read from the field configuration
			* @param array $field The settings for the field
			*
			* @since 0.0.2
			*/
			$query_parameters = apply_filters('piklist_autocomplete_rest_query_paramters', $query_parameters, $field);

			if (!empty(implode(null, $query_parameters))) {
				$autocomplete['config']['url'] = $autocomplete['config']['url'] . '?' . http_build_query($query_parameters);
			}

			array_push($attributes['class'], 'piklist-autocomplete');

			// maps the placeholder attribute to the associated property of the select2 control
			if (isset($attributes['placeholder'])) {
				$attributes['data-placeholder'] = $attributes['placeholder'];
				unset($attributes['placeholder']);
			}

			// sets the language. Notes: 
			// 	- if lang attribute is set in HTML, it takes precedence over the language parameter
			// 	- you should enqueue the associated language script in order to see the messages translated. For example:
			//		wp_enqueue_script('select2-es', plugins_url('lib/js/select2/i18n/es.js', __FILE__), array('piklist-autocomplete-select2'), false, true);
			if (!isset($autocomplete['config']['language'])) {
				$autocomplete['config']['language'] = strstr(get_locale(), '_', true);
			}

			// the current implementation uses select2 but that could change in the future,
			// so we use friendly names for the configuration
			static $data_mappings = array(
				'enable_ajax_loading' => 'enable-ajax',
				'minimum_input_length' => 'minimum-input-length',
				'delay_between_calls' => 'delay',
				'items_per_page' => 'items-per-page',
				'url' => 'autocomplete-url',
				'language' => 'language',
				'display_field_name' => 'display-field-name',
			);

			// save the data values to configure the field
			foreach($data_mappings as $key => $val) {
				if (isset($autocomplete['config'][$key])) {
					if (is_array($autocomplete['config'][$key])) {
						$attributes['data-' . $val] = json_encode($autocomplete['config'][$key]);
					} else {
						$attributes['data-' . $val] = $autocomplete['config'][$key];
					}
				}
			}
		}
		return $field;
	}
}

// creates the one an only instance of this plugin
Piklist_Autocomplete_Plugin::Instance();
?>