<?php
declare(strict_types=1);

$seed = static function (PDO $pdo, ?string $passwordHash = null): void {
    $pdo->beginTransaction();
    try {
        $user = $pdo->prepare('INSERT INTO users (id, email, password, role, manager_id) VALUES (?, ?, ?, ?, ?)');
        foreach ([
            [1, 'manager.a@app.test', 'manager', null],
            [2, 'manager.b@app.test', 'manager', null],
            [3, 'employee.a@app.test', 'employee', 1],
            [4, 'employee.b@app.test', 'employee', 2],
        ] as [$id, $email, $role, $manager]) {
            $user->execute([$id, $email, $passwordHash ?? password_hash('demo-password', PASSWORD_BCRYPT), $role, $manager]);
        }

        $now = gmdate('Y-m-d H:i:s');
        $expense = $pdo->prepare('INSERT INTO expenses (id, employee_id, description, amount, currency, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ([[42, 3, 2500, 'pending'], [43, 4, 1800, 'pending'], [44, 3, 1200, 'approved']] as [$id, $employee, $amount, $status]) {
            $expense->execute([$id, $employee, 'Demo expense', $amount, 'EUR', $status, $now]);
        }
        $pdo->prepare('INSERT INTO expense_approvals (id, expense_id, approved_by, comment, approved_at) VALUES (1, 44, 1, NULL, ?)')->execute([$now]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
};

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $pdo = require __DIR__ . '/../bootstrap.php';
        $seed($pdo);
        fwrite(STDOUT, "Seed data inserted.\n");
    } catch (Throwable) {
        fwrite(STDERR, "Seeding failed; check that the schema and seed data are compatible.\n");
        exit(1);
    }
}

return $seed;
