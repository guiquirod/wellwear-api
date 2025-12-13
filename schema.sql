SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(30) NOT NULL,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS `garment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('camiseta', 'camisa', 'polo', 'cazadora', 'sudadera', 'chandal', 'jersey', 'abrigo', 'gabardina', 'plumifero', 'vestido', 'pantalon', 'vaquero', 'falda', 'zapatos', 'botines', 'sandalias', 'botas') NOT NULL,
  `sup_type` ENUM('superior', 'inferior') NOT NULL,
  `fabric_type` JSON NOT NULL,
  `sleeve` ENUM('corto', 'largo') NOT NULL,
  `seasons` JSON NOT NULL,
  `picture` VARCHAR(255) NOT NULL,
  `main_color` VARCHAR(255) NOT NULL,
  `pattern` BOOLEAN NOT NULL DEFAULT FALSE,
  `is_second_hand` BOOLEAN NOT NULL DEFAULT FALSE,
  `worn` INT NOT NULL DEFAULT 0,
  `outfited` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `outfit` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `outfit_garment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outfit_id` INT NOT NULL,
  `garment_id` INT NOT NULL,
  FOREIGN KEY (`outfit_id`) REFERENCES `outfit`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`garment_id`) REFERENCES `garment`(`id`) ON DELETE CASCADE,
  UNIQUE (`outfit_id`, `garment_id`)
);

CREATE TABLE IF NOT EXISTS `outfit_calendar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `outfit_id` INT NOT NULL,
  `date_worn` DATE NOT NULL,
  FOREIGN KEY (`outfit_id`) REFERENCES `outfit`(`id`) ON DELETE CASCADE,
  UNIQUE (`outfit_id`, `date_worn`)
);

CREATE INDEX index_date_worn ON outfit_calendar (date_worn);

CREATE TABLE IF NOT EXISTS `user_level` (
  `user_id` INT PRIMARY KEY,
  `level` INT NOT NULL DEFAULT 1,
  `current_points` INT NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `achievement` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `type` ENUM('daily', 'weekly', 'monthly') NOT NULL,
  `points` INT NOT NULL DEFAULT 25
);

CREATE TABLE IF NOT EXISTS `user_achievement_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `achievement_id` INT NOT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`achievement_id`) REFERENCES `achievement`(`id`) ON DELETE CASCADE,
  UNIQUE (`user_id`, `achievement_id`)
);

INSERT INTO `achievement` (`title`, `type`, `points`) VALUES
('He creado un nuevo conjunto', 'weekly', 25),
('He regalado/donado una prenda que ya no usaba', 'weekly', 25),
('He arreglado una prenda', 'weekly', 25),
('He reutilizado una prenda que ya no usaba', 'daily', 25);