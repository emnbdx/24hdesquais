<?php
$file = 'data.json';
$data = json_decode(file_get_contents($file), true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time']) && !empty($_POST['name'])) {
    header('Content-Type: application/json');

    $time = $_POST['time'];
    $name = strip_tags($_POST['name']);

    if (in_array($name, $data[$time] ?? [])) {
        echo json_encode(['success' => false, 'message' => 'Vous êtes déjà inscrit à ce créneau']);
        exit;
    }

    $data[$time][] = $name;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'message' => "Inscription confirmée ! 🎉", 'participants' => $data[$time]]);
    exit;
}

$fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE);
$fmt->setPattern('EEEE HH:mm');

function capitalizeFirstLetter($dateStr)
{
    return mb_convert_case($dateStr, MB_CASE_TITLE, "UTF-8");
}

$times = [];
$start = new DateTime('2026-05-01 18:00:00');
$end = new DateTime('2026-05-02 17:59:59');

while ($start <= $end) {
    $timeString = $start->format('Y-m-d H:i:s');
    $timeRange = capitalizeFirstLetter($fmt->format($start->getTimestamp())) . ' - ' . $start->modify('+1 hour')->format('H:i');
    $times[$timeString] = $timeRange;
    $start->modify('-1 hour')->modify('+1 hour');
}

