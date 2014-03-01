<div class="wrap">

	<?php if ( wp_stream_filter_input( INPUT_GET, 'updated' ) || wp_stream_filter_input( INPUT_POST, 'summary' ) ) : ?>
		<div class="updated fade">
			<p><?php esc_html_e( 'Rule saved.', 'stream-notifications' ) ?></p>
		</div>
	<?php endif; ?>

	<h2><?php $rule->exists() ? esc_html_e( 'Edit Notification Rule', 'stream-notifications' ) : esc_html_e( 'Add New Notification Rule', 'stream-notifications' ); ?>
		<?php if ( $rule->exists() ) : ?>
			<?php
			$new_link = add_query_arg(
				array(
					'page' => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
					'view' => 'rule',
				),
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			);
			?>
			<a href="<?php echo esc_url( $new_link ) ?>" class="add-new-h2"><?php esc_html_e( 'Add New', 'stream-notifications' ) ?></a>
		<?php endif; ?>
	</h2>

	<form action="" method="post" id="rule-form">

		<?php
		wp_nonce_field( 'stream-notifications-form' );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		?>

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-<?php echo esc_attr( 1 == get_current_screen()->get_columns() ? 1 : 2 ); ?>">
				<div id="post-body-content">

					<div id="titlediv">
						<div id="titlewrap">
							<input type="text" name="summary" size="30" value="<?php echo esc_attr( $rule->summary ) ?>" id="title" autocomplete="off" keyev="true" placeholder="<?php esc_attr_e( 'Rule title', 'stream-notifications' ) ?>" autofocus="focus">
						</div>
					</div><!-- /titlediv -->
				</div><!-- /post-body-content -->

				<div id="postbox-container-1" class="postbox-container">
					<?php do_meta_boxes( get_current_screen()->id, 'side', $rule ); ?>
				</div><!-- postbox-container-1 -->

				<div id="postbox-container-2" class="postbox-container">
					<?php do_meta_boxes( get_current_screen()->id, 'normal', $rule ); ?>
				</div><!-- postbox-container-2 -->
			</div><!-- /postbody -->
		</div><!-- /poststuff -->
	</form>
</div><!-- /wrap -->

	<?php if ( $rule->triggers ) { ?>
		<script>
			var notification_rule = {
				triggers : <?php echo json_encode( $rule->triggers ) ?>,
				groups   : <?php echo json_encode( $rule->groups ) ?>,
				alerts   : <?php echo json_encode( $rule->alerts ) ?>,
			}
		</script>
	<?php } ?>

<script type="text/template" id="trigger-template-row">
<div class="trigger" rel="<%- vars.index %>">
	<div class="form-row">
		<input type="hidden" name="triggers[<%- vars.index %>][group]" value="<%- vars.group %>" />
		<div class="field relation">
			<select name="triggers[<%- vars.index %>][relation]" class="trigger-relation">
				<option value="and"><?php esc_html_e( 'AND', 'stream-notifications' ) ?></option>
				<option value="or"><?php esc_html_e( 'OR', 'stream-notifications' ) ?></option>
			</select>
		</div>
		<div class="field type">
			<select name="triggers[<%- vars.index %>][type]" class="trigger-type" rel="<%- vars.index %>" placeholder="Choose Rule">
				<option></option>
				<% _.each( vars.types, function( type, name ){ %>
				<option value="<%- name %>"><%- type.title %></option>
				<% }); %>
			</select>
		</div>
		<a href="#" class="delete-trigger"><?php esc_html_e( 'Delete', 'stream-notifications' ) ?></a>
	</div>
</div>
</script>

<script type="text/template" id="trigger-template-group">
<div class="group" rel="<%- vars.index %>">
	<div class="group-meta">
		<input type="hidden" name="groups[<%- vars.index %>][group]" value="<%- vars.parent %>" />
		<div class="field relation">
			<select name="groups[<%- vars.index %>][relation]" class="group-relation">
				<option value="and"><?php esc_html_e( 'AND', 'stream-notifications' ) ?></option>
				<option value="or"><?php esc_html_e( 'OR', 'stream-notifications' ) ?></option>
			</select>
		</div>
		<a href="#add-trigger" class="add-trigger button button-secondary" data-group="<%- vars.index %>"><?php esc_html_e( '+ Add Trigger', 'stream-notifications' ) ?></a>
		<a href="#add-trigger-group" class="add-trigger-group button button-primary" data-group="<%- vars.index %>"><?php esc_html_e( '+ Add Group', 'stream-notifications' ) ?></a>
		<a href="#" class="delete-group"><?php esc_html_e( 'Delete Group', 'stream-notifications' ) ?></a>
	</div>
