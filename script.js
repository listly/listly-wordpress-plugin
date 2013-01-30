jQuery(document).ready(function($)
{
	$('#ListlyAdminAuthStatus').click(function(e)
	{
		e.preventDefault();

		var Key = $(this).prev('input').val();
		var ElmMsg = $(this).next('span');

		ElmMsg.html('Loading...');

		$.post(ajaxurl, {'action': 'AJAXPublisherAuth', 'nounce': Listly.Nounce, 'Key': Key}, function(data)
		{
			ElmMsg.html(data);
		});
	});


	$('input[name="ListlyAdminListSearch"]').bind('keyup', function(event)
	{
		var ElmValue = $(this).val();
		var Container = $('#ListlyAdminYourList');

		if (ElmValue.length)
		{
			$('.ListlyAdminListSearchClear').show();
		}
		else
		{
			$('.ListlyAdminListSearchClear').hide();
		}

		if (ElmValue.length > 2)
		{
			Container.html('<p>Loading...</p>');

			$.post(ajaxurl, {'action': 'AJAXListSearch', 'nounce': Listly.Nounce, 'Term': ElmValue, 'SearchAll': $('input[name="ListlyAdminListSearchAll"]').is(':checked')}, function(data)
			{
				if (data.status == 'ok')
				{
					Container.empty();

					if (jQuery.isEmptyObject(data.results))
					{
						Container.append('<p>No results found!</p>');
					}
					else
					{
						$(data.results).each(function(i)
						{
							Container.append(data.results[i]);
						});
					}
				}
				else
				{
					Container.html(data.message);
				}
			}, 'json').error(function(jqXHR, textStatus)
			{
				Container.html('<p>Error: '+textStatus+'</p>');
			});
		}
	});


	$('.ListlyAdminListSearchClear').click(function(e)
	{
		e.preventDefault();

		$('.ListlyAdminListSearchClear').hide();
		$('input[name="ListlyAdminListSearch"]').val('').focus();
	});

});