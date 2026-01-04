<?php

if (!isset($_REQUEST['safety_key'])) {

    die();
}

// Admin Email.

$to = "test.you@gmail.com"; // write your email address in here.
// Fetching Values from URL.

$email_subject = "Request A Free Quote";

$move_from = isset( $_POST['move_from'] ) ? $_POST['move_from'] : '';
$move_to = isset( $_POST['move_to'] ) ? $_POST['move_to'] : '';
$moving_size = isset( $_POST['moving_size'] ) ? $_POST['moving_size'] : '';
$your_date = isset( $_POST['your_date'] ) ? $_POST['your_date'] : '';
$your_name = isset( $_POST['your_name'] ) ? $_POST['your_name'] : '';
$your_date = isset( $_POST['your_date'] ) ? $_POST['your_date'] : '';
$your_email = isset( $_POST['your_email'] ) ? $_POST['your_email'] : '';
$your_phone = isset( $_POST['your_phone'] ) ? $_POST['your_phone'] : '';

 
$your_name = $_POST['your_name'];
$your_email = $_POST['your_email'];
$your_phone = $_POST['your_phone'];

$template = '<div>Hello ' . $your_name . ',<br/>'
        . '<br/>Thank you...! For Contacting Us.<br/><br/>'
        . 'Moving From:' . $move_from . '<br/>'
        . 'Moving To:' . $move_to . '<br/>'
        . 'Moving Size:' . $moving_size . '<br/>'
        . 'Moving Date:' . $your_date . '<br/>'
        . 'Name:' . $your_name . '<br/>'
        . 'Email:' . $your_email . '<br/>'
        . 'Phone No:' . $your_phone . '<br/><br/>'
        . 'This is a Free Quote Request Email</div>';
$message = "<div>" . $template . "</div>";


$headers = 'MIME-Version: 1.0' . "\r\n";
$headers.='Content-type: text/html; charset=utf-8; charset=iso-8859-1' . "\r\n";
$headers.='From:' . $your_email . "\r\n"; // Sender's Email
mail($to, $email_subject, $message, $headers, '');

$data = array(
    'status' => 1,
    'msg' => "Your Query has been received, We will contact you soon."
);

echo json_encode($data);