<?php
	# sudo su -
	# mkdir -p /var/www/uploads/$sub
	# for d in /var /var/www /var/www/uploads ; do chown root:root $d ; chmod 755 $d ; done
	# chown root:apache /var/www/uploads/$sub
	# chmod 770 /var/www/uploads/$sub
	
	function safe_valu($vkeyname)
	{
		if (isset($_GET[$vkeyname])) { return $_GET[$vkeyname]; }
		if (isset($_POST[$vkeyname])) { return $_POST[$vkeyname]; }
		return "";
	}
	
	function rand_numb()
	{
		$o = "";
		for ($x = 0; $x < 6; $x += 1) { $o .= rand(0, 9); }
		return $o;
	}
	
	function remo_file($pathname)
	{
		if (!is_file($pathname)) { return 0; }
		$fmodtime = filectime($pathname);
		$lastfmod = intval((time() - $fmodtime) / (60 * 60));
		if ($lastfmod >= 48) { unlink($pathname); return 1; }
		return 0;
	}
	
	date_default_timezone_set("America/Toronto");
	srand(microtime() * 1000000);
	$WEB_EOL = "<br/>";
	$writedir = "/var/www/uploads/jon";
	
	$scptmode = safe_valu("mode");
	$pinncode = preg_replace("/[^0-9]/i", "", safe_valu("pinc"));
	$password = safe_valu("pass");
	$aeskhash = hash("SHA256", $password, true);
	
	if ($scptmode == "e")
	{
		$aesinitv = openssl_random_pseudo_bytes(16);
		if (($_FILES["file"]["error"] < 1) && ($_FILES["file"]["size"] < 4096000))
		{
			while (1)
			{
				$pinncode = rand_numb();
				$filename = ($writedir."/".$pinncode);
				if (!file_exists($filename)) { break; }
				if (remo_file($filename) == 1) { break; }
			}
			$fsrcobjc = fopen($_FILES["file"]["tmp_name"], "rb");
			$fdstobjc = fopen($filename, "wb");
			if (($fsrcobjc !== false) && ($fdstobjc !== false))
			{
				fwrite($fdstobjc, "".$_FILES["file"]["name"].""); # filename as string (unknown length)
				fwrite($fdstobjc, "\1"); # non-printable separator (1 byte)
				fwrite($fdstobjc, "".$_FILES["file"]["size"].""); # filesize in bytes (unknown length)
				fwrite($fdstobjc, "\1"); # non-printable separator (1 byte)
				$emessage = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $aeskhash, "magicstring", MCRYPT_MODE_CBC, $aesinitv);
				fwrite($fdstobjc, $emessage); # encrypted magic string (16 bytes)
				fwrite($fdstobjc, $aesinitv); # initialization vector (16 bytes)
				while (!feof($fsrcobjc))
				{
					$fsrcdata = fread($fsrcobjc, 16);
					$emessage = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $aeskhash, $fsrcdata, MCRYPT_MODE_CBC, $aesinitv);
					fwrite($fdstobjc, $emessage);
					$aesinitv = $emessage;
				}
				fclose($fdstobjc);
				fclose($fsrcobjc);
				chmod($filename, 0770);
			}
		}
	}
	
	if ($scptmode == "d")
	{
		$filelist = scandir($writedir);
		foreach ($filelist as $fileitem)
		{
			$itemname = ($writedir."/".$fileitem);
			if (remo_file($itemname) == 1) { continue; }
			if ($fileitem != $pinncode) { continue; }
			$fsrcobjc = fopen($itemname, "rb");
			if ($fsrcobjc !== false)
			{
				$filename = ""; $filechar = "";
				while ($filechar != "\1") { $filename .= $filechar; $filechar = fread($fsrcobjc, 1); }
				$filesize = ""; $filechar = "";
				while ($filechar != "\1") { $filesize .= $filechar; $filechar = fread($fsrcobjc, 1); }
				$filesize = intval($filesize);
				$magicode = fread($fsrcobjc, 16);
				$aesinitv = fread($fsrcobjc, 16);
				$dmessage = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $aeskhash, $magicode, MCRYPT_MODE_CBC, $aesinitv);
				if (rtrim($dmessage) == "magicstring")
				{
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename="'.$filename.'"');
					header('Content-Length: '.$filesize);
					$tempinit = $aesinitv;
					while ($filesize > 0)
					{
						$aesinitv = $tempinit;
						$fsrcdata = fread($fsrcobjc, 16);
						$tempinit = $fsrcdata;
						$dmessage = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $aeskhash, $fsrcdata, MCRYPT_MODE_CBC, $aesinitv);
						$templeng = 16; if ($filesize < 16) { $templeng = $filesize; }
						print(substr($dmessage, 0, $templeng));
						$filesize -= 16;
					}
					fclose($fsrcobjc);
					exit(0);
				}
				fclose($fsrcobjc);
			}
		}
	}
