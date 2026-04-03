<?php
require_once __DIR__ . '/vendor/autoload.php';

use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$file = 'data.json';
$data = json_decode(file_get_contents($file), true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'contact') {
        $name = strip_tags($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $message = strip_tags($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Merci de remplir tous les champs correctement.']);
            exit;
        }

        $brevoApiKey = $_ENV['BREVO_API_KEY'];

        if (empty($brevoApiKey)) {
            echo json_encode(['success' => false, 'message' => 'Configuration email non disponible.']);
            exit;
        }

        try {
            $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $brevoApiKey);
            $apiInstance = new TransactionalEmailsApi(new \GuzzleHttp\Client(), $config);

            $data = [
                'subject' => 'Contact - 24h des Quais de Bordeaux',
                'sender' => [
                    'name' => '24h des Quais de Bordeaux',
                    'email' => 'contact@24hdesquais.fr'
                ],
                'to' => [
                    [
                        'email' => 'eddy.montus@gmail.com'
                    ]
                ],
                'htmlContent' => '<p><strong>Nom:</strong> ' . htmlspecialchars($name) . '</p>' .
                    '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>' .
                    '<p><strong>Message:</strong></p>' .
                    '<p>' . nl2br(htmlspecialchars($message)) . '</p>',
                'replyTo' => [
                    'email' => $email,
                    'name' => $name
                ]
            ];

            $result = $apiInstance->sendTransacEmail($data);
            echo json_encode(['success' => true, 'message' => 'Message envoyé avec succès !']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi du message.']);
        }
        exit;
    }

    if (isset($_POST['time']) && !empty($_POST['name'])) {
        $time = $_POST['time'];
        $name = strip_tags($_POST['name']);

        if (in_array($name, $data[$time] ?? [])) {
            echo json_encode(['success' => false, 'message' => 'Tu es déjà inscrit·e à ce créneau']);
            exit;
        }

        $data[$time][] = $name;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => "Inscription confirmée ! 🎉", 'participants' => $data[$time]]);
        exit;
    }
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

        .popup-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fade-in 0.3s ease-out;
        }

        .popup-overlay.active {
            display: flex;
        }

        .popup-content {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: fade-in 0.3s ease-out;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .popup-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: var(--foreground);
            font-size: 1.5rem;
            cursor: pointer;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .popup-close:hover {
            background: var(--muted);
        }

        .popup-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .contact-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--background);
            color: var(--foreground);
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
        }

        .contact-form textarea:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), hsl(200, 80%, 45%));
            color: var(--primary-foreground);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            box-shadow: 0 0 20px hsla(185, 85%, 55%, 0.4);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .wa-float {
            position: fixed;
            bottom: 1.25rem;
            right: 1.25rem;
            z-index: 9998;
            pointer-events: none;
        }

        .wa-float-btn {
            pointer-events: auto;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            border: none;
            background: #25d366;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(37, 211, 102, 0.45), 0 2px 6px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            animation: wa-bob 3.2s ease-in-out infinite;
        }

        .wa-float-btn:hover {
            transform: scale(1.06);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.55), 0 4px 10px rgba(0, 0, 0, 0.25);
        }

        .wa-float-btn[aria-expanded="true"] {
            animation: none;
        }

        @keyframes wa-bob {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        .wa-float-panel {
            position: absolute;
            right: 0;
            bottom: calc(100% + 0.75rem);
            width: min(280px, calc(100vw - 2.5rem));
            padding: 1rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px) scale(0.96);
            transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s;
            pointer-events: none;
        }

        .wa-float-panel.is-open {
            pointer-events: auto;
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .wa-float-panel:not(.is-open) a {
            pointer-events: none;
        }

        .wa-float-panel p {
            margin: 0 0 0.75rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--foreground);
            text-align: center;
        }

        .wa-float-qr-link {
            display: block;
            margin-top: 0.25rem;
            border-radius: calc(var(--radius) - 2px);
            overflow: hidden;
            line-height: 0;
            -webkit-tap-highlight-color: rgba(37, 211, 102, 0.3);
            touch-action: manipulation;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
            box-shadow: 0 0 0 1px var(--border);
        }

        .wa-float-qr-link img {
            display: block;
            width: 100%;
            height: auto;
        }

        .wa-float-qr-link:active {
            transform: scale(0.98);
        }

        @media (hover: hover) and (pointer: fine) {
            .wa-float-qr-link:hover {
                box-shadow: 0 0 0 2px #25d366, 0 10px 28px rgba(0, 0, 0, 0.25);
            }
        }

        .wa-float-qr-link:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 3px;
        }

        .wa-float-qr-hint {
            display: none;
            margin: 0.65rem 0 0;
            font-size: 0.8rem;
            line-height: 1.35;
            color: var(--muted-foreground);
            text-align: center;
        }

        @media (hover: none) and (pointer: coarse) {
            .wa-float-qr-hint {
                display: block;
            }
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
            <p class="hero-text">🏃‍♂️ Un défi sportif extrême inspiré des <strong style="color: var(--primary);"><a href="https://fr.wikipedia.org/wiki/Backyard_ultra" target="_blank">Backyard Ultra</a></strong> et de <a href="https://www.instagram.com/thespeedproject/" target="_blank">TSP</a></p>

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
            <h2 class="section-title gradient-accent">Le concept</h2>
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
                    <p class="feature-desc">Rejoins le défi sur n'importe quel créneau</p>
                </div>
            </div>

            <div style="max-width: 56rem; margin: 0 auto;">
                <div class="card gradient-bg-card">
                    <h3 style="font-size: 2rem; font-weight: bold; margin-bottom: 1.5rem; color: var(--primary);">L'objectif & les règles</h3>
                    <div style="font-size: 1.125rem; line-height: 1.8;">
                        <p style="margin-bottom: 1.5rem;">
                            🌟 Ce défi n'est pas qu'une performance personnelle, c'est un événement
                            qui rassemble une communauté autour de valeurs fortes.
                        </p>
                        <p style="margin-bottom: 1.5rem;">
                            🎉 Rejoins cette aventure en réservant un créneau pour courir et
                            célébrer ensemble la solidarité et l'endurance humaine !
                        </p>
                        <p style="margin-bottom: 1.5rem;">
                            🤝 Il est possible, et même conseillé, d'accompagner les participant·e·s en t'inscrivant sur un créneau, que ce soit pour un ou plusieurs tours.
                            Viens partager l'expérience, soutenir les participant·es et vivre ce moment ensemble !
                        </p>
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                            <h4 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; color: var(--primary);">Modalités de participation</h4>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <span style="font-size: 1.5rem;">📋</span>
                                <div>
                                    <p style="font-weight: 600;">Briefing : 1 mai 2026 à 17h30</p>
                                    <p style="color: var(--muted-foreground);">Rendez-vous avant le départ pour les dernières consignes</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <span style="font-size: 1.5rem;">🕐</span>
                                <div>
                                    <p style="font-weight: 600;">Départ : 1 mai 2026 à 18h</p>
                                    <p style="color: var(--muted-foreground);">Un nouveau tour démarre chaque heure pile</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <span style="font-size: 1.5rem;">🎯</span>
                                <div>
                                    <p style="font-weight: 600;">Arrivée : 2 mai 2026 à 18h</p>
                                    <p style="color: var(--muted-foreground);">Le·la gagnant·e sera le premier à arriver</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <span style="font-size: 1.5rem;">🚶‍♂️</span>
                                <div>
                                    <p style="font-weight: 600;">Full Autonomie</p>
                                    <p style="color: var(--muted-foreground);">Cet événement se déroule en full autonomie. Chaque participant·e est responsable de sa propre organisation, de son ravitaillement et de sa sécurité.</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <span style="font-size: 1.5rem;">🎯</span>
                                <div>
                                    <p style="font-weight: 600;">Les Règles Backyard</p>
                                    <p style="color: var(--muted-foreground);">Chaque participant·e doit terminer son tour avant le début du suivant. Un nouveau départ a lieu chaque heure pile.</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                                <span style="font-size: 1.5rem;">🏆</span>
                                <div>
                                    <p style="font-weight: 600;">Le Dernier Tour</p>
                                    <p style="color: var(--muted-foreground);">Contrairement au Backyard Ultra, lors du dernier tour (le 24ème), le gagnant sera le premier à arriver. Pas de "Last Person Standing", mais une course pour la victoire !</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="parcours" class="section" style="background: var(--background);">
        <div class="container">
            <h2 class="section-title gradient-secondary">Le parcours</h2>
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
                    <p style="color: var(--muted-foreground);">Bordeaux rive droite</p>
                </div>
            </div>

            <div style="max-width: 56rem; margin: 0 auto;">
                <div class="card gradient-bg-card">
                    <h3 style="font-size: 2rem; font-weight: bold; margin-bottom: 1.5rem; color: var(--primary);">Point de départ & arrivée</h3>
                    <div style="font-size: 1.125rem; line-height: 2;">
                        <div style="display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem;">📍</span>
                            <div>
                                <p style="font-weight: 600;">Quai des Queyries, rive droite</p>
                                <p style="color: var(--muted-foreground);">Devant le parking de la Belle Saison (van Volkswagen blanc)</p>
                            </div>
                        </div>
                        <div style="margin-bottom: 1.5rem; text-align: center;">
                            <img src="parking.jpeg" alt="Parking de la Belle Saison" style="width: 100%; max-width: 600px; border-radius: var(--radius); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="disclaimer" class="section">
        <div class="container" style="max-width: 900px;">
            <h2 class="section-title gradient-secondary">Avertissement</h2>
            <p class="section-subtitle">
                Participation libre, sans cadre associatif ou commercial, chacun vient à ses propres risques.
            </p>
            <div class="card" style="font-size: 1.125rem; line-height: 1.8; background: linear-gradient(145deg, rgba(20, 30, 45, 0.9), rgba(15, 20, 30, 0.95)); border: 1px solid rgba(255, 255, 255, 0.08);">
                <p style="margin-bottom: 1rem;">
                    Cette course n'est pas déclarée officiellement et ne relève d'aucune structure organisatrice. Il n'y a ni inscription administrative, ni assurance collective, ni dispositif de sécurité mis en place par un tiers.
                </p>
                <p style="margin-bottom: 1rem;">
                    Chaque participant·e demeure intégralement responsable de son état de santé, de son équipement, de son alimentation, de sa sécurité, ainsi que du respect des règles de circulation en vigueur sur l'espace public.
                </p>
                <p>
                    En rejoignant un créneau, tu reconnais participer volontairement, en pleine autonomie, et accepter qu'aucune responsabilité ne puisse être engagée à l'encontre de l'initiateur ou des autres coureurs.
                </p>
            </div>
        </div>
    </section>

    <section id="inscriptions" class="section gradient-bg-card">
        <div class="container" style="max-width: 1200px;">
            <h2 class="section-title gradient-primary">Inscriptions</h2>
            <p class="section-subtitle">
                Choisis ton créneau et rejoins l'aventure !
                Tu peux courir un ou plusieurs tours.
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
                                <p class="participants-title">Participant·e·s :</p>
                                <ul>
                                    <?php foreach ($participants as $participant): ?>
                                        <li><?= htmlspecialchars($participant) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <form class="registration-form" data-time="<?= $timeString ?>" data-slot-id="<?= $slot['id'] ?>">
                            <input type="text" class="input" name="name" placeholder="Ton prénom" required>
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
                    <p style="color: var(--muted-foreground); margin-bottom: 1rem;">
                        Pour toute question ou information supplémentaire,
                        viens nous voir sur place ou envoie-moi un message !
                    </p>
                    <p style="margin: 0; display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; align-items: center;">
                        <a href="#" id="contactLink" style="color: var(--primary); text-decoration: underline; cursor: pointer;">Me contacter</a>
                    </p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?= date('Y') ?> 24h des Quais de Bordeaux. Un défi humain exceptionnel.</p>
            </div>
        </div>
    </footer>

    <div class="wa-float" id="waFloat">
        <button type="button" class="wa-float-btn" id="waFloatBtn" aria-expanded="false" aria-controls="waFloatPanel" title="Groupe WhatsApp">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
        </button>
        <div class="wa-float-panel" id="waFloatPanel" role="dialog" aria-labelledby="waFloatTitle">
            <p id="waFloatTitle">Rejoindre le groupe WhatsApp</p>
            <a class="wa-float-qr-link" href="https://chat.whatsapp.com/CJPOHYNmSSqKMRhLuyMUFS?mode=gi_t" target="_blank" rel="noopener noreferrer" title="Ouvrir le groupe WhatsApp">
                <img src="qrcode.png" width="240" height="240" alt="QR code — ouvre le groupe WhatsApp au toucher">
            </a>
            <p class="wa-float-qr-hint">Appuie sur le code pour ouvrir WhatsApp</p>
        </div>
    </div>

    <div class="popup-overlay" id="contactPopup">
        <div class="popup-content">
            <button class="popup-close" id="closePopup">&times;</button>
            <h2 class="popup-title">Me contacter</h2>
            <form class="contact-form" id="contactForm">
                <input type="text" class="input" name="name" placeholder="Ton nom" required>
                <input type="email" class="input" name="email" placeholder="Ton email" required>
                <textarea name="message" placeholder="Ton message" required></textarea>
                <button type="submit" class="btn btn-submit">Envoyer</button>
            </form>
        </div>
    </div>

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

            container.innerHTML = '<p class="participants-title">Participant·e·s :</p>';
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

                formData.append('action', 'register');
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
                    showToast('Erreur lors de l\'inscription. Réessaie.', 'error');
                } finally {
                    button.disabled = false;
                    button.textContent = originalButtonText;
                }
            });
        });

        const contactLink = document.getElementById('contactLink');
        const contactPopup = document.getElementById('contactPopup');
        const closePopup = document.getElementById('closePopup');
        const contactForm = document.getElementById('contactForm');

        function openPopup() {
            contactPopup.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePopupHandler() {
            contactPopup.classList.remove('active');
            document.body.style.overflow = '';
            contactForm.reset();
        }

        contactLink.addEventListener('click', (e) => {
            e.preventDefault();
            openPopup();
        });

        closePopup.addEventListener('click', closePopupHandler);

        contactPopup.addEventListener('click', (e) => {
            if (e.target === contactPopup) {
                closePopupHandler();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && contactPopup.classList.contains('active')) {
                closePopupHandler();
            }
        });

        const waFloatBtn = document.getElementById('waFloatBtn');
        const waFloatPanel = document.getElementById('waFloatPanel');
        const waFloat = document.getElementById('waFloat');

        function setWaOpen(open) {
            waFloatBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            waFloatPanel.classList.toggle('is-open', open);
        }

        waFloatBtn.addEventListener('click', () => {
            setWaOpen(!waFloatPanel.classList.contains('is-open'));
        });

        document.addEventListener('click', (e) => {
            if (waFloatPanel.classList.contains('is-open') && !waFloat.contains(e.target)) {
                setWaOpen(false);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && waFloatPanel.classList.contains('is-open')) {
                setWaOpen(false);
            }
        });

        contactForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(contactForm);
            formData.append('action', 'contact');

            const button = contactForm.querySelector('button[type="submit"]');
            const originalButtonText = button.textContent;

            button.disabled = true;
            button.textContent = 'Envoi...';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    contactForm.reset();
                    setTimeout(() => {
                        closePopupHandler();
                    }, 1500);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Erreur lors de l\'envoi du message. Réessaie.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = originalButtonText;
            }
        });
    </script>
</body>

</html>