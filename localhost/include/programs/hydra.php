<?php

define( "HYDRA_COUNT",4 );

$hydra_table = array(
	"program_0 SMALLINT REFERENCES programs( id ) ON DELETE SET NULL",
	"settings_0 SMALLINT",
	"program_1 SMALLINT REFERENCES programs( id ) ON DELETE SET NULL",
	"settings_1 SMALLINT",
	"program_2 SMALLINT REFERENCES programs( id ) ON DELETE SET NULL",
	"settings_2 SMALLINT",
	"program_3 SMALLINT REFERENCES programs( id ) ON DELETE SET NULL",
	"settings_3 SMALLINT"
);

$hydra_programs = array( "present","website" );

function hydra_start( $settings_array,$unit,$timestamp ) {
	global $programs;

	makestateful( );

	for( $i = 0; $i<HYDRA_COUNT; $i++ ) {
		if( !is_null( $settings_array[ "program_$i" ] ) ) {
			$progident = $programs[ (int)( $settings_array[ "program_$i" ] ) ]->ident;

			$guestsettings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_$progident WHERE id={$settings_array[ "settings_$i" ]};" ) );
			unset( $guestsettings[ "id" ] );

			$startcall = "{$progident}_start";
			$startcall( $guestsettings,$unit,$timestamp );
		}
	}
}

function hydra_onduplicate( &$settings_array ) {
	global $programs;

	for( $i = 0; $i<HYDRA_COUNT; $i++ ) {
		if( !is_null( $settings_array[ "program_$i" ] ) ) {
			$progident = $programs[ (int)( $settings_array[ "program_$i" ] ) ]->ident;

			$oldsettings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_$progident WHERE id={$settings_array[ "settings_$i" ]};" ) );
			unset( $oldsettings[ "id" ] );
			$newsettings = pg_fetch_row( pg_query( "INSERT INTO settings_$progident(".implode( ",",array_keys( $oldsettings ) ).") VALUES(".implode( ",",array_map( "pgvalue",array_values( $oldsettings ) ) ).") RETURNING id;" ) );

			$settings_array[ "settings_$i" ]= $newsettings[ 0 ];
		}
	}
}

function hydra_ondelete( $settings_array ) {
	global $programs;

	for( $i = 0; $i<HYDRA_COUNT; $i++ )
		if( !is_null( $settings_array[ "program_$i" ] ) ) {
			$progident = $programs[ (int)( $settings_array[ "program_$i" ] ) ]->ident;
			pg_query( "DELETE FROM settings_$progident WHERE id={$settings_array[ "settings_$i" ]};" );
		}
}

function hydra_stop( $settings_array,$unit,$timestamp ) {
	global $programs;

	for( $i = 0; $i<HYDRA_COUNT; $i++ ) {
		if( !is_null( $settings_array[ "program_$i" ] ) ) {
			$progident = $programs[ (int)( $settings_array[ "program_$i" ] ) ]->ident;

			$guestsettings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_$progident WHERE id={$settings_array[ "settings_$i" ]};" ) );
			unset( $guestsettings[ "id" ] );

			$stopcall = "{$progident}_stop";
			$stopcall( $guestsettings,$unit,$timestamp );
		}
	}
}