?>

<html>
	<head>
		<title>Secure File Transfer Site</title>
		<style>
			body
			{
				background: #EEEEEE;
			}
			
			a
			{
				color: #000000;
				border-bottom: 1px dotted;
				text-decoration: none;
			}
			
			.minwide
			{
				width: 480px;
			}
			
			.blue
			{
				background: linear-gradient(#2E88C4, #075698) repeat scroll 0 0 rgba(0, 0, 0, 0);
			}
			
			.green
			{
				background: linear-gradient(#B8DB29, #5A8F00) repeat scroll 0 0 rgba(0, 0, 0, 0);
			}
			
			.red
			{
				background: linear-gradient(#F04349, #C81E2B) repeat scroll 0 0 rgba(0, 0, 0, 0);
			}
			
			.bubble
			{
				border-radius: 10px;
				color: #FFFFFF;
				margin: 15px;
				padding: 15px;
			}
			
			.info
			{
				background: linear-gradient(#F9D835, #F3961C) repeat scroll 0 0 rgba(0, 0, 0, 0);
				border-radius: 10px;
				color: #000000;
				margin: 15px;
				padding: 15px;
			}
		</style>
	</head>
	
	<body>
		<center>
			<form method="post" action="index.php" enctype="multipart/form-data">
				<input type="hidden" name="mode" value="e" />
				<table class="minwide bubble green">
					<tr>
						<td colspan="3">
							<center><b>Upload A File</b></center>
						</td>
					</tr>
					<tr>
						<td colspan="3">
							&nbsp;
						</td>
					</tr>
					<tr>
						<td align="right" style="width:1%;">
							<label for="file">Filename:</label>
						</td>
						<td align="left">
							<input type="file" name="file" />
						</td>
						<td align="left" style="width:1%;">
							&nbsp;
						</td>
					</tr>
					<tr>
						<td align="right" style="width:1%;">
							<label for="pass">Password:</label>
						</td>
						<td align="left">
							<input type="text" name="pass" style="width:100%;" />
						</td>
						<td align="left" style="width:1%;">
							<input type="submit" name="submit" value="Upload" />
						</td>
					</tr>
				</table>
			</form>
			
			<table class="minwide info">
				<tr>
					<td>
						<center>
						&nbsp;
						<?php
							if ($pinncode != "") { print("<a href='?pinc=".$pinncode."'>"."Your PIN code is: ".$pinncode."</a>".PHP_EOL); }
							else { print("No PIN code detected".PHP_EOL); }
						?>
						&nbsp;
						</center>
					</td>
				</tr>
			</table>
			
			<form method="post" action="index.php">
				<input type="hidden" name="mode" value="d" />
				<table class="minwide bubble blue">
					<tr>
						<td colspan="3">
							<center><b>Download A File</b></center>
						</td>
					</tr>
					<tr>
						<td colspan="3">
							&nbsp;
						</td>
					</tr>
					<tr>
						<td align="right" style="width:1%;">
							<label for="pinc">Pincode:</label>
						</td>
						<td align="left">
							<input type="text" name="pinc" size="8" value="<?php print($pinncode); ?>" />
						</td>
						<td align="left" style="width:1%;">
							&nbsp;
						</td>
					</tr>
					<tr>
						<td align="right" style="width:1%;">
							<label for="pass">Password:</label>
						</td>
						<td align="left">
							<input type="password" name="pass" style="width:100%;" />
						</td>
						<td align="left" style="width:1%;">
							<input type="submit" name="submit" value="Download" />
						</td>
					</tr>
				</table>
			</form>
			
			<table class="bubble red">
				<tr>
					<td>
						<center><b>How does this thing work?</b></center> <br /> <br />
						* You first upload a file along with a password string <br />
						&nbsp; * You then copy the PIN code link above and send that to another person <br />
						&nbsp; * You also tell them the shared, secret password over a different medium <br />
						* Any PIN code which points to a file that was created over 48 hours ago will be deleted <br />
						* The server generates a random 128 bit Initialization Vector & a unique 6 digit PIN code <br />
						&nbsp; * [iv = rand-bytes(16)] <br />
						* The server calculates a secret 256 bit key using a hashing algorithm & your password <br />
						&nbsp; * [passhash = SHA-256(password)] <br />
						* The server encrypts your file data using a symmetric block cipher <br />
						&nbsp; * [encdata = AES-256-CBC(filedata, passhash, iv)] <br />
						* The server encrypts a magic string so that the password can be validated before decryption <br />
						&nbsp; * [encmagic = AES-256-CBC("magicstr", passhash, iv)] <br />
						* The server writes out a final file named by the PIN code with the following data: <br />
						&nbsp; * [filepin = (filename + \1 + filesize + \1 + encmagic + iv + encdata)] <br />
						* There is a 4 MB file size upload limit
					</td>
				</tr>
			</table>
		</center>
	</body>
</html>
