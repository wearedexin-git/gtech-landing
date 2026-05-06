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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
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

    try {
        // --- IMPOSTAZIONI SERVER SMTP (Parametri temporanei) ---
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.example.com';                     // Host del tuo server SMTP
        $mail->SMTPAuth   = true;                                   // Abilita autenticazione SMTP
        $mail->Username   = 'test@example.com';                     // Username SMTP
        $mail->Password   = 'password_temporanea';                  // Password SMTP
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Crittografia (STARTTLS o SMTPS)
        $mail->Port       = 587;                                    // Porta SMTP (es. 587 o 465)
        // -------------------------------------------------------

        // Mittente
        $mail->setFrom('noreply@gtechsrl.it', 'G-TECH Sito Web'); // L'indirizzo da cui parte l'email
        
        // Destinatario (A chi deve arrivare la richiesta)
        $mail->addAddress('info@gtechsrl.it', 'G-TECH Info'); 
        
        // Indirizzo di risposta (Reply-to: l'email di chi ha compilato il form)
        $mail->addReplyTo($email, $nome);

        // Contenuto dell'email
        $mail->isHTML(true);                                        
        $mail->Subject = 'Nuova richiesta di assistenza impianti da: ' . $nome;
        
        // Costruzione del corpo dell'email in HTML
        $body = "
            <h2 style='color: #1F201D; font-family: sans-serif;'>Nuovo messaggio dal sito web G-TECH</h2>
            <hr style='border: 1px solid #e0e0e0;' />
            <p><strong>Nome / Cognome:</strong> {$nome}</p>
            <p><strong>Città di provenienza:</strong> {$citta}</p>
            <p><strong>Recapito telefonico:</strong> {$telefono}</p>
            <p><strong>E-mail:</strong> {$email}</p>
            <p><strong>Messaggio:</strong><br/>" . nl2br($messaggio) . "</p>
        ";
        
        $mail->Body    = $body;
        // Versione Testo Semplice per i client che non supportano l'HTML
        $mail->AltBody = strip_tags(str_replace("<br/>", "\n", $body)); 

        // 3. Invio effettivo
        $mail->send();
        
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
