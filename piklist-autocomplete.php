<?php
/*
Plugin Name: Piklist Autocomplete
Description: Adds autocomplete field to piklist, with and without ajax support
Version: 0.0.1
Author: Manuel Abadía
Plugin Type: Piklist
License: GPL2
*/

/* piklist plugin check */

add_action('init', 'piklist_autocomplete_check_for_piklist');

/**
 * Checks that piklist is installed
 *
 * @return void
 * @since 0.0.1
 */
function piklist_autocomplete_check_for_piklist(){
	if(is_admin()){
   		include_once(plugin_dir_path( __FILE__ ).'class-piklist-checker.php');
 
		if (!piklist_checker::check(__FILE__)){
			return;
   		}
  	}
}

/* scripts/styles registration */

add_filter("piklist_field_assets", "piklist_autocomplete_field_assets");

/**
 * Sets the callback to register the resources for the autocomplete type
 *
 * @param array $field_assets The fields with its corresponding assets
 * @return array The updated array
 * @since 0.0.1
 */
function piklist_autocomplete_field_assets($field_assets) {
	$field_assets['autocomplete'] = array('callback' => 'piklist_autocomplete_render_field_assets');

	return $field_assets;
}

/**
 * registers the CSS and JS files required for autocompletion to work properly
 *
 * @param string $type The field type
 * @return void
 * @since 0.0.1
 */
function piklist_autocomplete_render_field_assets($type) {
	wp_enqueue_style('piklist-autocomplete-select2', plugins_url('lib/css/select2.min.css', __FILE__));

	wp_enqueue_script('piklist-autocomplete-select2', plugins_url('lib/js/select2/select2.min.js', __FILE__), array('jquery'), false, true);
	wp_enqueue_script('piklist-autocomplete-setup', plugins_url('parts/js/select2-setup.js', __FILE__), array('piklist-autocomplete-select2'), false, true);
}

/* autocomplete behaviour */

add_filter("piklist_field_list_types", "piklist_autocomplete_field_list_types");

/**
 * Adds the autocomplete field to the field list types
 *
 * @param array $field_list_types The registered field types
 * @return array The updated array
 * @since 0.0.1
 */
function piklist_autocomplete_field_list_types($field_list_types) {
	array_push($field_list_types['multiple_value'], 'autocomplete');

	return $field_list_types;
}

add_filter("piklist_request_field", "piklist_autocomplete_request_field");

/**
 * Performs the initialization for the autocomplete field
 *
 * @param array $field The settings for the field
 * @return array The updated field
 * @since 0.0.1
 */
function piklist_autocomplete_request_field($field) {
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

		// sets the default config settings
      	$field['autocomplete']['config'] = wp_parse_args($field['autocomplete']['config'], array(
      		'enable_ajax_loading' => null,		// enable ajax calls to get partial data
      		'minimum_input_length' => 0,		// minimum input length to generate an ajax call
      		'delay_between_calls' => 250,		// minimum delay between ajax calls
      		'items_per_page' => 20,				// number of items returned per call (max 100)
      		'url' => null,						// ajax url to call to get the associated data (if null we use the default REST API url)
      		'language' => null,					// languaje used for the different messages displayed (if null we use the current one)
      		'display_field_name' => null,		// field to show in the options
      	));

		// sets the default query settings
      	$field['autocomplete']['query'] = wp_parse_args($field['autocomplete']['query'], array(
      		'order' => null,					// order sort attribute
      		'orderby' => null,					// sort collection by object attribute
      		'include' => null,					// limit result set to specific IDs
      		'exclude' => null,					// ensure result set excludes specific IDs
      		'before' => null,					// limit response to posts/comments published before a given ISO8601 compliant date
      		'after' => null,					// limit response to posts/comments published after a given ISO8601 compliant date
      		'slug' => null,						// limit result set to users with one or more specific slugs
      		'status' => null,					// limit result set to posts assigned one or more statuses
      		'post_type' => null,				// post type to query for custom post types
      	));
    }
	return $field;
}

add_filter("piklist_pre_render_field", "piklist_autocomplete_pre_render_field");

/**
 * The main functionality of the autocomplete field is here
 *
 * @param array $field The settings for the field
 * @return array The updated field
 * @since 0.0.1
 */

function piklist_autocomplete_pre_render_field($field) {
	if ($field['type'] == 'autocomplete') {

		$attributes =& $field['attributes'];
		$autocomplete =& $field['autocomplete'];

		$query_url = '/wp/v2/';
		$query_entity = 'posts';
		$display_field_name = 'title';

		// resolves ajax url if not set
		if (!isset($autocomplete['config']['url'])) {
	      	if (isset($field['relate']['scope'])) {
		      	switch ($field['relate']['scope']) {
					case 'user':
					case 'user_meta':
			      		$query_entity = 'comments';
			      		$display_field_name = 'name';
			      		break;
				    case 'comment':
	      			case 'comment_meta':
			      		$query_entity = 'users';
			      		$display_field_name = 'content';
			      		break;
			     }
			}

			if ($query_entity == 'posts' && isset($autocomplete['query']['post_type'])) {
				$query_entity = $autocomplete['query']['post_type'];
				unset($autocomplete['query']['post_type']);
			}

			$query_url = $query_url.$query_entity;
			$autocomplete['config']['url'] = get_home_url(null, '/wp-json').$query_url;
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
			if (!isset($field['value'])) {
				$field['choices'] = array();
			} else {
				// otherwise performs a REST request with the required values
				$rest_request = new WP_REST_Request('GET', $query_url);
				$rest_request->set_query_params(array(
				  'include' => $field['value'],
				  'per_page' => 100
				));

				/**
				* piklist_autocomplete_rest_request
				* Filters the REST request
				*
				* @param WP_REST_Request $rest_request The REST request
				*
				* @since 0.0.1
				*/
				$rest_request = apply_filters('piklist_autocomplete_rest_request', $rest_request);

				global $post;
				$current_post = $post;

				$rest_response = rest_do_request($rest_request);
	 
				if ($rest_response->is_error()) {
		    		$error = $rest_response->as_error();
	    			wp_die(printf('<p>An error occurred: %s (%d) - code %s</p>', 
	    				$error->get_error_message(), $error->get_error_data(), $error->get_error_code()));
				}

				/**
				* piklist_autocomplete_rest_response
				* Filters the REST response
				*
				* @param WP_REST_Response $rest_response The REST response
				*
				* @since 0.0.1
				*/
				$rest_response = apply_filters('piklist_autocomplete_rest_response', $rest_response);
	 
				$field['choices'] = piklist($rest_response->get_data(), array('id', $autocomplete['config']['display_field_name']));

				$GLOBALS['post'] = $current_post;
				setup_postdata($current_post);
			}
		}

		$autocomplete['config']['url'] = $autocomplete['config']['url']."?".http_build_query($autocomplete['query']);		

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
		$data_mappings = array(
			'enable_ajax_loading' => 'enable-ajax',
			'minimum_input_length' => 'minimum-input-length',
			'delay_between_calls' => 'delay',
			'items_per_page' => 'items-per-page',
			'url' => 'autocomplete-url',
			'language' => 'language',
			'display_field_name' => 'display-field-name',
		);

		// save the data values to configure the control
		foreach($data_mappings as $key => $val) {
			$attributes['data-'.$val] = $autocomplete['config'][$key];
		}
	}
	return $field;
}

?>