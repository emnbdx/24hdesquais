<?php

declare(strict_types=1);

require_once __DIR__ . '/UserInputException.php';

final class IdentityTaken extends RuntimeException
{
}

final class Registration
{
    /**
     * @param list<string> $allowedSlots
     */
    public static function handle(string $action, bool $signupAvailable, array $allowedSlots): void
    {
        try {
            match ($action) {
                'signup' => self::requestSignup($signupAvailable, $allowedSlots),
                'login' => self::requestLogin($signupAvailable),
                'verify_code' => self::verify($signupAvailable, $allowedSlots),
                'update' => self::update($signupAvailable, $allowedSlots),
                'logout' => self::logout(),
                default => throw new UserInputException('Action inconnue.'),
            };
        } catch (UserInputException $e) {
            self::fail($e->getMessage());
        } catch (Throwable $e) {
            error_log($e::class . ': ' . $e->getMessage());
            self::fail('Une erreur est survenue. Réessaie.');
        }
    }

    /**
     * @return array{id: int, last_name: string, first_name: string, pseudo: string, email: string, slots: list<string>}|null
     */
    public static function member(int $id): ?array
    {
        $row = Db::fetchOne(
            'SELECT id, last_name, first_name, pseudo, email FROM members WHERE id = ?',
            [$id]
        );
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'last_name' => (string) $row['last_name'],
            'first_name' => (string) $row['first_name'],
            'pseudo' => (string) $row['pseudo'],
            'email' => (string) $row['email'],
            'slots' => self::slotKeys((int) $row['id']),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function participants(): array
    {
        $rows = Db::fetchAll(
            'SELECT m.pseudo, s.starts_at
             FROM member_slots s
             INNER JOIN members m ON m.id = s.member_id
             ORDER BY m.pseudo'
        );

        $bySlot = [];
        foreach ($rows as $row) {
            $bySlot[(string) $row['starts_at']][] = (string) $row['pseudo'];
        }

        return $bySlot;
    }

    /**
     * @param list<string> $allowedSlots
     */
    private static function requestSignup(bool $signupAvailable, array $allowedSlots): void
    {
        self::assertOpen($signupAvailable);
        $profile = self::profileFromPost($allowedSlots);

        if (Db::fetchOne('SELECT id FROM members WHERE email = ?', [$profile['email']]) !== null) {
            throw new UserInputException('Cet email est déjà inscrit. Connecte-toi pour modifier ton inscription.');
        }
        if (Db::fetchOne('SELECT id FROM members WHERE pseudo = ?', [$profile['pseudo']]) !== null) {
            throw new UserInputException('Ce pseudo est déjà pris.');
        }

        self::issueCode($profile['email'], 'signup', $profile);
        self::ok([
            'step' => 'code',
            'email' => $profile['email'],
            'message' => 'On t\'a envoyé un code à ' . $profile['email'] . '. Il est valable 10 minutes.',
        ]);
    }

    private static function requestLogin(bool $signupAvailable): void
    {
        self::assertOpen($signupAvailable);
        $email = self::email((string) ($_POST['email'] ?? ''));

        if (Db::fetchOne('SELECT id FROM members WHERE email = ?', [$email]) === null) {
            throw new UserInputException('Aucun compte avec cet email. Inscris-toi.');
        }

        self::issueCode($email, 'login', null);
        self::ok([
            'step' => 'code',
            'email' => $email,
            'message' => 'On t\'a envoyé un code à ' . $email . '. Il est valable 10 minutes.',
        ]);
    }

    /**
     * @param list<string> $allowedSlots
     */
    private static function verify(bool $signupAvailable, array $allowedSlots): void
    {
        self::assertOpen($signupAvailable);
        $email = self::email((string) ($_POST['email'] ?? ''));
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new UserInputException('Code invalide ou expiré.');
        }

        $now = date('Y-m-d H:i:s');
        $row = Db::fetchOne(
            'SELECT id FROM login_codes WHERE email = ? AND expires_at > ? AND attempts < 5 ORDER BY id DESC LIMIT 1',
            [$email, $now]
        );
        if ($row === null) {
            throw new UserInputException('Code invalide ou expiré.');
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $current = Db::fetchOne('SELECT * FROM login_codes WHERE id = ? FOR UPDATE', [(int) $row['id']]);
            if ($current === null || (string) $current['expires_at'] <= $now || (int) $current['attempts'] >= 5) {
                $pdo->rollBack();
                throw new UserInputException('Code invalide ou expiré.');
            }

            if (!password_verify($code, (string) $current['code_hash'])) {
                $attempts = (int) $current['attempts'] + 1;
                if ($attempts >= 5) {
                    Db::run('DELETE FROM login_codes WHERE id = ?', [(int) $current['id']]);
                } else {
                    Db::run('UPDATE login_codes SET attempts = ? WHERE id = ?', [$attempts, (int) $current['id']]);
                }
                $pdo->commit();
                throw new UserInputException('Code invalide ou expiré.');
            }

            $memberId = self::consumeCode($current, $allowedSlots, $now);
            Db::run('DELETE FROM login_codes WHERE email = ?', [$email]);
            $pdo->commit();
        } catch (IdentityTaken $e) {
            if ($pdo->inTransaction()) {
                Db::run('DELETE FROM login_codes WHERE email = ?', [$email]);
                $pdo->commit();
            }
            throw new UserInputException($e->getMessage());
        } catch (UserInputException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $message = match ((string) $current['purpose']) {
            'signup' => 'Inscription confirmée.',
            'email_change' => 'Adresse email mise à jour.',
            default => 'Connexion réussie.',
        };
        self::finishLogin($memberId, $message);
    }

    /**
     * @param list<string> $allowedSlots
     */
    private static function update(bool $signupAvailable, array $allowedSlots): void
    {
        self::assertOpen($signupAvailable);
        $memberId = (int) ($_SESSION['member_id'] ?? 0);
        $member = $memberId > 0 ? self::member($memberId) : null;
        if ($member === null) {
            throw new UserInputException('Connecte-toi pour modifier ton inscription.');
        }

        $profile = self::profileFromPost($allowedSlots);
        if (Db::fetchOne('SELECT id FROM members WHERE email = ? AND id <> ?', [$profile['email'], $member['id']]) !== null) {
            throw new UserInputException('Cet email est déjà inscrit.');
        }

        $now = date('Y-m-d H:i:s');
        try {
            Db::transaction(static function () use ($member, $profile, $now): void {
                Db::run(
                    'UPDATE members SET last_name = ?, first_name = ?, pseudo = ?, updated_at = ? WHERE id = ?',
                    [$profile['last_name'], $profile['first_name'], $profile['pseudo'], $now, $member['id']]
                );
                Db::run('DELETE FROM member_slots WHERE member_id = ?', [$member['id']]);
                self::insertSlots($member['id'], $profile['slots']);
            });
        } catch (PDOException $e) {
            if (self::isDuplicate($e, 'members_pseudo')) {
                throw new UserInputException('Ce pseudo est déjà pris.');
            }
            throw $e;
        }

        if ($profile['email'] === $member['email']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Modifications enregistrées.'];
            self::ok(['reload' => true]);
        }

        self::issueCode($profile['email'], 'email_change', [
            'member_id' => $member['id'],
            'email' => $profile['email'],
        ]);
        self::ok([
            'step' => 'code',
            'email' => $profile['email'],
            'message' => 'Tes infos sont enregistrées. Confirme ta nouvelle adresse : on t\'a envoyé un code à ' . $profile['email'] . '.',
        ]);
    }

    private static function logout(): void
    {
        unset($_SESSION['member_id']);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tu es déconnecté·e.'];
        self::ok(['reload' => true]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private static function issueCode(string $email, string $purpose, ?array $payload): void
    {
        self::assertCanSend($email);
        $now = date('Y-m-d H:i:s');
        Db::run(
            'UPDATE login_codes SET expires_at = ? WHERE email = ? AND expires_at > ?',
            [$now, $email, $now]
        );

        $code = sprintf('%06d', random_int(0, 999999));
        $hash = password_hash($code, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Hash du code impossible.');
        }

        Db::run(
            'INSERT INTO login_codes (email, code_hash, purpose, payload, expires_at, attempts, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)',
            [
                $email,
                $hash,
                $purpose,
                $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s', time() + 600),
                $now,
            ]
        );
        $id = (int) Db::pdo()->lastInsertId();

        try {
            Mailer::sendLoginCode($email, $code);
        } catch (Throwable $e) {
            Db::run('DELETE FROM login_codes WHERE id = ?', [$id]);
            if (!$e instanceof UserInputException) {
                error_log('Envoi du code impossible: ' . $e->getMessage());
            }
            throw new UserInputException('Envoi du code impossible pour le moment.');
        }
    }

    private static function assertCanSend(string $email): void
    {
        Db::run('DELETE FROM login_codes WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);

        $latest = Db::fetchOne(
            'SELECT created_at FROM login_codes WHERE email = ? ORDER BY id DESC LIMIT 1',
            [$email]
        );
        if ($latest !== null && strtotime((string) $latest['created_at']) > time() - 60) {
            throw new UserInputException('Attends une minute avant de redemander un code.');
        }

        $count = Db::fetchOne(
            'SELECT COUNT(*) AS n FROM login_codes WHERE email = ? AND created_at > ?',
            [$email, date('Y-m-d H:i:s', time() - 3600)]
        );
        if ((int) ($count['n'] ?? 0) >= 5) {
            throw new UserInputException('Trop de demandes. Réessaie dans une heure.');
        }
    }

    /**
     * @param array<string, mixed> $current
     * @param list<string> $allowedSlots
     */
    private static function consumeCode(array $current, array $allowedSlots, string $now): int
    {
        $purpose = (string) $current['purpose'];
        $email = (string) $current['email'];

        if ($purpose === 'login') {
            $member = Db::fetchOne('SELECT id FROM members WHERE email = ?', [$email]);
            if ($member === null) {
                throw new UserInputException('Aucun compte avec cet email.');
            }

            return (int) $member['id'];
        }

        $payload = json_decode((string) $current['payload'], true);
        if (!is_array($payload)) {
            throw new UserInputException('Demande introuvable. Recommence.');
        }

        if ($purpose === 'email_change') {
            $memberId = (int) ($payload['member_id'] ?? 0);
            $newEmail = self::email((string) ($payload['email'] ?? ''));
            if ($newEmail !== $email || Db::fetchOne('SELECT id FROM members WHERE id = ?', [$memberId]) === null) {
                throw new UserInputException('Demande introuvable. Recommence.');
            }

            try {
                Db::run(
                    'UPDATE members SET email = ?, updated_at = ? WHERE id = ?',
                    [$newEmail, $now, $memberId]
                );
            } catch (PDOException $e) {
                if (self::isDuplicate($e, 'members_email')) {
                    throw new IdentityTaken('Cet email est déjà inscrit.');
                }
                throw $e;
            }

            return $memberId;
        }

        if ($purpose !== 'signup') {
            throw new UserInputException('Demande introuvable. Recommence.');
        }

        if (self::email((string) ($payload['email'] ?? '')) !== $email) {
            throw new UserInputException('Demande introuvable. Recommence.');
        }

        $lastName = self::personName((string) ($payload['last_name'] ?? ''), 'Le nom');
        $firstName = self::personName((string) ($payload['first_name'] ?? ''), 'Le prénom');
        $pseudo = self::pseudo((string) ($payload['pseudo'] ?? ''));
        $slots = self::onlyAllowedSlots($payload['slots'] ?? null, $allowedSlots);

        try {
            Db::run(
                'INSERT INTO members (last_name, first_name, pseudo, email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$lastName, $firstName, $pseudo, $email, $now, $now]
            );
        } catch (PDOException $e) {
            if (self::isDuplicate($e, 'members_email')) {
                throw new IdentityTaken('Cet email est déjà inscrit. Connecte-toi pour modifier ton inscription.');
            }
            if (self::isDuplicate($e, 'members_pseudo')) {
                throw new IdentityTaken('Ce pseudo est déjà pris. Recommence l\'inscription.');
            }
            throw $e;
        }

        $memberId = (int) Db::pdo()->lastInsertId();
        self::insertSlots($memberId, $slots);

        return $memberId;
    }

    /**
     * @param list<string> $slots
     */
    private static function insertSlots(int $memberId, array $slots): void
    {
        $statement = Db::pdo()->prepare('INSERT INTO member_slots (member_id, starts_at) VALUES (?, ?)');
        foreach ($slots as $slot) {
            $statement->execute([$memberId, $slot]);
        }
    }

    /**
     * @param list<string> $allowedSlots
     * @return array{last_name: string, first_name: string, pseudo: string, email: string, slots: list<string>}
     */
    private static function profileFromPost(array $allowedSlots): array
    {
        return [
            'last_name' => self::personName((string) ($_POST['last_name'] ?? ''), 'Le nom'),
            'first_name' => self::personName((string) ($_POST['first_name'] ?? ''), 'Le prénom'),
            'pseudo' => self::pseudo((string) ($_POST['pseudo'] ?? '')),
            'email' => self::email((string) ($_POST['email'] ?? '')),
            'slots' => self::onlyAllowedSlots($_POST['slots'] ?? null, $allowedSlots),
        ];
    }

    /**
     * @return list<string>
     */
    private static function onlyAllowedSlots(mixed $raw, array $allowedSlots): array
    {
        if (!is_array($raw)) {
            throw new UserInputException('Choisis au moins un créneau.');
        }

        $allowed = array_fill_keys($allowedSlots, true);
        $slots = [];
        foreach ($raw as $value) {
            $value = (string) $value;
            if (!isset($allowed[$value])) {
                throw new UserInputException('Un créneau est invalide.');
            }
            $slots[$value] = $value;
        }

        $slots = array_values($slots);
        if ($slots === []) {
            throw new UserInputException('Choisis au moins un créneau.');
        }

        sort($slots);

        return $slots;
    }

    private static function personName(string $value, string $label): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
        $length = mb_strlen($value);
        if ($length < 1 || $length > 80 || preg_match('/[\x00-\x1F]/', $value)) {
            throw new UserInputException($label . ' est invalide.');
        }

        return $value;
    }

    private static function pseudo(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
        $length = mb_strlen($value);
        if ($length < 2 || $length > 40 || preg_match('/[\x00-\x1F]/', $value)) {
            throw new UserInputException('Le pseudo doit faire entre 2 et 40 caractères.');
        }

        return $value;
    }

    private static function email(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/\s/', $value) || mb_strlen($value) > 190 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new UserInputException('Email invalide.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function slotKeys(int $memberId): array
    {
        $rows = Db::fetchAll(
            'SELECT starts_at FROM member_slots WHERE member_id = ? ORDER BY starts_at',
            [$memberId]
        );

        return array_map(static fn (array $row): string => (string) $row['starts_at'], $rows);
    }

    private static function assertOpen(bool $signupAvailable): void
    {
        if (!$signupAvailable) {
            throw new UserInputException('Les inscriptions sont fermées.');
        }
    }

    private static function isDuplicate(PDOException $e, string $key): bool
    {
        $sqlState = (string) $e->getCode();

        return ($sqlState === '23000' || $sqlState === '1062') && str_contains($e->getMessage(), $key);
    }

    private static function finishLogin(int $memberId, string $message): void
    {
        session_regenerate_id(true);
        $_SESSION['member_id'] = $memberId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
        self::ok(['reload' => true]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function ok(array $extra): void
    {
        echo json_encode(['success' => true] + $extra, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function fail(string $message): void
    {
        echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
