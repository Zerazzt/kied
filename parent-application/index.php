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
$metrics = "";
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

					// formats json as string string 
					$jsonContent = file_get_contents($name);
					$metrics_obj = json_decode($jsonContent, true); 
		
					foreach ($metrics_obj as $key => $value) {
						$metrics .= "\"$key\": $value,\n";
					}
					$metrics = rtrim($metrics, ",\n");
					$metrics = nl2br($metrics);
		
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

			// formats json as string
			$jsonContent = file_get_contents($name);
			$metrics_obj = json_decode($jsonContent, true); 

			foreach ($metrics_obj as $key => $value) {
				$metrics .= "\"$key\": $value,\n";
			}
			$metrics = rtrim($metrics, ",\n");
			$metrics = nl2br($metrics);

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
	<link rel="stylesheet" href="styles.css">
</head>
<body>
	<form method="post" enctype="multipart/form-data">
        <h1>Descriptive Subtitle Generator</h1>
        <div class = "form-container">
			<div class="feature-sections-container">
				<div class="feature-section">
					<h3 class = "feature-section-heading">Audio Feature Generator</h3>
					<label for="parser">Select a generator: </label>
					<select name="parser" id="parser" onchange="showHideComponent()">
						<?php foreach (parsers as $parser => $key): ?>
						<option value="<?=$key?>"><?=$parser?></option>
						<?php endforeach; ?>
					</select>
					<label id="custom-parser-label">Upload .wav file: </label>
					<div class="input-file-button" id="input-file-button">
						<input type="file" id="wav" name="wav">
						<input type="hidden" name="MAX_FILE_SIZE" value="400000000"> 
					</div>
					<label for="url" id="spotify-parser-label">Enter Spotify Soundtrack URL: </label>
					<input type="url" id = "url" name="url">
			
				</div>
				<div class="feature-section">
					<h3 class = "feature-section-heading">Description Generator</h3>
					<label for="descriptor">Select a generator: </label>
					<select name="descriptor" id = "descriptor">
						<?php foreach (descriptors as $descriptor => $key): ?>
						<option value="<?=$key?>"><?=$descriptor?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<button type="submit" name="submit">Generate Description</button>

            <div class="feature-sections-container">
				<div class="results-section">
					<div class="output-section">
						<p>From audio feature generator: 
						</p>
						<br>
						<span id="metrics"><?php echo $metrics?></span>
					</div>
					<div class="output-section">
						<p>From description generator: </p>
						<br>
						<span> <?php echo $description?> </span>
					</div>
				</div>
			</div>
        </div>
        
    
    
    </form>
    <script>
		window.onload = function() {
			var fileInput = document.getElementById('input-file-button');
			var customParserLabel = document.getElementById('custom-parser-label');
			var spotifyParserLabel = document.getElementById('spotify-parser-label');
            var url = document.getElementById('url');
			fileInput.style.display = 'block';
			customParserLabel.style.display = 'block';
			url.style.display = 'none';
			spotifyParserLabel.style.display='none';
		}
        function showHideComponent() {
            // show hide inputs based on parser selection
			console.log("show hide");
            var selectedParser = document.getElementById('parser').value;
            var fileInput = document.getElementById('input-file-button');
			var customParserLabel = document.getElementById('custom-parser-label');
			var spotifyParserLabel = document.getElementById('spotify-parser-label');
            var url = document.getElementById('url');
			console.log(fileInput)
			console.log(selectedParser)
            if (selectedParser == '1') {
                fileInput.style.display = 'block';
				customParserLabel.style.display = 'block';
				url.style.display = 'none';
				spotifyParserLabel.style.display='none';
            }
            else if (selectedParser == '2') {
                fileInput.style.display = 'none';
				customParserLabel.style.display = 'none';
                url.style.display = 'block';
				spotifyParserLabel.style.display='block';
            }
            
        }
    </script>
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
		// echo $description;
	}
	?>
	</span>
</body>
</html>