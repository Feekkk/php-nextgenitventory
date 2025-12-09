<?php
$oldErrorReporting = error_reporting();
$oldDisplayErrors = ini_get('display_errors');
$oldLogErrors = ini_get('log_errors');

error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');

$tcpdfPath = __DIR__ . '/../tcpdf/tcpdf.php';
$vendorTcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($tcpdfPath)) {
    @require_once $tcpdfPath;
} elseif (file_exists($vendorTcpdfPath)) {
    @require_once $vendorTcpdfPath;
} elseif (file_exists($vendorAutoload)) {
    @require_once $vendorAutoload;
} else {
    // TCPDF is not available; PDF generation will fail gracefully.
}

error_reporting($oldErrorReporting);
ini_set('display_errors', $oldDisplayErrors);
ini_set('log_errors', $oldLogErrors);

class ReturnPDFGenerator {
    private $pdf;
    private $logoPath;
    
    public function __construct() {
        if (class_exists('TCPDF')) {
            $oldErrorReporting = error_reporting();
            $oldDisplayErrors = ini_get('display_errors');
            $oldLogErrors = ini_get('log_errors');
            ini_set('display_errors', '0');
            ini_set('log_errors', '0');
            error_reporting(0);
            $this->pdf = @new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            error_reporting($oldErrorReporting);
            ini_set('display_errors', $oldDisplayErrors);
            ini_set('log_errors', $oldLogErrors);
        } else {
            throw new Exception('TCPDF library not found. Please install via: composer require tecnickcom/tcpdf');
        }
        $this->logoPath = __DIR__ . '/../public/unikl-rcmp.png';
        $this->setupPDF();
    }
    
