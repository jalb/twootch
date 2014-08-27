<?

// Augmented from
// http://n4p1.wordpress.com/2013/08/08/how-to-download-archived-videos-from-twitch-tv/

// New features and fixes
// 1. the original script did not download all parts from a video, only the first one.
// 2. the 'downloads' directory is now created if missing.
// 3. files are downloaded to an temporary file with '.inprogress' suffix and renamed if wget returns OK.
// 4. filenames use the main video title, because video parts had useless names in some cases.
// 5. part filename contain video id and video part timestamp for eventual checking at the source.
// 6. json-as-php-arrays responses are stored for debugging purposes.
// 7. download only video parts that are missing, thus creating a mirror of past broadcasts.
// 8. check file size with HEADER request because json-reported file size is sometimes incorrect.

// Todo
// 1. more checks after download?
// 2. add exception processing and robust error checking/reporting.
// 3. parametrize object construction.
// 4. store download files in a hierarchy?
// 5. timeout on downloading / asynchronous
// 6. disk space checking 

class TwitchPastDownload {
	private $_channel;
	private $_url;
	private $_download_directory;
	
	function __construct() {
		date_default_timezone_set ( 'Europe/Paris' );
		$this->_download_directory = 'downloads';
		if (! is_dir ( $this->_download_directory )) {
			mkdir ( $this->_download_directory );
		}
	}
	
	function download_broadcasts($channel, $search_string) {
		$this->_channel = $channel;
		$this->_url = 'https://api.twitch.tv/kraken/channels/' . $this->_channel . '/videos?limit=200&broadcasts=true';
		// echo $this->_url . PHP_EOL;
		
		$c = curl_init ( $this->_url );
		$options = array (
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => array (
						'Content-type: application/json' 
				),
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 2 
		);
		curl_setopt_array ( $c, $options );
		$json = curl_exec ( $c );
		var_dump($json);
		$json_a = json_decode ( $json, true );
		
		// Reverse array to start with oldest videos, ie more likely to disappear.
		$json_a ['videos'] = array_reverse ( $json_a ['videos'] );
		
		// Save some debug information
		$channel_json = var_export ( $json_a, true );
		file_put_contents ( 'channel_json_' . $this->_channel . '_' . date ( 'YmdHms' ) . '.txt', $channel_json );
		
		if ($search_string) {
			$search_string = strtolower ( $search_string );
		}
		
		echo 'Found ' . count ( $json_a ['videos'] ) . ' videos.' . PHP_EOL;
		
		foreach ( $json_a ['videos'] as $video ) {
			echo 'Title: ' . $video ['title'] . PHP_EOL;
			if (! $search_string || strpos ( strtolower ( $video ['title'] ), $search_string ) !== false) {
				echo 'Match: ' . $video ['title'] . PHP_EOL;
				
				$id = $video ['_id'];
				$id = preg_replace ( '/[^0-9.]+/', '', $id );
				
				$video_url = "http://api.justin.tv/api/broadcast/by_archive/$id.json";
				$d = curl_init ( $video_url );
				$options2 = array (
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_HTTPHEADER => array (
								'Content-type: application/json' 
						) 
				);
				curl_setopt_array ( $d, $options2 );
				$json2 = curl_exec ( $d );
				$json2_a = json_decode ( $json2, true );
				if ($json2_a !== null) {
					
					// Save some debug information
					$video_json = var_export ( $json2_a, true );
					file_put_contents ( 'video_json_' . $id . '_' . date ( 'YmdHms' ) . '.txt', $video_json );
					
					echo 'Video title: ' . $video ['title'] . PHP_EOL;
					echo 'Video parts: ' . count ( $json2_a ) . PHP_EOL;
					
					$failed = false;
					foreach ( $json2_a as $part_number => $video_part ) {
						// Make a unique part filename
						$part_filename = $this->_download_directory . '/' . $this->format_title ( $video ['title'] ) . '_' . $id . '_part' . sprintf ( "%02d", $part_number ) . '_' . $video_part ['start_timestamp'] . '.flv';
						
						// Decide if we need to download or not
						$download = false;
						if (file_exists ( $part_filename )) {
							echo 'File exists: ' . $part_filename . PHP_EOL;
							// Check file size
							$filesize = filesize ( $part_filename );
							if ($filesize !== $video_part ['file_size']) {
								echo 'File size is ' . $filesize . ', should be ' . $video_part ['file_size'] . ': checking content-length.' . PHP_EOL;
								// Double check with HTTP content-length, Twitch API file_size is sometimes erroneous
								$content_length = $this->get_content_length ( $video_part ['video_file_url'] );
								if ($content_length !== $filesize) {
									echo 'File size is ' . $filesize . ', should be ' . $content_length . ': downloading.' . PHP_EOL;
									$download = true;
								} else {
									echo 'Content-length matches file size.' . PHP_EOL;
								}
							}
						} else {
							$download = true;
						}
						
						if ($download) {
							if (! $this->download_part ( $video_part ['video_file_url'], $part_filename )) {
								echo 'Could not download ' . $part_filename . PHP_EOL;
								$failed = true;
							}
						} else {
							echo 'Already downloaded: ' . $part_filename . PHP_EOL;
						}
					}
				} else {
					echo 'No information on video ' . $id . PHP_EOL;
				}
			}
		}
	}
	
	private function get_content_length($video_file_url) {
		$content_length = false;
		$ch = curl_init ( $video_file_url );
		curl_setopt ( $ch, CURLOPT_NOBODY, true );
		curl_setopt ( $ch, CURLOPT_TIMEOUT, 50 );
		curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
		if (curl_exec ( $ch )) {
			$content_length = ( int ) curl_getinfo ( $ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD );
		}
		curl_close ( $ch );
		return $content_length;
	}
	
	private function download_part($video_file_url, $part_filename) {
		$part_filename_temporary = $part_filename . '.inprogress';
		exec ( 'wget --progress=dot:mega -c ' . $video_file_url . ' -O "' . $part_filename_temporary . '"', $array, $exit_code );
		if ($exit_code === 0) {
			// OK, rename
			if (rename ( $part_filename_temporary, $part_filename )) {
				return true;
			} else {
				echo 'Error: could not rename ' . $part_filename_temporary . ' to ' . $part_filename . ': ' . PHP_EOL;
			}
		}
		return false;
	}
	
	// Sanitize and shorten title
	function format_title($title) {
		$out = preg_replace ( '/[^a-zA-Z0-9]/', '', $title );
		$out = substr ( $out, 0, 40 );
		return $out;
	}
}

if (isset ( $argv [1] )) {
	$channel = $argv [1];
} else {
	echo 'Syntax: past_broadcast <channel> [<search string>]' . PHP_EOL;
	exit(1);
}

if (isset ( $argv [2] )) {
	$search_string = $argv [2];
} else {
	$search_string = null;
}

$downloader = new TwitchPastDownload ();

$downloader->download_broadcasts ($channel, $search_string);
