<?php
/**
 * Email Sender for Blood Drive Notifications
 * Sends email notifications to donors as fallback when push notifications are not available
 */

class EmailSender {
    private $fromEmail;
    private $fromName;
    private $replyTo;
    
    public function __construct() {
        // Configure email settings
        $this->fromEmail = 'noreply@redcross.ph'; // Change to your actual sender email
        $this->fromName = 'Philippine Red Cross Blood Bank';
        $this->replyTo = 'admin@redcross.ph'; // Change to your actual reply-to email
    }
    
    /**
     * Send email notification to donor
     * @param array $donor Donor information (must include email)
     * @param array $bloodDrive Blood drive information
     * @return array Result with success status and message
     */
    public function sendEmailNotification($donor, $bloodDrive) {
        try {
            // Validate recipient email
            if (empty($donor['email'])) {
                error_log("EmailSender: No email address provided for donor ID: " . ($donor['donor_id'] ?? 'unknown'));
                return [
                    'success' => false,
                    'error' => 'No email address provided for donor',
                    'reason' => 'no_email'
                ];
            }
            
            $email = filter_var($donor['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) {
                error_log("EmailSender: Invalid email format for donor ID: " . ($donor['donor_id'] ?? 'unknown') . ", email: " . $donor['email']);
                return [
                    'success' => false,
                    'error' => 'Invalid email address format',
                    'reason' => 'invalid_email'
                ];
            }
            
            // Validate blood drive data
            if (empty($bloodDrive)) {
                error_log("EmailSender: Empty blood drive data provided");
                return [
                    'success' => false,
                    'error' => 'Blood drive information is required',
                    'reason' => 'missing_blood_drive_data'
                ];
            }
            
            // Prepare email content
            $donorName = trim(($donor['first_name'] ?? '') . ' ' . ($donor['middle_name'] ?? '') . ' ' . ($donor['surname'] ?? ''));
            if (empty(trim($donorName))) {
                $donorName = 'Valued Donor';
            }
            
            $subject = '🩸 Blood Drive Alert - ' . ($bloodDrive['location'] ?? 'Near You');
            
            // Generate email template with error handling
            try {
                $message = $this->generateEmailTemplate($donorName, $bloodDrive);
            } catch (Exception $e) {
                error_log("EmailSender: Error generating email template: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Failed to generate email template: ' . $e->getMessage(),
                    'reason' => 'template_generation_failed',
                    'recipient' => $email
                ];
            }
            
            // Generate headers with error handling
            try {
                $headers = $this->generateEmailHeaders();
            } catch (Exception $e) {
                error_log("EmailSender: Error generating email headers: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Failed to generate email headers: ' . $e->getMessage(),
                    'reason' => 'header_generation_failed',
                    'recipient' => $email
                ];
            }
            
            // Send email using PHP mail() function
            // Note: For production, consider using PHPMailer, SendGrid, or similar service
            $mailSent = @mail($email, $subject, $message, $headers);
            
            if ($mailSent) {
                error_log("EmailSender: Email sent successfully to: $email");
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'recipient' => $email
                ];
            } else {
                $error = error_get_last();
                $errorMessage = $error ? $error['message'] : 'Unknown error sending email';
                error_log("EmailSender: Failed to send email to $email - Error: $errorMessage");
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'reason' => 'mail_send_failed',
                    'recipient' => $email
                ];
            }
            
        } catch (Exception $e) {
            error_log("EmailSender: Exception in sendEmailNotification: " . $e->getMessage());
            error_log("EmailSender: Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Exception occurred: ' . $e->getMessage(),
                'reason' => 'exception',
                'recipient' => $donor['email'] ?? 'unknown'
            ];
        } catch (Error $e) {
            error_log("EmailSender: Fatal error in sendEmailNotification: " . $e->getMessage());
            error_log("EmailSender: File: " . $e->getFile() . " Line: " . $e->getLine());
            return [
                'success' => false,
                'error' => 'Fatal error: ' . $e->getMessage(),
                'reason' => 'fatal_error',
                'recipient' => $donor['email'] ?? 'unknown'
            ];
        }
    }
    
    /**
     * Generate HTML email template
     * @param string $donorName Name of the donor
     * @param array $bloodDrive Blood drive information
     * @return string HTML email template
     * @throws Exception if template generation fails
     */
    private function generateEmailTemplate($donorName, $bloodDrive) {
        try {
            $location = $bloodDrive['location'] ?? 'Selected Location';
            $date = $bloodDrive['drive_date'] ?? '';
            $time = $bloodDrive['drive_time'] ?? '';
            $bloodDriveId = $bloodDrive['id'] ?? '';
            $customMessage = $bloodDrive['message_template'] ?? '';
            
            // Format date for display
            $formattedDate = $date;
            if ($date && strtotime($date)) {
                $formattedDate = date('F j, Y', strtotime($date));
            }
            
            // Format time for display
            $formattedTime = $time;
            if ($time && preg_match('/^(\d{2}):(\d{2})/', $time, $matches)) {
                $hour = intval($matches[1]);
                $minute = $matches[2];
                $period = $hour >= 12 ? 'PM' : 'AM';
                $hour12 = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                $formattedTime = "$hour12:$minute $period";
            }
            
            // Build RSVP URL - adjust path based on your installation
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = '/RED-CROSS-THESIS'; // Adjust this if your installation is in a different path
            $rsvpUrl = $protocol . '://' . $host . $basePath . '/blood-drive-details?id=' . $bloodDriveId;
            
            $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Blood Drive Notification</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <div style='background-color: #d32f2f; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;'>
        <h1 style='margin: 0;'>🩸 Philippine Red Cross</h1>
        <p style='margin: 5px 0 0 0;'>Blood Drive Notification</p>
    </div>
    
    <div style='background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 5px 5px;'>
        <p style='font-size: 16px; margin-bottom: 20px;'>Dear $donorName,</p>
        
        " . (!empty($customMessage) ? "<p style='font-size: 16px; color: #d32f2f; font-weight: bold; margin-bottom: 20px;'>$customMessage</p>" : "") . "
        
        <p style='font-size: 16px; margin-bottom: 20px;'>
            We are excited to inform you about an upcoming blood drive in your area. Your blood type is urgently needed!
        </p>
        
        <div style='background-color: white; border-left: 4px solid #d32f2f; padding: 20px; margin: 20px 0; border-radius: 4px;'>
            <h2 style='color: #d32f2f; margin-top: 0;'>📅 Blood Drive Details</h2>
            <p style='margin: 10px 0;'><strong>📍 Location:</strong> $location</p>
            <p style='margin: 10px 0;'><strong>📆 Date:</strong> $formattedDate</p>
            <p style='margin: 10px 0;'><strong>⏰ Time:</strong> $formattedTime</p>
        </div>
        
        <div style='text-align: center; margin: 30px 0;'>
            <a href='$rsvpUrl' style='background-color: #d32f2f; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>
                Confirm Your Attendance
            </a>
        </div>
        
        <p style='font-size: 14px; color: #666; margin-top: 30px;'>
            Your participation can save lives. We appreciate your continued support to our blood donation program.
        </p>
        
        <p style='font-size: 14px; color: #666; margin-top: 20px;'>
            If you have any questions, please contact us at <a href='mailto:{$this->replyTo}' style='color: #d32f2f;'>{$this->replyTo}</a>
        </p>
        
        <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
        
        <p style='font-size: 12px; color: #999; text-align: center;'>
            This is an automated notification. Please do not reply to this email.
            <br>© " . date('Y') . " Philippine Red Cross. All rights reserved.
        </p>
    </div>
</body>
</html>";
        
            return $html;
        } catch (Exception $e) {
            error_log("EmailSender: Error in generateEmailTemplate: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate email headers
     * @return string Email headers
     * @throws Exception if header generation fails
     */
    private function generateEmailHeaders() {
        try {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "Reply-To: {$this->replyTo}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "X-Priority: 1\r\n"; // High priority
            
            return $headers;
        } catch (Exception $e) {
            error_log("EmailSender: Error in generateEmailHeaders: " . $e->getMessage());
            throw $e;
        }
    }
}

