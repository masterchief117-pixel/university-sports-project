DROP DATABASE IF EXISTS university_sports;

CREATE DATABASE university_sports
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE university_sports;

CREATE TABLE sports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sports_name (name)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('player', 'coach', 'hos') NOT NULL,
    sport_id INT UNSIGNED NULL,
    requested_sport_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_sport (role, sport_id),
    KEY idx_users_requested_sport (requested_sport_id),
    CONSTRAINT fk_users_sport
        FOREIGN KEY (sport_id) REFERENCES sports (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_users_requested_sport
        FOREIGN KEY (requested_sport_id) REFERENCES sports (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_type ENUM('team', 'player_coach', 'coaches_board', 'coach_hos') NOT NULL,
    sport_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversations_type_sport (conversation_type, sport_id),
    CONSTRAINT fk_conversations_sport
        FOREIGN KEY (sport_id) REFERENCES sports (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE conversation_participants (
    conversation_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversation_id, user_id),
    KEY idx_conversation_participants_user (user_id),
    CONSTRAINT fk_participants_conversation
        FOREIGN KEY (conversation_id) REFERENCES conversations (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_participants_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_conversation_created (conversation_id, created_at, id),
    CONSTRAINT fk_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES conversations (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sports (name) VALUES
    ('Basketball'),
    ('Soccer'),
    ('Volleyball'),
    ('Tennis'),
    ('Track and Field'),
    ('Swimming'),
    ('Baseball'),
    ('Softball'),
    ('Rugby'),
    ('Cricket'),
    ('Badminton'),
    ('Table Tennis'),
    ('Wrestling'),
    ('Field Hockey'),
    ('American Football');

INSERT INTO users (name, email, password_hash, role, sport_id) VALUES
    (
        'Dr. Sarah Jenkins',
        'hos@uni.edu',
        '$2y$10$8.ICQHCyCPzS.xygPO4cfuHsHZb6Kuxynn8/uUHOU1.7gY.UhSIXa.',
        'hos',
        NULL
    ),
    (
        'Coach Basketball',
        'coach.bb@uni.edu',
        '$2y$10$8.ICQHCyCPzS.xygPO4cfuHsHZb6Kuxynn8/uUHOU1.7gY.UhSIXa.',
        'coach',
        1
    ),
    (
        'Player Basketball',
        'p1.bb@uni.edu',
        '$2y$10$8.ICQHCyCPzS.xygPO4cfuHsHZb6Kuxynn8/uUHOU1.7gY.UhSIXa.',
        'player',
        1
    );
