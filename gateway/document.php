<?php
header('Content-Type: application/json');
date_default_timezone_set('America/New_York');
set_time_limit(60);

require_once 'document_function.php';
require_once 'mailer.php';

function processRequest()
{
    $gpDocument = new GpDocument();

    if (!empty($_GET['view_content_id'])) {
        $result = $gpDocument->getDocumentPdfUrl($_GET['view_content_id']);
        if ($result) {
            echo json_encode(['result' => 'Success', 'document_url' => $result . '.pdf']);
        } else {
            echoErrorAndExit(400, 'Invalid Request');
        }
    } else {
        echoErrorAndExit(400, 'Invalid Request');
    }
}
processRequest();
