<?php

$windows_table = array(
	"base SMALLINT NOT NULL DEFAULT 0",
	"overwrite BOOLEAN NOT NULL DEFAULT 't'",
);

$windows_profiles = array(
	"Leere Oberfläche mit Administratorrechten"=>"/usr/local/lib/windows_adjusted.qcow"
);

function windows_start( $settings_array,$unit,$timestamp ) {
	global $windows_profiles;
	makestateful( );

	rcboot( $unit->mac,"192.168.0.0/25" );
	mounttools( $unit->name );

	$baseid = (int)( $settings_array[ "base" ] );

	if( $baseid<0 || $baseid>=count( $windows_profiles ) )
		$baseid = 0;

	$overwrite = $settings_array[ "overwrite" ]=="t"?"1":"0";

	cqappend( "&qemu ".escapeshellarg( array_values( $windows_profiles )[ $baseid ] )." $overwrite",$unit->name );
}

function windows_stop( $settings_array,$unit,$timestamp ) {
	cqappend( "if [[ -f windows.pid && -S windows.sock ]] ; then pid=$(cat windows.pid) ; if kill -0 \$pid ; then echo $'{\"execute\":\"qmp_capabilities\"}\\n{\"execute\":\"system_powerdown\"}' | nc -U windows.sock ; fi ; while kill -0 \$pid; do sleep 1; done ; fi ;",$unit->name );
}

function windows_ondelete( $settings_array ) {
}

function windows_onduplicate( $settings_array ) {
}

function windows_preferences( $settings_array,$unit ) {
	global $windows_profiles;

?>

<table>
<tr><th><label for="overwrite">Profil zurücksetzen</label></th><td><input type="checkbox" name="overwrite"<?php if( $settings_array[ "overwrite" ]=='t' ) echo ' checked="checked"'; ?> /></td></tr>
<tr><th><label for="base">Rücksetz- oder Ausgangsprofil</label></th><td>
	<select name="base"><?php
	$baseid = (int)( $settings_array[ "base" ] );

	$profilenames = array_keys( $windows_profiles );
	for( $i = 0; $i<count( $windows_profiles ); $i++ ) {
?>
		<option value="<?php echo $i; ?>"<?php if( $i==$baseid ) echo ' selected="selected"'; ?>><?php echo $profilenames[ $i ]; ?></option>
<?php } ?>
	</select>
</td></tr>
</table>

<?php

}

function windows_configure( &$settings_array,$unit ) {
	global $windows_profiles;
	if( isset( $_POST[ "base" ] ) ) {
		
		$baseid = (int)( $_POST[ "base" ] );
		if( $baseid<0 || $baseid>count( $windows_profiles ) )
			$baseid = 0;

		$settings_array[ "base" ]= $baseid;
		$settings_array[ "overwrite" ]=( isset( $_POST[ "overwrite" ] )&& $_POST[ "overwrite" ]=='on' )?"t":"f";
	}
	return NULL;
}

function windows_getstate( $unit ) {
	return NULL;
}

?>
