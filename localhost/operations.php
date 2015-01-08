<?php

/* Possible database inconsistencies which have to be checked for:

- Profiles references non-existent settings
- Units references non-existent settings
- Unit may not be listed in profile

However:

- Profiles may not referece an existent program and non-existent
  settings.

Prerequesite: Profile with id $currentprofile exists in profiles. The flow of program is as follows:

- Load a complete list of programs and IDs for various uses throughout
  the program

Part 1: Profile management

1.1 Update the current profile to what was last seen by the user

1.2 If requested, create a new host
1.3 If requested, delete hosts (and while looping, also find out whether
     we go to settings) (*)
1.4 If requested, save the current profile
1.5 If requested, load a specific profile into the current profile
1.6 If requested, delete a specific profile
1.7 If requested, set all hosts in current profile to "keep"

Part 2: Host management

2.1 Load a complete table of the current state and current profile of all
	units

Then, for each host:

2.2 If requested, apply settings to host
2.3 If requested to go to settings by (*), load the respective settings
2.4 If requested, execute current profile

Then, cumulatively

2.5 If requested, query all hosts for their current running state

*/

/** Number of seconds a computer is allowed to boot before unresponsiveness is
 * considered death */
define( "MAXBOOT",60 );

/** Number of seconds a computer is allowed to remain unresponsive but pingable
 * after shutdown signal */
define( "MAXSHUTDOWN",80 );

/** Path to where bash component (batch ping) resides */
define( "BASHDIR","/var/www/ilrc" );

/** Working directory of programs (for their bash components to work) */
define( "UTILDIR","/var/www/ilrc" );

/** Logfile (publicly accessible) for operations */
define( "LOGFILE","/var/www/localhost/access.log" );

class Unit {
	public $name;
	public $id;
	public $ip;
	public $subnet;
	public $mac;
	public $program;
	public $keep;
	public $state;
	public $settings;
	public $uptime;
	public $downtime;
	public $runningprogram;
	public $runningsettings;
}

class Program {
	public $id;
	public $name;
	public $ident;
}

function logentry( $message ) {
	file_put_contents( LOGFILE,"[".date( "c",$_SERVER[ "REQUEST_TIME" ] )."] {$_SERVER[ "REMOTE_ADDR"]}: $message\n",FILE_APPEND|LOCK_EX );
}

function pgvalue( $value ) {
	if( is_null( $value ) )
		return "NULL";
	else
		return "'".pg_escape_string( $value )."'";
}

/* Settings */

$dbhost = "localhost";
$dbuser = "ilrc";
$dbdb = "ilrc";
$dbpass = "ilrcpass";
$currentprofile = 0;

$conn = pg_connect( "host=$dbhost dbname=$dbdb user=$dbuser password=$dbpass" );

/* Get the list of available programs and their associated id so that
references to a program id may be resolved to a program name on the client
side. */

$programs_result = pg_query( "SELECT id,name,ident FROM programs;" );
$programs = array( );
while( $row = pg_fetch_array( $programs_result ) ) {
	$program = new Program( );

	$program->id = (int)( $row[ "id" ] );
	$program->name = $row[ "name" ];
	$program->ident = $row[ "ident" ];

	$programs[ $program->id ]= $program;

	include( "programs/{$program->ident}.php" );
	$table_settings = "{$program->ident}_table";

// TODO: Postgresql 9.5 CREATE SEQUENCE ... IF NOT EXISTS
	@pg_query( "CREATE SEQUENCE settings_{$program->ident}_seq;" );
	pg_query( "CREATE TABLE IF NOT EXISTS settings_{$program->ident}(id integer PRIMARY KEY DEFAULT nextval('settings_{$program->ident}_seq'),".implode( ",",$$table_settings )."); ALTER SEQUENCE settings_{$program->ident}_seq OWNED BY settings_{$program->ident}.id;" );
}

require_once( "copyprofile.php" );

$passedcount = isset( $_POST[ "hostcount" ] )?(int)( $_POST[ "hostcount" ] ):0;

