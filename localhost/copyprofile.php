<?php

function condstr( $target,$isid ) {
	return $isid?"profilenames.id=$target":"profilenames.name=".pgvalue( $target );
}

function delete_profile_data( $target,$isid ) {
	$condstr = condstr( $target,$isid );

	$result = pg_query( "SELECT profiles.settings AS id,programs.ident AS ident FROM profilenames,profiles,programs WHERE profilenames.id=profiles.id AND profiles.program=programs.id AND $condstr;" );

	while( $row = pg_fetch_array( $result ) ) {
		$progident = $row[ "ident" ];
		$deletecall = "{$progident}_ondelete";

		$oldsettings = pg_fetch_assoc( pg_query( "SELECT * FROM settings_{$progident} WHERE id='{$row[ "id" ]}';" ) );
		unset( $oldsettings[ "id" ] );
		$deletecall( $oldsettings );

		pg_query( "DELETE FROM settings_{$row[ "ident" ]} WHERE id='{$row[ "id" ]}';" );
	}

	pg_query( "DELETE FROM profiles USING profilenames WHERE profilenames.id=profiles.id AND $condstr;" );
}

function copy_profile( $target,$target_isid,$source,$source_isid ) {
	global $programs;

	$target_condstr = condstr( $target,$target_isid );
	$source_condstr = condstr( $source,$source_isid );

	$exists_result = pg_query( "SELECT id FROM profilenames WHERE $target_condstr;" );

	if( $newprofile = pg_fetch_array( $exists_result ) )
		delete_profile_data( $target,$target_isid );
	else {
		assert( !$target_isid );
		$newprofile = pg_fetch_array( pg_query( "INSERT INTO profilenames(name) VALUES(".pgvalue( $target ).") RETURNING id;" ) );
	}
	
	$duplicates = pg_query( "INSERT INTO profiles SELECT {$newprofile[ 0 ]},unit,program,settings,keep FROM profiles,profilenames WHERE profilenames.id=profiles.id AND $source_condstr RETURNING id,unit,program,settings;" );

/* Introspect each settings table, duplicate and rewrite */
	while( $duplicate = pg_fetch_array( $duplicates ) ) {
		$ident = $programs[ $duplicate[ "program" ] ]->ident;
		$setting = $duplicate[ "settings" ];

		if( $original = pg_fetch_array( pg_query( "SELECT * FROM settings_$ident WHERE id='$setting';" ),NULL,PGSQL_ASSOC ) ) {
			unset( $original[ "id" ] );

			$duplicatecall = "{$ident}_onduplicate";
			$duplicatecall( $original );

			$newsettings = pg_fetch_array( pg_query( "INSERT INTO settings_$ident(id,".implode( ",",array_keys( $original ) ).") VALUES(DEFAULT,".implode( ",",array_map( "pgvalue",array_values( $original ) ) ).") RETURNING id;" ) );

			pg_query( "UPDATE profiles SET settings={$newsettings[ 0 ]} WHERE unit={$duplicate[ "unit" ]} AND id={$duplicate[ "id" ]};" );
		}
	}
}

?>
