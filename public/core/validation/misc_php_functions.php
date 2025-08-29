<?php  
function checkPDFVersion($uploadedFile) {
    // Check for errors in the uploaded file
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        // Open the uploaded file directly
        $tmpFile = $uploadedFile['tmp_name'];

        // pdf version information
        $filepdf = fopen($tmpFile, "r");

        if ($filepdf) {
            $line_first = fgets($filepdf);

            // extract number such as 1.4, 1.5 from the first read line of the PDF file
            preg_match_all('!\d+!', $line_first, $matches);

            // save that number in a variable
            $pdfversion = implode('.', $matches[0]);

            fclose($filepdf);

            return $pdfversion;
        } else {
            // Failed to open the PDF file.
            return null;
        }
    } else {
        // Error uploading the file.
        return null;
    }
}



?>