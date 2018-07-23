/* global phpbb */

(function($) {  // Avoid conflicts with other libraries

'use strict';

/**
 * The following callbacks are for reording items. row_down
 * is triggered when an item is moved down, and row_up is triggered when
 * an item is moved up and moves the row up or down.
 */
phpbb.addAjaxCallback('row_down', function(res) {
	if (typeof res.success === 'undefined' || !res.success) {
		return;
	}

	var $firstTr = $(this).parents('tr'),
		$secondTr = $firstTr.next(),
		$spacerTr = $('#spacer'),
		$afterSpacerTr = $spacerTr.next();

	if ($secondTr.is('#spacer')) {
		$firstTr.insertAfter($spacerTr);
		$afterSpacerTr.insertBefore($spacerTr);
	} else {
		$firstTr.insertAfter($secondTr);
	}
});

phpbb.addAjaxCallback('row_up', function(res) {
	if (typeof res.success === 'undefined' || !res.success) {
		return;
	}

	var $secondTr = $(this).parents('tr'),
		$firstTr = $secondTr.prev(),
		$spacerTr = $('#spacer'),
		$beforeSpacerTr = $spacerTr.prev();

	if ($firstTr.is('#spacer')) {
		$secondTr.insertBefore($spacerTr);
		$beforeSpacerTr.insertAfter($spacerTr);
	} else {
		$secondTr.insertBefore($firstTr);
	}
});

})(jQuery); // Avoid conflicts with other libraries
