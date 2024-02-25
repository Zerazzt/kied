<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$direx = explode('/', getcwd());
define('DOCROOT', "/$direx[1]/$direx[2]/$direx[3]/");
define('WEBROOT', "/$direx[1]/$direx[2]/$direx[3]/$direx[4]/");

const parsers = [
	'custom'  => 1,
	'spotify' => 2
];

const descriptors = [
	'custom' => 1,
	'gpt'    => 2
];

const dir = WEBROOT.'tmp/';

$command     = "";
$output      = "";
$status      = 0;
$processed   = false;
$name        = "";
$described   = false;
$description = "";

if (isset($_POST['submit'])) {
	if ($_POST['parser'] == parsers['custom']) {
		if (is_uploaded_file($_FILES['wav']['tmp_name'])) {
			$id = uniqid();
			$fileName = dir.$id.".".pathinfo($_FILES['wav']['name'], PATHINFO_EXTENSION);
			if (!move_uploaded_file($_FILES['wav']['tmp_name'], $fileName)) {
				echo "MOVE ERROR";
			}
			else {
				$command = DOCROOT."parser/parser.o ".escapeshellarg($fileName)." ".WEBROOT."tmp/".$id.".json";
				exec($command, $output, $status);
				if ($status === 0) {
					$processed = true;
					$name = WEBROOT."tmp/".$id.".json";
					if (file_exists($fileName)) {
						unlink($fileName);
					}
				}
				else {
					echo "PARSER EXEC FAIL:";
					var_dump($output);
				}
			}
		}
		else {
			echo "UPLOAD FAIL";
		}
	}
	else if ($_POST['parser'] == parsers['spotify']) {
		$parts = explode("/", $_POST['url']);
		$trackID = $parts[count($parts) - 1];
		$id = uniqid();
		$command = "python3 ".DOCROOT."data-retriever/spotify-audio-features.py ".escapeshellarg($trackID)." ".WEBROOT."tmp/".$id.".json";
		exec($command, $output, $status);
		if ($status === 0) {
			$processed = true;
			$name = WEBROOT."tmp/".$id.".json";
		}
		else {
			echo "SPOTIFY EXEC FAIL:";
			var_dump($output);
		}
	}

	if ($processed) {
		if ($_POST['descriptor'] == descriptors['gpt']) {
			// The last string is to reroute errors so that they're visible in the output
			// Part of trying to debug what what was happening with the custom parser output as input
			$command = "python3 ".DOCROOT."descriptive-subtitle/descriptive-subtitle.py ".$name." 2>&1";
			exec($command, $output, $status);
			if ($status === 0) {
				$described = true;
				$description = $output[0];
			}
			else {
				echo "GPT EXEC FAIL:";
				var_dump($output);
			}
		}
		else if ($_POST['descriptor'] == descriptors['custom']) {
			$described = true;
			$description = "NOT DONE";
		}
	}
}

?>
<!DOCTYPE html>
<html lang="en-US">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
</head>
<body>
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="MAX_FILE_SIZE" value="400000000"> 
		<select name="parser">
			<?php foreach (parsers as $parser => $key): ?>
			<option value="<?=$key?>"><?=$parser?></option>
			<?php endforeach; ?>
		</select>

		<select name="descriptor">
			<?php foreach (descriptors as $descriptor => $key): ?>
			<option value="<?=$key?>"><?=$descriptor?></option>
			<?php endforeach; ?>
		</select>

		<input type="file" name="wav">
		<input type="url" name="url">

		<button type="submit" name="submit">Submit</button>
	</form>

	<pre>
<?php
// Don't change this alignment unless you're changing the pre block as well.
if ($processed) {
	echo file_get_contents($name);
}
?>
	</pre>

	<span>
	<?php if ($described) {
		echo $description;
	}
	?>
	</span>
</body>
</html>