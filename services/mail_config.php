<?php
define('MAIL_HOST', 'localhost');
define('MAIL_PORT', 1025);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_EMAIL', 'noreply@rcmp.unikl.edu.my');
define('MAIL_FROM_NAME', 'UNIKL RCMP IT Inventory');
define('MAIL_REPLY_TO', 'it@rcmp.unikl.edu.my');

function sendEmail($to, $subject, $body, $attachments = []) {
    $boundary = md5(time());
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . MAIL_REPLY_TO;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    if (!empty($attachments)) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n";
        
        foreach ($attachments as $attachment) {
            if (file_exists($attachment['path'])) {
                $fileContent = file_get_contents($attachment['path']);
                $fileContent = chunk_split(base64_encode($fileContent));
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: {$attachment['type']}; name=\"{$attachment['name']}\"\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= $fileContent . "\r\n";
            }
        }
        
        $message .= "--{$boundary}--";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $message = $body;
    }
    
    $headersString = implode("\r\n", $headers);
    
    $result = @mail($to, $subject, $message, $headersString);
    
    if (!$result) {
        error_log("Failed to send email to: {$to}");
        return false;
    }
    
    return true;
}
?>

