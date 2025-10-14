<?php
echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Dossier des Attestations - EVENT2</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            max-width: 800px;
            width: 100%;
            text-align: center;
        }
        .attestation-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2.5rem;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        .subtitle {
            color: #666;
            margin-bottom: 2rem;
        }
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .file-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s;
        }
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 2rem;
            color: #007bff;
            margin-bottom: 1rem;
        }
        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .file-type {
            color: #666;
            font-size: 0.9rem;
        }
        .actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
            transform: translateY(-2px);
        }
    </style>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container'>
        <div class='attestation-icon'>
            <i class='fas fa-folder-open'></i>
        </div>
        <h1>Dossier des Attestations</h1>
        <p class='subtitle'>Gérez vos attestations de participation générées</p>";

$files = scandir('.');
$attestationFiles = array_filter($files, function($file) {
    return !in_array($file, ['.', '..', 'index.php']) &&
           (pathinfo($file, PATHINFO_EXTENSION) === 'pdf' ||
            pathinfo($file, PATHINFO_EXTENSION) === 'html');
});

if (empty($attestationFiles)) {
    echo "<p style='color: #666; margin: 2rem 0;'>Aucun fichier d'attestation trouvé.</p>";
} else {
    echo "<div class='files-grid'>";
    foreach ($attestationFiles as $file) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $icon = $extension === 'pdf' ? 'fas fa-file-pdf' : 'fas fa-file-code';
        $type = $extension === 'pdf' ? 'PDF' : 'HTML';
        $color = $extension === 'pdf' ? '#dc3545' : '#007bff';

        echo "<a href='$file' target='_blank' style='text-decoration: none; color: inherit;'>";
        echo "<div class='file-card'>";
        echo "<div class='file-icon' style='color: $color;'>";
        echo "<i class='$icon'></i>";
        echo "</div>";
        echo "<div class='file-name'>" . htmlspecialchars($file) . "</div>";
        echo "<div class='file-type'>Format: $type</div>";
        echo "<div style='margin-top: 10px;'>";
        echo "<button style='padding: 8px 16px; background: $color; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;'>";
        echo "📂 Voir le fichier";
        echo "</button>";
        echo "</div>";
        echo "</div>";
        echo "</a>";
    }
    echo "</div>";
}

echo "
        <div class='actions'>
            <a href='../organisateur/dashboard.php' class='btn btn-primary'>
                <i class='fas fa-arrow-left'></i>
                Retour au Dashboard
            </a>
            <a href='../generate_attestation.php?event_id=3' class='btn btn-secondary'>
                <i class='fas fa-plus'></i>
                Générer de nouvelles attestations
            </a>
        </div>
    </div>
</body>
</html>";
?>