function hydra_preferences( $settings_array,$unit ) {
	global $programs;
	global $messages;

	$disabled = $unit->rights<RIGHTS_EDIT?' disabled="disabled"':"";

	$program_ids = array_filter( $programs,function( $program ) { global $hydra_programs; return in_array( $program->ident,$hydra_programs ); } );

	$insettings = false;

	for( $i = 0; $i<4; $i++ )
		if( isset( $_POST[ "hydra_{$i}_settings" ] ) ) {
			if( !is_null( $settings_array[ "program_$i" ] ) ) {
				$progident = $programs[ (int)( $settings_array[ "program_$i" ] ) ]->ident;
				$guest_settings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id={$settings_array[ "settings_$i" ]};" ) );
				unset( $guest_settings[ "id" ] );

				$settingscall = "{$progident}_preferences";
?>
		<p id="buttonscenter">
			<input type="hidden" name="guestnumber" value="<?php echo $i; ?>" />
			<input type="submit"<?php echo $disabled; ?> name="guestokay" value="Unterprogramm speichern" />
			<input type="submit" name="guestcancel" value="Unterprogramm verwerfen" />
		</p>
<?php
				$settingscall( $guest_settings,$unit );

				$insettings = true;
			}

		}

	if( !$insettings ) {
		foreach( $messages as $message ) {
?>
	<div class="row">
		<p style="border: 2px solid <?php echo $message[ 1 ]; ?>; background-color: <?php echo $message[ 0 ]; ?>; color: <?php echo $message[ 1 ]; ?>">
			<b><?php echo $message[ 2 ]; ?></b>
		</p>
	</div>
<?php

		}

?>
<table>
<?php

		for( $i = 0; $i<4; $i++ ) {

?>
<tr><td>
	<select<?php echo $disabled; ?> name="program_<?php echo $i; ?>">
		<option value=""<?php if( is_null( $settings_array[ "program_$i" ] ) ) echo ' selected="selected"'; ?>>(Kein Programm)</option>
<?php

			foreach( $program_ids as $id=>$program ) {

?>
		<option value="<?php echo $id; ?>"<?php if( $settings_array[ "program_$i" ]=="$id" ) echo ' selected="selected"'; ?>><?php echo $programs[ $id ]->name; ?></option>
<?php

			}

?>
	</select>
</td><td>
	<button type="submit" name="hydra_<?php echo $i; ?>_settings"><img src="icons/preferences.png" alt="Einstellungen" /></button>
</td></tr>
<?php

		}

?>
</table>
<?php

	}

}

function hydra_configure( &$settings_array,$unit ) {
	global $programs;
	global $hydra_programs;

	for( $i = 0; $i<4; $i++ ) {
		if( isset( $_POST[ "program_$i" ] ) ) {
			$old_program = $settings_array[ "program_$i" ];
			$new_program = $_POST[ "program_$i" ]==""?NULL:(int)( $_POST[ "program_$i" ] );

			if( is_null( $new_program )|| !isset( $programs[ $new_program ] )|| !in_array( $programs[ $new_program ]->ident,$hydra_programs ) )
				$new_program = NULL;

			if( $old_program!=$new_program ) {
				if( !is_null( $old_program ) )
					pg_query( "DELETE FROM settings_{$programs[ $old_program ]->ident} WHERE id='{$settings_array[ "settings_$i" ]}';" );

				if( !is_null( $new_program ) )
					$newidrow = pg_fetch_array( pg_query( "INSERT INTO settings_{$programs[ $new_program ]->ident} DEFAULT VALUES RETURNING id;" ) );
				else
					$newidrow = NULL;

				$settings_array[ "settings_$i" ]= $newidrow[ 0 ];
				$settings_array[ "program_$i" ]= $new_program;
			}
		}
	}

	$message = NULL;

	if( isset( $_POST[ "guestnumber" ] )&& !isset( $_POST[ "guestcancel" ] ) ) {
		$i = (int)( $_POST[ "guestnumber" ] );

		if( $i>=0 && $i<HYDRA_COUNT ) {
			$progident = $programs[ (int)( $settings_array[ "program_$i" ] ) ]->ident;
			$guest_settings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id={$settings_array[ "settings_$i" ]};" ) );
			unset( $guest_settings[ "id" ] );
			
			$configcall = "{$progident}_configure";
			$message = $configcall( $guest_settings,$unit );

			pg_query( "UPDATE settings_{$progident} SET (".implode( ",",array_keys( $guest_settings ) ).")=(".implode( ",",array_map( "pgvalue",array_values( $guest_settings ) ) ).") WHERE id={$settings_array[ "settings_$i" ]};" );
		}
	}

	return $message;
}

function hydra_getstate( $unit ) {
	return NULL;
}

?>
