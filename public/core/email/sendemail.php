<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


include_once (__DIR__."/phpmailer/src/Exception.php");
include_once (__DIR__."/phpmailer/src/PHPMailer.php");
include_once (__DIR__."/phpmailer/src/SMTP.php");


function send_email($template_id,$user_name,$employee_id,$password,$email){
// Instantiation and passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    //Server settings
    //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
    $mail->SMTPDebug = 0;
    $mail->isSMTP();                                            // Send using SMTP
    // Send using SMTP
   		//$mail->Host       = 'smtp.office365.com';     // Set the SMTP server to send through
        $mail->Host       = 'smtp.hostinger.com';
		$mail->SMTPAuth = true; // authentication enabled
		$mail->SMTPAutoTLS=true;
	   
	//	$mail->Username   = 'systemalert@cipla.com';                     // SMTP username
	  //  $mail->Password   = 'Cipla@321';                               // SMTP password
		$mail->Username   = 'omkar@palcoa.com';
        $mail->Password   = 'Mandar@131620';
		//$mail->Port       = 587; // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
	 $mail->Port       = 465;
    //Recipients
    $mail->setFrom('omkar@palcoa.com', 'HVACVADMS Alert');
    $mail->addAddress($email, $user_name);     // Add a recipient
    
    
    
    $template_account_creation="Dear ".$user_name.",<br> Your account is successfully created. Please find the login details below.<br>
                                <br>Login ID: ".$employee_id." <br>Password: ".$password."<br><br>- ProVal";
    
    $template_password_reset="Dear User,<br> Your request for password reset is successfully processed. Please find the login details below.<br>
                                <br>Login ID: ".$employee_id." <br>Password: ".$password."<br><br>- ProVal";
    
    
    
    
    
    // Content
    $mail->isHTML(true);                                  // Set email format to HTML
    $mail->Subject = 'HVACVADMS Alert';
    
    if($template_id==1)
    {
    $mail->Body    = $template_account_creation;
    }
    else if($template_id==2)
    {
        
        $mail->Body    = $template_password_reset;
    }
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
    
    $mail->send();
    //echo 'Message has been sent';
} catch (Exception $e) {
    //echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
}