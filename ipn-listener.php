<?php
// Lire les données envoyées par PayPal
$raw_post_data = file_get_contents('php://input');
$req = 'cmd=_notify-validate&' . $raw_post_data;

// Vérifier la validité auprès de PayPal
$ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
$response = curl_exec($ch);
curl_close($ch);

// Fichier log (optionnel)
file_put_contents("log_ipn.txt", date("Y-m-d H:i:s") . " - " . print_r($_POST, true) . "\n", FILE_APPEND);

// Vérification du paiement
if (strcmp($response, "VERIFIED") == 0) {
    // Infos du paiement
    $payer_email = $_POST['payer_email'];
    $payment_status = $_POST['payment_status'];
    $transaction_id = $_POST['txn_id'];
    $montant = $_POST['mc_gross'];
    $devise = $_POST['mc_currency'];
    $receiver_email = $_POST['receiver_email'];

    // Vérifier que le paiement est bien destiné à votre compte PayPal
    $mon_email_paypal = "calliope.commande@gmail.com"; // Remplacez par votre email PayPal
    if ($receiver_email != $mon_email_paypal) {
        die("Erreur : paiement destiné à un autre compte.");
    }

    // Vérifier si le paiement est "Completed"
    if ($payment_status == "Completed") {
        // Envoyer un email de confirmation
        $to = "calliope.commande@gmail.com"; // Remplacez par votre adresse email
        $subject = "Nouveau paiement reçu";
        $message = "Paiement reçu de $payer_email \nMontant : $montant $devise\nTransaction ID : $transaction_id";
        mail($to, $subject, $message);
    }
}
?>
