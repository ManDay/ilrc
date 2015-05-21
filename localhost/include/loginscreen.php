<div class="row" id="main">
	<p id="buttonscenter">
<?php
	
	if( !isset( $_POST[ "logout" ] ) ) {
		foreach( $_POST as $var=>$val ) {

?>
		<input type="hidden" name="<?php echo htmlentities( $var ); ?>" value="<?php echo htmlentities( $val ); ?>" />
<?php
		
		}

		if( isset( $_GET[ "refresh" ] ) ) {

?>
		<input type="hidden" name="refresh" value="1" />
<?php

		}
	}

?>
		<input type="hidden" name="hostcount" value="<?php echo count( $units ); ?>" />
		<input type="password" style="vertical-align:middle;" name="login" value="" />
		<button type="submit" style="vertical-align:middle;"><img src="icons/unlock.png" alt="Entsperren" /></button>
	</p>
</div>
