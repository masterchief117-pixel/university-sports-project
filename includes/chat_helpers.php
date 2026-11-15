<?php
declare(strict_types=1);

function get_chat_context(PDO $pdo, string $type, ?int $targetUserId): array
{
    if (!array_key_exists('user_id', $_SESSION) || !array_key_exists('sport_id', $_SESSION)) {
        json_error('Your session is no longer valid. Please log in again.', 401);
    }

    $sessionUserId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
    if ($sessionUserId === false || $sessionUserId < 1) {
        json_error('Your session is no longer valid. Please log in again.', 401);
    }

    $sport_id = $_SESSION['sport_id'];
    if ($sport_id !== null) {
        $sport_id = filter_var($sport_id, FILTER_VALIDATE_INT);
        if ($sport_id === false || $sport_id < 1) {
            json_error('Your session is no longer valid. Please log in again.', 401);
        }
        $sport_id = (int) $sport_id;
    }

    $currentUserQuery = $pdo->prepare(
        'SELECT id, role, sport_id FROM users WHERE id = :id LIMIT 1'
    );
    $currentUserQuery->execute(['id' => $sessionUserId]);
    $currentUser = $currentUserQuery->fetch();

    $storedSportId = !$currentUser || $currentUser['sport_id'] === null
        ? null
        : (int) $currentUser['sport_id'];
    if (!$currentUser || $currentUser['role'] !== ($_SESSION['role'] ?? null)
        || $storedSportId !== $sport_id) {
        json_error('Your session is no longer valid. Please log in again.', 401);
    }

    $actorId = (int) $currentUser['id'];
    $actorRole = $currentUser['role'];

    if ($type === 'team') {
        if (!in_array($actorRole, ['player', 'coach'], true) || $sport_id === null || $targetUserId !== null) {
            json_error('Team chat is not available for this account.', 403);
        }

        $membersQuery = $pdo->prepare(
            "SELECT id FROM users
             WHERE sport_id = :sport_id AND role IN ('player', 'coach')
             ORDER BY id"
        );
        $membersQuery->execute(['sport_id' => $sport_id]);
        $participantIds = array_map('intval', $membersQuery->fetchAll(PDO::FETCH_COLUMN));

        return [
            'actor_id' => $actorId,
            'participant_ids' => $participantIds,
            'sport_id' => $sport_id,
            'is_group' => true,
        ];
    }

    if ($type === 'coaches_board') {
        if ($actorRole !== 'coach' || $targetUserId !== null) {
            json_error('The coaches board is restricted to coaches.', 403);
        }

        $members = $pdo->query("SELECT id FROM users WHERE role = 'coach' ORDER BY id");

        return [
            'actor_id' => $actorId,
            'participant_ids' => array_map('intval', $members->fetchAll(PDO::FETCH_COLUMN)),
            'sport_id' => null,
            'is_group' => true,
        ];
    }

    $allowedCounterpartRole = null;
    if ($type === 'player_coach' && in_array($actorRole, ['player', 'coach'], true)) {
        if ($sport_id === null) {
            json_error('A sport must be assigned to use player and coach chat.', 403);
        }
        $allowedCounterpartRole = $actorRole === 'player' ? 'coach' : 'player';
    } elseif ($type === 'coach_hos' && in_array($actorRole, ['coach', 'hos'], true)) {
        $allowedCounterpartRole = $actorRole === 'coach' ? 'hos' : 'coach';
    } else {
        json_error('Unsupported conversation type for this account.', 403);
    }

    if ($targetUserId === null) {
        if ($type === 'player_coach') {
            $counterpartQuery = $pdo->prepare(
                'SELECT id FROM users
                 WHERE role = :role AND sport_id = :sport_id
                 ORDER BY id'
            );
            $counterpartQuery->execute([
                'role' => $allowedCounterpartRole,
                'sport_id' => $sport_id,
            ]);
        } else {
            $counterpartQuery = $pdo->prepare(
                'SELECT id FROM users WHERE role = :role ORDER BY id'
            );
            $counterpartQuery->execute(['role' => $allowedCounterpartRole]);
        }
        $counterpartIds = array_map('intval', $counterpartQuery->fetchAll(PDO::FETCH_COLUMN));

        if (count($counterpartIds) !== 1) {
            json_error('Select a conversation partner.', 400);
        }
        $targetUserId = $counterpartIds[0];
    }

    if ($targetUserId === $actorId) {
        json_error('You cannot start a conversation with yourself.', 400);
    }

    $counterpartQuery = $pdo->prepare(
        'SELECT id, role, sport_id FROM users WHERE id = :id LIMIT 1'
    );
    $counterpartQuery->execute(['id' => $targetUserId]);
    $counterpart = $counterpartQuery->fetch();

    if (!$counterpart || $counterpart['role'] !== $allowedCounterpartRole) {
        json_error('That conversation partner is not allowed.', 403);
    }

    if ($type === 'player_coach') {
        if ($counterpart['sport_id'] === null || (int) $counterpart['sport_id'] !== $sport_id) {
            json_error('You can only chat with a player or coach in your sport.', 403);
        }
        $sport_id = (int) $counterpart['sport_id'];
    } else {
        $sport_id = null;
    }

    return [
        'actor_id' => $actorId,
        'participant_ids' => [$actorId, (int) $counterpart['id']],
        'sport_id' => $sport_id,
        'is_group' => false,
    ];
}

