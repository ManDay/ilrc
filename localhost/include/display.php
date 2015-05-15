<?xml version="1.1" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Innovations Lab Remote Control</title>
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
	<div class="row"><h1>InnoLab <span>Remote Control (Version 0.3)</span></h1></div>
<?php
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
<?php

if( is_null( $role ) ) {
	require( "include/loginscreen.php" );
} else {
	if( !is_null( $singleunit ) ) {
		if( $singlemode==SINGLE_SETTINGS )
			include( "include/settings.php" );
		elseif( $singlemode==SINGLE_DETAILS )
			require( "include/details.php" );
	} else
		require( "include/overview.php" );
}

?>
</form>
</body>
</html>
