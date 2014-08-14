<script type="text/template" id="trigger-template-row">
<div class="trigger" rel="<%- vars.index %>">
	<div class="form-row">
		<input type="hidden" name="triggers[<%- vars.index %>][group]" value="<%- vars.group %>"/>
		<div class="field relation">
			<select name="triggers[<%- vars.index %>][relation]" class="trigger-relation">
				<option value="and"><?php esc_html_e( 'AND', 'stream' ) ?></option>
				<option value="or"><?php esc_html_e( 'OR', 'stream' ) ?></option>
			</select>
		</div>
		<div class="field type">
			<select name="triggers[<%- vars.index %>][type]" class="trigger-type" rel="<%- vars.index %>" placeholder="Choose Rule">
				<option></option>
				<% _.each( vars.types, function( type, name ) { %>
					<option value="<%- name %>"><%- type.title %></option>
				<% }); %>
			</select>
		</div>
		<a href="#" class="delete-trigger"><?php esc_html_e( 'Delete', 'stream' ) ?></a>
	</div>
</div>
</script>

<script type="text/template" id="trigger-template-group">
<div class="group" rel="<%- vars.index %>">
	<div class="group-meta">
		<input type="hidden" name="groups[<%- vars.index %>][group]" value="<%- vars.parent %>"/>
		<div class="field relation">
			<select name="groups[<%- vars.index %>][relation]" class="group-relation">
				<option value="and"><?php esc_html_e( 'AND', 'stream' ) ?></option>
				<option value="or"><?php esc_html_e( 'OR', 'stream' ) ?></option>
			</select>
		</div>
		<a href="#add-trigger" class="add-trigger button button-secondary" data-group="<%- vars.index %>"><?php esc_html_e( '+ Add Trigger', 'stream' ) ?></a>
		<a href="#add-trigger-group" class="add-trigger-group button button-primary" data-group="<%- vars.index %>"><?php esc_html_e( '+ Add Group', 'stream' ) ?></a>
		<a href="#" class="delete-group"><?php esc_html_e( 'Delete Group', 'stream' ) ?></a>
	</div>
</div>
</script>

<script type="text/template" id="trigger-template-options">
<div class="trigger-options">
	<div class="field operator">
		<select name="triggers[<%- vars.index %>][operator]" class="trigger-operator">
			<% _.each( vars.operators, function( list, name ) { %>
				<option value="<%- name %>"><%- list %></option>
			<% }); %>
		</select>
	</div>
	<div class="field value">
		<% if ( ['select', 'ajax'].indexOf( vars.type ) != -1 ) { %>
		<select name="triggers[<%- vars.index %>][value]<% if ( vars.multiple ) { %>[]<% } %>" class="trigger-value<% if ( vars.subtype ) { %> <%- vars.subtype %><% } %>" data-ajax="<% ( vars.ajax ) %>" <% if ( vars.multiple ) { %> multiple="multiple"<% } %>>
			<option></option>
			<% if ( vars.options ) { %>
				<% _.each( vars.options, function( list, name ) { %>
					<option value="<%- name %>"><%- list %></option>
				<% }); %>
			<% } %>
		</select>
		<% } else { %>
		<input type="text" name="triggers[<%- vars.index %>][value]" class="trigger-value type-<%- vars.type %> <% if ( vars.tags ) { %>tags<% } %> <% if ( vars.ajax ) { %>ajax<% } %><% if ( vars.subtype ) { %> <%- vars.subtype %><% } %>">
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
				<% _.each( vars.adapters, function( type, name ) { %>
					<option value="<%- name %>"><%- type.title %></option>
				<% }); %>
			</select>
			<a href="#" class="delete-alert alignright"><?php esc_html_e( 'Delete', 'stream' ) ?></a>
			<div class="clear"></div>
		</div>
	</div>
</div>
</script>

<script type="text/template" id="alert-template-options">
<table class="alert-options form-table" data-type="<%- vars.type %>">
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
					<% if ( ['select'].indexOf( field.type ) != -1 ) { %>
						<select name="alerts[<%- vars.index %>][<%- field_name %>]" class="alert-value widefat" data-ajax="<% ( field.ajax ) %>" <% if ( field.multiple ) { %> multiple="multiple"<% } %> <%- argsHTML %>>
							<option></option>
							<% if ( field.options ) { %>
								<% _.each( field.options, function( list, name ) { %>
									<option value="<%- name %>"><%- list %></option>
								<% }); %>
							<% } %>
						</select>
					<% } else if ( ['textarea'].indexOf( field.type ) != -1 ) { %>
						<textarea name="alerts[<%- vars.index %>][<%- field_name %>]" class="alert-value large-text code" rows="10" cols="80" <%- argsHTML %>></textarea>
					<% } else if ( ['error'].indexOf( field.type ) != -1 ) { %>
						<%= field.message %>
					<% } else { %>
						<input type="text" name="alerts[<%- vars.index %>][<%- field_name %>]" class="alert-value widefat <% if ( field.tags ) { %>tags<% } %> <% if ( field.ajax ) { %>ajax<% } %>" <% if ( field.ajax && field.key ) { %> data-ajax-key="<%- field.key %>"<% } %> <%- argsHTML %>>
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
