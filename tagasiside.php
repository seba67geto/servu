<?php
declare(strict_types=1);

session_start();

$errors = [];
$name = '';
$email = '';
$rating = '5';
$message = '';
$saved = isset($_GET['saved']);
$databaseError = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $database = new PDO('sqlite:' . __DIR__ . '/tagasiside.sqlite');
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $database->exec(
        'CREATE TABLE IF NOT EXISTS tagasiside (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nimi TEXT NOT NULL,
            email TEXT NOT NULL,
            hinnang INTEGER NOT NULL CHECK (hinnang BETWEEN 1 AND 5),
            sonum TEXT NOT NULL,
            loodud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $rating = (string) ($_POST['rating'] ?? '');
        $message = trim((string) ($_POST['message'] ?? ''));
        $csrfToken = (string) ($_POST['csrf_token'] ?? '');

        if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            $errors[] = 'Vormi sessioon aegus. Palun proovi uuesti.';
        }
        if ($name === '' || strlen($name) > 100) {
            $errors[] = 'Nimi on kohustuslik ja võib olla kuni 100 tähemärki.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $errors[] = 'Sisesta korrektne e-posti aadress.';
        }
        if (!in_array($rating, ['1', '2', '3', '4', '5'], true)) {
            $errors[] = 'Vali hinnang ühest viieni.';
        }
        if ($message === '' || strlen($message) > 2000) {
            $errors[] = 'Tagasiside on kohustuslik ja võib olla kuni 2000 tähemärki.';
        }

        if ($errors === []) {
            $statement = $database->prepare(
                'INSERT INTO tagasiside (nimi, email, hinnang, sonum)
                 VALUES (:nimi, :email, :hinnang, :sonum)'
            );
            $statement->execute([
                ':nimi' => $name,
                ':email' => $email,
                ':hinnang' => (int) $rating,
                ':sonum' => $message,
            ]);

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: tagasiside.php?saved=1');
            exit;
        }
    }
} catch (PDOException $exception) {
    $databaseError = 'Tagasisidet ei saa praegu salvestada. Kontrolli, et PHP SQLite laiendus oleks lubatud.';
}

include 'nav.php';
?>

<main class="feedback-page">
    <section class="feedback-card" aria-labelledby="feedback-title">
        <h1 id="feedback-title">Anna meile tagasisidet</h1>
        <p>Sinu arvamus aitab meil teenust paremaks muuta.</p>

        <?php if ($saved): ?>
            <p class="notice notice-success" role="status">Aitäh! Sinu tagasiside on salvestatud.</p>
        <?php endif; ?>

        <?php if ($databaseError !== ''): ?>
            <p class="notice notice-error" role="alert"><?= htmlspecialchars($databaseError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="notice notice-error" role="alert">
                <strong>Palun paranda järgmised vead:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="feedback-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="name">Nimi</label>
            <input id="name" name="name" type="text" maxlength="100" required autocomplete="name"
                   value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">

            <label for="email">E-post</label>
            <input id="email" name="email" type="email" maxlength="254" required autocomplete="email"
                   value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

            <label for="rating">Hinnang</label>
            <select id="rating" name="rating" required>
                <?php for ($value = 5; $value >= 1; $value--): ?>
                    <option value="<?= $value ?>" <?= $rating === (string) $value ? 'selected' : '' ?>><?= $value ?> / 5</option>
                <?php endfor; ?>
            </select>

            <label for="message">Tagasiside</label>
            <textarea id="message" name="message" rows="6" maxlength="2000" required><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></textarea>

            <button type="submit" <?= $databaseError !== '' ? 'disabled' : '' ?>>Saada tagasiside</button>
        </form>
    </section>
</main>

<?php include 'footer.php'; ?>
