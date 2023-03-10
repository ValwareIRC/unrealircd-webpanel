<!DOCTYPE html>
<title>UnrealIRCd Panel</title>
<link rel="icon" type="image/x-icon" href="/img/favicon.ico">
<link href="css/unrealircd-admin.css" rel="stylesheet">
<body class="body-for-sticky">
<div id="headerContainer">
<h2><a href="">UnrealIRCd <small>Administration Panel</small></a></h2></div>
<script src="js/unrealircd-admin.js" defer></script>
<div class="topnav">
  <a data-tab-target="#overview" class="active" href="#overview">Overview</a>
  <a data-tab-target="#Users" href="#Users">Users</a>
  <a data-tab-target="#Channels" href="#Channels">Channels</a>
  <a data-tab-target="#TKL" href="#TKL">Server Bans</a>
  <a data-tab-target="#Spamfilter" href="#Spamfilter">Spamfilter</a>
  <a data-tab-target="#News" href="#News">News</a>
</div> 
<?php
define('UPATH', dirname(__FILE__));
require_once "config.php";
require_once UPATH . '/vendor/autoload.php';
require_once "connection.php";
require_once "Classes/class-log.php";
require_once "Classes/class-message.php";
require_once "Classes/class-rpc.php";

do_log($_POST);

if (!empty($_POST)) {

	if ($sf = $_POST['sf_add']) // if it was a spamfilter entry
	{
		/* get targets */
		$targets = []; // empty arrae
		foreach($_POST as $key => $value)
		{
			if (substr($key, 0, 7) == "target_")
				$targets[] = str_replace(["target_", "_"], ["", "-"], $key);
		}
		if (empty($targets))
			Message::Fail("No target was specified");

		if (!isset($_POST['sf_bantype']))
			Message::Fail("No action was chosen");

		else
		{

			$bantype = $_POST['sf_bantype'];
			$targ_chars = "";
			foreach($targets as $targ)
			{
				switch ($targ) {
					case "channel":
						$targ_chars .= "c";
						break;
					case "private":
						$targ_chars .= "p";
						break;
					case "channel-notice":
						$targ_chars .= "N";
						break;
					case "private-notice":
						$targ_chars .= "n";
						break;
					case "part":
						$targ_chars .= "P";
						break;
					case "quit":
						$targ_chars .= "q";
						break;
					case "dcc":
						$targ_chars .= "d";
						break;
					case "away":
						$targ_chars .= "a";
						break;
					case "topic":
						$targ_chars .= "t";
						break;
					case "messagetag":
						$targ_chars .= "T";
						break;
					case "user":
						$targ_chars .= "u";
						break;
				}
			}
			/* duplicate code for now [= */
			$banlen_w = (isset($_POST['banlen_w'])) ? $_POST['banlen_w'] : NULL;
			$banlen_d = (isset($_POST['banlen_d'])) ? $_POST['banlen_d'] : NULL;
			$banlen_h = (isset($_POST['banlen_h'])) ? $_POST['banlen_h'] : NULL;
			$duration = "";
			if (!$banlen_d && !$banlen_h && !$banlen_w)
				$duration .= "0";
			
			else
			{
				if ($banlen_w)
					$duration .= $banlen_w;
				if ($banlen_d)
					$duration .= $banlen_d;
				if ($banlen_h)
					$duration .= $banlen_h;
			}
			$match_type = $_POST['matchtype']; // should default to 'simple'
				$reason = isset($_POST['ban_reason']) ? $_POST['ban_reason'] : "No reason";
				$soft = (isset($_POST['soft'])) ? true : false;
				if ($soft)
					$targ_chars = "%" . $targ_chars;
				if ($rpc->spamfilter()->add($sf, $match_type, $targ_chars, $bantype, $duration, $reason))
					Message::Success("Added spamfilter entry \"$sf\" [match type: $match_type] [targets: $targ_chars] [reason: $reason]");
				else
					Message::Fail("Could not add spamfilter entry \"$sf\" [match type: $match_type] [targets: $targ_chars] [reason: $reason]: $rpc->error");
		}

	}

	else if (!($bantype = $_POST['bantype'])) // if it was a ban entry
	{
	} 
	else if (!($users = $_POST["userch"]))
	{
		/* check if this came from our Server Bans tab. */
		if (!($iphost = $_POST['tkl_add']))
			Message::Fail("No user was specified");

		else /* It did */
		{
			if ((
					$bantype == "gline" ||
					$bantype == "gzline" ||
					$bantype == "shun" ||
					$bantype == "eline"
				) && strpos($iphost, "@") == false) // doesn't have full mask
				$iphost = "*@" . $iphost;

			$soft = ($_POST['soft']) ? true : false;

			if ($soft)
				$iphost = "%" . $iphost;
			/* duplicate code for now [= */
			$banlen_w = (isset($_POST['banlen_w'])) ? $_POST['banlen_w'] : NULL;
			$banlen_d = (isset($_POST['banlen_d'])) ? $_POST['banlen_d'] : NULL;
			$banlen_h = (isset($_POST['banlen_h'])) ? $_POST['banlen_h'] : NULL;
			$duration = "";
			if (!$banlen_d && !$banlen_h && !$banlen_w)
				$duration .= "0";
			
			else
			{
				if ($banlen_w)
					$duration .= $banlen_w;
				if ($banlen_d)
					$duration .= $banlen_d;
				if ($banlen_h)
					$duration .= $banlen_h;
			}
			$msg_msg = ($duration == "0" || $duration == "0w0d0h") ? "permanently" : "for ".rpc_convert_duration_string($duration);
			$reason = (isset($_POST['ban_reason'])) ? $_POST['ban_reason'] : "No reason";
			if ($rpc->serverban()->add($iphost, $bantype, $duration, $reason))
			{
				Message::Success("Host / IP: $iphost has been $bantype" . "d $msg_msg: $reason");
			}
			else
				Message::Fail("The $bantype against \"$iphost\" could not be added: $rpc->error");
		}
	}
	else /* It came from the Users tab */
	{
		foreach ($_POST["userch"] as $user)
		{
			$user = base64_decode($user);
			$bantype = (isset($_POST['bantype'])) ? $_POST['bantype'] : NULL;
			if (!$bantype) /* shouldn't happen? */
			{
				Message::Fail("An error occured");
				return;
			}
			$banlen_w = (isset($_POST['banlen_w'])) ? $_POST['banlen_w'] : NULL;
			$banlen_d = (isset($_POST['banlen_d'])) ? $_POST['banlen_d'] : NULL;
			$banlen_h = (isset($_POST['banlen_h'])) ? $_POST['banlen_h'] : NULL;

			$duration = "";
			if (!$banlen_d && !$banlen_h && !$banlen_w)
				$duration .= "0";
			
			else
			{
				if ($banlen_w)
					$duration .= $banlen_w;
				if ($banlen_d)
					$duration .= $banlen_d;
				if ($banlen_h)
					$duration .= $banlen_h;
			}

			$nick = $rpc->user()->get($user);
			if (!$nick)
			{
				Message::Fail("Could not find that user. Maybe they disconnected after you clicked this?");
				return;
			}

			$msg_msg = ($duration == "0" || $duration == "0w0d0h") ? "permanently" : "for ".rpc_convert_duration_string($duration);
			$reason = (isset($_POST['ban_reason'])) ? $_POST['ban_reason'] : "No reason";
			if ($rpc->serverban()->add($user, $bantype, $duration, $reason))
			{
				$c = $nick->client;
				Message::Success($c->name . " (*@".$c->hostname.") has been $bantype" . "d $msg_msg: $reason");
			}
		}
	}

	if (!empty($_POST['tklch']))
		foreach ($_POST as $key => $value) {
			foreach ($value as $tok) {
				$tok = explode(",", $tok);
				$ban = base64_decode($tok[0]);
				$type = base64_decode($tok[1]);
				if ($rpc->serverban()->delete($ban, $type))
					Message::Success("$type has been removed for $ban");
				else
					Message::Fail("Unable to remove $type on $ban: $rpc->error");
			}
		}

	if (!empty($_POST['sf']))
		foreach ($_POST as $key => $value) {
			foreach ($value as $tok) {
				$tok = explode(",", $tok);
				$name = base64_decode($tok[0]);
				$match_type = base64_decode($tok[1]);
				$spamfilter_targets = base64_decode($tok[2]);
				$ban_action = base64_decode($tok[3]);
				if ($rpc->spamfilter()->delete($name, $match_type, $spamfilter_targets, $ban_action))
					Message::Success("Spamfilter on $name has been removed");
				else
					Message::Fail("Unable to remove spamfilter on $name: $rpc->error");
			}
		}
}

