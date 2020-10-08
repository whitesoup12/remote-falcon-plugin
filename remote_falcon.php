<h1 style="margin-left: 1em;">Remote Falcon Plugin v4.5.3</h1>
<h4 style="margin-left: 1em;"></h4>

<?php
/**GLOBALS */
$pageLocation = "Location: ?plugin=fremote-falcon&page=remote_falcon.php";
$pluginPath = "/home/fpp/media/plugins/remote-falcon";
$scriptPath = "/home/fpp/media/plugins/remote-falcon/scripts";
$remoteFppEnabled = trim(file_get_contents("$pluginPath/remote_fpp_enabled.txt"));
$interruptScheduleEnabled = trim(file_get_contents("$pluginPath/interrupt_schedule_enabled.txt"));
$playlists = "";

$url = "http://127.0.0.1/api/playlists";
$options = array(
	'http' => array(
		'method'  => 'GET'
		)
);
$context = stream_context_create( $options );
$result = file_get_contents( $url, false, $context );
$response = json_decode( $result, true );
foreach($response as $item) {
	if(file_exists("$pluginPath/remote_playlist.txt")) {
		$remotePlaylist = file_get_contents("$pluginPath/remote_playlist.txt");
		if($item == $remotePlaylist) {
			$playlists .= "<option selected=\"selected\" value=\"{$item}\">{$item}</option>";
		}else {
			$playlists .= "<option value=\"{$item}\">{$item}</option>";
		}
	}else {
		$playlists .= "<option value=\"{$item}\">{$item}</option>";
	}
}

$url = "http://127.0.0.1/api/plugin/remote-falcon/updates";
$options = array(
	'http' => array(
		'method'  => 'POST',
		'header'=>  "Content-Type: application/json; charset=UTF-8\r\n" .
								"Accept: application/json\r\n"
		)
);
$context = stream_context_create( $options );
$result = file_get_contents( $url, false, $context );
$response = json_decode( $result, true );
if($response['updatesAvailable'] == 0) {
	echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Remote Falcon Plugin is up to date!</h3>
	";
}else if($response['updatesAvailable'] == 1) {
	echo "
		<h3 style=\"margin-left: 1em; color: #a72525;\">A new update is available for the Remote Falcon Plugin!</h3>
		<h3 style=\"margin-left: 1em; color: #a72525;\">Go to the Plugin Manager to update</h3>
	";
}

if(file_exists("$pluginPath/remote_token.txt")) {
	$remoteToken = file_get_contents("$pluginPath/remote_token.txt");
	if($remoteToken) {
		echo "
			<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 1:</h3>
			<h5 style=\"margin-left: 1em;\">If you need to update your remote token, place it in the input box below and click \"Update Token\".</h5>
			<div style=\"margin-left: 1em;\">
				<form method=\"post\">
					<input type=\"password\" name=\"remoteToken\" id=\"remoteToken\" size=100 value=\"${remoteToken}\">
					<br>
					<input id=\"saveRemoteTokenButton\" class=\"button\" name=\"saveRemoteToken\" type=\"submit\" value=\"Update Token\"/>
				</form>
			</div>
		";
	}
} else {
	echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 1:</h3>
		<h5 style=\"margin-left: 1em;\">Place your unique remote token, found on your Remote Falcon Control Panel, in the input box below and click \"Save Token\".</h5>
		<div style=\"margin-left: 1em;\">
			<form method=\"post\">
				<input type=\"password\" name=\"remoteToken\" id=\"remoteToken\" size=100>
				<br>
				<input id=\"saveRemoteTokenButton\" class=\"button\" name=\"saveRemoteToken\" type=\"submit\" value=\"Save Token\"/>
			</form>
		</div>
	";
}
if (isset($_POST['saveRemoteToken'])) {
	$remoteToken = trim($_POST['remoteToken']);
  global $pluginPath;
	shell_exec("rm -f $pluginPath/remote_token.txt");
	shell_exec("echo $remoteToken > $pluginPath/remote_token.txt");
	echo "
		<div style=\"margin-left: 1em;\">
			<h4 style=\"color: #D65A31;\">Remote Token successfully saved.</h4>
		</div>
	";
}

echo "
	<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 2:</h3>
	<h5 style=\"margin-left: 1em;\">
		Pick which playlist you want to sync with Remote Falcon and click \"Sync Playlist\". The playlist you sync with RF should be 
		its own playlist that is not used in any schedules.
		<br />
		Any changes made to the selected playlist will require it to be resynched. 
		If at any time you want to change the synched playlist, simply select the one you want and click \"Sync Playlist\".
	</h5>
	<div style=\"margin-left: 1em;\">
		<form method=\"post\">
			<select id=\"remotePlaylist\" name=\"remotePlaylist\">
				${playlists}
			</select>
			<br>
			<input id=\"saveRemotePlaylistButton\" class=\"button\" name=\"saveRemotePlaylist\" type=\"submit\" value=\"Sync Playlist\"/>
		</form>
	</div>
