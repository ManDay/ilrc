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

 1 If requested, load a specific profile into the current profile
 2 If requested, create a new host
 3 Load a complete table of the current state and current profile of all
	 units. Then, for each host, if permitted...
 4 Detect whether we will go to settings for the host
 5 Set correct program on host and prepare settings entry
 6 Apply specific settings to host
 7 If requested, set all hosts in current profile to "keep"
 8 If requested, execute current profile for the host
 9 If requested, change permissions on the host and delete it
10 If requested, save the current profile
11 If requested, query all hosts for their current running state
12 If requested, delete a specific profile

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

define( "SINGLE_SETTINGS",1 );
define( "SINGLE_DETAILS",2 );

define( "RIGHTS_NONE",0 );
define( "RIGHTS_APPLY",1 );
define( "RIGHTS_EDIT",2 );
define( "RIGHTS_DELETE",3 );

class Unit {
	public $name;
	public $id;
	public $ip;
	public $subnet;
	public $mac;
	public $program;
	public $rights;
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

function has_profile_right( $profilename,&$list=NULL ) {
	global $role;
	if( $role==ILRC_ADMIN )
		return true;

	$failed_rights = pg_query( "SELECT units.name FROM profiles LEFT JOIN profilenames ON profiles.id=profilenames.id LEFT JOIN units ON profiles.unit=units.id LEFT JOIN permissions ON units.id=permissions.unit AND permissions.role=$role WHERE profilenames.name=".pgvalue( $profilename )." AND profiles.keep=false AND( permissions.rights IS NULL OR permissions.rights<".RIGHTS_DELETE." );" );

	$failed = false;

	while( $failed_host = pg_fetch_row( $failed_rights ) ) {
		$failed = true;
		if( is_null( $list ) )
			break;

		$list[ ]= $failed_host[ 0 ];
	}
	
	return !$failed;
}

function verify_consistency( ) {
	global $programs;

	foreach( $programs as $program ) {

		$cond_array = array( );
		for( $i = 0; $i<4; $i++ )
			$cond_array[ ]="settings_hydra.program_$i={$program->id} AND settings_hydra.settings_$i=settings_{$program->ident}.id";
			
		$mismatch = pg_fetch_row( pg_query( "SELECT COUNT(*) FROM settings_{$program->ident} WHERE NOT EXISTS(SELECT * FROM profiles WHERE program={$program->id} AND settings=settings_{$program->ident}.id) AND NOT EXISTS(SELECT * FROM units WHERE program={$program->id} AND settings=settings_{$program->ident}.id) AND NOT EXISTS(SELECT * FROM settings_hydra WHERE ".join( " OR ",$cond_array ).");" ) )[ 0 ];

		if( $mismatch!=0 )
			if( isset( $_REQUEST[ "fix" ] ) ) {
				pg_query( "DELETE FROM settings_{$program->ident} WHERE NOT EXISTS(SELECT * FROM profiles WHERE program={$program->id} AND settings=settings_{$program->ident}.id) AND NOT EXISTS(SELECT * FROM units WHERE program={$program->id} AND settings=settings_{$program->ident}.id) AND NOT EXISTS(SELECT * FROM settings_hydra WHERE ".join( " OR ",$cond_array ).");" );
				echo '<p style="border: 1px solid black; background-color: blue; padding: 2px;">Settings for '.$program->name." had $mismatch mismatches. The inconsistency was corrected.</p>";
			} else {
				logentry( "Mismatch: $mismatch on {$program->name}" );
				echo '<p style="border: 1px solid black; background-color: red; padding: 2px;">Settings for '.$program->name." has $mismatch mismatches.".' This is (despite the alarming color) a non-critical debug message. Please try to remember what you just did and <a href="mailto:sodhi@fir.rwth-aachen.de">tell me</a>. The mismatch can be automatically corrected by calling this URL with <i>?fix</i> appended to it.</p>';
			}
	}
}

require_once( "copyprofile.php" );

$passedcount = isset( $_POST[ "hostcount" ] )?(int)( $_POST[ "hostcount" ] ):0;

/* SECTION 1, Load profile. Everyone can do that. */
if( isset( $_POST[ "load_profile" ] )&& isset( $_POST[ "scenario" ] )&& $_POST[ "scenario" ]!="" )
	copy_profile( 0,true,$_POST[ "scenario" ],false );

/* SECTION 3, Create new host. Everyone can do that. */
if( isset( $_POST[ "new" ],$_POST[ "new_mac" ],$_POST[ "new_name" ],$_POST[ "new_subnet" ] ) ) {
	if( $role==ILRC_ADMIN )
		pg_query( "INSERT INTO units(mac,name,subnet) VALUES(".pgvalue( $_POST[ "new_mac" ] ).",".pgvalue( $_POST[ "new_name" ] ).",".pgvalue( $_POST[ "new_subnet" ] ) .");" );
	else
		pg_query( "WITH creation AS (INSERT INTO units(mac,name,subnet) VALUES(".pgvalue( $_POST[ "new_mac" ] ).",".pgvalue( $_POST[ "new_name" ] ).",".pgvalue( $_POST[ "new_subnet" ] ) .") RETURNING id) INSERT INTO permissions(role,unit,rights) SELECT $role,id,3 FROM creation;" );
	
	logentry( "Created new host {$_POST[ "new_name" ]}" );
}

/* SECTION 5, Load complete table of all units */
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
	profiles.settings AS settings,
	permissions.rights AS rights
	FROM
		units
	LEFT JOIN profiles ON units.id=profiles.unit AND profiles.id=$currentprofile
	LEFT JOIN permissions ON units.id=permissions.unit AND permissions.role=".pgvalue( $role )." ORDER BY units.name;";

/** [ 0 ]: Background-Color, [ 1 ]: Text- and Border-Color, [ 2 ]: Message */
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

$singleunit = NULL;
$units_fqdns = array( );

$timestamp = time( );

while( $row = pg_fetch_array( $state ) ) {
	$unit = new Unit( );

	$unit->name = $row[ "name" ];
	$unit->id = (int)( $row[ "id" ] );
	$unit->subnet = $row[ "subnet" ];
	$unit->ip = $row[ "ip" ];
	$unit->rights =( $role==ILRC_ADMIN )?RIGHTS_DELETE:$row[ "rights" ];
	$unit->mac = $row[ "mac" ];
	$unit->program = $row[ "program" ]?( (int)( $row[ "program" ] ) ):NULL;
	$unit->keep = $row[ "keep" ]=="t" || is_null( $unit->program );
	$unit->state = array( "gray","white","???" );
	$unit->settings = $row[ "settings" ];
	$unit->uptime = is_null( $row[ "uptime" ] )?NULL:floatval( $row[ "uptime" ] );
	$unit->downtime = is_null( $row[ "downtime" ] )?NULL:floatval( $row[ "downtime" ] );
	$unit->runningprogram = $row[ "runprogram" ];
	$unit->runningsettings = $row[ "runsettings" ];
	
	$units_fqdns[ ]= "'{$unit->name}'";

	$query = "";
	$prefix = NULL;
	$fetchsettings = false;

	for( $i = 0; $i<$passedcount; $i++ )
		if( isset( $_POST[ "mode_{$i}_id" ] )&& (int)( $_POST[ "mode_{$i}_id" ] )==$unit->id ) {
			$prefix = "mode_{$i}";
			break;
		}

/* SECTION 4, Detect whether we will use the host in a single view */
	if( !is_null( $prefix ) ) {
		if( isset( $_POST[ "{$prefix}_settings" ] )&& !isset( $_POST[ "settingsokay" ] )&& !isset( $_POST[ "settingscancel" ] ) ) {
			// SET
			$singleunit = $unit;
			$singlemode = SINGLE_SETTINGS;

			$fetchsettings = true;
		} elseif( isset( $_POST[ "{$prefix}_details" ] ) ) {
			// DET
			$singleunit = $unit;
			$singlemode = SINGLE_DETAILS;
		}

		if( isset( $_POST[ $prefix ] )&& !isset( $_POST[ "load_profile" ] ) )
			$unit->keep = $_POST[ $prefix ]=="keep";
	}

/* SECTION 5, Set the correct program on the host. This should generally
 * correct for the situation where the host does not have a program yet. In
 * that situation, the current role a priori has sufficients rights, because it
 * must have been that user who created the host. */
	if( $unit->rights<RIGHTS_APPLY || isset( $_POST[ "keepall" ] ) )
		$unit->keep = true;
	
	$keep = $unit->keep?"TRUE":"FALSE";

	$newprogram = is_null( $unit->program )?array_keys( $programs )[ 0 ]:$unit->program;

	if( $unit->rights>=RIGHTS_EDIT && isset( $_POST[ "{$prefix}_target" ] )&&isset( $programs[ (int)( $_POST[ "{$prefix}_target" ] ) ] )&& !isset( $_POST[ "load_profile" ] ) )
		$newprogram = (int)( $_POST[ "{$prefix}_target" ] );

	if( !is_null( $unit->program )&& $unit->program!=$newprogram ) {
		$progident = $programs[ $unit->program ]->ident;
		$deletecall = "{$progident}_ondelete";
		
		$oldsettings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id='{$unit->settings}';" ) );
		unset( $oldsettings[ "id" ] );
		$deletecall( $oldsettings );

		$query .= "DELETE FROM settings_{$progident} WHERE id='{$unit->settings}';";

		$unit->program = NULL;
	}

	if( is_null( $unit->program ) ) {
		$newidrow = pg_fetch_array( pg_query( "INSERT INTO settings_{$programs[ $newprogram ]->ident} DEFAULT VALUES RETURNING id;" ) );
		$newid = $newidrow[ 0 ];

		$query .= "UPDATE profiles SET program=$newprogram,keep=$keep,settings=$newid WHERE id=$currentprofile AND unit={$unit->id}; INSERT INTO profiles(id,unit,program,settings,keep) SELECT $currentprofile,id,$newprogram,$newid,$keep FROM units WHERE id={$unit->id} AND NOT EXISTS(SELECT * FROM profiles WHERE id=$currentprofile AND unit={$unit->id});";

		$unit->program = $newprogram;
		$unit->settings = $newid;
	} else
		// Program remains unchanged, keep may change
		$query .= "UPDATE profiles SET keep=$keep WHERE id=$currentprofile AND unit={$unit->id};";

	pg_query( $query );

/* SECTION 7, Read settings if necessary. That is: If $fetchsettings is on
 * because we want to go to the settings dialog. If we update the settings, we
 * read them first in order to obtain a list of fields (we don't actually need
 * the data). If the unit will be executed, we need the settings for the actual
 * execution. */
	if( $fetchsettings || $unit->id==$applyid || $perform && !$unit->keep ) {
		$progident = $programs[ $unit->program ]->ident;

		$settingsarray = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id={$unit->settings};" ) );
		unset( $settingsarray[ "id" ] );

		if( $unit->id==$applyid && $unit->rights>=RIGHTS_EDIT ) {
			$configcall = "{$progident}_configure";
			if( !is_null( $message = $configcall( $settingsarray,$unit ) ) )
				$messages[ ]= $message;

			pg_query( "UPDATE settings_{$progident} SET (".implode( ",",array_keys( $settingsarray ) ).")=(".implode( ",",array_map( "pgvalue",array_values( $settingsarray ) ) ).") WHERE id={$unit->settings};" );
		}

		if( $fetchsettings )
			$settings = $settingsarray;

/* SECTION 8, Execute program */
		if( $perform && !$unit->keep ) {
			$startcall = "{$progident}_start";
			$is_stateful = false;

			$logsum .= "$unit->name($progident) ";

			$phproot = getcwd( );
			chdir( UTILDIR );
			$startcall( $settingsarray,$unit,$timestamp );
			chdir( $phproot );

			if( $is_stateful ) {
				$duplicatecall = "{$progident}_onduplicate";
				$duplicatecall( $settingsarray );

				$newsettings = pg_fetch_array( pg_query( "INSERT INTO settings_$progident(".implode( ",",array_keys( $settingsarray ) ).") VALUES(".implode( ",",array_map( "pgvalue",array_values( $settingsarray ) ) ).") RETURNING id;" ) );

				$query .= "UPDATE units SET program={$unit->program},settings={$newsettings[ 0 ]} WHERE id={$unit->id};";

				$unit->runningprogram = $unit->program;
				$unit->runningsettings = $unit->settings;
			}
		}
	}
		
/* SECTION 9, change permissions and optionally delete host */
	if( !is_null( $prefix ) ) {
		if( isset( $_POST[ "{$prefix}_delete" ] ) ) {
			// DEL
			$settings_result = pg_query( "SELECT program,settings FROM profiles WHERE unit={$unit->id};" );
			while( $row = pg_fetch_assoc( $settings_result ) )
				$query .= "DELETE FROM settings_{$programs[ $row[ "program" ] ]->ident} WHERE	id={$row[ "settings" ]};";

			$settings_result = pg_query( "SELECT settings FROM units WHERE id={$unit->id};" );
			if( !is_null( $runningid = pg_fetch_row( $settings_result )[ 0 ] ) )
				$query .= "DELETE FROM settings_{$programs[ $row[ "program" ] ]->ident} WHERE	id={$runningid};";

			$query .= "DELETE FROM units WHERE id={$unit->id};";
			logentry( "Deleted host #{$unit->id}" );

			$unit = NULL;
		} elseif( isset( $_POST[ "{$prefix}_rights" ],$_POST[ "{$prefix}_rolecount" ] )&& $unit->rights==RIGHTS_DELETE ) {
			// PER
			$rolecount = (int)( $_POST[ "{$prefix}_rolecount" ] );

			for( $j = 0; $j<$rolecount; $j++ ) {
				if( isset( $_POST[ $prefix."_rights_".$j ],$_POST[ $prefix."_rights_{$j}_id" ] ) ) {
					$roleid = (int)( $_POST[ $prefix."_rights_{$j}_id" ] );

					if( $roleid!=$role ) {
						$roleright = (int)( $_POST[ $prefix."_rights_".$j ] );

						if( $roleright<0 || $roleright>RIGHTS_DELETE )
							$roleright = 0;

						$query .= "UPDATE permissions SET rights=$roleright WHERE unit={$unit->id} AND role=$roleid; INSERT INTO permissions(unit,role,rights) SELECT {$unit->id},$roleid,$roleright WHERE NOT EXISTS(SELECT * FROM permissions WHERE unit={$unit->id} AND role=$roleid);";
					}
				}
			}
		}
	}

	if( $query!="" )
		pg_query( $query );

	if( !is_null( $unit ) ) {
		if( $unit->rights<RIGHTS_APPLY )
			$unit->keep = true;
		$units[ ]= $unit;
	}
}

/* SECTION 10, Save profile. If it already, exists, the role must have profile
 * rights */
if( isset( $_POST[ "save_profile" ] )&& isset( $_POST[ "profile_name" ] )&& $_POST[ "profile_name" ]!="" ) {
	$profilename = $_POST[ "profile_name" ];

	$exists = pg_fetch_row( pg_query( "SELECT EXISTS(SELECT * FROM profilenames WHERE name=".pgvalue( $profilename ).");" ) )[ 0 ]=="t";

	$faillist = array( );
	if( !$exists || has_profile_right( $profilename,$faillist ) ) {
		copy_profile( $_POST[ "profile_name" ],false,0,true );

		logentry( "Saved profile {$_POST[ "profile_name" ]}" );
	} else {
		logentry( "Modification of profile $profilename failed due to insufficient rights" );
		$messages[ ]= array( "grey","red","Ihnen fehlen bei ".join( ",",$faillist )." die nötigten Rechte, um das unter dem Namen '$profilename' bestehende Profil zu überschreiben!" );
	}
}

/* SECTION 11, Delete profile */
if( isset( $_POST[ "delete_profile" ] )&& isset( $_POST[ "profile_name" ] )&& $_POST[ "profile_name" ]!="" ) {
	$profilename = $_POST[ "profile_name" ];

	$faillist = array( );
	if( has_profile_right( $profilename,$faillist ) ) {
		delete_profile_data( $profilename,false );
		pg_query( "DELETE FROM profilenames WHERE name=".pgvalue( $profilename ).";" );

		logentry( "Deleted profile $profilename" );
	} else {
		logentry( "Deletion of profile $profilename failed due to insufficient rights" );
		$messages[ ]= array( "grey","red","Ihnen fehlen bei ".join( ",",$faillist )." die nötigten Rechte, um dieses Profil zu löschen!" );
	}
}

verify_consistency( );

if( $perform )
	logentry( $logsum );

/* SECTION 12, Query state of units
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
if( isset( $_POST[ "refresh" ] )|| isset( $_POST[ "perform" ] )&& is_null( $singleunit ) ) {
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

if( $role==ILRC_ADMIN && isset( $_POST[ "usercount" ] ) ) {
	$usercount = (int)( $_POST[ "usercount" ] );

	$query = "";
	
	for( $i = 0; $i<$usercount; $i++ ) {
		if( isset( $_POST[ "user_{$i}_id" ] ) ) {
			$userid = (int)( $_POST[ "user_{$i}_id" ] );
			
			if( isset( $_POST[ "user_{$i}_delete" ] ) )
				$query .= "DELETE FROM roles WHERE id=$userid;";
			elseif( isset( $_POST[ "user_{$i}_changepass" ],$_POST[ "user_{$i}_password" ] ) )
				$query .= "UPDATE roles SET checksum=md5(".pgvalue( $_POST[ "user_{$i}_password" ] ).") WHERE id=$userid;";
		}
	}

	if( isset( $_POST[ "user_new" ],$_POST[ "user_new_name" ],$_POST[ "user_new_password" ] ) )
		$query .= "INSERT INTO roles(name,checksum) VALUES(".pgvalue( $_POST[ "user_new_name" ] ).",md5(".pgvalue( $_POST[ "user_new_password" ] )."));";

	if( $query!="" )
		pg_query( $query );
}

if( !is_null( $singleunit ) ) {
	if( $singlemode==SINGLE_SETTINGS )
		include( "settings.php" );
	elseif( $singlemode==SINGLE_DETAILS )
		include( "details.php" );
} else
	include( "overview.php" );

?>
