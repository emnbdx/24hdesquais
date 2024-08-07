<?php
$file = 'data.json';
$data = json_decode(file_get_contents($file), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time']) && !empty($_POST['name'])) {
    $time = $_POST['time'];
    $name = strip_tags($_POST['name']);

    // Vérifie si le prénom existe déjà pour ce créneau
    if (!in_array($name, $data[$time] ?? [])) {
        $data[$time][] = $name;
        file_put_contents($file, json_encode($data));
    }

    // Redirection pour éviter les doublons de soumission
    header("Location: https://24hdesquais.fr");
    exit;
}

$fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE);
$fmt->setPattern('EEEE HH:mm');

// Utilisation de la fonction ucfirst pour mettre en majuscule la première lettre du jour
function capitalizeFirstLetter($dateStr)
{
    return mb_convert_case($dateStr, MB_CASE_TITLE, "UTF-8");
}

// Création des créneaux horaires
$times = [];
$start = new DateTime('2023-10-20 16:00:00');
$end = new DateTime('2023-10-21 15:00:00');

while ($start <= $end) {
    $timeString = $start->format('Y-m-d H:i:s');
    $timeRange = capitalizeFirstLetter($fmt->format($start->getTimestamp())) . ' - ' . $start->modify('+1 hour')->format('H:i');
    $times[$timeString] = $timeRange;
    $start->modify('-1 hour')->modify('+1 hour'); // Reset to start of the hour before moving to next
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Défi 24h</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0F1928;
            color: #ffffff;
        }

        .container {
            max-width: 800px;
            margin-top: 20px;
        }

        img.logo {
            max-width: 200px !important;
            /* Taille maximale du logo */
            display: block;
            /* Centrer le logo */
            margin: 0 auto;
        }

        .card {
            color: #212529;
        }

        .payment {
            text-align: center;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <img src="logo.jpeg" alt="Logo du Défi" class="logo img-fluid mb-3">

        <div class="my-4">
            <a href="#infos" class="btn btn-info">Informations</a>
            <a href="#register" class="btn btn-info">Inscriptions</a>
        </div>

        <div id="infos">
            <p class="lead">
                🏃‍♂️ Le 19 & 20 juillet 2024, je me lance le défi de courir 24 heures, inspiré par les règles des <a
                    href="https://fr.wikipedia.org/wiki/Backyard_ultra" target="_blank"
                    style="color: #FFFFFF; text-decoration: underline;">Backyard Ultra</a> (même si j'ai eu l'idée
                before it was cool).
                <br /><br />
                Chaque heure, je prévois de faire un tour des quais de Bordeaux, soit une boucle de 6,8 km.
                <br />
                ▶️ vendredi à 16h
                <br />
                📍 Quai des Queyries rive droite devant le parking de la Belle Saison (van volksagen blanc)
                <img src="parking.jpeg" alt="Lieu de départ" class="img-fluid my-2" />
                <br />
                ⏹️ 24h plus tard !
                <br /><br />
                L'objectif est de me prouver que je peux relever ce challenge sportif extrême.
                <br /><br />
                🌟 Mais je ne m'arrête pas là ! J'envisage de transformer cette performance personnelle en un événement
                sportif et festif pour soutenir une association, car j'aime donner du sens à mes actions et partager
                cette énergie pour une bonne cause.
                <br/>
                Stay tuned pour la suite de cette histoire ...  
                <br /><br />
                🎉 Rejoignez-moi dans cette aventure en réservant des créneaux pour courir et célébrer la
                solidarité et l'endurance humaine !
            </p>
        </div>

        <div id="register">
            <h1 class="mb-3">Inscription pour le Défi de 24 heures</h1>
            <?php foreach ($times as $time => $timeRange): ?>
                <div class="card mb-2">
                    <div class="card-body">
                        <h5 class="card-title"><?= $timeRange ?></h5>
                        <?php if (isset($data[$time])): ?>
                            <ul class="my-4">
                                <?php foreach ($data[$time] as $registeredName): ?>
                                    <li><?= htmlspecialchars($registeredName) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <form action="" method="post">
                            <input type="hidden" name="time" value="<?= $time ?>">
                            <div class="mb-3">
                                <label for="name-<?= $time ?>" class="form-label">Prénom:</label>
                                <input type="text" class="form-control" id="name-<?= $time ?>" name="name" required>
                            </div>
                            <button type="submit" class="btn btn-primary">S'inscrire</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="payment">
            Fait lors d'un running par <a href="https://github.com/emnbdx" target="blank">Eddy</a>. Pour le soutien: <a
                href="https://www.buymeacoffee.com/emnbdx" target="blank">🍻</a> ou <a
                href="https://paypal.me/EddyMontus?country.x=FR&amp;locale.x=en_US" target="blank">Paypal</a>
        </div>
    </div>
</body>

</html>