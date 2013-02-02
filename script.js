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
		var SearchAll = $('input[name="ListlyAdminListSearchAll"]').is(':checked') ? 'all' : 'publisher';

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

			$.ajax
			({
				type: 'POST',
				url: Listly.SiteURL + 'autocomplete/list.json',
				data: {'term': ElmValue, 'key': Listly.Key, 'type': SearchAll},
				jsonp: 'callback',
				jsonpCallback: 'jsonCallback',
				contentType: 'application/json',
				dataType: 'jsonp',
				success: function(data)
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
								Container.append('<p> <img class="avatar" src="'+data.results[i].user_image+'" alt="" /> <a class="ListlyAdminListEmbed" target="_new" href="http://list.ly/preview/'+data.results[i].list_id+'?key='+Listly.Key+'&source=wp_plugin" title="Get Short Code"><img src="'+Listly.PluginURL+'images/shortcode.png" alt="" /></a> <a class="strong" target="_blank" href="http://list.ly/'+data.results[i].list_id+'?source=wp_plugin" title="Go to List on List.ly">'+data.results[i].title+'</a> </p>');
							});
						}
					}
					else
					{
						Container.html(data.message);
					}
				},
				error: function(jqXHR, textStatus, errorThrown)
				{
					Container.html('<p>Error: '+errorThrown+'</p>');
				}
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