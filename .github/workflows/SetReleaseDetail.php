<?php
try {
    // argv[1] = source XML file path
    // argv[2] = destination XML file path

    // Load the XML file from the path defined
    $xml_source=simplexml_load_file($argv[1]) or die("Error: Cannot create object");
    // Obtain the value to echo
    $value = $xml_source->version;
    echo "Value found in source XML: " . $value[0] . "\n";
    // Now we need to read the file to update
    $xml_dest=simplexml_load_file($argv[2]) or die("Error: Cannot create object");
    $element = $xml_dest->{$argv[3]};
    echo "Value found in destination XML: " . $element[0] . "\n";
    $xml_dest->{$argv[4]} = $value[0];
    $xml_dest->asXML($argv[2]);
    $updated_element = $xml_dest->version;
    echo "Updated Value found in destination XML: " . $updated_element[0] . "\n";
    } catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>  