/* SECTION 1.1, Update current profile */
$query = "";
for( $i = 0; $i<$passedcount; $i++ ) {
	if( isset( $_POST[ "mode_$i" ],$_POST[ "mode_{$i}_target" ],$_POST[ "mode_{$i}_id" ] ) ) {
		$id = (int)( $_POST[ "mode_{$i}_id" ] );

		$keep = $_POST[ "mode_$i" ]=="keep"?"TRUE":"FALSE";
		$program = (int)( $_POST[ "mode_{$i}_target" ] );

		$old_result = pg_query( "SELECT program,settings FROM profiles WHERE id=$currentprofile AND unit=$id;" );

		if( ( $old = pg_fetch_array( $old_result ) )&& $program!=(int)( $old[ "program" ] ) ) {
			$progident = $programs[ (int)( $old[ "program" ] ) ]->ident;
			$deletecall = "{$progident}_ondelete";
			
			$oldsettings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id='{$old[ "settings" ]}';" ) );
			unset( $oldsettings[ "id" ] );
			$deletecall( $oldsettings );

			$query .= "DELETE FROM settings_{$progident} WHERE id='{$old[ "settings" ]}';";
		}

		if( !$old || $program!=(int)( $old[ "program" ] ) ) {
			$newidrow = pg_fetch_array( pg_query( "INSERT INTO settings_{$programs[ $program ]->ident} DEFAULT VALUES RETURNING id;" ) );
			$settings = $newidrow[ 0 ];
		} else
			$settings = $old[ "settings" ];

		$query .= "UPDATE profiles SET program=$program,keep=$keep,settings=$settings WHERE id=$currentprofile AND unit=$id; INSERT INTO profiles(id,unit,program,settings,keep) SELECT 0,id,$program,$settings,$keep FROM units WHERE id=$id AND NOT EXISTS(SELECT * FROM profiles WHERE id=$currentprofile AND unit=$id);";
	}
}

/* SECTION 1.2, Create new host */
if( isset( $_POST[ "new" ],$_POST[ "new_mac" ],$_POST[ "new_name" ],$_POST[ "new_subnet" ] ) ) {
	$query .= "INSERT INTO units(mac,name,subnet) VALUES(".pgvalue( $_POST[ "new_mac" ] ).",".pgvalue( $_POST[ "new_name" ] ).",".pgvalue( $_POST[ "new_subnet" ] ) .");";

	logentry( "Created new host {$_POST[ "new_name" ]}" );
}

$settingsid = NULL;

/* SECTION 1.3, Delete hosts */
for( $i = 0; $i<$passedcount; $i++ ) {
	if( isset( $_POST[ "mode_{$i}_id" ] ) ) {
		$id = (int)( $_POST[ "mode_{$i}_id" ] );
			if( isset( $_POST[ "mode_{$i}_settings" ] ) ) {
				// SET
				$settingsid = $id;
			} elseif( isset( $_POST[ "mode_{$i}_delete" ] ) ) {
				// DEL
				$settings_result = pg_query( "SELECT program,settings FROM profiles WHERE unit=$id;" );
				while( $row = pg_fetch_assoc( $settings_result ) )
					$query .= "DELETE FROM settings_{$programs[ $row[ "program" ] ]->ident} WHERE	id={$row[ "settings" ]};";

				$query .= "DELETE FROM units WHERE id=$id;";
				logentry( "Deleted host #$id" );
			}
		}
}

if( $query!="" )
	pg_query( $query );

/* SECTION 1.4, Save profile */
if( isset( $_POST[ "save_profile" ] )&& isset( $_POST[ "profile_name" ] )&& $_POST[ "profile_name" ]!="" ) {
	copy_profile( $_POST[ "profile_name" ],false,0,true );

	logentry( "Saved profile {$_POST[ "profile_name" ]}" );
}

/* SECTION 1.5, Load profile */
if( isset( $_POST[ "load_profile" ] )&& isset( $_POST[ "scenario" ] )&& $_POST[ "scenario" ]!="" )
	copy_profile( 0,true,$_POST[ "scenario" ],false );

/* SECTION 1.6, Delete profile */
if( isset( $_POST[ "delete_profile" ] )&& isset( $_POST[ "profile_name" ] )&& $_POST[ "profile_name" ]!="" ) {
	delete_profile_data( $_POST[ "profile_name" ],false );
	pg_query( "DELETE FROM profilenames WHERE name=".pgvalue( $_POST[ "profile_name" ] ).";" );

	logentry( "Deleted profile {$_POST[ "profile_name" ]}" );
}

/* SECTION 1.7, Keep all hosts */
if( isset( $_POST[ "keepall" ] ) ) {
	pg_query( "UPDATE profiles SET keep='t' WHERE id=$currentprofile;" );
}

/* SECTION 2.1, Load complete table of all units */
$state_query = "SELECT
	units.name AS name,
	units.id AS id,
	units.subnet AS subnet,
	units.program AS runprogram,
	units.settings AS runsettings,
	units.ip AS ip,
	units.mac AS mac,
	EXTRACT( EPOCH FROM now( )-units.woken )AS uptime,
	EXTRACT( EPOCH FROM now( )-units.killed )AS downtime,
	profiles.keep AS keep,
	profiles.program AS program,
	profiles.settings AS settings
	FROM
		units
	LEFT JOIN
		profiles
	ON units.id=unit AND profiles.id=$currentprofile ORDER BY units.name;";

