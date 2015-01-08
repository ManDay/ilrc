<?php

define( "MAX_FILESIZE",128*1024*1024 );
define( "UPLOAD_DIR","/var/www/ilrc/shared/uploads/present" );

$present_table = array(
	"fullscreen BOOLEAN NOT NULL DEFAULT 't'",
	"filename VARCHAR(255)",
	"onquit SMALLINT NOT NULL DEFAULT 0",
	"application SMALLINT NOT NULL DEFAULT 0",
	"monitor SMALLINT NOT NULL DEFAULT 0"
);

$present_applications = array(
	"LibreOffice"=>"pptviewer",
	"Evince PDF"=>"pdfviewer",
	"Video Abspieler"=>"movviewer"
);

function present_start( $settings_array,$unit,$timestamp ) {
	global $present_applications;

	makestateful( );

	rcboot( $unit->mac,$unit->subnet );
	mounttools( $unit->name );

	$fullscreen = $settings_array[ "fullscreen" ]=="t"?1:0;

	$applications = array_values( $present_applications );
	$appid = (int)( $settings_array[ "application" ] );

	if( $appid<0 )
		$appid = 0;
	if( $appid>=count( $applications ) )
		$appid = count( $applications )-1;

	$application = $applications[ $appid ];

	cqappend( "&present {$settings_array[ "onquit" ]} {$settings_array[ "monitor" ]} ".escapeshellarg( $settings_array[ "filename" ] )." $application",$unit->name );
}

function present_ondelete( $settings_array ) {
}

function present_onduplicate( $settings_array ) {
}

function present_stop( $settings_array,$unit,$timestamp ) {
	cqappend( "stop present_{$settings_array[ "monitor" ]}",$unit->name );
}

function present_preferences( $settings_array,$unit ) {
	global $present_applications;

?>

<table>
<tr><th><label for="current">Aktuelle Datei</label></th><td>
	<select name="current"><?php

	$current = $settings_array[ "filename" ];

	$files = scandir( UPLOAD_DIR );
	foreach( $files as $file )
		if( $file!=".." && $file!="." ) {

?>
		<option value="<?php echo $file; ?>"<?php if( $current==$file ) echo ' selected="selected"'; ?>><?php echo $file ; ?></option>
<?php

		}

?>
	</select>
</td></tr>
<tr><th><label for="new">Neue Datei hochladen</label></th><td><input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILESIZE; ?>" /><input type="file" name="new" /></td></tr>
<tr><th><label for="fullscreen">Vollbildmodus</label></th><td><input type="checkbox" name="fullscreen"<?php if( $settings_array[ "fullscreen" ]=='t' ) echo ' checked="checked"'; ?> /></td></tr>
<tr><th><label for="onquit">Bei Beendung durch Nutzer</label></th><td>
	<select name="onquit"><?php
	$onquit = (int)( $settings_array[ "onquit" ] );
?>
		<option value="0"<?php if( $onquit==0 ) echo ' selected="selected"'; ?>>Herunterfahren</option>
		<option value="1"<?php if( $onquit==1 ) echo ' selected="selected"'; ?>>Reaktivieren</option>
		<option value="2"<?php if( $onquit==2 ) echo ' selected="selected"'; ?>>Ignorieren</option>
	</select>
</td></tr>
<tr><th><label for="application">Anwendung</label></th><td>
	<select name="application"><?php
	$appid = (int)( $settings_array[ "application" ] );

	$appnames = array_keys( $present_applications );
	for( $i = 0; $i<count( $present_applications ); $i++ ) {
?>
		<option value="<?php echo $i; ?>"<?php if( $i==$appid ) echo ' selected="selected"'; ?>><?php echo $appnames[ $i ]; ?></option>
<?php } ?>
	</select>
</td></tr>
<tr><th><label for="monitor">Monitor</label></th><td><input type="number" min="0" name="monitor" value="<?php echo $settings_array[ "monitor" ]; ?>" /></td></tr>
</table>

<?php

}

function present_configure( &$settings_array,$unit ) {
	global $present_applications;

	if( isset( $_POST[ "onquit" ] ) ) {
		$settings_array[ "fullscreen" ]=( isset( $_POST[ "fullscreen" ] )&& $_POST[ "fullscreen" ]=='on' )?"t":"f";
		
		$onquit = (int)( $_POST[ "onquit" ] );
		if( $onquit>2 )
			$onquit = 0;

		$appid = (int)( $_POST[ "application" ] );

		if( $appid<0 )
			$appid = 0;
		if( $appid>=count( $present_applications ) )
			$appid = count( $present_applications )-1;

		$settings_array[ "onquit" ]= $onquit;
		$settings_array[ "application" ]= $appid;

		setmonitor( $settings_array );

		if( isset( $_FILES[ "new" ] )&& $_FILES[ "new" ][ "error" ]!=UPLOAD_ERR_NO_FILE ) {
			$present = $_FILES[ "new" ];

			if( $present[ "size" ]>MAX_FILESIZE )
				return array( "red","yellow","Fehler: Datei darf nicht größer als ".MAX_FILESIZE." Bytes sein!" );

			if( $present[ "error" ]!=UPLOAD_ERR_OK )
				return array( "red","yellow","Allgemeiner Fehler: Code {$present[ "error" ]}" );

			if( !preg_match( "/^[[:alnum:],-\\.]+/",$present[ "name" ] ) )
				return array( "red","yellow","Fehler: Dateiname darf nur Buchstaben, Zahlen und Strich, Komma und Punkt enthalten!" );

			$target = UPLOAD_DIR."/{$present[ "name" ]}";
			move_uploaded_file( $present[ "tmp_name" ],$target );
			chmod( $target,0664 );

			$settings_array[ "filename" ]= $present[ "name" ];
		} elseif( isset( $_POST[ "current" ] ) ) {
			$file = $_POST[ "current" ];
			$files = scandir( UPLOAD_DIR );
			if( in_array( $file,$files ) )
				$settings_array[ "filename" ]= $file;
			else
				return array( "red","yellow","Fehler: Die gewünschte Datei wurde in der Zwischenzeit gelöscht!".print_r( $files,true ) );
		}
	}
	return NULL;
}

function present_getstate( $unit ) {
	return NULL;
}

?>
