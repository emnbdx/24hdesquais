<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/UserInputException.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Registration.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_csrf(): void
{
    echo '<input type="hidden" name="csrf" value="' . h((string) ($_SESSION['csrf'] ?? '')) . '">';
}

/**
 * @param array{last_name?: string, first_name?: string, pseudo?: string, email?: string} $values
 */
function render_profile_fields(array $values): void
{
    echo '<div class="form-row">';
    echo '<label class="field"><span>Nom</span>';
    echo '<input class="input" name="last_name" autocomplete="family-name" required maxlength="80" value="' . h($values['last_name'] ?? '') . '">';
    echo '</label>';
    echo '<label class="field"><span>Prénom</span>';
    echo '<input class="input" name="first_name" autocomplete="given-name" required maxlength="80" value="' . h($values['first_name'] ?? '') . '">';
    echo '</label>';
    echo '</div>';
    echo '<label class="field"><span>Pseudo</span><span class="field-help">Affiché sur les créneaux</span>';
    echo '<input class="input" name="pseudo" autocomplete="nickname" required minlength="2" maxlength="40" value="' . h($values['pseudo'] ?? '') . '">';
    echo '</label>';
    echo '<label class="field"><span>Email</span>';
    echo '<input class="input" type="email" name="email" autocomplete="email" required maxlength="190" value="' . h($values['email'] ?? '') . '">';
    echo '</label>';
}

/**
 * @param array<string, list<array{key: string, time: string}>> $slotsByDay
 * @param list<string> $selected
 */
