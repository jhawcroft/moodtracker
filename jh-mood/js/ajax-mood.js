function jh_mood_set(inMood)
{
	jQuery.post(
		JHAjaxMood.ajaxurl,
		{
			// the following action hooks will be fired:
			// wp_ajax_jh-ajax-mood-set
			action : 'jh-ajax-mood-set',
	 
			mood : inMood,
			
			mood_nonce: JHAjaxMood.mood_nonce,
		},
		function( response ) {
			jQuery('#jh-mood-current').html(response);
		}
	);

}

function jh_mood_graph(inShow)
{
	jQuery.post(
		JHAjaxMood.ajaxurl,
		{
			// the following action hooks will be fired:
			// wp_ajax_jh-ajax-mood-set
			action : 'jh-ajax-mood-graph',
	 
			mood_graph : inShow,
		},
		function( response ) {
			jQuery('#jh-mood-current').html(response);
		}
	);
}
