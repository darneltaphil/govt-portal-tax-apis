<?php
require_once 'functions.php';
require_once 'pdfmaker.php';

class GpDocument
{
    private $db, $pdfMaker;

    public function __construct()
    {
        $this->db = new MysqliDb(getenv('DB_SERVER'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
        $this->pdfMaker = new PDFMaker();
    }

    public function getDocumentPdfUrl($id)
    {
        $result = $this->db->where('id', $id)->getOne('gp_documents', ['id', 'content']);
        if ($this->pdfMaker->printPdf($result['content'], 'document_' . $id)) {
            return 'document_' . $id;
        } else {
            return null;
        }
    }
}
