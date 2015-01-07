<?php

$website_table = array(
	"profile SMALLINT NOT NULL DEFAULT 0",
	"url VARCHAR(255)",
	"onquit SMALLINT NOT NULL DEFAULT 0",
	"monitor SMALLINT NOT NULL DEFAULT 0"
);

function website_start( $settings_array,$unit,$timestamp ) {
	makestateful( );

	rcboot( $unit->mac,"192.168.0.0/25" );
	mounttools( $unit->name );

	cqappend( "stop website_{$settings_array[ "monitor" ]}",$unit->name );
	cqappend( "&repeat {$settings_array[ "onquit" ]} website_{$settings_array[ "monitor" ]} website ".escapeshellarg( $settings_array[ "url" ] )." {$settings_array[ "profile" ]} {$settings_array[ "monitor" ]}",$unit->name );
}

function website_stop( $settings_array,$unit,$timestamp ) {
	cqappend( "stop website_{$settings_array[ "monitor" ]}",$unit->name );
}

function website_ondelete( $settings_array ) {
}

function website_onduplicate( $settings_array ) {
}

function website_preferences( $settings_array,$unit ) {

?>

<table>
<tr><th><label for="url">Webseite</label></th><td><input type="text" name="url" value="<?php echo $settings_array[ "url" ]; ?>"/></td></tr>
<tr><th><label for="profile">Darstellung</label></th><td>
	<select name="profile"><?php

	if( ( $profile = (int)( $settings_array[ "profile" ] ) )<0 || $profile>2 )
		$profile = 0;

?>
		<option value="0"<?php if( $profile==0 ) echo ' selected="selected"'; ?>>Standard</option>
		<option value="1"<?php if( $profile==1 ) echo ' selected="selected"'; ?>>Vollbild</option>
		<option value="2"<?php if( $profile==2 ) echo ' selected="selected"'; ?>>Vollbild ohne Menu</option>
	</select>
</td></tr>
<tr><th><label for="onquit">Bei Beendung durch Nutzer</label></th><td>
	<select name="onquit"><?php

	if( ( $onquit = (int)( $settings_array[ "onquit" ] ) )<0 || $onquit>2 )
		$onquit = 0;
?>
		<option value="0"<?php if( $onquit==0 ) echo ' selected="selected"'; ?>>Herunterfahren</option>
		<option value="1"<?php if( $onquit==1 ) echo ' selected="selected"'; ?>>Reaktivieren</option>
		<option value="2"<?php if( $onquit==2 ) echo ' selected="selected"'; ?>>Ignorieren</option>
	</select>
</td></tr>
<tr><th><label for="monitor">Monitor</label></th><td><input type="number" min="0" name="monitor" value="<?php echo $settings_array[ "monitor" ]; ?>" /></td></tr>
</table>

<?php

}

function website_configure( &$settings_array,$unit ) {
	if( isset( $_POST[ "onquit" ] ) ) {
		
		$onquit = (int)( $_POST[ "onquit" ] );
		if( $onquit>2 )
			$onquit = 0;

		if( isset( $_POST[ "url" ] ) )
			$settings_array[ "url" ]= $_POST[ "url" ];

		setmonitor( $settings_array );

		if( isset( $_POST[ "profile" ] ) )
			$settings_array[ "profile" ]= max( 0,(int)( $_POST[ "profile" ] ) );

		if( isset( $_POST[ "onquit" ] ) )
			$settings_array[ "onquit" ]= max( 0,(int)( $_POST[ "onquit" ] ) );
	}
	return NULL;
}

function website_getstate( $unit ) {
	return NULL;
}

?>
