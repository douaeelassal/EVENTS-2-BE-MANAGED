<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/Database.php';
require_once 'includes/Security.php';

// Vérification de sécurité - seulement les organisateurs peuvent générer des attestations
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    echo "<!-- Debug: Session check failed - user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set') . ", role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set') . " -->";
    echo "<div style='text-align: center; padding: 50px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 10px; margin: 50px auto; max-width: 600px;'>";
    echo "<h2> Accès refusé</h2>";
    echo "<p>Vous devez être connecté en tant qu'organisateur pour accéder à cette page.</p>";
    echo "<p><a href='auth/login.php' style='color: #721c24; text-decoration: underline;'>Se connecter</a></p>";
    echo "</div>";
    exit;
}

$db = Database::getInstance();

// Récupérer l'ID de l'événement avec débogage
$event_id = (int)($_GET['event_id'] ?? 0);

echo "<!-- Debug: event_id = $event_id -->";
echo "<!-- Debug: session user_id = " . ($_SESSION['user_id'] ?? 'non défini') . " -->";
echo "<!-- Debug: session user_role = " . ($_SESSION['user_role'] ?? 'non défini') . " -->";

if ($event_id <= 0) {
    echo "<div style='text-align: center; padding: 50px; background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; border-radius: 10px; margin: 50px auto; max-width: 600px;'>";
    echo "<h2> ID d'événement invalide</h2>";
    echo "<p>L'ID de l'événement doit être un nombre positif.</p>";
    echo "<p><a href='debug_event_status.php' style='color: #856404;'>Retour au diagnostic</a></p>";
    echo "</div>";
    exit;
}

