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
			<th colspan="4" class="longcell">Gewünschter Zustand</th>
		</tr><?php
	
for( $i = 0; $i<count( $units ); $i++ ) {
	$unit = $units[ $i ];
		
		?><tr>
			<td class="data"><?php echo $unit->name; ?></td>
			<td class="data"><?php echo $unit->mac; ?></td>
			<td class="data"><?php echo is_null( $unit->ip )?"<i>{$unit->subnet}</i>":$unit->ip; ?></td>
			<td class="state" style="background-color: <?php echo $unit->state[ 0 ]; ?>; color: <?php echo $unit->state[ 1 ]; ?>"><?php echo $unit->state[ 2 ]; ?></td>
			<td>
				<input type="hidden" name="mode_<?php echo $i; ?>_id" value="<?php echo $unit->id; ?>" />
				<input type="radio" name="mode_<?php echo $i; ?>" value="keep" id="mode_<?php echo $i; ?>_keep"<?php if( $unit->keep ) echo ' checked="checked"'; ?> />
				<label for="mode_<?php echo $i; ?>_keep">Beibehalten</label>
			</td>
			<td>
				<input type="radio" name="mode_<?php echo $i; ?>" value="change" id="mode_<?php echo $i; ?>_change"<?php if( !$unit->keep ) echo ' checked="checked"'; ?> />
				<select name="mode_<?php echo $i; ?>_target">
	<?php foreach( $programs as $program ) { ?>
					<option value="<?php echo $program->id; ?>"<?php if( $unit->program==$program->id ) echo ' selected="selected"'; ?>><?php echo $program->name; ?></option>
<?php } ?>
				</select>
			</td>
			<td><button type="submit" name="mode_<?php echo $i; ?>_settings"><img src="icons/preferences.png" alt="Einstellungen" /></button></td>
			<td><button type="submit" name="mode_<?php echo $i; ?>_delete"><img src="icons/delete.png" alt="Host löschen" /></button></td>
		</tr><?php } ?><tr>
			<td><input type="text" name="new_name" class="hostinput" /></td>
			<td><input type="text" name="new_mac" class="hostinput" /></td>
			<td><input type="text" name="new_subnet" class="hostinput" /></td>
			<td colspan="4"></td>
			<td><button type="submit" name="new"><img src="icons/new.png" alt="Neuer Host" /></button></td>
		</tr></table>
	</div>
	<div class="row" id="footer">
		<img src="pcmap.png" alt="PC Karte" />
	</div>
