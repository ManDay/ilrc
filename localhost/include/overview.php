<?php
	foreach( $messages as $message ) {
?>
	<div class="row">
		<p style="border: 2px solid <?php echo $message[ 1 ]; ?>; background-color: <?php echo $message[ 0 ]; ?>; color: <?php echo $message[ 1 ]; ?>">
			<b><?php echo $message[ 2 ]; ?></b>
		</p>
	</div>
<?php } ?>
	<div class="row" id="profile">
		<p id="buttonstopleft">
			<select name="scenario">
				<option value="">(Aktuelles Profil)</option><?php while( $profile = pg_fetch_array( $profiles_result ) ) { ?>
				<option value="<?php echo $profile[ "name" ]; ?>"><?php echo $profile[ "name" ]; ?></option><?php } ?>
			</select>
			<input type="submit" name="load_profile" value="Laden" />
		</p><p id="buttonstopright">
			<input type="text" name="profile_name" value="<?php echo isset( $_POST[ "scenario" ] )?htmlentities( $_POST[ "scenario" ] ):""; ?>" />
			<input type="submit" name="save_profile" value="Speichern" />
			<input type="submit" name="delete_profile" value="Löschen" />
		</p>
	</div>
	<div class="row" id="main">
		<p id="buttonscenter">
			<input type="hidden" name="hostcount" value="<?php echo count( $units ); ?>" />
			<input type="submit" name="perform" value="Anwenden" />
			<input type="submit" name="refresh" value="Status aktualisieren" />
			<input type="submit" name="keepall" value="Alle beibehalten" />
		</p>
		<table><tr>
			<th>Hostname</th>
			<th>MAC Addr.</th>
			<th>IP Addr.</th>
			<th>Zustand</th>
			<th colspan="3" class="longcell">Gewünschter Zustand</th>
			<th></th>
		</tr><?php
	
for( $i = 0; $i<count( $units ); $i++ ) {
	$unit = $units[ $i ];

	$disabled_edit = $unit->rights<RIGHTS_EDIT?" disabled='disabled'":"";
	$disabled_apply = $unit->rights<RIGHTS_APPLY?" disabled='disabled'":"";
		
		?><tr>
			<td class="data"><?php echo $unit->name; ?></td>
			<td class="data"><?php echo $unit->mac; ?></td>
			<td class="data"><?php echo is_null( $unit->ip )?"<i>{$unit->subnet}</i>":$unit->ip; ?></td>
			<td class="state" style="background-color: <?php echo $unit->state[ 0 ]; ?>; color: <?php echo $unit->state[ 1 ]; ?>"><?php echo $unit->state[ 2 ]; ?></td>
			<td>
				<input type="hidden" name="mode_<?php echo $i; ?>_id" value="<?php echo $unit->id; ?>" />
				<input type="radio"<?php echo $disabled_apply; ?> name="mode_<?php echo $i; ?>" value="keep" id="mode_<?php echo $i; ?>_keep"<?php if( $unit->keep ) echo ' checked="checked"'; ?> />
				<label for="mode_<?php echo $i; ?>_keep">Beibehalten</label>
			</td>
			<td>
				<input type="radio"<?php echo $disabled_apply; ?> name="mode_<?php echo $i; ?>" value="change" id="mode_<?php echo $i; ?>_change"<?php if( !$unit->keep ) echo ' checked="checked"'; ?> />
				<select<?php echo $disabled_edit; ?> name="mode_<?php echo $i; ?>_target">
	<?php foreach( $programs as $program ) { ?>
					<option value="<?php echo $program->id; ?>"<?php if( $unit->program==$program->id ) echo ' selected="selected"'; ?>><?php echo $program->name; ?></option>
<?php } ?>
				</select>
			</td>
			<td><button type="submit" name="mode_<?php echo $i; ?>_settings"><img src="icons/preferences.png" alt="Einstellungen" /></button></td>
			<td><button type="submit" name="mode_<?php echo $i; ?>_details"><img src="icons/details.png" alt="Details" /></button></td>
		</tr><?php } ?><tr>
			<td><input type="text" name="new_name" class="hostinput" /></td>
			<td><input type="text" name="new_mac" class="hostinput" /></td>
			<td><input type="text" name="new_subnet" class="hostinput" /></td>
			<td colspan="4"></td>
			<td><button type="submit" name="new"><img src="icons/new.png" alt="Neuer Host" /></button></td>
		</tr></table>
	</div>
<?php

if( $role==ILRC_ADMIN ) {

	$users_res = pg_query( "SELECT name,id FROM roles;" );

?>
	<div class="row" id="admin">
		<p>
			<input type="hidden" name="usercount" value="<?php echo pg_num_rows( $users_res ); ?>" />
		</p>
		<table>
			<tr><th>Benutzername</th><th colspan="2">Passwort ändern</th><th>Löschen</th></tr>
<?php

	$i = 0;
	while( $user = pg_fetch_row( $users_res ) ) {
?>
		<tr>
			<td><?php echo $user[ 0 ]; ?><input type="hidden" name="user_<?php echo $i; ?>_id" value="<?php echo $user[ 1 ]; ?>" /></td>
			<td><input type="password" name="user_<?php echo $i; ?>_password" /></td>
			<td><button type="submit" name="user_<?php echo $i; ?>_changepass"><img src="icons/password.png" alt="Passwort ändern" /></button></td>
			<td><button type="submit" name="user_<?php echo $i; ?>_delete"><img src="icons/delete.png" alt="Löschen" /></button></td>
		</tr>

<?php

		$i++;
	}

?>
		<tr>
			<td><input type="text" name="user_new_name" /></td>
			<td><input type="password" name="user_new_password" /></td>
			<td></td>
			<td><button type="submit" name="user_new"><img src="icons/new.png" alt="Neuer Benutzer" /></button></td>
		</tr>
		</table>
		<hr />
		<table>
			<tr><td>
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_MAPSIZE; ?>" />
				<input type="file" name="newpcmap" />
			</td>
			<td><input type="submit" name="pcmapupload" value="Neue PC Karte hochladen" /></td></tr>
		</table>
	</div>
<?php

}

?>
	<div class="row" id="footer">
		<img src="pcmap.png" alt="PC Karte" />
	</div>