rpc_pop_lists();
?>

<div class="tab-content\">
<div id="overview" data-tab-content class="active">
	<table class='unrealircd_overview'>
	<th>Chat Overview</th><th></th>
		<tr><td><b>Users</b></td><td><?php echo count(RPC_List::$user); ?></td></tr>
		<tr><td><b>Opers</b></td><td><?php echo RPC_List::$opercount; ?></td></tr>
		<tr><td><b>Services</b></td><td><?php echo RPC_List::$services_count; ?></td></tr>
		<tr><td><b>Most popular channel</b></td><td><?php echo RPC_List::$most_populated_channel; ?> (<?php echo RPC_List::$channel_pop_count; ?> users)</td></tr>
		<tr><td><b>Channels</b></td><td><?php echo count(RPC_List::$channel); ?></td></tr>
		<tr><td><b>Server bans</b></td><td><?php echo count(RPC_List::$tkl); ?></td></tr>
		<tr><td><b>Spamfilter entries</b></td><td><?php echo count(RPC_List::$spamfilter); ?></td></tr></th>
	</table></div></div>
	
	<div class="tab-content\">
	<div id="Users" data-tab-content>
	<table class='users_filter'>
	<th class="thuf">Filter by: </th>
	<th>
		<form action="" method="post">
			Nick: <input name="uf_nick" id="uf_nick" type="text">
			<input class="cute_button2" type="submit" value="Search">
		</form>
	</th>
	<th>
		<form action="" method="post">
			Hostname: <input name="uf_host" id="uf_host" type="text">
			<input class="cute_button2" type="submit" value="Search">
		</form>
	</th>
	<th>
		<form action="" method="post">
			IP: <input name="uf_ip" id="uf_ip" type="text">
			<input class="cute_button2" type="submit" value="Search">
		</form>
	</th>
	<th class="thuffer">
		<form action="" method="post">
			Account: <input name="uf_account" id="uf_account" type="text">
			<input class="cute_button2" type="submit" value="Search">
		</form>
	</th>
	</form>
	</table>
	<?php
	if (isset($_POST['uf_nick']) && strlen($_POST['uf_nick']))
		Message::Info("Listing users which match nick: \"" . $_POST['uf_nick'] . "\"");

	if (isset($_POST['uf_ip']) && strlen($_POST['uf_ip']))
		Message::Info("Listing users which match IP: \"" . $_POST['uf_ip'] . "\"");

	if (isset($_POST['uf_host']) && strlen($_POST['uf_host']))
		Message::Info("Listing users which match hostmask: \"" . $_POST['uf_host'] . "\"");

	if (isset($_POST['uf_account']) && strlen($_POST['uf_account']))
		Message::Info("Listing users which match account: \"" . $_POST['uf_account'] . "\"");

	?>
	<table class='users_overview'>
	<th><input type="checkbox" label='selectall' onClick="toggle_user(this)" />Select all</th>
	<th>Nick</th>
	<th>UID</th>
	<th>Host / IP</th>
	<th>Account</th>
	<th>Usermodes<a href="https://www.unrealircd.org/docs/User_modes" target="_blank">??????</a></th>
	<th>Oper</th>
	<th>Secure</th>
	<th>Connected to</th>
	<th>Reputation <a href="https://www.unrealircd.org/docs/Reputation_score" target="_blank">??????</a></th>
	
	<form action="" method="post">
	<?php
		foreach(RPC_List::$user as $user)
		{

			/* Some basic filtering for NICK */
			if (isset($_POST['uf_nick']) && strlen($_POST['uf_nick']) && 
			strpos(strtolower($user->name), strtolower($_POST['uf_nick'])) !== 0 &&
			strpos(strtolower($user->name), strtolower($_POST['uf_nick'])) == false)
				continue;

			/* Some basic filtering for HOST */
			if (isset($_POST['uf_host']) && strlen($_POST['uf_host']) && 
			strpos(strtolower($user->hostname), strtolower($_POST['uf_host'])) !== 0 &&
			strpos(strtolower($user->hostname), strtolower($_POST['uf_host'])) == false)
				continue;

			/* Some basic filtering for IP */
			if (isset($_POST['uf_ip']) && strlen($_POST['uf_ip']) && 
			strpos(strtolower($user->ip), strtolower($_POST['uf_ip'])) !== 0 &&
			strpos(strtolower($user->ip), strtolower($_POST['uf_ip'])) == false)
				continue;

			/* Some basic filtering for ACCOUNT */
			if (isset($_POST['uf_account']) && strlen($_POST['uf_account']) && 
			strpos(strtolower($user->user->account), strtolower($_POST['uf_account'])) !== 0 &&
			strpos(strtolower($user->user->account), strtolower($_POST['uf_account'])) == false)
				continue;

			echo "<tr>";
			echo "<td><input type=\"checkbox\" value='" . base64_encode($user->id)."' name=\"userch[]\"></td>";
			$isBot = (strpos($user->user->modes, "B") !== false) ? ' <span class="label">Bot</span>' : "";
			echo "<td>".$user->name.$isBot.'</td>';
			echo "<td>".$user->id."</td>";
			echo "<td>".$user->hostname." (".$user->ip.")</td>";
			$account = (isset($user->user->account)) ? $user->user->account : '<span class="label bluelabel	">None</span>';
			echo "<td>".$account."</td>";
			$modes = (isset($user->user->modes)) ? "+" . $user->user->modes : "<none>";
			echo "<td>".$modes."</td>";
			$oper = (isset($user->user->operlogin)) ? $user->user->operlogin." <span class=\"label bluelabel\">".$user->user->operclass."</span>" : "";
			if (!strlen($oper))
				$oper = (strpos($user->user->modes, "S") !== false) ? '<span class="label secure-connection">Service</span>' : "";
			echo "<td>".$oper."</td>";
			$secure = (isset($user->tls)) ? "<span class=\"label secure-connection\">Secure</span>" : "<span class=\"label redlabel\">Insecure</span>";
			echo "<td>".$secure."</td>";
			echo "<td>".$user->user->servername."</td>";
			echo "<td>".$user->user->reputation."</td>";
		}
	?></table>
	<label for="bantype">Apply action: </label><br>
	<select name="bantype" id="bantype">
			<option value=""></option>
		<optgroup label="Bans">
			<option value="gline">GLine</option>
			<option value="gzline">GZLine</option>
		</optgroup>
	</select>
	<br>
	<label for="banlen_w">Duration: </label><br>
	<select name="banlen_w" id="banlen_w">
			<?php
			for ($i = 0; $i <= 56; $i++)
			{
				if (!$i)
					echo "<option value=\"0w\"></option>";
				else
				{
					$w = ($i == 1) ? "week" : "weeks";
					echo "<option value=\"$i" . "w\">$i $w" . "</option>";
				}
			}
			?>
	</select>
	<select name="banlen_d" id="banlen_d">
			<?php
			for ($i = 0; $i <= 31; $i++)
			{
				if (!$i)
					echo "<option value=\"0d\"></option>";
				else
				{
					$d = ($i == 1) ? "day" : "days";
					echo "<option value=\"$i" . "d\">$i $d" . "</option>";
				}
			}
			?>
	</select>
	<select name="banlen_h" id="banlen_h">
			<?php
			for ($i = 0; $i <= 24; $i++)
			{
				if (!$i)
					echo "<option value=\"0d\"></option>";
				else
				{
					$h = ($i == 1) ? "hour" : "hours";
					echo "<option value=\"$i" . "h\">$i $h" . "</option>";
				}
			}
			?>
	</select>
	<br><label for="ban_reason">Reason:<br></label>
	<textarea name="ban_reason" id="ban_reason">No reason</textarea><br>
	<input class="cute_button" type="submit" value="Apply">
	</form>
	
	</div></div>

	<div class="tab-content\">
	<div id="Channels" data-tab-content>
	<p></p>
	<table class='users_overview'>
	<th>Name</th>
	<th>Created</th>
	<th>User count</th>
	<th>Topic</th>
	<th>Topic Set</th>
	<th>Modes</th>
	
	<?php
		foreach(RPC_List::$channel as $channel)
		{
			echo "<tr>";
			echo "<td>".$channel->name."</td>";
			echo "<td>".$channel->creation_time."</td>";
			echo "<td>".$channel->num_users."</td>";
			$topic = (isset($channel->topic)) ? $channel->topic : "";
			echo "<td>".$topic."</td>";
			$setby = (isset($channel->topic)) ? "By ".$channel->topic_set_by .", at ".$channel->topic_set_at : "";
			echo "<td>".$setby."</td>";
			$modes = (isset($channel->modes)) ? "+" . $channel->modes : "<none>";
			echo "<td>".$modes."</td>";
		}
	?></table></div></div>


	<div class="tab-content\">
	<div id="TKL" data-tab-content>
	<div class="tkl_add_boxheader">
		Add Server Ban
	</div>
	<div class="tkl_add_form">
		
		<form action="" method="post">
			<div class="align_label">IP / Host:</div><input class="input_text" type="text" id="tkl_add" name="tkl_add"><br>
			<div class="align_label">Ban Type:</div><select name="bantype" id="bantype">
				<option value=""></option>
				<optgroup label="Bans">
					<option value="kline">Kill Line (KLine)</option>
					<option value="gline">Global Kill Line (GLine)</option>
					<option value="zline">Zap Line (ZLine)</option>
					<option value="gzline">Global Zap Line (GZLine)</option>
					
				</optgroup>
				<optgroup label="Restrictions">
					<option value="local-qline">Reserve Nick Locally(QLine)</option>
					<option value="qline">Reserve Nick Globally (QLine)</option>
					<option value="shun">Shun</option>

				</optgroup>
				<optgroup label="Settings">
					<option value="except">Global Exception (ELine)</option>
					<option value="local-exception">Local Exception (ELine)</option>
				</optgroup>
			</select><br>
			<div class="align_label"><label for="banlen_w">Duration: </label></div>
					<select name="banlen_w" id="banlen_w">
							<?php
							for ($i = 0; $i <= 56; $i++)
							{
								if (!$i)
									echo "<option value=\"0w\"></option>";
								else
								{
									$w = ($i == 1) ? "week" : "weeks";
									echo "<option value=\"$i" . "w\">$i $w" . "</option>";
								}
							}
							?>
					</select>
					<select name="banlen_d" id="banlen_d">
							<?php
							for ($i = 0; $i <= 31; $i++)
							{
								if (!$i)
									echo "<option value=\"0d\"></option>";
								else
								{
									$d = ($i == 1) ? "day" : "days";
									echo "<option value=\"$i" . "d\">$i $d" . "</option>";
								}
							}
							?>
					</select>
					<select name="banlen_h" id="banlen_h">
							<?php
							for ($i = 0; $i <= 24; $i++)
							{
								if (!$i)
									echo "<option value=\"0d\"></option>";
								else
								{
									$h = ($i == 1) ? "hour" : "hours";
									echo "<option value=\"$i" . "h\">$i $h" . "</option>";
								}
							}
							?>
					</select>
					<br><div class="align_label"><label for="ban_reason">Reason: </label></div>
					<input class="input_text" type="text" id="ban_reason" name="ban_reason"><br>
					<input class="input_text" type="checkbox" id="soft" name="soft">Don't affect logged-in users (soft)
					<div class="align_right_button_tkl_add"><input class="cute_button" type="submit" id="submit" value="Submit"></div>
		</form>
	</div>
	<table class='users_overview'>
	<form action="" method="post">
	<th><input type="checkbox" label='selectall' onClick="toggle_tkl(this)" />Select all</th>
	<th>Mask</th>
	<th>Type</th>
	<th>Set By</th>
	<th>Set On</th>
	<th>Expires</th>
	<th>Duration</th>
	<th>Reason</th>
	
	<?php
		foreach(RPC_List::$tkl as $tkl)
		{
			echo "<tr>";
			echo "<td><input type=\"checkbox\" value='" . base64_encode($tkl->name).",".base64_encode($tkl->type) . "' name=\"tklch[]\"></td>";
			echo "<td>".$tkl->name."</td>";
			echo "<td>".$tkl->type_string."</td>";
			echo "<td>".$tkl->set_by."</td>";
			echo "<td>".$tkl->set_at_string."</td>";
			echo "<td>".$tkl->expire_at_string."</td>";
			echo "<td>".$tkl->duration_string."</td>";
			echo "<td>".$tkl->reason."</td>";
		}
	?></table><p><input class="cute_button" type="submit" value="Delete selected"></p></form></div></div>
	

	<div class="tab-content\">
	<div id="Spamfilter" data-tab-content>
	<p></p>
	<div class="tkl_add_boxheader">
		Add Spamfilter Entry
	</div>
	<div class="tkl_add_form">
		
		<form action="" method="post">
			<div class="align_label">Entry: </div><input class="input_text" type="text" id="sf_add" name="sf_add"><br>
			<div class="align_label">MatchType: </div><select name="matchtype" id="matchtype">
				<option value="simple">Simple</option>
				<option value="regex">Regular Expression</option>
			</select><br>
			<div class="align_label">Action: </div><select name="sf_bantype" id="sf_bantype">
				<option value=""></option>
				<optgroup label="Bans">
					<option value="kline">Kill Line (KLine)</option>
					<option value="gline">Global Kill Line (GLine)</option>
					<option value="zline">Zap Line (ZLine)</option>
					<option value="gzline">Global Zap Line (GZLine)</option>
					
				</optgroup>
				<optgroup label="Restrictions">
					<option value="tempshun">Temporary Shun (Session only)</option>
					<option value="shun">Shun</option>
					<option value="block">Block</option>
					<option value="dccblock">DCC Block</option>
					<option value="viruschan">Send to "Virus Chan"</option>
				</optgroup>
				<optgroup label="Other">
					<option value="warn">Warn the user</option>
				</optgroup>
			</select><br>
			
			<div class="align_label"><label for="banlen_w">Targets: </label></div>
			<input type="checkbox" class="input_text" id="target_channel" name="target_channel">Channel messages<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_private" name="target_private">Private messages<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_channel_notice" name="target_channel_notice">Channel notices<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_private_notice" name="target_private_notice">Private notices<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_part" name="target_part">Part reason<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_dcc" name="target_dcc">DCC Filename<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_away" name="target_away">Away messages<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_topic" name="target_topic">Channel topic<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_messagetag" name="target_messagetag">MessageTags<br>
			<div class="align_label"><label></label></div><input type="checkbox" class="input_text" id="target_user" name="target_user">Userhost (nick!user@host:realname)<br>
			<div class="align_label"><label for="banlen_w">Duration: </label></div>
			<select name="banlen_w" id="banlen_w">
					<?php
					for ($i = 0; $i <= 56; $i++)
					{
						if (!$i)
							echo "<option value=\"0w\"></option>";
						else
						{
							$w = ($i == 1) ? "week" : "weeks";
							echo "<option value=\"$i" . "w\">$i $w" . "</option>";
						}
					}
					?>
			</select>
			<select name="banlen_d" id="banlen_d">
					<?php
					for ($i = 0; $i <= 31; $i++)
					{
						if (!$i)
							echo "<option value=\"0d\"></option>";
						else
						{
							$d = ($i == 1) ? "day" : "days";
							echo "<option value=\"$i" . "d\">$i $d" . "</option>";
						}
					}
					?>
			</select>
			<select name="banlen_h" id="banlen_h">
					<?php
					for ($i = 0; $i <= 24; $i++)
					{
						if (!$i)
							echo "<option value=\"0d\"></option>";
						else
						{
							$h = ($i == 1) ? "hour" : "hours";
							echo "<option value=\"$i" . "h\">$i $h" . "</option>";
						}
					}
					?>
			</select>
			<br><div class="align_label"><label for="ban_reason">Reason: </label></div>
			<input class="input_text" type="text" id="ban_reason" name="ban_reason"><br>
			<input class="input_text" type="checkbox" id="soft" name="soft">Don't affect logged-in users (soft)
			<div class="align_right_button_tkl_add"><input class="cute_button" type="submit" id="submit" value="Submit"></div>
		</form>
	</div>
	<table class='users_overview'>
	<form action="" method="post">
	<th><input type="checkbox" label='selectall' onClick="toggle_sf(this)" />Select all</th>
	<th>Mask</th>
	<th>Type</th>
	<th>Set By</th>
	<th>Set On</th>
	<th>Expires</th>
	<th>Duration</th>
	<th>Match Type</th>
	<th>Action</th>
	<th>Action Duration</th>
	<th>Target</th>
	<th>Reason</th>
	
	<?php
		foreach(RPC_List::$spamfilter as $sf)
		{
			echo "<tr>";
			echo "<td><input type=\"checkbox\" value='" . base64_encode($sf->name).",".base64_encode($sf->match_type).",".base64_encode($sf->spamfilter_targets).",".base64_encode($sf->ban_action) . "' name=\"sf[]\"></td>";
			echo "<td>".$sf->name."</td>";
			echo "<td>".$sf->type_string."</td>";
			echo "<td>".$sf->set_by."</td>";
			echo "<td>".$sf->set_at_string."</td>";
			echo "<td>".$sf->expire_at_string."</td>";
			echo "<td>".$sf->duration_string."</td>";
			echo "<td>".$sf->match_type."</td>";
			echo "<td>".$sf->ban_action."</td>";
			echo "<td>".$sf->ban_duration_string."</td>";
			for ($i = 0, $targs = ""; $i < strlen($sf->spamfilter_targets); $i++)
			{
				$c = $sf->spamfilter_targets[$i];
				if ($c == "c")
					$targs .= "Channel, ";
				else if ($c == "p")
					$targs .= "Private,";
				else if ($c == "n")
					$targs .= "Notice, ";
				else if ($c == "N")
					$targs .= "Channel notice, ";
				else if ($c == "P")
					$targs .= "Part message, ";
				else if ($c == "q")
					$targs .= "Quit message, ";
				else if ($c == "d")
					$targs .= "DCC filename, ";
				else if ($c == "a")
					$targs .= "Away message, ";
				else if ($c == "t")
					$targs .= "Channel topic, ";
				else if ($c == "T")
					$targs .= "MessageTag, ";
				else if ($c == "u")
					$targs .= "Usermask, ";
			}
			$targs = rtrim($targs,", ");
			echo "<td>".$targs."</td>";
			echo "<td>".$sf->reason."</td>";
			
		}
	?></table><p><input class="cute_button" type="submit" value="Delete selected"></p></form></div></div>



	<div class="tab-content\">
	<div id="News" data-tab-content>
	<iframe style="border:none;" height="1000" width="600" data-tweet-url="https://twitter.com/Unreal_IRCd" src="data:text/html;charset=utf-8,%3Ca%20class%3D%22twitter-timeline%22%20href%3D%22https%3A//twitter.com/Unreal_IRCd%3Fref_src%3Dtwsrc%255Etfw%22%3ETweets%20by%20Unreal_IRCd%3C/a%3E%0A%3Cscript%20async%20src%3D%22https%3A//platform.twitter.com/widgets.js%22%20charset%3D%22utf-8%22%3E%3C/script%3E%0A%3Cstyle%3Ehtml%7Boverflow%3Ahidden%20%21important%3B%7D%3C/style%3E"></iframe>
	<iframe style="border:none;" height="1000" width="600" data-tweet-url="https://twitter.com/irc_stats" src="data:text/html;charset=utf-8,%3Ca%20class%3D%22twitter-timeline%22%20href%3D%22https%3A//twitter.com/irc_stats%3Fref_src%3Dtwsrc%255Etfw%22%3ETweets%20by%20IRC%20Stats%3C/a%3E%0A%3Cscript%20async%20src%3D%22https%3A//platform.twitter.com/widgets.js%22%20charset%3D%22utf-8%22%3E%3C/script%3E%0A%3Cstyle%3Ehtml%7Boverflow%3Ahidden%20%21important%3B%7D%3C/style%3E"></iframe>
	</div></div>
	
</body>
