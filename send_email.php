<?php
// Script per l'invio del form di contatto tramite PHPMailer


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Se usi Composer scommenta questa riga:
// require 'vendor/autoload.php';

// Se hai scaricato PHPMailer manualmente nella cartella 'PHPMailer', scommenta queste righe:
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Inclusione del file di configurazione
require_once 'config.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 0. Verifica reCAPTCHA v3
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_secret = RECAPTCHA_SECRET_KEY;

    $recaptcha_response = isset($_POST['recaptcha_token']) ? $_POST['recaptcha_token'] : '';

    // Chiamata a Google tramite cURL (più affidabile di file_get_contents)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $recaptcha_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $recaptcha_secret,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    // DEBUG: Se vuoi vedere la risposta grezza, scommenta la riga sotto
    // die("Debug Google Response: " . $response);

    $response_keys = json_decode($response, true);

    if (!$response_keys || !$response_keys["success"]) {
        $error_codes = (isset($response_keys['error-codes']) && is_array($response_keys['error-codes']))
            ? implode(", ", $response_keys['error-codes'])
            : 'Sconosciuto (Response: ' . htmlspecialchars($response) . ')';
        die("Errore reCAPTCHA: Verifica fallita. Motivo: " . $error_codes);
    }

    // Se il punteggio è troppo basso (sotto 0.5)
    if (isset($response_keys["score"]) && $response_keys["score"] < 0.5) {
        die("Errore reCAPTCHA: Punteggio basso (" . $response_keys["score"] . "). Sospetto bot.");
    }

    // 1. Sanificazione degli input
    // Evita attacchi XSS e rimuove spazi extra
    $nome = isset($_POST['nome']) ? htmlspecialchars(trim($_POST['nome'])) : '';
    $citta = isset($_POST['citta']) ? htmlspecialchars(trim($_POST['citta'])) : '';
    $telefono = isset($_POST['telefono']) ? htmlspecialchars(trim($_POST['telefono'])) : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $messaggio = isset($_POST['messaggio']) ? htmlspecialchars(trim($_POST['messaggio'])) : '';

    // Validazione base lato server
    if (empty($nome) || empty($citta) || empty($telefono) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Errore: Per favore compila tutti i campi obbligatori con dati validi.");
    }

    // 2. Inizializzazione PHPMailer
    $mail = new PHPMailer(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;

    try {
        // --- IMPOSTAZIONI SERVER SMTP ---
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_SECURE == 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        // ---------------------------------


        // Mittente
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME); // L'indirizzo da cui parte l'email

        // 1. INVIO NOTIFICA ALL'AMMINISTRATORE
        $mail->addAddress(EMAIL_ADMIN, EMAIL_ADMIN_NAME);


        $mail->isHTML(true);
        $mail->Subject = 'Nuova richiesta di assistenza impianti da: ' . $nome;

        $bodyAdmin = "
            <!DOCTYPE html>
            <html lang='it'>
            <head><meta charset='UTF-8'></head>
            <body>
            <h2 style='color: #1F201D; font-family: sans-serif;'>Nuovo messaggio dal sito web G-TECH</h2>
            <hr style='border: 1px solid #e0e0e0;' />
            <p><strong>Nome / Cognome:</strong> {$nome}</p>
            <p><strong>Città di provenienza:</strong> {$citta}</p>
            <p><strong>Recapito telefonico:</strong> {$telefono}</p>
            <p><strong>E-mail:</strong> {$email}</p>
            <p><strong>Messaggio:</strong><br/>" . nl2br($messaggio) . "</p>
            </body>
            </html>
        ";

        $mail->Body = $bodyAdmin;
        $mail->AltBody = strip_tags(str_replace("<br/>", "\n", $bodyAdmin));
        $mail->send(); // Invia la mail all'admin

        // 2. INVIO MAIL DI CONFERMA ALL'UTENTE
        $mail->clearAddresses(); // Rimuove i destinatari precedenti
        $mail->addAddress($email, $nome); // Aggiunge solo l'utente

        $mail->Subject = 'Conferma ricezione richiesta - G-TECH';
        $bodyUser = "
            <!DOCTYPE html>
            <html lang='it'>
            <head><meta charset='UTF-8'></head>
            <body>
            <h2 style='color: #1F201D; font-family: sans-serif;'>Grazie per averci contattato</h2>
            <p>Gentile <strong>{$nome}</strong>,</p>
            <p>La tua mail è stata inviata correttamente. Il nostro team ti risponderà il prima possibile.</p>
            <br>
            <p>Cordiali saluti,<br>Team G-TECH</p>
            </body>
            </html>
        ";

        $mail->Body = $bodyUser;
        $mail->AltBody = "Gentile {$nome}, la tua mail è stata inviata correttamente. Grazie per averci contattato. Team G-TECH";

        $mail->send(); // Invia la mail di conferma all'utente

        // 4. Gestione della risposta: Redirect alla Thank You Page in caso di successo
        header('Location: thank-you.html');
        exit();

    } catch (Exception $e) {
        // Gestione Errore se l'email non parte
        echo "C'è stato un problema nell'invio del messaggio. Errore Mailer: {$mail->ErrorInfo}";
    }
} else {
    // Se qualcuno prova ad accedere direttamente a questo file senza inviare il form,
    // viene reindirizzato alla pagina principale.
    header('Location: index.html');
    exit();
}
?>