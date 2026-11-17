ALTER TABLE conversations
    MODIFY conversation_type
        ENUM('team', 'player_coach', 'coaches_board', 'coach_hos', 'player_player') NOT NULL;
