



jQuery($ => {
	$('.cpl-ajax-facet').each(function(index, element) {
		const facetType = $(element).data('facet-type');
		const label     = $(element).data('label');

		if(!facetType) return;

		const params = new URLSearchParams(window.location.search)
		const preSelected = formatQueryParams(params)[facetType] || [];

		$(element).hide();

		$.ajax({
			url: cplVars.ajax_url,
			type: "POST",
			data: {
				action: "cpl_dropdown_facet",
				facet_type: facetType,
				selected: preSelected,
				label: label
			},
			success: function(data) {
				console.log("Data", data)
				if( data.trim() ) {
					$(element).html(data);
					$(element).show();
					initializeDropdown(element);
				}
			},
			error: function(error) {
				console.log(error);
			}
		})
	})

	function formatQueryParams(params) {
		const output = {}
	
		for(const [key, value] of params.entries()) {
			if(key.slice(-2) === '[]') {
				const newKey = key.slice(0, -2);
				output[newKey] = [...(output[newKey] || []), value]
			}
			else {
				output[key] = value;
			}
		}
	
		return output;
	}

	function initializeDropdown(element) {
		$(element).find('.cpl-filter--has-dropdown a').on('click', function(e) {
			e.preventDefault();
			$(this).parent().toggleClass('open');
		})

		$(element).find('input[type="checkbox"]').on('change', submitOnChange)

		$('.cpl-filter--toggle--button').on('click', function(e) {
			e.preventDefault();
			$(element).find('.cpl-filter--has-dropdown').toggle()
		});
	}

	function submitOnChange(e) {
		// Munge the URL to discard pagination when fiilter options change
		var form = $(this).parents("form.cpl-filter--form");
		var location = window.location;
		var baseUrl = location.protocol + "//" + location.hostname;
		var pathSplit = location.pathname.split("/");
		let finalPath = "";

		// Get the URL before the `page` element
		var gotBoundary = false;
		$(pathSplit).each(function (index, token) {
			if ("page" === token) {
				gotBoundary = true;
			}
			if (!gotBoundary) {
				if ("" === token) {
					if (!finalPath.endsWith("/")) {
						finalPath += "/";
					}
				} else {
					finalPath += token;
					if (!finalPath.endsWith("/")) {
						finalPath += "/";
					}
				}
			}
		});
		// Finish and add already-used GET params
		if (!finalPath.endsWith("/")) {
			finalPath += "/";
		}
		if (location.search && location.search.length > 0) {
			finalPath += location.search;
		}
		// Set form property and do it
		$(form).attr("action", baseUrl + finalPath);
		$(".cpl-filter--form").submit();
	}
})


