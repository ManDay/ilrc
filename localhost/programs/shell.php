<?php

$shell_table = array(
	"startcode TEXT NOT NULL DEFAULT ''",
	"startsync TIMESTAMP DEFAULT NULL",
	"stateful BOOLEAN DEFAULT 'f'",
	"stopcode TEXT NOT NULL DEFAULT ''",
	"stopsync TIMESTAMP DEFAULT NULL"
);

function shell_start( $settings_array,$unit,$timestamp ) {
	if( $settings_array[ "stateful" ]=="t" )
		makestateful( );

	rcboot( $unit->mac,"192.168.0.0/25" );
	mounttools( $unit->name );

	$command = "shell ".escapeshellarg( str_replace( array( "\n","\r" ),array( ";" ),$settings_array[ "startcode" ] ) );

	if( !is_null( $settings_array[ "startsync" ] ) ) {
		$targettime = strtotime( $settings_array[ "startsync" ] );
		$command = ";{$targettime};$command";
	}

	cqappend( $command,$unit->name );
}

function shell_stop( $settings_array,$unit,$timestamp ) {
	mounttools( $unit->name );

	$command = "shell ".escapeshellarg( str_replace( array( "\n","\r" ),array( ";" ),$settings_array[ "stopcode" ] ) );

	if( !is_null( $settings_array[ "stopsync" ] ) ) {
		$targettime = strtotime( $settings_array[ "stopsync" ] );
		$command = ";{$targettime};$command";
	}

	cqappend( $command,$unit->name );
}

function shell_preferences( $settings_array,$unit ) {
	global $shell_applications;

?>

<p>Synchronisations-Quelle bei <?php echo date( "c" ); ?></p>

<table>
<tr><th><label for="startcode">Start Programmcode</label></th><td><input type="text" name="startcode" value="<?php echo htmlentities( $settings_array[ "startcode" ] ); ?>" /></td></tr>
<tr><th><label for="startsync">Synchronisation</label></th><td><input type="text" name="startsync" value="<?php echo $settings_array[ "startsync" ]; ?>" /></tr>
<tr><th><label for="stateful">Programm etablieren</label></th><td><input name="stateful" type="checkbox"<?php if( $settings_array[ "stateful" ]=="t" ) echo( ' checked="checked"' ); ?> /></tr>
<tr><th><label for="stopcode">Stop Programmcode</label></th><td><input type="text" name="stopcode" value="<?php echo htmlentities( $settings_array[ "stopcode" ] ); ?>" /></td></tr>
<tr><th><label for="stopsync">Synchronisation</label></th><td><input type="text" name="stopsync" value="<?php echo $settings_array[ "stopsync" ]; ?>" /></tr>
</table>

<?php

}

function shell_ondelete( $settings_array ) {
}

function shell_onduplicate( $settings_array ) {
}


function shell_configure( &$settings_array,$unit ) {
	global $shell_applications;

	if( isset( $_POST[ "startcode" ] ) )
		$settings_array[ "startcode" ]= $_POST[ "startcode" ];

	if( isset( $_POST[ "stopcode" ] ) )
		$settings_array[ "stopcode" ]= $_POST[ "stopcode" ];

	if( isset( $_POST[ "startsync" ] ) )
		$settings_array[ "startsync" ]= $_POST[ "startsync" ]?$_POST[ "startsync" ]:NULL;

	if( isset( $_POST[ "stopsync" ] ) )
		$settings_array[ "stopsync" ]= $_POST[ "stopsync" ]?$_POST[ "stopsync" ]:NULL;

	$settings_array[ "stateful" ]=( isset( $_POST[ "stateful" ] )&& $_POST[ "stateful" ]=="on" )?"t":"f";

	return NULL;
}

function shell_getstate( $unit ) {
	return NULL;
}

?>
