<?php
try
{
    // Get the Environment file for the build
    $env_file = getenv('GITHUB_ENV');
	$branch = $argv[2];
	 
	 // Get the Date and time
    $date = date('Ymd.his', time());
	 
	echo "Date calculated to : " . $date . "\n";
	echo 'Branch being used is :' . $branch . '\n' ;
	
	 // Calculate the branch for the tag
	$branchtag = str_replace('/', '-' ,str_replace('refs/heads/', '', $branch));
    echo 'Short Branch Name is : ' . $branchtag . '\n' ;
	 
	// Load the xml file with the version in
	if (file_exists($argv[1]))
	{
		echo "File " . $argv[1] . "has been found\n" ;
		// Load the configuration File found
    	$xml=simplexml_load_file($argv[1]) or die("Error: Cannot create object");

		// State the version in the file
		$version = "v" . $xml->{$argv[3]};

		// See if this is the main branch
	 	if (strcmp($branch,'refs/heads/main') == 0) // This is main
	 	{
	 		// This is the main branch so it must be the version without the date
			$env_Entry = $argv[4] . "=" . $version;
	 	}
	 	else {
			$env_Entry = $argv[4] . "=" . $version . "-" . $date . "-" . $branchtag;
	 	}
	}
	else
	{
	    // No file exists so you can only use the date/time 
	    echo "File " . $argv[1] . "has NOT been found\n" ;
	 	if (strcmp($branch,'refs/heads/main') == 0) // This is main
	 	{
			$env_Entry = $argv[4] . "=" . $date;
	 	}
	 	else {
			$env_Entry = $argv[4] . "=" . $date . "-" . $branchtag;
	 	}
	}
	 
	// Now write the calculated entry into the file
	echo "Environment Entry : " . $env_Entry;
    file_put_contents($env_file, $env_Entry, FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
	 if (strcmp(argv[2],'refs/head/main') == 0) // This is main
	 {
	 	// This is the main branch so it must be the version without the date
    	file_put_contents($env_file, '{$argv[4]}=v0.0.0', FILE_APPEND | LOCK_EX);
	 }
	 else {
	 	// Now write the version (for the fixes branch)
    	file_put_contents($env_file, '{$argv[4]}=v0.0.0-{$date}-{$branchtag}', FILE_APPEND | LOCK_EX);
	 }
	
}
?>
