/* --------------------------------------------------------------------------------
  Manu's custom javascript code
--------------------------------------------------------------------------------- */

;(function($, window, document, undefined) {
  'use strict';

  function resolve(path, obj) {
  	return path.split('.').reduce(function(prev, curr) {
    	return prev ? prev[curr] : null
    }, obj || self)
  }

  $(document).ready(function() {
    $('.piklist-autocomplete').each(function() {
    	var currElement = $(this);

    	if (currElement.data('enable-ajax')) {
	    	currElement.select2({
				ajax: {
					url: function () {
		      			return currElement.data('autocomplete-url');
		    		},
					dataType: 'json',
					data: function (params) {
						var query = {
							search: params.term,
							page: params.page || 1,
							per_page: currElement.data('items-per-page')
						};

						return query;
					},
					transport: function (params, success, failure) {
						var read_headers = function(data, textStatus, jqXHR) {
					        var total_pages = parseInt(jqXHR.getResponseHeader('X-WP-TotalPages')) || 1;
					        var display_field_name = currElement.data('display-field-name');

					        var formatted_data = $.map(data, function (obj) {
							  obj.text = resolve(display_field_name, obj);

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
			currElement.select2();
		}
	});

  });

})(jQuery, window, document);
