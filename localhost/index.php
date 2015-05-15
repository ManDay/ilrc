<?php

/** POST- GET- Policy
 *
 * Sent data which effect permanent changes, i.e.
 *
 * - Create host
 * - Delete host
 * - Save profile
 * - Delete profile
 * - Modify settings
 * - Load profile
 *
 * are send as POST data, because refreshing the page on most browsers will in
 * that case warn the user of resending data. The associated actions will be
 * performed when a POST request is received.
 * Since, from the user's perspective, the following page will be a regular
 * view (of either the overview or an individual host), that following page
 * must be regularly "refreshable". This implies we redirect from after the
 * POST request with a GET request to the page that is expected. The passed GET
 * parameters are all parameters which affect the view, i.e.
 *
 * - Refresh status
 * - View of settings
 * - Subview of settings
 *
 * Since the request for views is initially submitted as POST, those are
 * on the first reception being converted into GET. The identifiers may
 * of course coincide. Only the GET shall actually effect the view, to the
 * effect that every view can be shown and be refreshable by the user.
 * Similarly, only POST shall actually perform actions, to the the effect that
 * actions may not be injected through specifically crafted URLs.
 */

/** Admin token */
require( "include/config.php" );
define( "ILRC_ADMIN",-1 );
define( "ILRC_ADMINALIAS","Administrator" );

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
	file_put_contents( ILRC_LOGFILE,"[".date( "c",$_SERVER[ "REQUEST_TIME" ] )."] ".( is_null( $rolename )?"":"$rolename@" )."{$_SERVER[ "REMOTE_ADDR"]}: $message\n",FILE_APPEND|LOCK_EX );
}

/* Authentication */

if( !isset( $_REQUEST[ "logout" ] ) ) {
/* Verify existing */
	if( isset( $_SESSION[ "role" ] ) ) {
		if( $_SESSION[ "role" ]==ILRC_ADMIN ) {
				$role = ILRC_ADMIN;
				$rolename = ILRC_ADMINALIAS;
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
			$auth_res = pg_query( "SELECT id,name FROM roles WHERE upper(name)=upper(".pgvalue( $loginrole ).") AND checksum IS NULL;" );
		}

		if( $role_row = pg_fetch_row( $auth_res ) ) {
			$rolename = $role_row[ 1 ];
			$role = $role_row[ 0 ];
		}

		if( $loginstr==ILRC_ADMINNAME ) {
			$rolename = ILRC_ADMINALIAS;
			$role = ILRC_ADMIN;
		}
	}
}

$singleunit = NULL;
$singlemode = 0;

/** [ 0 ]: Background-Color, [ 1 ]: Text- and Border-Color, [ 2 ]: Message */
if( isset( $_SESSION[ "messages" ] ) ) {
	$messages = $_SESSION[ "messages" ];
	unset( $_SESSION[ "messages" ] );
} else
	$messages = array( );

if( !is_null( $role ) ) {
	$_SESSION[ "role" ]= $role;
	$_SESSION[ "rolename" ]= $rolename;
	require( "include/operations.php" );
} elseif( isset( $_SESSION[ "role" ] ) ) {
	unset( $_SESSION[ "role" ] );
} elseif( isset( $loginrole ) ) {
	$messages[ ]= array( "silver","red","Authentifikation gescheitert! Versuch wurde protokolliert." );
	logentry( "Authentication failed for role '$loginrole'" );
}

if( count( $_POST )>0 ) {
	$get_array = array( );
	foreach( $_GET as $name=>$value )
		$get_array[ ]= $name."=".urlencode( $value );

	$_SESSION[ "messages" ]= $messages;
	if( count( $get_array )>0 ) {
		header( "Location: http://{$_SERVER[ "SERVER_NAME" ]}?".implode( "&",$get_array ) );
	} else
		header( "Location: http://{$_SERVER[ "SERVER_NAME" ]}" );
} else {
	require( "include/display.php" );
}


?>
