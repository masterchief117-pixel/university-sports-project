ALTER TABLE users
    MODIFY role ENUM('player', 'coach', 'hos', 'photographer') NOT NULL;

CREATE TABLE IF NOT EXISTS galleries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sport_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    cover_photo TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_galleries_sport (sport_id),
    CONSTRAINT fk_galleries_sport
        FOREIGN KEY (sport_id) REFERENCES sports (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gallery_id INT UNSIGNED NOT NULL,
    file_path TEXT NOT NULL,
    caption VARCHAR(255) NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_photos_gallery (gallery_id),
    CONSTRAINT fk_photos_gallery
        FOREIGN KEY (gallery_id) REFERENCES galleries (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
