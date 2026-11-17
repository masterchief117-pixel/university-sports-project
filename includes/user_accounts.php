<?php
declare(strict_types=1);

function create_player_account(
    PDO $pdo,
    string $name,
    string $email,
    string $password,
    int $sportId
): void {
    create_account($pdo, $name, $email, $password, 'player', $sportId);
}

function create_account(
    PDO $pdo,
    string $name,
    string $email,
    string $password,
    string $role,
    ?int $sportId = null
): void {
    $allowedRoles = ['player', 'coach', 'hos', 'photographer'];
    if (!in_array($role, $allowedRoles, true)) {
        throw new InvalidArgumentException('Unsupported role provided for account creation.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, sport_id)
         VALUES (:name, :email, :password_hash, :role, :sport_id)'
    );
    $insert->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'sport_id' => $sportId,
    ]);
}

function create_photographer_account(PDO $pdo, string $name, string $email, string $password): void
{
    create_account($pdo, $name, $email, $password, 'photographer', null);
}

function drop_player_from_sport(PDO $pdo, int $playerId, int $sportId): bool
{
    $update = $pdo->prepare(
        "UPDATE users
         SET sport_id = NULL
         WHERE id = :player_id AND role = 'player' AND sport_id = :sport_id"
    );
    $update->execute([
        'player_id' => $playerId,
        'sport_id' => $sportId,
    ]);

    return $update->rowCount() === 1;
}

function assign_unassigned_player(PDO $pdo, int $playerId, int $sportId): bool
{
    $update = $pdo->prepare(
        "UPDATE users
         SET sport_id = :sport_id, requested_sport_id = NULL
         WHERE id = :player_id AND role = 'player' AND sport_id IS NULL"
    );
    $update->execute([
        'sport_id' => $sportId,
        'player_id' => $playerId,
    ]);

    return $update->rowCount() === 1;
}

function request_sport_for_unassigned_player(PDO $pdo, int $playerId, int $sportId): bool
{
    $update = $pdo->prepare(
        "UPDATE users
         SET requested_sport_id = :requested_sport_id
         WHERE id = :player_id AND role = 'player' AND sport_id IS NULL"
    );
    $update->execute([
        'requested_sport_id' => $sportId,
        'player_id' => $playerId,
    ]);

    return $update->rowCount() === 1;
}
