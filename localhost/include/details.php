<?php

$disablestr = ' disabled="disabled"';

$alldisabled = $singleunit->rights!=RIGHTS_DELETE?$disablestr:"";

$rights_res = pg_query( "SELECT roles.id,roles.name,permissions.rights FROM roles LEFT JOIN permissions ON roles.id=permissions.role AND permissions.unit=".pgvalue( $singleunit->id )." ORDER BY roles.id;" );

?>
	<div class="row"><p><b>Details für <code><?php echo $singleunit->name; ?></code></b></p></div>
	<div class="row" id="main">
		<p id="buttonscenter">
			<input type="hidden" name="hostcount" value="1" />
			<input type="hidden" name="mode_0_rolecount" value="<?php echo pg_num_rows( $rights_res ); ?>" />
			<input type="hidden" name="mode_0_id" value="<?php echo $singleunit->id; ?>" />
			<input type="submit"<?php echo $alldisabled; ?> name="mode_0_delete" value="Host Löschen" />
			<input type="submit"<?php echo $alldisabled; ?> name="mode_0_rights" value="Speichern" />
			<input type="submit" name="detailscancel" value="Verwerfen" />
		</p>
		<table><tr>
			<th>Benutzergruppe</th>
			<th>Kein Zugriff</th>
			<th>Anwenden</th>
			<th>Verändern</th>
			<th>Löschen</th>
		</tr>
<?php

$i = 0;

while( $row = pg_fetch_row( $rights_res ) ) {
	$rights = (int)( $row[ 2 ] ); 
	$id = (int)( $row[ 0 ] );

	if( $id==$role )
		$disabled = $disablestr;
	else
		$disabled = $alldisabled;

?>
		<tr>
			<td>
				<?php echo htmlentities( $row[ 1 ] ); ?>
				<input type="hidden" name="mode_0_rights_<?php echo $i; ?>_id" value="<?php echo $id; ?>" />
			</td>
			<td colspan="4" style="padding-right: 3em;"><input style="width:100%;color:black;" type="range"<?php echo $disabled; ?> min="0" max="3" step="1" name="mode_0_rights_<?php echo $i; ?>" value="<?php echo $rights; ?>" /></td>
		</tr>
<?php

	$i++;
}

?>
		</table>
	</div>