";
if (isset($_POST['saveRemotePlaylist'])) {
	$remotePlaylist = trim($_POST['remotePlaylist']);
	if(file_exists("$pluginPath/remote_token.txt")) {
		shell_exec("rm -f $pluginPath/remote_playlist.txt");
		shell_exec("echo $remotePlaylist > $pluginPath/remote_playlist.txt");
		$playlists = array();
		$remoteToken = trim(file_get_contents("$pluginPath/remote_token.txt"));
		$remotePlaylistEncoded = str_replace(' ', '%20', $remotePlaylist);
		$url = "http://127.0.0.1/api/playlist/${remotePlaylistEncoded}";
		$options = array(
			'http' => array(
				'method'  => 'GET'
				)
		);
		$context = stream_context_create( $options );
		$result = file_get_contents( $url, false, $context );
		$response = json_decode( $result, true );
		$mainPlaylist = $response['mainPlaylist'];
		$index = 1;
		foreach($mainPlaylist as $item) {
			if($item['type'] == 'both') {
				$playlist = null;
				$playlist->playlistName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
				$playlist->playlistDuration = $item['duration'];
				$playlist->playlistIndex = $index;
				array_push($playlists, $playlist);
			}else if($item['type'] == 'media') {
				$playlist = null;
				$playlist->playlistName = pathinfo($item['mediaName'], PATHINFO_FILENAME);
				$playlist->playlistDuration = $item['duration'];
				$playlist->playlistIndex = $index;
				array_push($playlists, $playlist);
			}else if($item['type'] == 'sequence') {
				$playlist = null;
				$playlist->playlistName = pathinfo($item['sequenceName'], PATHINFO_FILENAME);
				$playlist->playlistDuration = $item['duration'];
				$playlist->playlistIndex = $index;
				array_push($playlists, $playlist);
			}
			$index++;
		}
		$url = "https://remotefalcon.com/remotefalcon/api/syncPlaylists";
		$data = array(
			'playlists' => $playlists
		);
		$options = array(
			'http' => array(
				'method'  => 'POST',
				'content' => json_encode( $data ),
				'header'=>  "Content-Type: application/json; charset=UTF-8\r\n" .
										"Accept: application/json\r\n" .
										"remotetoken: $remoteToken\r\n"
				)
		);
		$context = stream_context_create( $options );
		$result = file_get_contents( $url, false, $context );
		$response = json_decode( $result );
		if($response) {
			echo "
				<div style=\"margin-left: 1em;\">
					<h4 style=\"color: #D65A31;\">Playlist successfully synced!</h4>
				</div>
			";
		}else {
			echo "
				<div style=\"margin-left: 1em;\">
					<h4 style=\"color: #a72525;\">There was an error synching your playlist!</h4>
				</div>
			";
		}
	}else {
		echo "
			<div style=\"margin-left: 1em;\">
				<h4 style=\"color: #a72525;\">You must enter your remote token first!</h4>
			</div>
		";
	}
}

if(strval($remoteFppEnabled) == "true") {
	echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 3:</h3>
		<h5 style=\"margin-left: 1em;\">Adjust the toggle below to turn Remote FPP on or off.
		<br />
		This setting is what allows FPP to retrieve viewer requests.
		<br />
		Any time this toggle is modified you must click \"Save Toggle\" and Restart FPP.</h5>
		<div style=\"margin-left: 1em;\">
			<form method=\"post\">
				<input type=\"checkbox\" name=\"remoteFppEnabled\" id=\"remoteFppEnabled\" checked/> Remote FPP Enabled
				<br>
				<input id=\"updateTogglesButton\" class=\"button\" name=\"updateToggles\" type=\"submit\" value=\"Save Toggle\"/>
			</form>
		</div>
	";
}else {
	echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 3:</h3>
		<h5 style=\"margin-left: 1em;\">Adjust the toggle below to turn Remote FPP on or off.
		<br />
		This setting is what allows FPP to retrieve viewer requests.
		<br />
		Any time this toggle is modified you must click \"Save Toggle\" and Restart FPP.</h5>
		<div style=\"margin-left: 1em;\">
			<form method=\"post\">
				<input type=\"checkbox\" name=\"remoteFppEnabled\" id=\"remoteFppEnabled\"/> Remote FPP Enabled
				<br>
				<input id=\"updateTogglesButton\" class=\"button\" name=\"updateToggles\" type=\"submit\" value=\"Save Toggle\"/>
			</form>
		</div>
	";
}
if (isset($_POST['updateToggles'])) {
  global $pluginPath;
	$remoteFppChecked = "false";
	if (isset($_POST['remoteFppEnabled'])) {
		$remoteFppChecked = "true";
	}
	shell_exec("rm -f $pluginPath/remote_fpp_enabled.txt");
	shell_exec("echo $remoteFppChecked > $pluginPath/remote_fpp_enabled.txt");
	echo "
		<div style=\"margin-left: 1em;\">
			<h4 style=\"color: #D65A31;\">Toggle has been successfully updated.</h4>
		</div>
	";
	$remoteFppEnabled = trim(file_get_contents("$pluginPath/remote_fpp_enabled.txt"));
}