$messages = array( );
$unit_fqdns = array( );
$units = array( );

$state = pg_query( $state_query );

$applyid =( !isset( $_POST[ "settingscancel" ] )&& isset( $_POST[ "settingsapply" ],$_POST[ "mode_0_id" ] ) )?(int)( $_POST[ "mode_0_id" ] ):NULL;
$perform = isset( $_POST[ "perform" ] );

$logsum = "Executed profile: ";

include( "utils.php" );

function makestateful( ) {
	global $programs;
	global $is_stateful;
	global $unit;
	global $timestamp;

/* For Meta Programs which start other programs, make only the Meta program
 * stateful (it must request so). */
	if( !$is_stateful && !is_null( $unit->runningprogram ) ) {
		$runprogident = $programs[ $unit->runningprogram ]->ident;
		$stopcall = "{$runprogident}_stop";
		$runsettingsarray = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$runprogident} WHERE id={$unit->runningsettings};" ) );
		unset( $runsettingsarray[ "id" ] );
		$stopcall( $runsettingsarray,$unit,$timestamp );

		$deletecall = "{$runprogident}_ondelete";
		$deletecall( $runsettingsarray );
		pg_query( "DELETE FROM settings_$runprogident WHERE id={$unit->runningsettings};" );
	}

	$is_stateful = true;
}

function register_up( ) {
	global $unit;
	pg_query( "UPDATE units SET woken=now( ) WHERE id='".pg_escape_string( $unit->id )."';" );
	$unit->uptime = 0;
}
function register_down( ) {
	global $unit;
	pg_query( "UPDATE units SET killed=now( ) WHERE id='".pg_escape_string( $unit->id )."';" );
	$unit->downtime = 0;
}

$dosettings = false;
$units_fqdns = array( );

$timestamp = time( );

while( $row = pg_fetch_array( $state ) ) {
	$unit = new Unit( );

	$unit->name = $row[ "name" ];
	$unit->id = $row[ "id" ];
	$unit->subnet = $row[ "subnet" ];
	$unit->ip = $row[ "ip" ];
	$unit->mac = $row[ "mac" ];
	$unit->program = $row[ "program" ];
	$unit->keep = $row[ "keep" ]=="t" || is_null( $unit->program );
	$unit->state = array( "gray","white","???" );
	$unit->settings = $row[ "settings" ];
	$unit->uptime = is_null( $row[ "uptime" ] )?NULL:floatval( $row[ "uptime" ] );
	$unit->downtime = is_null( $row[ "downtime" ] )?NULL:floatval( $row[ "downtime" ] );
	$unit->runningprogram = $row[ "runprogram" ];
	$unit->runningsettings = $row[ "runsettings" ];

	$units_fqdns[ ]= "'{$unit->name}'";

	if( !is_null( $unit->program )&&( $unit->id==$settingsid || $unit->id==$applyid )|| $perform && !$unit->keep ) {
		$progident = $programs[ $unit->program ]->ident;

		$settingsarray = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id={$unit->settings};" ) );
		unset( $settingsarray[ "id" ] );

/* SECTION 2.2, Apply settings to host */
		if( $unit->id==$applyid ) {
			$configcall = "{$progident}_configure";
			if( !is_null( $message = $configcall( $settingsarray,$unit ) ) )
				$messages[ ]= $message;

			pg_query( "UPDATE settings_{$progident} SET (".implode( ",",array_keys( $settingsarray ) ).")=(".implode( ",",array_map( "pgvalue",array_values( $settingsarray ) ) ).") WHERE id={$unit->settings};" );
		}

/* SECTION 2.3, Remember settings for Setting dialog */
		if( $unit->id==$settingsid ) {
			$settings = $settingsarray;
			$settingsunit = $unit;

			if( !isset( $_POST[ "settingsokay" ] )&& !isset( $_POST[ "settingscancel" ] ) ) {
				$dosettings = true;
				$settingscall = "{$progident}_preferences";
				$settingsprogname = $programs[ $unit->program ]->name;
			}
		}

/* SECTION 2.4, Execute program */
		if( $perform && !$unit->keep ) {
			$startcall = "{$progident}_start";
			$is_stateful = false;

			$logsum .= "$unit->name -> $progident; ";

			$phproot = getcwd( );
			chdir( UTILDIR );
			$startcall( $settingsarray,$unit,$timestamp );
			chdir( $phproot );

			if( $is_stateful ) {
				$duplicatecall = "{$progident}_onduplicate";
				$duplicatecall( $settingsarray );

				$newsettings = pg_fetch_array( pg_query( "INSERT INTO settings_$progident(".implode( ",",array_keys( $settingsarray ) ).") VALUES(".implode( ",",array_map( "pgvalue",array_values( $settingsarray ) ) ).") RETURNING id;" ) );

				pg_query( "UPDATE units SET program={$unit->program},settings={$newsettings[ 0 ]} WHERE id={$unit->id};" );

				$unit->runningprogram = $unit->program;
				$unit->runningsettings = $unit->settings;
			}
		}
	}

	$units[ ]= $unit;
}

