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

class HandoverPDFGenerator {
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
        $this->pdf->SetTitle('Asset Handover Document');
        $this->pdf->SetSubject('Equipment Handover Summary');
        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    public function generate($data) {
        $this->addHandoverPage($data);
        $this->addSoftwareCompliancePage($data);
        $this->addLiabilityPage($data);
        
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
    
    private function addHandoverPage($data) {
        $this->pdf->AddPage();
        $this->addHeader('HANDING OVER OF COMPANY\'S NOTEBOOK/DESKTOP');
        
        $this->pdf->Ln(6);
        
        $startY = $this->pdf->GetY();
        
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(173, 216, 230);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell(45, 12, 'TO:', 1, 0, 'L', true);
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
        $this->pdf->Cell(0, 6, 'I hereby hand over the following: -', 0, 1, 'L');
        
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, '1. Asset Information\'s', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        
        $assetInfo = [
            'Item Name:' => $data['category'] ?? '',
            'Brand Name:' => $data['brand'] ?? '',
            'Model Name:' => $data['model'] ?? '',
            'Serial Number:' => $data['serial_num'] ?? '',
            'Adapter:' => !empty($data['accessories']) ? $data['accessories'] : 'N/A',
            'Remark' => !empty($data['handover_notes']) ? $data['handover_notes'] : 'N/A'
        ];
        
        foreach ($assetInfo as $label => $value) {
            $this->pdf->Cell(50, 5, $label, 0, 0, 'L');
            $this->pdf->Cell(0, 5, $value, 0, 1, 'L');
        }
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'to be used for your daily work.', 0, 1, 'L');
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Please comply with the following company\'s requirements: -', 0, 1, 'L');
        
        $this->pdf->Ln(2);
        $this->pdf->SetFont('helvetica', '', 9);
        
        $requirements = [
            'i. To comply with Company Notebook/Desktop Usage Policy. (Please refer to it.rcmp.unikl.edu.my)',
            'ii. To use this Notebook/Desktop for working purposes only.',
            'iii. To use for teaching purposes and use at appropriate place only. (If related)',
            'iv. Installation of any unauthorized/illegal software into this Notebook/Desktop is strictly prohibited.',
            'v. Any request for repair due to mechanical defect must be forwarded to the IT Department by filling in the requisition form and subject to approval by the management.',
            'vi. The user is responsible for repairing or replacement cost of the damage or loss due to negligence or intentional misconduct.'
        ];
        
        foreach ($requirements as $req) {
            $this->pdf->MultiCell(0, 4, $req, 0, 'L');
        }
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Hand over by:', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['handover_by_name'] ?? 'IT Department', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['handover_by_designation'] ?? 'IT Staff', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Date:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['handover_date'] ?? date('Y-m-d'), 0, 1, 'L');
        
        $this->addComputerGeneratedMessage();
    }
    
    private function addSoftwareCompliancePage($data) {
        $this->pdf->AddPage();
        $this->addHeader('EMPLOYEE SOFTWARE COMPLIANCE STATEMENT');
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'UNIVERSITI KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK (UNIKL RCMP)', 0, 1, 'L');
        
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Software Policy Regarding the Use of Computer Software', 0, 1, 'L');
        
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', '', 9);
        
        $policies = [
            'UNIKL RCMP licenses the use of computer software from a variety of outside companies. UNIKL RCMP does not own this software or its related documentation, and unless authorized by the software developers, does not have the right to reproduce it, even for back-up purposes, unless explicitly allowed by the software owner. (eg: Microsoft, Adobe and etc).',
            'UNIKL RCMP employees shall use the software only in accordance with the license agreements and will not install unauthorized copies of the commercial software.',
            'UNIKL RCMP employees shall not download or upload unauthorized software over the Internet.',
            'UNIKL RCMP employees learning of any misuse of software or company IT equipment (which includes vandalism of the certificate of authenticity sticker on the PC casing chassis, PC monitors, CD media etc) which could be detrimental to the business of the company shall notify their immediate supervisor.',
            'Under the Copyright Act 1987, offenders can be fined from RM2,000 to RM20,000 for each infringing copy and/or face imprisonment of up to 5 years. UNIKL RCMP does not condone the illegal duplication of software. UNIKL RCMP employees who make, acquire, or use authorized copies of computer software shall be disciplined as appropriate under the circumstances. Such discipline action may include termination.',
            'Any doubts concerning whether any employee may copy/duplicate or use a given software program should be raised with the immediate supervisor before proceeding.'
        ];
        
        foreach ($policies as $index => $policy) {
            $this->pdf->MultiCell(0, 4, ($index + 1) . '. ' . $policy, 0, 'L');
            $this->pdf->Ln(1);
        }
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Acknowledgement', 0, 1, 'L');
        
        $this->pdf->Ln(1);
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->MultiCell(0, 4, 'I am fully aware of the software use policies of UNIKL RCMP and agree to uphold those policies.', 0, 'L');
        
        $this->addComputerGeneratedMessage();
    }
    
    private function addLiabilityPage($data) {
        $this->pdf->AddPage();
        $this->addHeader();
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', '', 10);
        $staffName = $data['staff_name'] ?? '[staff name]';
        $this->pdf->MultiCell(0, 4, "I, {$staffName} received the above mentioned Notebook/Desktop in satisfactory condition and agree to abide by the UNIKL Royal College of Medicine Perak regulations on the usage of company's equipment.", 0, 'L');
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_name'] ?? '', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_designation'] ?? '', 0, 1, 'L');
        $this->pdf->Ln(1);
        $this->pdf->Cell(50, 6, 'Staff no/IC No:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_id'] ?? '', 0, 1, 'L');
        
        $this->pdf->Ln(6);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Liability Statement :-', 0, 1, 'L');
        
        $this->pdf->Ln(3);
        $this->pdf->SetFont('helvetica', 'I', 10);
        $this->pdf->SetFillColor(245, 245, 245);
        $this->pdf->SetTextColor(0, 0, 0);
        $liabilityText = "'I, {$staffName} agree to pay all costs associated with damage to the above peripherals or its associated peripheral equipment. I also agree to pay for replacement cost of the equipment should it be lost or stolen.'";
        $this->pdf->MultiCell(0, 5, $liabilityText, 0, 'L', true);
        
        $this->pdf->Ln(4);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetFillColor(255, 255, 255);
        $this->pdf->MultiCell(0, 4, 'My signature above indicates my agreement with the above liability statement', 0, 'L');
        
        $this->addComputerGeneratedMessage();
    }
}

function generateHandoverPDF($data) {
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
        
        $generator = new HandoverPDFGenerator();
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

