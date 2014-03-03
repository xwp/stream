/* globals stream_notifications */
jQuery(function($){
	"use strict";

	$(".delete .action-link, .submitdelete").click(function() {
		return confirm(stream_notifications_actions.messages.deletePermanently);
	});
});