if( $perform )
	logentry( $logsum );

/* SECTION 2.5, Query state of units
 *
 * A unit can be in one of various states, depending on how it responds
 * to ping  ssh, what its woken and killed timestamps are and whether it
 * has a running program (it usually always does).
 *
 *The following mutually exclusive states are used for the following situations. Situations:
 *
 * P: Pingable
 * S: SSHable
 * U: Uptime not NULL
 * U><MB: Uptime greater/smaller than MAXBOOT
 * D: Dontime not NULL
 * D><MS: Downtime greater/smaller than MAXSHUTDOWN
 * P: Program not NULL
 * U><D: Uptime greater/smaller than downtime
 * R: Program is not NULL
 *
 * Shorthands
 * K: ( D && D<U ) || !U - Box is supposed to be down
 * A: !K = ( !D || U<D ) && U - Box ssuposed to be up
 *
 * DOWN: !P && K
 * UNREACHABLE: !P && A && U>MB
 * UNCONTROLLED: P && !S && !( A && U<=MB )
 * BOOTING: ( !P || !S )&& A && U<=MB
 * UP: P && S && !A && D>MS
 *
 * Lastly, there is a program-dependent state for
 *
 * P && ( S && A || !$A && D<=MS )
 */
if( isset( $_POST[ "refresh" ] )|| isset( $_POST[ "perform" ] )&& is_null( $settingsid ) ) {
	$stateres = array( );

	$phproot = getcwd( );
	chdir( BASHDIR );
	exec( "./batch_ping.bsh ".implode( " ",$units_fqdns ),$stateres );
	chdir( $phproot );

	$i = 0;
	$ipquery = "";
	foreach( $units as $unit ) {
		$s = $stateres[ $i ][ 0 ]=="*";
		$p = $stateres[ $i ][ 0 ]!="-";
		$r = !is_null( $unit->runningprogram );
		$a = !is_null( $unit->uptime )&&( is_null( $unit->downtime )|| $unit->uptime<$unit->downtime );

		if( ( !$p || !$s )&& $a && $unit->uptime<=MAXBOOT )
			$unit->state = array( "magenta","white","Boot" );
		elseif( $p ) {
			if( $r && ( $s && $a || !$a && $unit->downtime<MAXSHUTDOWN ) ) {
				$getstatename = "{$programs[ $unit->runningprogram ]->ident}_getstate";
				$statestring = $getstatename( $unit->name,$unit->mac,$unit->ip );
				if( is_null( $statestring ) )
					$statestring = $programs[ $unit->runningprogram ]->name;
				$unit->state = array( "green","white",$statestring );
			} elseif ( $s && !$a &&( is_null( $unit->downtime )|| $unit->downtime>MAXSHUTDOWN ) )
				$unit->state = array( "green","silver","An" );
			elseif( !$s )
				$unit->state = array( "gray","yellow","Unkontrollierbar" );
			else
				$unit->state = array( "silver","black","THIS IS BUG P[$p] S[$s] A[$a]" );
		} else {
			if( !$a )
				$unit->state = array( "gray","silver","Aus" );
			elseif( $a && $unit->uptime>MAXBOOT )
				$unit->state = array( "yellow","red","Unerreichbar" );
			else
				$unit->state = array( "silver","black","THIS IS BUG P[$p] S[$s] A[$a]" );
		}

		$unit->ip = $p?substr( $stateres[ $i ],1 ):NULL;

		$ipquery .= "UPDATE units SET ip=".pgvalue( $unit->ip )." WHERE id={$unit->id};";

		$i++;
	}

	if( $ipquery )
		pg_query( $ipquery );
}

$profiles_result = pg_query( "SELECT id,name FROM profilenames WHERE id!=$currentprofile;" );

if( $dosettings )
	include( "settings.php" );
else
	include( "overview.php" );

?>
