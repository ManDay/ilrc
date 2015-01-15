<?php

$progident = $programs[ $singleunit->program ]->ident;
$progname = $programs[ $singleunit->program ]->name;
$settingscall = "{$progident}_preferences";

?>
	<div class="row"><p><b>&ldquo;<?php echo $progname; ?>&rdquo; Einstellung für <code><?php echo $singleunit->name; ?></code></b></p></div>
	<div class="row" id="main">
		<p id="buttonscenter">
			<input type="hidden" name="hostcount" value="1" />
			<input type="hidden" name="mode_0_id" value="<?php echo $singleunit->id; ?>" />
			<input type="hidden" name="mode_0_settings" value="" />
			<input type="hidden" name="settingsapply" value="1" />
			<input<?php if( $singleunit->rights<RIGHTS_EDIT ) echo ' disabled="disabled"'; ?> type="submit" name="settingsokay" value="Speichern" />
			<input type="submit" name="settingscancel" value="Verwerfen" />
		</p>

<?php

$settingscall( $settings,$singleunit );

?>
	</div>
