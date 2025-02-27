<?php
// Active le mode debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connexion à la base SQLite
$db = new SQLite3('paiements.db');

// Vérifier si la table existe, sinon la créer
$db->exec("CREATE TABLE IF NOT EXISTS paiements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payer_email TEXT,
    montant REAL,
    devise TEXT,
    statut TEXT,
    transaction_id TEXT UNIQUE,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Lire les données envoyées par PayPal
$raw_post_data = file_get_contents('php://input');
$req = 'cmd=_notify-validate&' . $raw_post_data;

// Vérifier la validité auprès de PayPal
$ch = curl_init('https://ipnpb.paypal.com/cgi-bin/webscr');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
$response = curl_exec($ch);
curl_close($ch);

// Récupérer la clé de chiffrement depuis les variables d'environnement
$encryption_key = getenv('ENCRYPTION_KEY');

// Fonction pour chiffrer les données
function encryptData($data, $key) {
    $cipher = "aes-256-cbc";
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// Vérification du paiement
if (strcmp($response, "VERIFIED") == 0) {
    // Infos du paiement
    $payer_email = $_POST['payer_email'] ?? 'inconnu';
    $payment_status = $_POST['payment_status'] ?? 'Échec';
    $transaction_id = $_POST['txn_id'] ?? 'inconnu';
    $montant = $_POST['mc_gross'] ?? 0;
    $devise = $_POST['mc_currency'] ?? 'EUR';
    $receiver_email = $_POST['receiver_email'] ?? '';
    
    // Vérifier que le paiement est bien destiné à votre compte PayPal
    $mon_email_paypal = "calliope.commande@gmail.com"; // Remplacez par votre email PayPal
    if ($receiver_email != $mon_email_paypal) {
        die("Erreur : paiement destiné à un autre compte.");
    }

    // Vérifier si le paiement est "Completed"
    if ($payment_status == "Completed") {
        // Enregistrer les informations du paiement de manière sécurisée
        $data = "Paiement reçu de $payer_email\nMontant : $montant $devise\nTransaction ID : $transaction_id\nDate : " . date("Y-m-d H:i:s");
        $encrypted_data = encryptData($data, $encryption_key);
        file_put_contents(__DIR__ . "/paiements_secure.txt", $encrypted_data . "\n", FILE_APPEND);
        
        // Envoyer un email de confirmation
        $to = "calliope.commande@gmail.com"; // Remplacez par votre adresse email
        $subject = "Nouveau paiement reçu";
        $message = $data;
        mail($to, $subject, $message);
    }
}

// Répondre à PayPal
header("HTTP/1.1 200 OK");
?>
