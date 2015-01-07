<?php

$shutdown_table = array(
	"force BOOLEAN NOT NULL DEFAULT 'f'",
);

function shutdown_start( $settings_array,$unit,$timestamp ) {
	makestateful( );
	$force = $settings_array[ "force" ]=='t';

	if( isreachable( $unit ) ) {
		mounttools( $unit->name );
		if( $force )
			cqinject( "&terminate now",$unit->name );
		else {
			cqappend( "&terminate",$unit->name );
		}
	}

	register_down( );
}

function shutdown_stop( $settings_array,$unit,$timestamp ) {
}

function shutdown_ondelete( $settings_array ) {
}

function shutdown_onduplicate( $settings_array ) {
}

function shutdown_preferences( $settings_array,$unit ) {
?>

<table>
<tr><th><label for="force">Hart abschalten</label></th><td><input type="checkbox" name="force"<?php if( $settings_array[ "force" ]=='t' ) echo ' checked="checked"'; ?> /></td></tr>
</table>

<?php
}

function shutdown_configure( &$settings_array,$unit ) {
	$settings_array[ "force" ]=( isset( $_POST[ "force" ] )&& $_POST[ "force" ]=="on" )?"t":"f";
}

function shutdown_getstate( $unit ) {
	return "Herunterfahren";
}

?>