if(strval($interruptScheduleEnabled) == "true") {
	echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 4:</h3>
		<h5 style=\"margin-left: 1em;\">Adjust the toggle below to choose if you want the scheduled playlist to be interrupted when a request is received.
		<br />
		Default is on, meaning the scheduled playlist will be interrupted with a new request
		<br />
		Any time this toggle is modified you must click \"Save Toggle\" and Restart FPP.</h5>
		<div style=\"margin-left: 1em;\">
			<form method=\"post\">
				<input type=\"checkbox\" name=\"interruptScheduleEnabled\" id=\"interruptScheduleEnabled\" checked/> Interrupt Scheduled Playlist
				<br>
				<input id=\"interruptSchedulesButton\" class=\"button\" name=\"interruptScheduleToggle\" type=\"submit\" value=\"Save Toggle\"/>
			</form>
		</div>
	";
}else {
	echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 4:</h3>
		<h5 style=\"margin-left: 1em;\">Adjust the toggle below to choose if you want the scheduled playlist to be interrupted when a request is received.
		<br />
		Default is on, meaning the scheduled playlist will be interrupted with a new request
		<br />
		Any time this toggle is modified you must click \"Save Toggle\" and Restart FPP.</h5>
		<div style=\"margin-left: 1em;\">
			<form method=\"post\">
				<input type=\"checkbox\" name=\"interruptScheduleEnabled\" id=\"interruptScheduleEnabled\"/> Interrupt Scheduled Playlist
				<br>
				<input id=\"interruptSchedulesButton\" class=\"button\" name=\"interruptScheduleToggle\" type=\"submit\" value=\"Save Toggle\"/>
			</form>
		</div>
	";
}
if (isset($_POST['interruptScheduleToggle'])) {
  global $pluginPath;
	$interruptScheduleChecked = "false";
	if (isset($_POST['interruptScheduleEnabled'])) {
		$interruptScheduleChecked = "true";
	}
	shell_exec("rm -f $pluginPath/interrupt_schedule_enabled.txt");
	shell_exec("echo $interruptScheduleChecked > $pluginPath/interrupt_schedule_enabled.txt");
	echo "
		<div style=\"margin-left: 1em;\">
			<h4 style=\"color: #D65A31;\">Toggle has been successfully updated.</h4>
		</div>
	";
	$interruptScheduleEnabled = trim(file_get_contents("$pluginPath/interrupt_schedule_enabled.txt"));
}

echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 5:</h3>
		<h5 style=\"margin-left: 1em;\">Restart FPP</h5>
	";

echo "
		<h3 style=\"margin-left: 1em; color: #D65A31;\">Step 6:</h3>
		<h5 style=\"margin-left: 1em;\">Profit!</h5>
	";

echo "
		<h5 style=\"margin-left: 1em;\">While Remote Falcon is 100% free for users, there are still associated costs with owning and maintaining a server and 
		database. If you would like to help support Remote Falcon you can donate using the button below.</h5>
		<h5 style=\"margin-left: 1em;\">Donations will <strong>never</strong> be required but will <strong>always</strong> be appreciated.</h5>
		<a href=\"https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=FFKWA2CFP6JC6&currency_code=USD&source=url\" target=\"_blank\"> <img style=\"margin-left: 1em;\" alt=\"RF_Donate\" src=\"https://remotefalcon.com/support-button.png\"></a>
	";

$date = date("Y-m-d");
$date2 = date("Y-m-d", strtotime("-1 days", strtotime(date("Y-m-d"))));
$date3 = date("Y-m-d", strtotime("-2 days", strtotime(date("Y-m-d"))));
echo "
		<p style=\"margin-left: 1em;\">
			<a href=\"download_log.php?name=$date.txt\">Download FPP Log From $date</a>
			<a href=\"download_log.php?name=$date2.txt\">Download FPP Log From $date2</a>
			<a href=\"download_log.php?name=$date3.txt\">Download FPP Log From $date3</a>
		</p>
	";

echo "
	<h5 style=\"margin-left: 1em;\">Changelog:</h5>
	<ul>
	<li>
			<strong>4.5.3</strong>
			<ul>
				<li>
					Ability to download FPP logs
				</li>
			</ul>
		</li>
		<li>
			<strong>4.5.2</strong>
			<ul>
				<li>
					Removed FPP Stats feature
				</li>
			</ul>
		</li>
		<li>
			<strong>4.5.1</strong>
			<ul>
				<li>
					Added support to sync sequence only items to Remote Falcon
				</li>
			</ul>
		</li>
		<li>
			<strong>4.5.0</strong>
			<ul>
				<li>
					Performance fixes to decrease CPU usage
				</li>
				<li>
					Make sure requests don't keep coming in when schedule is done
				</li>
				<li>
					Clear Now Playing when show stops
				</li>
			</ul>
		</li>
	</ul>
";
?>