function render_slot_picker(array $slotsByDay, array $selected): void
{
    $selected = array_fill_keys($selected, true);
    echo '<fieldset class="slot-picker-set">';
    echo '<legend>Tes créneaux</legend>';
    echo '<p class="field-help">Un ou plusieurs tours.</p>';
    echo '<div class="slot-picker-tools">';
    echo '<button type="button" class="linkish js-slots-all">Tout sélectionner</button>';
    echo '<button type="button" class="linkish js-slots-none">Aucun</button>';
    echo '</div>';
    foreach ($slotsByDay as $day => $daySlots) {
        echo '<p class="slot-picker-day">' . h($day) . '</p>';
        echo '<div class="slot-picker">';
        foreach ($daySlots as $slot) {
            $checked = isset($selected[$slot['key']]) ? ' checked' : '';
            echo '<label class="slot-choice">';
            echo '<input type="checkbox" name="slots[]" value="' . h($slot['key']) . '"' . $checked . '>';
            echo '<span>' . h($slot['time']) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }
    echo '</fieldset>';
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('quais_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$tz = new DateTimeZone('Europe/Paris');
$now = new DateTimeImmutable('now', $tz);
$raceStart = new DateTimeImmutable('2027-05-01 18:00:00', $tz);
$raceEnd = $raceStart->modify('+24 hours');
$briefingAt = $raceStart->modify('-30 minutes');
$resultsAvailableFrom = $raceEnd->modify('+24 hours');

try {
    $registrationEnd = new DateTimeImmutable((string) ($_ENV['REGISTRATION_END_DATE'] ?? '2027-04-30 23:59:59'), $tz);
} catch (Exception) {
    $registrationEnd = new DateTimeImmutable('2027-04-30 23:59:59', $tz);
}

$registrationsOpen = $now < $registrationEnd;
$showResults = !$registrationsOpen && $now >= $resultsAvailableFrom;
$showLive = !$registrationsOpen && !$showResults;
$showLiveLink = $showLive || $showResults;
$liveTrackingUrl = 'https://livechrono.run/';

$pastEditions = [
    [
        'year' => '2026',
        'when' => '1-2 mai 2026',
        'detail' => 'Vendredi 18h – samedi 18h',
        'laps' => 24,
        'results' => [
            ['name' => 'Eddy', 'laps' => 24, 'note' => 'Dernier tour 48:33'],
            ['name' => 'Arnaud', 'laps' => 12],
            ['name' => 'Ben', 'laps' => 11],
            ['name' => 'Amaury', 'laps' => 10],
            ['name' => 'Aro', 'laps' => 9],
            ['name' => 'Alexis', 'laps' => 8],
            ['name' => 'Hugo', 'laps' => 8],
            ['name' => 'Maxime', 'laps' => 7],
            ['name' => 'Maxime R', 'laps' => 7],
            ['name' => 'Antoine', 'laps' => 6],
            ['name' => 'Quentin P', 'laps' => 6],
            ['name' => 'Vincent', 'laps' => 6],
            ['name' => 'Xavier', 'laps' => 6],
            ['name' => 'Marine', 'laps' => 5],
            ['name' => 'Théo', 'laps' => 5],
            ['name' => 'Alex', 'laps' => 4],
            ['name' => 'Dominique', 'laps' => 4],
            ['name' => 'Kevin', 'laps' => 4],
            ['name' => 'Ludovic', 'laps' => 4],
            ['name' => 'Louis', 'laps' => 3],
            ['name' => 'Oscar', 'laps' => 3],
            ['name' => 'Quentin A', 'laps' => 3],
            ['name' => 'Victor', 'laps' => 3],
            ['name' => 'Thomas', 'laps' => 2],
            ['name' => 'Cedric', 'laps' => 1],
            ['name' => 'Laetitia', 'laps' => 1],
            ['name' => 'Laurane', 'laps' => 1],
            ['name' => 'Mathilde', 'laps' => 1],
        ],
    ],
    [
        'year' => '2024',
        'when' => '19-20 juillet 2024',
        'detail' => 'Vendredi 16h – samedi 16h',
        'laps' => 24,
        'results' => [
            ['name' => 'Eddy', 'laps' => 12],
            ['name' => 'Paul', 'laps' => 6],
            ['name' => 'Amandine', 'laps' => 4],
            ['name' => 'Louis', 'laps' => 3],
            ['name' => 'Clément', 'laps' => 1],
            ['name' => 'Alice', 'laps' => 1],
        ],
    ],
];

$fmtDate = new IntlDateFormatter(
    'fr_FR',
    IntlDateFormatter::LONG,
    IntlDateFormatter::NONE,
    'Europe/Paris',
    IntlDateFormatter::GREGORIAN,
    'd MMMM y'
);
$fmtDay = new IntlDateFormatter(
    'fr_FR',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'Europe/Paris',
    IntlDateFormatter::GREGORIAN,
    'EEEE'
);

$formatDate = static function (DateTimeImmutable $dt) use ($fmtDate): string {
    $raw = $fmtDate->format($dt);

    return $raw === false ? $dt->format('j/m/Y') : $raw;
};
$moment = static function (DateTimeImmutable $dt) use ($formatDate): string {
    $time = $dt->format('i') === '00' ? $dt->format('G') . 'h' : $dt->format('G\hi');

    return $formatDate($dt) . ' à ' . $time;
};
$dayName = static function (DateTimeImmutable $dt) use ($fmtDay): string {
    $raw = $fmtDay->format($dt);
    $name = $raw === false ? $dt->format('l') : $raw;

    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
};

$labelStart = $moment($raceStart);
$labelEnd = $moment($raceEnd);
$labelBriefing = $moment($briefingAt);
$labelFooter = $raceStart->format('j') . '-' . $formatDate($raceEnd);

$slots = [];
$slotsByDay = [];
for ($i = 0; $i < 24; $i++) {
    $start = $raceStart->modify('+' . $i . ' hours');
    $end = $start->modify('+1 hour');
    $slot = [
        'id' => $i + 1,
        'key' => $start->format('Y-m-d H:i:s'),
        'day' => $dayName($start),
        'time' => $start->format('H:i') . ' - ' . $end->format('H:i'),
    ];
    $slots[] = $slot;
    $slotsByDay[$slot['day']][] = $slot;
}
$allowedSlotKeys = array_column($slots, 'key');

$dbReady = false;
try {
    Db::pdo();
    $dbReady = true;
} catch (Throwable $e) {
    error_log('Connexion base de données impossible: ' . $e->getMessage());
}

$signupAvailable = $registrationsOpen && $dbReady;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $csrf = (string) ($_POST['csrf'] ?? '');
    $sessionCsrf = (string) ($_SESSION['csrf'] ?? '');
    if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Session expirée, recharge la page.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = (string) $_POST['action'];
    if ($action === 'contact') {
        $name = strip_tags((string) ($_POST['name'] ?? ''));
        $email = filter_var((string) ($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $message = strip_tags((string) ($_POST['message'] ?? ''));

        if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Merci de remplir tous les champs correctement.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $html = '<p><strong>Nom:</strong> ' . htmlspecialchars($name) . '</p>'
            . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
            . '<p><strong>Message:</strong></p>'
            . '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
        $replyName = addcslashes($name, '"\\');

        try {
            Mailer::send(
                'eddy.montus@gmail.com',
                'Contact - 24h des Quais de Bordeaux',
                $html,
                "Nom: {$name}\nEmail: {$email}\n\n{$message}",
                "\"{$replyName}\" <{$email}>"
            );
            echo json_encode(['success' => true, 'message' => 'Message envoyé avec succès !'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('Erreur envoi email: ' . $e->getMessage());
            $public = $e instanceof UserInputException ? $e->getMessage() : 'Erreur lors de l\'envoi du message.';
            echo json_encode(['success' => false, 'message' => $public], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if (!$dbReady && $action !== 'logout') {
        echo json_encode(['success' => false, 'message' => 'Les inscriptions sont momentanément indisponibles.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    Registration::handle($action, $signupAvailable, $allowedSlotKeys);
    exit;
}

$member = null;
if ($dbReady && !empty($_SESSION['member_id'])) {
    $member = Registration::member((int) $_SESSION['member_id']);
    if ($member === null) {
        unset($_SESSION['member_id']);
    }
}

$participants = $dbReady ? Registration::participants() : [];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (!is_array($flash) || !isset($flash['message']) || !is_string($flash['message'])) {
    $flash = null;
}
$flashJson = json_encode(
    $flash,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
