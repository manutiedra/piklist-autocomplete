/* --------------------------------------------------------------------------------
  Init piklist-autocomplete fields
--------------------------------------------------------------------------------- */

;(function($, window, document, undefined) {
  'use strict';

  function resolve(obj, path) {
  	return path.split('.').reduce(function(prev, curr) {
    	return prev ? prev[curr] : null
    }, obj || self)
  }

  $(document).ready(function() {
    $('.piklist-autocomplete').each(function() {
    	var curr_element = $(this);

    	if (curr_element.data('enable-ajax')) {
	    	curr_element.select2({
				ajax: {
					url: function () {
		      			return curr_element.data('autocomplete-url');
		    		},
					dataType: 'json',
					data: function (params) {
						var query = {
							search: params.term,
							page: params.page || 1,
							per_page: curr_element.data('items-per-page')
						};

						return query;
					},
					transport: function (params, success, failure) {
						var read_headers = function(data, textStatus, jqXHR) {
					        var total_pages = parseInt(jqXHR.getResponseHeader('X-WP-TotalPages')) || 1;
					        var display_field_name = curr_element.data('display-field-name');

					        var formatted_data = $.map(data, function (obj) {
							  obj.text = resolve(obj, display_field_name);

							  return obj;
							});

					        return {
					          	results: formatted_data,
					          	pagination: {
					            	more: params.data.page < total_pages
					          	}
					        };
					    };

		    			var $request = $.ajax(params);

		    			$request.then(read_headers).then(success);
					    $request.fail(failure);

					    return $request;
					}
				}
			});
		} else {
			curr_element.select2();
		}
	});

  });

})(jQuery, window, document);
