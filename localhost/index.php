<?xml version="1.1" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Innovations Lab Remote Control</title>
<?php

/** Logfile (publicly accessible) for operations */
define( "LOGFILE","/var/www/localhost/access.log" );

/** Admin token */
require( "include/config.php" );
define( "ILRC_ADMIN",-1 );

session_start( );

/* Connection setup */

$dbhost = "localhost";
$dbuser = "ilrc";
$dbdb = "ilrc";
$dbpass = "ilrcpass";
$currentprofile = 0;

$conn = pg_connect( "host=$dbhost dbname=$dbdb user=$dbuser password=$dbpass" );

function pgvalue( $value ) {
	if( is_null( $value ) )
		return "NULL";
	else
		return "'".pg_escape_string( $value )."'";
}

$role = NULL;
$rolename = NULL;

function logentry( $message ) {
	global $rolename;
	file_put_contents( LOGFILE,"[".date( "c",$_SERVER[ "REQUEST_TIME" ] )."] ".( is_null( $rolename )?"":"$rolename@" )."{$_SERVER[ "REMOTE_ADDR"]}: $message\n",FILE_APPEND|LOCK_EX );
}

/* Authentication */

if( !isset( $_REQUEST[ "logout" ] ) ) {
/* Verify existing */
	if( isset( $_SESSION[ "role" ] ) ) {
		if( $_SESSION[ "role" ]==ILRC_ADMIN ) {
				$role = ILRC_ADMIN;
				$rolename = ILRC_ADMINNAME;
		} else {
			$roles_res = pg_query( "SELECT * FROM roles WHERE upper(name)=upper(".pgvalue( $_SESSION[ "rolename" ] ).");" );
			if( $role_row = pg_fetch_row( $roles_res ) ) {
				$role = $role_row[ 0 ];
				$rolename = $_SESSION[ "rolename" ];
			}
		}
	}
	if( isset( $_POST[ "login" ] ) ) {
	/* Login and authentication */
		$loginstr = $_POST[ "login" ];
		if( (bool)( strpos( $loginstr,":",0 ) ) ) {
			$loginrole = strstr( $loginstr,":",true );
			$loginpass = substr( strstr( $loginstr,":" ),1 );

			$auth_res = pg_query( "SELECT id,name FROM roles WHERE upper(name)=upper(".pgvalue( $loginrole ).") AND checksum=md5(".pgvalue( $loginpass ).");" );
		} else {
			$loginrole = $loginstr;
			$auth_res = pg_query( "SELECT id,name FROM roles WHERE upper(name)=upper(".pgvalue( $loginrole ).") AND checksum=NULL;" );
		}

		if( $role_row = pg_fetch_row( $auth_res ) ) {
			$rolename = $role_row[ 1 ];
			$role = $role_row[ 0 ];
		}

		if( $loginstr==ILRC_ADMINNAME ) {
			$rolename = ILRC_ADMINNAME;
			$role = ILRC_ADMIN;
		}
	}
}

if( !is_null( $role ) ) {
	$_SESSION[ "role" ]= $role;
	$_SESSION[ "rolename" ]= $rolename;
} elseif( isset( $_SESSION[ "role" ] ) )
	unset( $_SESSION[ "role" ] );

?>
</head>
<body>
<form enctype="multipart/form-data" method="post" action=".">
<?php

if( !is_null( $role ) ) {
	if( $role==ILRC_ADMIN ) {

?>
	<div style="float:left;">
		<button type="submit" name="logout" style="vertical-align:middle;"><img style="vertical-align:bottom" src="icons/admin.png" alt="Sperren" /></button>
	</div>
<?php

		} else {

?>
	<div style="float:left;">
		<button type="submit" name="logout" style="vertical-align:middle;"><?php echo $rolename; ?> <img style="vertical-align:bottom" src="icons/lock.png" alt="Sperren" /></button>
	</div>
<?php

	}
}

?>
	<ul class="smalllinks">
		<li><a href="http://wiki.fir.de/index.php?title=Innovations_Lab_Remote_Control">Hilfe</a></li>
		<li><a href="#footer">PC Karte</a></li>
		<li><a href="mailto:cedric.sodhi@fir.rwth-aachen.de">Kontakt</a></li>
	</ul>
	<div class="row"><h1>InnoLab <span>Remote Control (Version 0.2)</span></h1></div>
<?php

if( is_null( $role ) ) {
	if( isset( $loginrole ) ) {

?>
	<div class="row">
		<p style="border: 2px solid red; background-color: silver; color: red; ?>">
			<b>Authentifikation gescheitert! Versuch wurde protokolliert.</b>
		</p>
	</div>
<?php

		logentry( "Authentication failed for role '$loginrole'" );
	}
	require( "include/loginscreen.php" );
} else
	require( "include/operations.php" );

?>
</form>
</body>
</html>