// Vérifier que l'événement appartient à l'organisateur connecté
$stmt = $db->prepare("
    SELECT e.*, u.nom_complet as organisateur_nom
    FROM evenements e
    JOIN utilisateurs u ON e.organisateur_id = u.id
    WHERE e.id = ? AND e.organisateur_id = ?
");
$stmt->execute([$event_id, $_SESSION['user_id']]);
$event = $stmt->fetch();

if (!$event) {
    echo "<!-- Debug: Événement non trouvé pour event_id=$event_id, user_id=" . $_SESSION['user_id'] . " -->";
    die("Erreur: Événement non trouvé ou accès non autorisé. Vérifiez que l'événement vous appartient.");
}

// Vérifier que l'événement est terminé
$eventStatus = trim(strtolower($event['statut']));
if ($eventStatus !== 'termine') {
    echo "<!-- Debug: Événement statut actuel = '{$event['statut']}' (trimmed: '$eventStatus') -->";
    die("L'événement doit être marqué comme terminé pour générer les attestations. Statut actuel: '{$event['statut']}'");
}

// Récupérer tous les participants à cet événement
$stmt = $db->prepare("
    SELECT u.id, u.nom_complet, u.email, i.date_inscription
    FROM utilisateurs u
    JOIN inscriptions i ON u.id = i.utilisateur_id
    WHERE i.evenement_id = ? AND i.statut = 'confirme'
    ORDER BY u.nom_complet
");
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll();

echo "<!-- Debug: Participants trouvés = " . count($participants) . " -->";

if (empty($participants)) {
    echo "<!-- Debug: Aucun participant trouvé pour event_id=$event_id -->";
    echo "<div style='text-align: center; padding: 50px; background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; border-radius: 10px; margin: 50px auto; max-width: 600px;'>";
    echo "<h2> Aucun participant confirmé</h2>";
    echo "<p>L'événement '{$event['titre']}' n'a aucun participant confirmé.</p>";
    echo "<p>Les participants doivent avoir le statut 'confirmé' pour générer des attestations.</p>";
    echo "<p><a href='debug_event_status.php' style='color: #856404;'>Retour au diagnostic</a></p>";
    echo "</div>";
    exit;
}

// Créer le répertoire attestations s'il n'existe pas
$attestations_dir = 'attestations';
if (!file_exists($attestations_dir)) {
    mkdir($attestations_dir, 0755, true);
}

// Générer le PDF pour chaque participant
$success_count = 0;
$errors = [];

foreach ($participants as $participant) {
    try {
        echo "<!-- Debug: Génération pour {$participant['nom_complet']} -->";
        generateParticipantAttestation($event, $participant);
        $success_count++;
        echo "<!-- Debug: Succès pour {$participant['nom_complet']} -->";
    } catch (Exception $e) {
        $errors[] = "Erreur pour {$participant['nom_complet']}: " . $e->getMessage();
        echo "<!-- Debug: Erreur pour {$participant['nom_complet']}: " . $e->getMessage() . " -->";
    }
}

echo "<!-- Debug: Génération terminée. Succès: $success_count, Erreurs: " . count($errors) . " -->";

// Fonction pour générer l'attestation PDF d'un participant
function generateParticipantAttestation($event, $participant) {
    // Créer le contenu HTML de l'attestation
    $html = generateAttestationHTML($event, $participant);

    // Nom du fichier PDF
    $filename = 'attestation_' . $event['id'] . '_' . $participant['id'] . '.pdf';

    // Chemin de sauvegarde
    $filepath = 'attestations/' . $filename;

    // Créer le répertoire s'il n'existe pas
    if (!file_exists('attestations')) {
        mkdir('attestations', 0755, true);
    }

    // Générer le PDF avec FPDF
    generatePDF($event, $participant, $filepath);

    // Sauvegarder l'information dans la base de données
    saveAttestationRecord($event['id'], $participant['id'], $filename);
}

// Fonction pour générer le HTML de l'attestation
function generateAttestationHTML($event, $participant) {
    $date_evenement = date('d/m/Y', strtotime($event['date_debut']));
    $date_attestation = date('d/m/Y');

    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Attestation de Participation - EVENT2</title>
        <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Georgia', serif;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .attestation-container {
                background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
                max-width: 850px;
                width: 100%;
                box-shadow: 
                    0 0 40px rgba(212, 175, 55, 0.6),
                    0 20px 60px rgba(0, 0, 0, 0.4),
                    inset 0 0 30px rgba(212, 175, 55, 0.1);
                border: 8px solid #d4af37;
                border-radius: 20px;
                position: relative;
                padding: 60px 50px 40px;
                margin: 20px auto;
                overflow: hidden;
            }

            .attestation-container::before {
                content: '';
                position: absolute;
                top: -4px;
                left: -4px;
                right: -4px;
                bottom: -4px;
                background: linear-gradient(45deg, #d4af37, #f9d77e, #d4af37, #f9d77e);
                border-radius: 20px;
                z-index: -1;
                animation: shimmer 3s linear infinite;
            }

            @keyframes shimmer {
                0% { filter: hue-rotate(0deg) brightness(1); }
                50% { filter: hue-rotate(20deg) brightness(1.2); }
                100% { filter: hue-rotate(0deg) brightness(1); }
            }

            .attestation-header {
                text-align: center;
                margin-bottom: 40px;
                position: relative;
                padding-bottom: 25px;
                border-bottom: 4px solid #d4af37;
            }

            .attestation-icon {
                position: absolute;
                top: -40px;
                left: 50%;
                transform: translateX(-50%);
                width: 90px;
                height: 90px;
                background: linear-gradient(135deg, #d4af37, #f9d77e);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 45px;
                box-shadow: 
                    0 10px 30px rgba(212, 175, 55, 0.4),
                    inset 0 0 20px rgba(255, 255, 255, 0.3);
                border: 4px solid white;
            }

            .attestation-logo {
                font-size: 36px;
                font-weight: bold;
                background: linear-gradient(135deg, #d4af37, #f9d77e);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin: 25px 0 15px;
                text-shadow: 2px 2px 4px rgba(212, 175, 55, 0.3);
                letter-spacing: 3px;
            }

            .attestation-title {
                font-size: 28px;
                font-weight: bold;
                color: #1a1a2e;
                margin-bottom: 12px;
                letter-spacing: 3px;
                text-transform: uppercase;
            }

            .attestation-subtitle {
                font-size: 15px;
                color: #666;
                letter-spacing: 2px;
                font-style: italic;
            }

            .decorative-line {
                width: 120px;
                height: 4px;
                background: linear-gradient(90deg, transparent, #d4af37, transparent);
                margin: 20px auto;
                border-radius: 2px;
            }

            .attestation-content {
                line-height: 2;
                margin: 35px 0;
            }

            .attestation-intro {
                font-size: 18px;
                margin-bottom: 30px;
                text-align:left;
                color: #333;
                font-weight: 500;
            }

            .attestation-certify {
                font-size: 20px;
                font-weight: bold;
                margin: 35px 0;
                text-align: center;
                color: #d4af37;
                letter-spacing: 2px;
                text-transform: uppercase;
            }

            .participant-info {
                background: linear-gradient(135deg, rgba(212, 175, 55, 0.08), rgba(249, 215, 126, 0.08));
                border: 3px solid #d4af37;
                border-radius: 15px;
                padding: 30px;
                margin: 25px 0;
                box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2);
            }

            .info-item {
                margin: 15px 0;
                display: flex;
                align-items: center;
            }

            .info-label {
                font-weight: bold;
                color: #1a1a2e;
                font-size: 17px;
                min-width: 200px;
                display: inline-block;
            }

            .info-value {
                color: #333;
                font-size: 17px;
                background: white;
                padding: 10px 18px;
                border-radius: 8px;
                border-left: 4px solid #d4af37;
                flex: 1;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }

            .event-info {
                background: linear-gradient(135deg, #fff9e6, #ffefc1);
                border: 3px solid #d4af37;
                border-radius: 15px;
                padding: 30px;
                margin: 25px 0;
                box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2);
            }

            .event-info-title {
                font-size: 22px;
                font-weight: bold;
                color: #d4af37;
                text-align: center;
                margin-bottom: 25px;
                text-transform: uppercase;
                letter-spacing: 2px;
            }

            .attestation-conclusion {
                font-size: 17px;
                text-align: center;
                margin: 35px 0;
                color: #333;
                font-style: italic;
                padding: 20px;
                background: rgba(212, 175, 55, 0.05);
                border-radius: 10px;
            }

            .attestation-signature {
                margin-top: 60px;
                display: flex;
                justify-content: space-around;
                align-items: flex-end;
                padding: 30px 0;
            }

            .signature-block {
                text-align: center;
            }

            .signature-line {
                width: 250px;
                border-top: 3px solid #d4af37;
                margin: 50px auto 15px;
            }

            .signature-text {
                font-size: 15px;
                color: #666;
                margin: 8px 0;
            }

            .signature-name {
                font-weight: bold;
                color: #1a1a2e;
                font-size: 18px;
                margin-top: 10px;
            }

            .attestation-footer {
                margin-top: 50px;
                text-align: center;
                font-size: 13px;
                color: #999;
                border-top: 2px solid #d4af37;
                padding-top: 25px;
            }

            .watermark {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 100px;
                color: rgba(212, 175, 55, 0.04);
                font-weight: bold;
                z-index: 0;
                pointer-events: none;
                letter-spacing: 10px;
            }

            @media print {
                body {
                    background: white;
                    padding: 0;
                }

                .attestation-container {
                    box-shadow: none;
                    border: 6px solid #d4af37;
                    margin: 0;
                    padding: 50px 40px;
                }

                .attestation-container::before {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class='watermark'>EVENT2</div>
        <div class='attestation-container'>
            <div class='attestation-header'>
                <div class='attestation-icon'>
                    <i class='fas fa-award'></i>
                </div>
                <div class='attestation-logo'>EVENT2</div>
                <div class='decorative-line'></div>
                <div class='attestation-title'>Attestation de Participation</div>
                <div class='attestation-subtitle'>Plateforme de Gestion d'Événements</div>
            </div>

            <div class='attestation-content'>
                <p class='attestation-intro'>
                    Je soussigné, <strong>" . htmlspecialchars($event['organisateur_nom'], ENT_QUOTES, 'UTF-8') . "</strong>,
                    organisateur de l'événement, atteste par la présente que :
                </p>

                <div class='participant-info'>
                    <div class='info-item'>
                        <span class='info-label'>Nom complet :</span>
                        <span class='info-value'>" . htmlspecialchars($participant['nom_complet'], ENT_QUOTES, 'UTF-8') . "</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Adresse email :</span>
                        <span class='info-value'>" . htmlspecialchars($participant['email'], ENT_QUOTES, 'UTF-8') . "</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Date d'inscription :</span>
                        <span class='info-value'>" . date('d/m/Y', strtotime($participant['date_inscription'])) . "</span>
                    </div>
                </div>

                <p class='attestation-certify'>A Participé à l'Événement Suivant :</p>

                <div class='event-info'>
                    <div class='event-info-title'>Détails de l'Événement</div>
                    <div class='info-item'>
                        <span class='info-label'>Titre de l'événement :</span>
                        <span class='info-value'>" . htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') . "</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Date de l'événement :</span>
                        <span class='info-value'>" . $date_evenement . "</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Lieu :</span>
                        <span class='info-value'>" . htmlspecialchars($event['lieu'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') . "</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Organisateur :</span>
                        <span class='info-value'>" . htmlspecialchars($event['organisateur_nom'], ENT_QUOTES, 'UTF-8') . "</span>
                    </div>
                </div>

                <p class='attestation-conclusion'>
                    Cette attestation est délivrée à titre de preuve de participation active et assidue à l'événement susmentionné.
                </p>
            </div>

            <div class='attestation-signature'>
                <div class='signature-block'>
                    <p class='signature-text'>Fait à Casablanca</p>
                    <p class='signature-text'>Le " . $date_attestation . "</p>
                </div>
                <div class='signature-block'>
                    <p class='signature-text'>L'organisateur</p>
                    <div class='signature-line'></div>
                    <p class='signature-name'>" . htmlspecialchars($event['organisateur_nom'], ENT_QUOTES, 'UTF-8') . "</p>
                </div>
            </div>

            <div class='attestation-footer'>
                <p><strong>Document généré automatiquement par la plateforme EVENT2</strong></p>
                <p>www.event2.com • Système professionnel de gestion d'événements</p>
            </div>
        </div>
    </body>
    </html>";
}

// Fonction pour créer les fichiers de polices nécessaires
function createFontFiles() {
    $font_dir = 'font';

    // Créer le répertoire font s'il n'existe pas
    if (!file_exists($font_dir)) {
        mkdir($font_dir, 0755, true);
    }

    // Fichiers de polices de base nécessaires pour FPDF
    $font_files = [
        'helvetica.php' => "<?php \$type='Core'; \$name='Helvetica'; \$displayName='Helvetica'; \$cw=array(0=>278); ?>",
        'helveticab.php' => "<?php \$type='Core'; \$name='Helvetica-Bold'; \$displayName='Helvetica Bold'; \$cw=array(0=>278); ?>",
        'helveticai.php' => "<?php \$type='Core'; \$name='Helvetica-Oblique'; \$displayName='Helvetica Oblique'; \$cw=array(0=>278); ?>",
        'helveticabi.php' => "<?php \$type='Core'; \$name='Helvetica-BoldOblique'; \$displayName='Helvetica Bold Oblique'; \$cw=array(0=>278); ?>",
        'courier.php' => "<?php \$type='Core'; \$name='Courier'; \$displayName='Courier'; \$cw=array(0=>600); ?>",
        'courierb.php' => "<?php \$type='Core'; \$name='Courier-Bold'; \$displayName='Courier Bold'; \$cw=array(0=>600); ?>",
        'courieri.php' => "<?php \$type='Core'; \$name='Courier-Oblique'; \$displayName='Courier Oblique'; \$cw=array(0=>600); ?>",
        'courierbi.php' => "<?php \$type='Core'; \$name='Courier-BoldOblique'; \$displayName='Courier Bold Oblique'; \$cw=array(0=>600); ?>"
    ];

    foreach ($font_files as $filename => $content) {
        $filepath = $font_dir . '/' . $filename;
        if (!file_exists($filepath)) {
            file_put_contents($filepath, $content);
            echo "<!-- Debug: Créé $filepath -->";
        }
    }
}

// Fonction pour générer le PDF avec FPDF
function generatePDF($event, $participant, $filepath) {
    echo "<!-- Debug: Début génération PDF pour {$participant['nom_complet']} -->";

    try {
        // Créer les fichiers de polices s'ils n'existent pas
        createFontFiles();

        require_once 'fpdf.php';
        echo "<!-- Debug: FPDF chargé avec succès -->";

        // Créer une nouvelle instance PDF
        $pdf = new FPDF();
        echo "<!-- Debug: Instance PDF créée -->";
        $pdf->AddPage();

        // Configuration de l'encodage UTF-8 pour FPDF
        $pdf->SetTextColor(212, 175, 55); // Doré
        $pdf->SetFont('Arial', 'B', 24);

        // En-tête avec icône stylisée
        $pdf->Cell(0, 15, utf8_decode('EVENT2'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 10, utf8_decode('ATTESTATION DE PARTICIPATION'), 0, 1, 'C');
        $pdf->Ln(10);

        // Informations de l'organisateur
        $pdf->SetFont('Arial', '', 12);
        $pdf->MultiCell(0, 8, utf8_decode('Je soussigné, ' . $event['organisateur_nom'] . ', organisateur de l\'événement, atteste par la présente que :'), 0, 'C');
        $pdf->Ln(8);

        // Informations du participant avec cadre stylé
        $pdf->SetFillColor(248, 249, 250);
        $pdf->Rect(20, $pdf->GetY(), 170, 30, 'F');

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Nom complet :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode($participant['nom_complet']), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Adresse email :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode($participant['email']), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Date d\'inscription :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, date('d/m/Y', strtotime($participant['date_inscription'])), 0, 1);
        $pdf->Ln(8);

        // Titre de section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(212, 175, 55);
        $pdf->Cell(0, 10, utf8_decode('A PARTICIPÉ À L\'ÉVÉNEMENT SUIVANT :'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // Informations de l'événement avec cadre stylé
        $pdf->SetFillColor(255, 249, 230);
        $pdf->Rect(20, $pdf->GetY(), 170, 40, 'F');

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Titre de l\'événement :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode($event['titre']), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Date de l\'événement :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, date('d/m/Y', strtotime($event['date_debut'])), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Lieu :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode($event['lieu'] ?? 'Non spécifié'), 0, 1);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(60, 8, utf8_decode('Organisateur :'), 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode($event['organisateur_nom']), 0, 1);
        $pdf->Ln(12);

        // Conclusion stylisée
        $pdf->SetFont('Arial', 'I', 11);
        $pdf->MultiCell(0, 8, utf8_decode('Cette attestation est délivrée à titre de preuve de participation active et assidue à l\'événement susmentionné.'), 0, 'C');
        $pdf->Ln(15);

        // Signature avec style amélioré - centrée à la fin
        $pdf->Ln(20);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 8, utf8_decode('Fait à Casablanca, le ') . date('d/m/Y'), 0, 1, 'C');
        $pdf->Cell(0, 8, utf8_decode('L\'organisateur'), 0, 1, 'C');
        $pdf->Ln(20);

        // Ligne de signature stylisée centrée avec bordure dorée
        $pdf->SetDrawColor(212, 175, 55);
        $pdf->SetLineWidth(0.8);
        $pdf->Cell(60); // Centrage
        $pdf->Cell(70, 1, '', 1, 1, 'C');
        $pdf->Cell(60); // Centrage
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(70, 8, utf8_decode($event['organisateur_nom']), 0, 1, 'C');

        // Pied de page stylisé
        $pdf->Ln(25);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, utf8_decode('Document généré automatiquement par la plateforme EVENT2'), 0, 1, 'C');
        $pdf->Cell(0, 5, utf8_decode('www.event2.com - Système professionnel de gestion d\'événements'), 0, 1, 'C');

        // Sauvegarder le PDF
        $pdf->Output($filepath, 'F');
        echo "<!-- Debug: PDF sauvegardé à $filepath -->";

        if (file_exists($filepath)) {
            echo "<!-- Debug: Fichier PDF créé avec succès, taille: " . filesize($filepath) . " bytes -->";
        } else {
            echo "<!-- Debug: ERREUR - Fichier PDF non créé -->";
        }

    } catch (Exception $e) {
        echo "<!-- Debug: ERREUR génération PDF: " . $e->getMessage() . " -->";
        throw $e;
    }
}

// Fonction pour sauvegarder l'enregistrement de l'attestation
function saveAttestationRecord($event_id, $participant_id, $filename) {
    $db = Database::getInstance();

    try {
        // Récupérer l'ID de l'inscription pour cette participation
        $stmt = $db->prepare("
            SELECT id FROM inscriptions
            WHERE evenement_id = ? AND utilisateur_id = ?
        ");
        $stmt->execute([$event_id, $participant_id]);
        $inscription = $stmt->fetch();

        if ($inscription) {
            $inscription_id = $inscription['id'];
            $numero_unique = 'ATT-' . $event_id . '-' . $participant_id . '-' . time();

            $stmt = $db->prepare("
                INSERT INTO attestations (inscription_id, numero_unique, chemin_fichier_pdf, date_generation, statut_envoi)
                VALUES (?, ?, ?, NOW(), 'en_attente')
                ON DUPLICATE KEY UPDATE chemin_fichier_pdf = ?, date_generation = NOW(), statut_envoi = 'en_attente'
            ");

            $stmt->execute([$inscription_id, $numero_unique, $filename, $filename]);
        }

    } catch (PDOException $e) {
        // Erreur de sauvegarde, mais ne pas interrompre la génération
        error_log("Erreur sauvegarde attestation: " . $e->getMessage());
    }
}

// Afficher le résultat
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération des Attestations - EVENT2</title>
    <link href="https://cdn.jsdelivr.net/npm/lucide@0.263.1/dist/umd/lucide.js" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            box-shadow: 
                0 0 40px rgba(212, 175, 55, 0.6),
                0 20px 60px rgba(0, 0, 0, 0.4),
                inset 0 0 30px rgba(212, 175, 55, 0.1);
            border: 8px solid #d4af37;
            padding: 50px;
            max-width: 700px;
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            background: linear-gradient(45deg, #d4af37, #f9d77e, #d4af37, #f9d77e);
            border-radius: 20px;
            z-index: -1;
            animation: rotate 4s linear infinite;
        }

        @keyframes rotate {
            0% { filter: hue-rotate(0deg) brightness(1); }
            50% { filter: hue-rotate(20deg) brightness(1.2); }
            100% { filter: hue-rotate(0deg) brightness(1); }
        }

        .success-icon, .error-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: 5px solid white;
        }

        .success-icon {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .error-icon {
            background: linear-gradient(135deg, #dc3545, #e74c3c);
        }

        h1 {
            color: #1a1a2e;
            margin-bottom: 15px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.2em;
        }

        .decorative-line {
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
            margin: 25px auto;
            border-radius: 2px;
        }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 35px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(249, 215, 126, 0.1));
            padding: 25px;
            border-radius: 15px;
            border: 3px solid #d4af37;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3em;
            font-weight: bold;
            background: linear-gradient(135deg, #d4af37, #f9d77e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: #666;
            font-size: 1em;
            margin-top: 10px;
            font-weight: 600;
        }

        .event-info {
            background: linear-gradient(135deg, #fff9e6, #ffefc1);
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            text-align: left;
            border: 3px solid #d4af37;
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2);
        }

        .event-title {
            font-weight: bold;
            color: #d4af37;
            margin-bottom: 15px;
            font-size: 1.3em;
            text-align: center;
        }

        .event-info p {
            margin: 10px 0;
            color: #333;
            font-size: 1em;
        }

        .errors {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border: 3px solid #dc3545;
            color: #721c24;
            padding: 20px;
            border-radius: 15px;
            margin-top: 30px;
            text-align: left;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.2);
        }

        .errors h4 {
            margin-bottom: 15px;
            color: #721c24;
        }

        .errors ul {
            margin: 0;
            padding-left: 25px;
        }

        .errors li {
            margin: 8px 0;
        }

        .actions {
            margin-top: 35px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 1em;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #545b62);
            color: white;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #545b62, #3d4449);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px;
            }

            h1 {
                font-size: 2em;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success_count > 0): ?>
            <div class="success-icon">
                <i data-lucide="award"></i>
            </div>
            <h1>Génération Réussie !</h1>
            <div class="decorative-line"></div>
            <p class="subtitle">Les attestations ont été générées avec succès</p>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $success_count ?></div>
                    <div class="stat-label">Attestations Générées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $event_id ?></div>
                    <div class="stat-label">ID Événement</div>
                </div>
            </div>

            <div class="event-info">
                <div class="event-title">📋 <?= htmlspecialchars($event['titre'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="decorative-line"></div>
                <p><strong>📌 Organisateur :</strong> <?= htmlspecialchars($event['organisateur_nom'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>📅 Date :</strong> <?= date('d/m/Y', strtotime($event['date_debut'])) ?></p>
                <p><strong>📍 Lieu :</strong> <?= htmlspecialchars($event['lieu'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <h4>⚠️ Erreurs Rencontrées :</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="decorative-line"></div>

            <div class="actions">
                <a href="organisateur/dashboard.php" class="btn btn-primary">
                    <i data-lucide="arrow-left"></i>
                    Retour au Dashboard
                </a>
            </div>

            <?php if ($success_count > 0): ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: #e8f5e8; border-radius: 10px; border: 1px solid #c3e6c3;">
                <h3 style="color: #2d5016; margin-bottom: 1rem;">📁 Fichiers générés :</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <?php
                    foreach ($participants as $participant) {
                        $filename = 'attestation_' . $event['id'] . '_' . $participant['id'] . '.pdf';
                        $filepath = 'attestations/' . $filename;
                        if (file_exists($filepath)) {
                            echo "<div style='background: white; padding: 1rem; border-radius: 8px; border: 1px solid #ddd; text-align: center;'>";
                            echo "<i data-lucide='file-text' style='font-size: 2rem; color: #dc3545; margin-bottom: 0.5rem;'></i>";
                            echo "<div style='font-weight: bold; margin-bottom: 0.5rem;'>Pour: " . htmlspecialchars($participant['nom_complet']) . "</div>";
                            echo "<a href='$filepath' target='_blank' style='display: inline-block; padding: 8px 16px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-size: 14px;'>";
                            echo "<i data-lucide='download'></i> Télécharger PDF";
                            echo "</a>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="error-icon">
                <i data-lucide="x-circle"></i>
            </div>
            <h1>Erreur de Génération</h1>
            <div class="decorative-line"></div>
            <p class="subtitle">Aucune attestation n'a pu être générée</p>

            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <h4>Détails des Erreurs :</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="decorative-line"></div>

            <div class="actions">
                <a href="organisateur/dashboard.php" class="btn btn-primary">
                    <i data-lucide="arrow-left"></i>
                    Retour au Dashboard
                </a>
                <a href="debug_event_status.php" class="btn btn-secondary">
                    <i data-lucide="bug"></i>
                    Diagnostic
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });
    </script>
</body>
</html>