$startHour = 18;
$timeSlots = [];
for ($i = 0; $i < 24; $i++) {
    $hour = ($startHour + $i) % 24;
    $day = $i < 6 ? "Vendredi" : "Samedi";
    $nextHour = ($hour + 1) % 24;
    $timeSlots[] = [
        'id' => $i + 1,
        'day' => $day,
        'time' => str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00 - ' . str_pad($nextHour, 2, '0', STR_PAD_LEFT) . ':00'
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>24h des Quais de Bordeaux</title>
    <style>
        :root {
            --background: hsl(220, 30%, 8%);
            --foreground: hsl(180, 100%, 95%);
            --card: hsl(220, 25%, 12%);
            --card-foreground: hsl(180, 100%, 95%);
            --primary: hsl(185, 85%, 55%);
            --primary-foreground: hsl(220, 30%, 8%);
            --secondary: hsl(25, 95%, 60%);
            --secondary-foreground: hsl(220, 30%, 8%);
            --muted: hsl(220, 20%, 20%);
            --muted-foreground: hsl(180, 15%, 65%);
            --accent: hsl(45, 100%, 55%);
            --accent-foreground: hsl(220, 30%, 8%);
            --border: hsl(220, 20%, 25%);
            --radius: 0.75rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--background);
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 20px hsla(185, 85%, 55%, 0.4);
            }

            50% {
                box-shadow: 0 0 40px hsla(185, 85%, 55%, 0.8);
            }
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.6s ease-out;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none !important;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-hero {
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            color: var(--primary-foreground);
            font-weight: bold;
        }

        .btn-hero:hover {
            box-shadow: 0 0 40px hsla(185, 85%, 55%, 0.3);
        }

        .btn-outline {
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--foreground);
        }

        .btn-outline:hover {
            background: var(--muted);
        }

        .btn-register {
            background: linear-gradient(135deg, var(--secondary), hsl(15, 90%, 55%));
            color: var(--secondary-foreground);
            font-weight: 600;
            width: 100%;
            padding: 0.75rem;
        }

        .btn-register:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
        }

        .card:hover {
            box-shadow: 0 0 40px hsla(185, 85%, 55%, 0.3);
            transform: translateY(-2px);
        }

        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background-image: url('hero-bordeaux.jpg');
            background-size: cover;
            background-position: center;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(15, 25, 40, 0.8) 0%, rgba(15, 25, 40, 0.6) 50%, var(--background) 100%);
        }

        .hero-content {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero-title {
            font-size: clamp(4rem, 12vw, 9rem);
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: clamp(2rem, 6vw, 6rem);
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .hero-description {
            font-size: clamp(1.5rem, 3vw, 3rem);
            color: var(--muted-foreground);
            font-weight: 300;
        }

        .hero-text {
            font-size: clamp(1.25rem, 2vw, 2rem);
            margin-bottom: 2rem;
        }

        .hero-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 1.125rem;
            color: var(--muted-foreground);
            margin-bottom: 3rem;
        }

        .hero-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
            margin-bottom: 3rem;
        }

        @media (min-width: 640px) {
            .hero-actions {
                flex-direction: row;
                justify-content: center;
            }
        }

        .scroll-indicator {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            animation: bounce 2s infinite;
        }

        .section {
            padding: 5rem 1rem;
        }

        .section-title {
            font-size: clamp(2.5rem, 5vw, 6rem);
            font-weight: bold;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .section-subtitle {
            font-size: clamp(1.25rem, 2vw, 1.5rem);
            text-align: center;
            color: var(--muted-foreground);
            max-width: 48rem;
            margin: 0 auto 4rem;
        }

        .gradient-primary {
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-secondary {
            background: linear-gradient(135deg, var(--secondary), hsl(15, 90%, 55%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-accent {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-bg-card {
            background: linear-gradient(135deg, var(--card), var(--muted));
        }

        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        @media (min-width: 768px) {
            .grid-2 {
                grid-template-columns: repeat(2, 1fr);
            }

            .grid-3 {
                grid-template-columns: repeat(3, 1fr);
            }

            .grid-4 {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .feature-card {
            text-align: center;
        }

        .feature-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem;
            color: var(--primary);
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .feature-desc {
            color: var(--muted-foreground);
        }

        .input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--background);
            color: var(--foreground);
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }

        .input:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .slot-card {
            position: relative;
        }

        .slot-number {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: var(--primary-foreground);
            margin-right: 0.75rem;
        }

        .slot-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .slot-day {
            font-weight: bold;
        }

        .slot-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--muted-foreground);
        }

        .icon {
            width: 1rem;
            height: 1rem;
        }

        .participants-list {
            margin: 1rem 0;
        }

        .participants-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--muted-foreground);
            margin-bottom: 0.5rem;
        }

        .participants-list ul {
            list-style: none;
        }

        .participants-list li {
            font-size: 0.875rem;
            padding: 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .participants-list li::before {
            content: "•";
            color: var(--primary);
        }

        .footer {
            background: var(--card);
            border-top: 1px solid var(--border);
            padding: 3rem 1rem;
        }

        .footer-grid {
            display: grid;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 768px) {
            .footer-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .footer-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-section h4 {
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section li {
            padding: 0.5rem 0;
            color: var(--muted-foreground);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            color: var(--muted-foreground);
        }

        .icon-wrapper {
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            color: var(--primary-foreground);
        }

        a {
            color: var(--primary);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            animation: slide-in 0.3s ease-out;
            min-width: 300px;
            max-width: 400px;
        }

        .toast-success {
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            color: var(--primary-foreground);
        }

        .toast-error {
            background: hsl(0, 70%, 50%);
            color: white;
        }

        @keyframes slide-in {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slide-out {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .btn-register:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>

    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="hero-content animate-fade-in">
            <h1 class="hero-title">24h</h1>
            <div class="hero-subtitle">= 24 TOURS</div>
            <p class="hero-description">des quais de bordeaux</p>
            <p class="hero-text">🏃‍♂️ Un défi sportif extrême inspiré des <strong style="color: var(--primary);">Backyard Ultra</strong></p>

            <div class="hero-actions">
                <a href="#inscriptions" class="btn btn-hero" style="animation: pulse-glow 2s ease-in-out infinite;">S'inscrire maintenant</a>
                <a href="#concept" class="btn btn-outline">En savoir plus</a>
            </div>

            <div class="hero-info">
                <div>▶️ 1 mai 2026 à 18h</div>
                <div>📍 Quai des Queyries, Rive Droite</div>
                <div>⏹️ 24h plus tard !</div>
            </div>

            <div class="scroll-indicator">↓</div>
        </div>
    </section>

    <section id="concept" class="section">
        <div class="container">
            <h2 class="section-title gradient-accent">Le Concept</h2>
            <p class="section-subtitle">
                Un événement sportif et festif unique qui célèbre l'endurance,
                la solidarité et l'esprit humain au cœur de Bordeaux.
            </p>

            <div class="grid grid-4" style="margin-bottom: 4rem;">
                <div class="card feature-card">
                    <div class="icon-wrapper">🕐</div>
                    <h3 class="feature-title">24 Heures</h3>
                    <p class="feature-desc">Un tour chaque heure pendant 24 heures consécutives</p>
                </div>
                <div class="card feature-card">
                    <div class="icon-wrapper">📍</div>
                    <h3 class="feature-title">6,8 km</h3>
                    <p class="feature-desc">Une boucle complète des quais de Bordeaux à chaque tour</p>
                </div>
                <div class="card feature-card">
                    <div class="icon-wrapper">🏆</div>
                    <h3 class="feature-title">Défi Extrême</h3>
                    <p class="feature-desc">Inspiré des règles des Backyard Ultra</p>
                </div>
                <div class="card feature-card">
                    <div class="icon-wrapper">👥</div>
                    <h3 class="feature-title">Solidarité</h3>
                    <p class="feature-desc">Rejoignez le défi sur n'importe quel créneau</p>
                </div>
            </div>

            <div style="max-width: 56rem; margin: 0 auto;">
                <div class="card gradient-bg-card">
                    <h3 style="font-size: 2rem; font-weight: bold; margin-bottom: 1.5rem; color: var(--primary);">L'Objectif</h3>
                    <div style="font-size: 1.125rem; line-height: 1.8;">
                        <p style="margin-bottom: 1rem;">
                            🌟 Ce défi n'est pas qu'une performance personnelle, c'est un événement
                            qui rassemble une communauté autour de valeurs fortes.
                        </p>
                        <p style="margin-bottom: 1rem;">
                            🎉 Rejoignez cette aventure en réservant un créneau pour courir et
                            célébrer ensemble la solidarité et l'endurance humaine !
                        </p>
                        <p style="color: var(--accent); font-weight: 600;">
                            Stay tuned pour la suite de cette histoire...
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="parcours" class="section" style="background: var(--background);">
        <div class="container">
            <h2 class="section-title gradient-secondary">Le Parcours</h2>
            <p class="section-subtitle">
                Une boucle magique au cœur de Bordeaux
            </p>

            <div class="grid grid-3" style="margin-bottom: 3rem;">
                <div class="card" style="text-align: center;">
                    <div class="icon-wrapper">🛤️</div>
                    <h3 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;">6,8 km</h3>
                    <p style="color: var(--muted-foreground);">Distance par tour</p>
                </div>
                <div class="card" style="text-align: center;">
                    <div class="icon-wrapper">🧭</div>
                    <h3 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;">24 Tours</h3>
                    <p style="color: var(--muted-foreground);">163,2 km au total</p>
                </div>
                <div class="card" style="text-align: center;">
                    <div class="icon-wrapper">📍</div>
                    <h3 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;">Les Quais</h3>
                    <p style="color: var(--muted-foreground);">Bordeaux Rive Droite</p>
                </div>
            </div>

            <div style="max-width: 56rem; margin: 0 auto;">
                <div class="card gradient-bg-card">
                    <h3 style="font-size: 2rem; font-weight: bold; margin-bottom: 1.5rem; color: var(--primary);">Point de Départ & Arrivée</h3>
                    <div style="font-size: 1.125rem; line-height: 2;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem;">📍</span>
                            <div>
                                <p style="font-weight: 600;">Quai des Queyries, Rive Droite</p>
                                <p style="color: var(--muted-foreground);">Devant le parking de la Belle Saison (van Volkswagen blanc)</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem;">🕐</span>
                            <div>
                                <p style="font-weight: 600;">Départ : 1 mai 2026 à 18h</p>
                                <p style="color: var(--muted-foreground);">Un nouveau tour démarre chaque heure pile</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                            <span style="font-size: 1.5rem;">🎯</span>
                            <div>
                                <p style="font-weight: 600;">Les Règles Backyard</p>
                                <p style="color: var(--muted-foreground);">Chaque participant doit terminer son tour avant le début du suivant</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="inscriptions" class="section gradient-bg-card">
        <div class="container" style="max-width: 1200px;">
            <h2 class="section-title gradient-primary">Inscriptions</h2>
            <p class="section-subtitle">
                Choisissez votre créneau et rejoignez l'aventure !
                Vous pouvez courir un ou plusieurs tours.
            </p>

            <div class="grid grid-3">
                <?php foreach ($timeSlots as $index => $slot): ?>
                    <?php
                    $hour = ($startHour + $index) % 24;
                    $day = $index < 6 ? '2026-05-01' : '2026-05-02';
                    $timeString = $day . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00:00';
                    $participants = $data[$timeString] ?? [];
                    ?>
                    <div class="card slot-card">
                        <div class="slot-header">
                            <div class="slot-number"><?= $slot['id'] ?></div>
                            <div>
                                <h3 class="slot-day"><?= $slot['day'] ?></h3>
                                <div class="slot-time">
                                    <span>🕐</span>
                                    <span><?= $slot['time'] ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="participants-list" id="participants-<?= $slot['id'] ?>">
                            <?php if (!empty($participants)): ?>
                                <p class="participants-title">Participants :</p>
                                <ul>
                                    <?php foreach ($participants as $participant): ?>
                                        <li><?= htmlspecialchars($participant) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <form class="registration-form" data-time="<?= $timeString ?>" data-slot-id="<?= $slot['id'] ?>">
                            <input type="text" class="input" name="name" placeholder="Votre prénom" required>
                            <button type="submit" class="btn btn-register">S'inscrire</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h3 class="footer-title">24h = 24 Tours</h3>
                    <p style="color: var(--muted-foreground);">Des Quais de Bordeaux</p>
                </div>
                <div class="footer-section">
                    <h4>Informations Pratiques</h4>
                    <ul>
                        <li>📅 1-2 Mai 2026</li>
                        <li>📍 Quai des Queyries</li>
                        <li>🏃‍♂️ 6,8 km par tour</li>
                        <li>⏰ 24 heures non-stop</li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p style="color: var(--muted-foreground);">
                        Pour toute question ou information supplémentaire,
                        venez nous voir sur place ou rejoignez-nous directement !
                    </p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2024 24h des Quais de Bordeaux. Un défi humain exceptionnel.</p>
            </div>
        </div>
    </footer>

    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slide-out 0.3s ease-out';
                setTimeout(() => {
                    container.removeChild(toast);
                }, 300);
            }, 3000);
        }

        function updateParticipantsList(slotId, participants) {
            const container = document.getElementById(`participants-${slotId}`);

            if (participants.length === 0) {
                container.innerHTML = '';
                return;
            }

            const ul = document.createElement('ul');
            participants.forEach(name => {
                const li = document.createElement('li');
                li.textContent = name;
                ul.appendChild(li);
            });

            container.innerHTML = '<p class="participants-title">Participants :</p>';
            container.appendChild(ul);
        }

        document.querySelectorAll('.registration-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(form);
                const time = form.dataset.time;
                const slotId = form.dataset.slotId;
                const button = form.querySelector('button');
                const input = form.querySelector('input[name="name"]');

                formData.append('time', time);

                const originalButtonText = button.textContent;
                button.disabled = true;
                button.textContent = 'Inscription...';

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showToast(data.message, 'success');
                        input.value = '';
                        updateParticipantsList(slotId, data.participants);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Erreur lors de l\'inscription. Veuillez réessayer.', 'error');
                } finally {
                    button.disabled = false;
                    button.textContent = originalButtonText;
                }
            });
        });
    </script>
</body>

</html>