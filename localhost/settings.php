	<div class="row"><p><b>&ldquo;<?php echo $settingsprogname; ?>&rdquo; Einstellung für <code><?php echo $settingsunit->name; ?></code></b></p></div>
	<div class="row" id="main">
		<p id="buttonscenter">
			<input type="hidden" name="hostcount" value="1" />
			<input type="hidden" name="mode_0_id" value="<?php echo $settingsunit->id; ?>" />
			<input type="hidden" name="mode_0_settings" value="" />
			<input type="hidden" name="settingsapply" value="1" />
			<input type="submit" name="settingsokay" value="Speichern" />
			<input type="submit" name="settingscancel" value="Verwerfen" />
		</p>

<?php

$settingscall( $settings,$settingsunit );

?>
	</div>
