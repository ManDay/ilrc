<?php

function isreachable( $unit ) {
	$state = $unit->state[ 2 ];
	return $state!="Aus" && $state!="Unerreichbar" && $state!="Unkontrollierbar";
}

function cqappend( $command,$name,$nowait = false ) {
	file_put_contents( "bootsequences/$name",str_replace( "\n","",$command )."\n",FILE_APPEND );
	exec( "./cqappend.bsh ".escapeshellarg( $name )." &>/dev/null &" );
}

function cqinject( $command,$name ) {
	exec( "./cqinject.bsh ".escapeshellarg( $name )." ".escapeshellarg( $command )." &>/dev/null &" );
}

function rcboot( $mac,$subnet ) {
	$ip_ip = strstr( $subnet,"/",true );
	$netmask = (int)( substr( strstr( $subnet,"/" ),1 ) );
	$ipmask = 32-$netmask;

	$iplong = ip2long( $ip_ip );
	$ones = 1;
	for( $i = 0; $i<$ipmask; $i++ )
		$ones =( $ones<<1 )|1;

	$macbin = pack( "H12",str_replace( ":","",$mac ) );

	$broadcast = long2ip( $iplong|$ones );
	$packet = str_repeat( chr( 0xff ),6 ).str_repeat( $macbin,16 );

	if( $sock = socket_create( AF_INET,SOCK_DGRAM,SOL_UDP ) ) {
		socket_set_option( $sock,SOL_SOCKET,SO_BROADCAST,1 );
		socket_sendto( $sock,$packet,strlen( $packet ),MSG_DONTROUTE,$broadcast,7000 );
		socket_close( $sock );

		// To limit network throughput for sensitive networks
		usleep( 100 );
	}

	register_up( );
}

function mounttools( $name ) {
	cqappend( "mount /home/shared",$name );
}

function setmonitor( &$settings_array ) {
	if( isset( $_POST[ "monitor" ] ) ) {
		$monitor = (int)( $_POST[ "monitor" ] );
		if( $monitor<0 )
			$monitor = 0;

		$settings_array[ "monitor" ]= $monitor;
	}
}

?>