    private function setupPDF() {
        $this->pdf->SetCreator('UNIKL RCMP IT Inventory');
        $this->pdf->SetAuthor('UNIKL RCMP');
        $this->pdf->SetTitle('Asset Return Document');
        $this->pdf->SetSubject('Equipment Return Summary');
        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    public function generate($data) {
        $this->addReturnPage($data);
        $this->addConditionPage($data);
        
        return $this->pdf->Output('', 'S');
    }
    
    private function addHeader($title = '') {
        $this->pdf->SetY(15);
        
        if (file_exists($this->logoPath)) {
            $logoWidth = 35;
            $x = ($this->pdf->GetPageWidth() - $logoWidth) / 2;
            $this->pdf->Image($this->logoPath, $x, 15, $logoWidth, 0, '', '', '', false, 300, '', false, false, 0, false);
        }
        
        $this->pdf->SetY(40);
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->Cell(0, 6, 'UNIVERSITY KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK', 0, 1, 'C');
        
        if (!empty($title)) {
            $this->pdf->Ln(4);
            $this->pdf->SetFont('helvetica', 'B', 13);
            $this->pdf->Cell(0, 8, $title, 0, 1, 'C');
        }
    }
    
    private function addComputerGeneratedMessage() {
        $currentY = $this->pdf->GetY();
        $pageHeight = $this->pdf->GetPageHeight();
        $margin = 15;
        $footerHeight = 10;
        
        if ($currentY + $footerHeight > $pageHeight - $margin) {
            return;
        }
        
        $y = $pageHeight - $margin;
        $this->pdf->SetY($y);
        $this->pdf->Line(15, $y - 2, $this->pdf->GetPageWidth() - 15, $y - 2);
        $this->pdf->SetY($y);
        $this->pdf->SetFont('helvetica', 'I', 7);
        $this->pdf->SetTextColor(120, 120, 120);
        $this->pdf->Cell(0, 4, 'This document is computer generated. No signature is required.', 0, 1, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
    }
    
    private function addReturnPage($data) {
        $this->pdf->AddPage();
        $this->addHeader('RETURN OF COMPANY\'S NOTEBOOK/DESKTOP');
        
        $this->pdf->Ln(6);
        
        $startY = $this->pdf->GetY();
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(173, 216, 230);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(45, 12, 'RETURNED BY:', 1, 0, 'L', true);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->Cell(0, 12, $data['staff_name'] ?? '', 1, 1, 'L', true);
        
        $this->pdf->SetY($startY + 14);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(200, 200, 200);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(45, 12, 'ASSET ID:', 1, 0, 'L', true);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->Cell(0, 12, $data['asset_id'] ?? '', 1, 1, 'L', true);
        
        $this->pdf->SetY($startY + 28);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'I hereby return the following asset: -', 0, 1, 'L');
        
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, '1. Asset Information', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        
        $assetInfo = [
            'Item Name:' => $data['category'] ?? '',
            'Brand Name:' => $data['brand'] ?? '',
            'Model Name:' => $data['model'] ?? '',
            'Serial Number:' => $data['serial_num'] ?? '',
            'Handover Date:' => $data['handover_date'] ?? 'N/A',
            'Return Date:' => $data['return_date'] ?? date('Y-m-d'),
            'Remark:' => !empty($data['return_notes']) ? $data['return_notes'] : 'N/A'
        ];
        
        foreach ($assetInfo as $label => $value) {
            $this->pdf->Cell(50, 5, $label, 0, 0, 'L');
            $this->pdf->Cell(0, 5, $value, 0, 1, 'L');
        }
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Returned by:', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_name'] ?? '', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_designation'] ?? '', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Staff no/IC No:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_id'] ?? '', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Date:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['return_date'] ?? date('Y-m-d'), 0, 1, 'L');
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Received by:', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['received_by_name'] ?? 'IT Department', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['received_by_designation'] ?? 'IT Staff', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Date:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['return_date'] ?? date('Y-m-d'), 0, 1, 'L');
        
        $this->addComputerGeneratedMessage();
    }
    
    private function addConditionPage($data) {
        $this->pdf->AddPage();
        $this->addHeader('ASSET CONDITION REPORT');
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Asset Return Condition Assessment', 0, 1, 'L');
        
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', '', 10);
        
        $assetInfo = [
            'Asset ID:' => $data['asset_id'] ?? '',
            'Item Name:' => $data['category'] ?? '',
            'Serial Number:' => $data['serial_num'] ?? '',
            'Return Date:' => $data['return_date'] ?? date('Y-m-d')
        ];
        
        foreach ($assetInfo as $label => $value) {
            $this->pdf->Cell(50, 5, $label, 0, 0, 'L');
            $this->pdf->Cell(0, 5, $value, 0, 1, 'L');
        }
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Condition Status:', 0, 1, 'L');
        
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', '', 10);
        $condition = $data['condition'] ?? 'Good';
        $conditionStatus = [
            'Good' => 'Asset is in good working condition with no visible damage.',
            'Fair' => 'Asset is functional but shows signs of wear or minor damage.',
            'Poor' => 'Asset has significant damage or requires repair.',
            'Damaged' => 'Asset is damaged and may require replacement or extensive repair.'
        ];
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Status: ' . $condition, 0, 1, 'L');
        
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', '', 9);
        if (isset($conditionStatus[$condition])) {
            $this->pdf->MultiCell(0, 4, $conditionStatus[$condition], 0, 'L');
        }
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Damage/Issues Report:', 0, 1, 'L');
        
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', '', 9);
        $damageReport = !empty($data['damage_report']) ? $data['damage_report'] : 'No damage or issues reported.';
        $this->pdf->MultiCell(0, 4, $damageReport, 0, 'L');
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Accessories Returned:', 0, 1, 'L');
        
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', '', 9);
        $accessories = !empty($data['accessories_returned']) ? $data['accessories_returned'] : 'All accessories returned as per handover.';
        $this->pdf->MultiCell(0, 4, $accessories, 0, 'L');
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Assessment Notes:', 0, 1, 'L');
        
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', '', 9);
        $assessmentNotes = !empty($data['assessment_notes']) ? $data['assessment_notes'] : 'Asset returned and inspected. Ready for re-assignment or maintenance as required.';
        $this->pdf->MultiCell(0, 4, $assessmentNotes, 0, 'L');
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Inspected by:', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['received_by_name'] ?? 'IT Department', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['received_by_designation'] ?? 'IT Staff', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Date:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['return_date'] ?? date('Y-m-d'), 0, 1, 'L');
        
        $this->addComputerGeneratedMessage();
    }
}

function generateReturnPDF($data) {
    $oldErrorReporting = error_reporting();
    $oldDisplayErrors = ini_get('display_errors');
    $oldLogErrors = ini_get('log_errors');
    
    $oldErrorHandler = set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if ($errno === E_DEPRECATED || $errno === E_STRICT || $errno === E_WARNING) {
            return true;
        }
        if (strpos($errfile, 'tcpdf') !== false) {
            return true;
        }
        return false;
    }, E_ALL);
    $obStarted = false;
    
    try {
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        error_reporting(0);
        
        if (!ob_get_level()) {
            ob_start();
            $obStarted = true;
        }
        
        $generator = new ReturnPDFGenerator();
        $result = $generator->generate($data);
        
        if ($obStarted) {
            ob_end_clean();
        }
        
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        ini_set('log_errors', $oldLogErrors);
        if ($oldErrorHandler !== null) {
            set_error_handler($oldErrorHandler);
        } else {
            restore_error_handler();
        }
        return $result;
    } catch (Exception $e) {
        if ($obStarted && ob_get_level()) {
            ob_end_clean();
        }
        error_reporting($oldErrorReporting);
        ini_set('display_errors', $oldDisplayErrors);
        ini_set('log_errors', $oldLogErrors);
        if ($oldErrorHandler !== null) {
            set_error_handler($oldErrorHandler);
        } else {
            restore_error_handler();
        }
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}
?>