function sync_chat_group_members(PDO $pdo, int $conversationId, array $participantIds): void
{
    $placeholders = implode(', ', array_fill(0, count($participantIds), '?'));
    $delete = $pdo->prepare(
        "DELETE FROM conversation_participants
         WHERE conversation_id = ? AND user_id NOT IN ({$placeholders})"
    );
    $delete->execute(array_merge([$conversationId], $participantIds));

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO conversation_participants (conversation_id, user_id)
         VALUES (:conversation_id, :user_id)'
    );
    foreach ($participantIds as $participantId) {
        $insert->execute([
            'conversation_id' => $conversationId,
            'user_id' => $participantId,
        ]);
    }
}

function find_chat_conversation(
    PDO $pdo,
    string $type,
    array $context,
    bool $createIfMissing
): ?int {
    if ($context['is_group']) {
        if ($context['sport_id'] === null) {
            $find = $pdo->prepare(
                'SELECT id FROM conversations
                 WHERE conversation_type = :type AND sport_id IS NULL
                 ORDER BY id LIMIT 1'
            );
            $find->execute(['type' => $type]);
        } else {
            $find = $pdo->prepare(
                'SELECT id FROM conversations
                 WHERE conversation_type = :type AND sport_id = :sport_id
                 ORDER BY id LIMIT 1'
            );
            $find->execute(['type' => $type, 'sport_id' => $context['sport_id']]);
        }

        $conversationId = $find->fetchColumn();
        if ($conversationId === false) {
            if (!$createIfMissing) {
                return null;
            }

            $insert = $pdo->prepare(
                'INSERT INTO conversations (conversation_type, sport_id)
                 VALUES (:type, :sport_id)'
            );
            $insert->bindValue(':type', $type, PDO::PARAM_STR);
            $insert->bindValue(
                ':sport_id',
                $context['sport_id'],
                $context['sport_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $insert->execute();
            $conversationId = $pdo->lastInsertId();
        }

        $conversationId = (int) $conversationId;
        sync_chat_group_members($pdo, $conversationId, $context['participant_ids']);
        return $conversationId;
    }

    $participantIds = $context['participant_ids'];
    sort($participantIds, SORT_NUMERIC);
    $conditions = 'c.sport_id IS NULL';
    $parameters = [$type];

    if ($context['sport_id'] !== null) {
        $conditions = 'c.sport_id = ?';
        $parameters[] = $context['sport_id'];
    }

    $find = $pdo->prepare(
        "SELECT c.id FROM conversations c
         WHERE c.conversation_type = ?
           AND {$conditions}
           AND EXISTS (
               SELECT 1 FROM conversation_participants cp
               WHERE cp.conversation_id = c.id AND cp.user_id = ?
           )
           AND EXISTS (
               SELECT 1 FROM conversation_participants cp
               WHERE cp.conversation_id = c.id AND cp.user_id = ?
           )
           AND (
               SELECT COUNT(*) FROM conversation_participants cp
               WHERE cp.conversation_id = c.id
           ) = 2
         ORDER BY c.id LIMIT 1"
    );
    $parameters[] = $participantIds[0];
    $parameters[] = $participantIds[1];
    $find->execute($parameters);
    $conversationId = $find->fetchColumn();

    if ($conversationId !== false) {
        return (int) $conversationId;
    }

    if (!$createIfMissing) {
        return null;
    }

    $insertConversation = $pdo->prepare(
        'INSERT INTO conversations (conversation_type, sport_id)
         VALUES (:type, :sport_id)'
    );
    $insertConversation->bindValue(':type', $type, PDO::PARAM_STR);
    $insertConversation->bindValue(
        ':sport_id',
        $context['sport_id'],
        $context['sport_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $insertConversation->execute();
    $conversationId = (int) $pdo->lastInsertId();

    $insertParticipant = $pdo->prepare(
        'INSERT INTO conversation_participants (conversation_id, user_id)
         VALUES (:conversation_id, :user_id)'
    );
    foreach ($participantIds as $participantId) {
        $insertParticipant->execute([
            'conversation_id' => $conversationId,
            'user_id' => $participantId,
        ]);
    }

    return $conversationId;
}

function get_chat_contacts(PDO $pdo, string $role, ?int $sportId = null): array
{
    if ($role === 'player' || $role === 'coach') {
        if ($sportId === null) {
            return [];
        }
        $query = $pdo->prepare(
            'SELECT id, name, email FROM users
             WHERE role = :role AND sport_id = :sport_id
             ORDER BY name, id'
        );
        $query->execute(['role' => $role, 'sport_id' => $sportId]);
    } else {
        $query = $pdo->prepare(
            'SELECT id, name, email FROM users WHERE role = :role ORDER BY name, id'
        );
        $query->execute(['role' => $role]);
    }

    return $query->fetchAll();
}
