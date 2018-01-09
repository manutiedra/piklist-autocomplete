/* --------------------------------------------------------------------------------
  Manu's custom javascript code
--------------------------------------------------------------------------------- */

;(function($, window, document, undefined) {
  'use strict';

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
							  obj.text = obj[display_field_name];

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