</div>
</script>

<script type="text/template" id="trigger-template-options">
<div class="trigger-options">
	<div class="field operator">
		<select name="triggers[<%- vars.index %>][operator]" class="trigger-operator">
			<% _.each( vars.operators, function( list, name ){ %>
			<option value="<%- name %>"><%- list %></option>
			<% }); %>
		</select>
	</div>
	<div class="field value">
		<% if ( ['select', 'ajax'].indexOf( vars.type ) != -1 ){ %>
		<select name="triggers[<%- vars.index %>][value]" class="trigger-value" data-ajax="<% ( vars.ajax ) %>" <% if ( vars.multiple ){ %>multiple="multiple"<% } %>>
			<option></option>
			<% if ( vars.options ) { %>
				<% _.each( vars.options, function( list, name ){ %>
				<option value="<%- name %>"><%- list %></option>
				<% }); %>
			<% } %>
		</select>
		<% } else { %>
		<input type="text" name="triggers[<%- vars.index %>][value]" class="trigger-value type-<%- vars.type %> <% if ( vars.tags ){ %>tags<% } %> <% if ( vars.ajax ){ %>ajax<% } %>">
		<% } // endif%>
	</div>
</div>
</script>

<script type="text/template" id="alert-template-row">
<div class="alert" rel="<%- vars.index %>">
	<div class="form-row">
		<div class="type">
			<span class="circle"><%- vars.index + 1 %></span>
			<select name="alerts[<%- vars.index %>][type]" class="alert-type" rel="<%- vars.index %>" placeholder="Choose Type">
				<option></option>
				<% _.each( vars.adapters, function( type, name ){ %>
				<option value="<%- name %>"><%- type.title %></option>
				<% }); %>
			</select>
			<a href="#" class="delete-alert alignright"><?php esc_html_e( 'Delete', 'stream-notifications' ) ?></a>
			<div class="clear"></div>
		</div>
	</div>
</div>
</script>

<script type="text/template" id="alert-template-options">
<table class="alert-options form-table">
	<% for ( field_name in vars.fields ) { var field = vars.fields[field_name]; %>
		<% var argsHTML = ( typeof field.args === "object" ? "data-args=" + JSON.stringify( field.args ) : "" ); %>
		<tr>
			<th class="label">
				<label><%- field.title %></label>
				<% if ( field.hint ) { %>
					<% var hints = ( typeof field.hint === "object" ? field.hint : [field.hint] ); %>
					<% for ( i in hints ) { var hint = hints[i]; %>
						<p class="description"><%= hint %></p>
					<% } %>
				<% } %>
			</th>
			<td>
				<div class="field value">
					<% if ( ['select'].indexOf( field.type ) != -1 ){ %>
						<select name="alerts[<%- vars.index %>][<%- field_name %>]" class="alert-value widefat" data-ajax="<% ( field.ajax ) %>" <% if ( field.multiple ){ %>multiple="multiple"<% } %> <%- argsHTML %>>
							<option></option>
							<% if ( vars.fields[field] ) { %>
								<% _.each( vars.fields[field], function( list, name ){ %>
								<option value="<%- name %>"><%- list %></option>
								<% }); %>
							<% } %>
						</select>
					<% } else if ( ['textarea'].indexOf( field.type ) != -1 ) { %>
						<textarea name="alerts[<%- vars.index %>][<%- field_name %>]" class="alert-value large-text code" rows="10" cols="80" <%- argsHTML %>></textarea>
					<% } else if ( ['error'].indexOf( field.type ) != -1 ) { %>
						<%= field.message %>
					<% } else { %>
						<input type="text" name="alerts[<%- vars.index %>][<%- field_name %>]" class="alert-value widefat <% if ( field.tags ){ %>tags<% } %> <% if ( field.ajax ){ %>ajax<% } %>" <% if ( field.ajax && field.key ){ %>data-ajax-key="<%- field.key %>"<% } %> <%- argsHTML %>>
					<% } %>
					<% if ( field.after ) { %>
						<%- field.after %>
					<% } %>
				</div>
			</td>
		</tr>
	<% } %>
	<% if ( typeof vars.hints != 'undefined' && vars.hints ) { %>
		<tr>
			<th></th>
			<td><%= vars.hints %></td>
		</tr>
	<% } %>
</table>

</script>
