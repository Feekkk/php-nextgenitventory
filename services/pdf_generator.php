<?php
$tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
if (file_exists($tcpdfPath)) {
    require_once $tcpdfPath;
} else {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class HandoverPDFGenerator {
    private $pdf;
    private $logoPath;
    
    public function __construct() {
        if (class_exists('TCPDF')) {
            $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        } else {
            throw new Exception('TCPDF library not found. Please install via: composer require tecnickcom/tcpdf');
        }
        $this->logoPath = __DIR__ . '/../public/unikl-logo.png';
        $this->setupPDF();
    }
    
    private function setupPDF() {
        $this->pdf->SetCreator('UNIKL RCMP IT Inventory');
        $this->pdf->SetAuthor('UNIKL RCMP');
        $this->pdf->SetTitle('Asset Handover Document');
        $this->pdf->SetSubject('Equipment Handover Form');
        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetAutoPageBreak(true, 20);
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    public function generate($data) {
        $this->addPage1($data);
        $this->addPage2($data);
        $this->addPage3($data);
        
        return $this->pdf->Output('', 'S');
    }
    
    private function addPage1($data) {
        $this->pdf->AddPage();
        
        if (file_exists($this->logoPath)) {
            $this->pdf->Image($this->logoPath, 15, 15, 30, 0, '', '', '', false, 300, '', false, false, 0, false);
        }
        
        $this->pdf->SetY(25);
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'UNIVERSITY KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK', 0, 1, 'C');
        
        $this->pdf->SetY(35);
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->Cell(0, 10, 'EMPLOYEE SOFTWARE COMPLIANCE STATEMENT', 0, 1, 'C');
        
        $this->pdf->SetY(50);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->MultiCell(0, 6, 'UNIVERSITI KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK (UNIKL RCMP) software policy regarding the use of computer software.', 0, 'L');
        
        $this->pdf->SetY(60);
        $this->pdf->SetFont('helvetica', '', 10);
        
        $policies = [
            '1. UNIKL RCMP licenses the use of computer software from a variety of outside companies. UNIKL RCMP does not own this software or its related documentation, and unless authorized by the software developers, does not have the right to reproduce it, even for back-up purposes, unless explicitly allowed by the software owner. (eg: Microsoft, Adobe and etc).',
            '2. UNIKL RCMP employees shall use the software only in accordance with the license agreements and will not install unauthorized copies of the commercial software.',
            '3. UNIKL RCMP employees shall not download or upload unauthorized software over the Internet.',
            '4. UNIKL RCMP employees learning of any misuse of software or company IT equipment (which includes vandalism of the certificate of authenticity sticker on the PC casing chassis, PC monitors, CD media etc) which could be detrimental to the business of the company shall notify their immediate supervisor.',
            '5. Under the Copyright Act 1987, offenders can be fined from RM2,000 to RM20,000 for each infringing copy and/or face imprisonment of up to 5 years. UNIKL RCMP does not condone the illegal duplication of software. UNIKL RCMP employees who make, acquire, or use authorized copies of computer software shall be disciplined as appropriate under the circumstances. Such discipline action may include termination.',
            '6. Any doubts concerning whether any employee may copy/duplicate or use a given software program should be raised with the immediate supervisor before proceeding.'
        ];
        
        foreach ($policies as $policy) {
            $this->pdf->MultiCell(0, 6, $policy, 0, 'L');
            $this->pdf->Ln(3);
        }
        
        $this->pdf->SetY($this->pdf->GetY() + 10);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->MultiCell(0, 6, 'I am fully aware of the software use policies of UNIKL RCMP and agree to uphold those policies', 0, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 15);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'Employee Signature', 0, 1, 'L');
        $this->pdf->Ln(10);
        $this->pdf->Cell(0, 6, 'Employee Name: ' . ($data['staff_name'] ?? ''), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'Employee Designation: ' . ($data['staff_designation'] ?? ''), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'Staff ID: ' . ($data['staff_id'] ?? ''), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'Date: ' . ($data['handover_date'] ?? date('Y-m-d')), 0, 1, 'L');
    }
    
    private function addPage2($data) {
        $this->pdf->AddPage();
        
        if (file_exists($this->logoPath)) {
            $this->pdf->Image($this->logoPath, 15, 15, 30, 0, '', '', '', false, 300, '', false, false, 0, false);
        }
        
        $this->pdf->SetY(25);
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'UNIVERSITY KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK', 0, 1, 'C');
        
        $this->pdf->SetY(35);
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->Cell(0, 10, 'HANDING OVER OF COMPANY\'S NOTEBOOK/DESKTOP', 0, 1, 'C');
        
        $this->pdf->SetY(50);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'TO:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['staff_name'] ?? '', 0, 1, 'L');
        $this->pdf->Cell(50, 6, 'ASSET ID:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['asset_id'] ?? '', 0, 1, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 10);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->MultiCell(0, 6, 'I hereby hand over the following: -', 0, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 5);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, '1. Asset Information\'s', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(50, 6, 'Item Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, ($data['category'] ?? '') . ' - ' . ($data['brand'] ?? '') . ' ' . ($data['model'] ?? ''), 0, 1, 'L');
        
        $this->pdf->Cell(50, 6, 'Brand Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['brand'] ?? '', 0, 1, 'L');
        
        $this->pdf->Cell(50, 6, 'Model Name:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['model'] ?? '', 0, 1, 'L');
        
        $this->pdf->Cell(50, 6, 'Serial Number:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['serial_num'] ?? '', 0, 1, 'L');
        
        $this->pdf->Cell(50, 6, 'Adapter:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['accessories'] ?? 'N/A', 0, 1, 'L');
        
        $this->pdf->Cell(50, 6, 'Remark:', 0, 0, 'L');
        $this->pdf->Cell(0, 6, $data['handover_notes'] ?? 'N/A', 0, 1, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 10);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->MultiCell(0, 6, 'to be used for your daily work. Please comply with the following company\'s requirements: -', 0, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 5);
        $requirements = [
            'i. To comply with Company Notebook/Desktop Usage Policy. (Please refer to it.rcmp.unikl.edu.my)',
            'ii. To use this Notebook/Desktop for working purposes only.',
            'iii. To use for teaching purposes and use at appropriate place only. (If related)',
            'iv. Installation of any unauthorized/illegal software into this Notebook/Desktop is strictly prohibited.',
            'v. Any request for repair due to mechanical defect must be forwarded to the IT Department by filling in the requisition form and subject to approval by the management.',
            'vi. The user is responsible for repairing or replacement cost of the damage or loss due to negligence or intentional misconduct.'
        ];
        
        foreach ($requirements as $req) {
            $this->pdf->MultiCell(0, 6, $req, 0, 'L');
            $this->pdf->Ln(2);
        }
        
        $this->pdf->SetY($this->pdf->GetY() + 15);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'HAND OVER BY:', 0, 1, 'L');
        $this->pdf->Ln(10);
        $this->pdf->Cell(0, 6, 'NAME: ' . ($data['handover_by_name'] ?? 'IT Department'), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'DESIGNATION: ' . ($data['handover_by_designation'] ?? 'IT Staff'), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'DATE: ' . ($data['handover_date'] ?? date('Y-m-d')), 0, 1, 'L');
    }
    
    private function addPage3($data) {
        $this->pdf->AddPage();
        
        if (file_exists($this->logoPath)) {
            $this->pdf->Image($this->logoPath, 15, 15, 30, 0, '', '', '', false, 300, '', false, false, 0, false);
        }
        
        $this->pdf->SetY(25);
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->Cell(0, 10, 'UNIVERSITY KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK', 0, 1, 'C');
        
        $this->pdf->SetY(50);
        $this->pdf->SetFont('helvetica', '', 10);
        $staffName = $data['staff_name'] ?? '[staff name]';
        $this->pdf->MultiCell(0, 6, "I, {$staffName}, received the above mentioned Notebook/Desktop in satisfactory condition and agree to abide by the UNIKL Royal College of Medicine Perak regulations on the usage of company's equipment.", 0, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 15);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'NAME: ' . ($data['staff_name'] ?? ''), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'DESIGNATION: ' . ($data['staff_designation'] ?? ''), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'STAFF NO/IC NO: ' . ($data['staff_id'] ?? ''), 0, 1, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 10);
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->Cell(0, 6, 'Liability Statement: -', 0, 1, 'L');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetY($this->pdf->GetY() + 5);
        $liability = "I, {$staffName} agree to pay all costs associated with damage to the above peripherals or its associated peripheral equipment. I also agree to pay for replacement cost of the equipment should it be lost or stolen.";
        $this->pdf->MultiCell(0, 6, $liability, 0, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 10);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 6, 'My signature below indicates my agreement with the above liability statement', 0, 1, 'L');
        
        $this->pdf->SetY($this->pdf->GetY() + 15);
        $this->pdf->Cell(0, 6, 'SIGNATURE: ' . ($data['digital_signoff'] ?? ''), 0, 1, 'L');
        $this->pdf->Cell(0, 6, 'DATE: ' . ($data['handover_date'] ?? date('Y-m-d')), 0, 1, 'L');
    }
}

function generateHandoverPDF($data) {
    try {
        $generator = new HandoverPDFGenerator();
        return $generator->generate($data);
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}
?>

