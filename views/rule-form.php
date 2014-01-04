<div class="wrap">
	
	<h2><?php $rule->exists() ? _e( 'Edit Notification Rule', 'stream_notification' ) : _e( 'Add Notification Rule', 'stream_notification' ); ?></h2>
	
	<form action="" method="post">
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">

					<div id="titlediv">
						<div id="titlewrap">
							<input type="text" name="summary" size="30" value="<?= $rule->summary ?>" id="title" autocomplete="off" keyev="true" placeholder="<?= _e( 'Rule title', 'stream_notification' ) ?>">
						</div>
					</div><!-- /titlediv -->
				</div><!-- /post-body-content -->

				<div id="postbox-container-1" class="postbox-container">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						<div id="submitdiv" class="postbox ">
							<h3 class="hndle">
								<span>
									<?php _e( 'Status', 'stream_notification' ) ?>
								</span>
							</h3>
							<div class="inside">
								<div class="submitbox" id="submitpost">
									<div id="minor-publishing">
										<div id="misc-publishing-actions">
											<div class="misc-pub-section misc-pub-post-status">
												<label for="post_status">
													<?php _e( 'Active', 'stream_notification' ) ?>
												</label>
												<span id="post-status-display">
													<input type="checkbox" name="visibility" id="post_status" value="1" <?php checked( $rule->visibility, 1 ) ?>>
												</span>
											</div>
										</div>
									</div>
									
									<div id="major-publishing-actions">
										<div id="delete-action">
											<a class="submitdelete deletion" href="#delete-post">
												<?php _e( 'Move to Trash', 'stream_notification' ) ?>
											</a>
										</div>
										
										<div id="publishing-action">
											<span class="spinner"></span>
											<input type="submit" name="publish" id="publish" class="button button-primary button-large" value="<?php _e( 'Save', 'stream_notification' ) ?>" accesskey="p">
										</div>
										<div class="clear"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div><!-- postbox-container-1 -->

				<div id="postbox-container-2" class="postbox-container">
					
					<div id="normal-sortables" class="meta-box-sortables ui-sortable">

						<div id="triggers" class="postbox">
							<h3 class="hndle">
								<span><?php _e( 'Triggers', 'stream_notification' ) ?></span>
								<a class="add-trigger" href="#add-trigger" data-group="0"><?php _e( 'Add Trigger', 'stream_notification' ) ?></a>
								<a class="add-trigger-group" href="#add-trigger-group" data-group="0"><?php _e( 'Add Group', 'stream_notification' ) ?></a>
							</h3>
							<div class="inside">
								
								<div class="group" rel="0">
									
								</div>

							</div>
						</div>

						<div id="action" class="postbox">
							<h3 class="hndle"><span><?php _e( 'Action', 'stream_notification' ) ?></span></h3>
							<div class="inside">
								
							</div>
						</div>

					</div>

				</div><!-- postbox-container-2 -->
			</div><!-- /postbody -->
		</div><!-- /poststuff -->
	</form>
</div><!-- /wrap -->

<?php if ( $rule->triggers ): ?>
<script>
	var triggers = <?php echo json_encode( $rule->triggers ) ?>;
	var groups   = <?php echo json_encode( $rule->groups ) ?>;
	var actions  = <?php echo json_encode( $rule->actions ) ?>;
</script>
<?php endif ?>

<script type="text/template" id="trigger-template-row">
<div class="trigger" rel="<%- vars.index %>">
	<div class="form-row">
		<input type="hidden" name="triggers[<%- vars.index %>][group]" value="<%- vars.group %>" />
		<div class="field relation">
			<select name="triggers[<%- vars.index %>][relation]" class="trigger_relation">
				<option value="and"><?php _e( 'AND', 'stream_notification' ) ?></option>
				<option value="or"><?php _e( 'OR', 'stream_notification' ) ?></option>
			</select>
		</div>
		<div class="field type">
			<select name="triggers[<%- vars.index %>][type]" class="trigger_type" rel="<%- vars.index %>" placeholder="Choose Rule">
				<option></option>
				<% _.each( vars.types, function( type, name ){ %>
	            <option value="<%- name %>"><%- type.title %></option>
		        <% }); %>
			</select>
		</div>
		<a href="#" class="delete-trigger">Delete</a>
	</div>
</div>
</script>

<script type="text/template" id="trigger-template-group">
<div class="group" rel="<%- vars.index %>">
	<div class="group-meta">
		<input type="hidden" name="groups[<%- vars.index %>][group]" value="<%- vars.parent %>" />
		<div class="field relation">
			<select name="groups[<%- vars.index %>][relation]" class="group_relation">
				<option value="and"><?php _e( 'AND', 'stream_notification' ) ?></option>
				<option value="or"><?php _e( 'OR', 'stream_notification' ) ?></option>
			</select>
		</div>
		<a href="#add-trigger" class="add-trigger" data-group="<%- vars.index %>">Add Trigger</a>
		<a href="#add-trigger-group" class="add-trigger-group" data-group="<%- vars.index %>">Add Group</a>
		<a href="#" class="delete-group">Remove</a>
	</div>
</div>
</script>

<script type="text/template" id="trigger-template-options">
<div class="trigger_options">
	<div class="field operator">
		<select name="triggers[<%- vars.index %>][operator]" class="trigger_operator">
			<% _.each( vars.operators, function( list, name ){ %>
            <option value="<%- name %>"><%- list %></option>
	        <% }); %>
		</select>
	</div>
	<div class="field value">
		<% if ( ['select', 'ajax'].indexOf( vars.type ) != -1 ){ %>
		<select name="triggers[<%- vars.index %>][value]" class="trigger_value" data-ajax="<% ( vars.ajax ) %>" <% if ( vars.multiple ){ %>multiple="multiple"<% } %>>
			<option></option>
			<% if ( vars.options ) { %>
				<% _.each( vars.options, function( list, name ){ %>
	            <option value="<%- name %>"><%- list %></option>
		        <% }); %>
	        <% } %>
		</select>
		<% } else { %>
		<input type="text" name="triggers[<%- vars.index %>][value]" class="trigger_value <% if ( vars.tags ){ %>tags<% } %> <% if ( vars.ajax ){ %>ajax<% } %>">
		<% } // endif%>
	</div>
</div>
</script>

<style>
	.field, .trigger_type, .trigger_options, .trigger_value { float: left; }
	.form-row { clear:both; overflow: hidden; margin-bottom: 10px; background: #eee; padding: 10px; }
	.group { padding: 20px; background: #ccc; border: 1px solid black; margin: 10px; }
	.group-meta { float: left;
		margin-top: -25px;
		margin-left: -25px;
		margin-bottom: 20px;
		background: #fff;
		padding: 10px;
		border-radius: 5px; }
	.group-meta a {
		font-size: 10px;
		padding-left: 5px;
	}
	.group .trigger:first-of-type .field.relation,
	.trigger.first .field.relation {
		display: none;
	}
	.delete-trigger { float: right; }

	.field.relation select { width: 50px !important; }
</style>