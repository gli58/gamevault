-- GameVault CMS Database Setup
-- Run this SQL in phpMyAdmin to create the database structure

CREATE DATABASE IF NOT EXISTS gamevault_cms;
USE gamevault_cms;

-- Users table (admins and registered users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Games table
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category_id INT,
    release_date DATE,
    developer VARCHAR(100),
    rating DECIMAL(3,1) DEFAULT 0.0,
    image_path VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Comments table
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin user (username: admin, password: admin123)
INSERT INTO users (username, password, email, is_admin) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@gamevault.com', 1);

-- Insert sample categories
INSERT INTO categories (name) VALUES 
('Action'),
('Adventure'),
('RPG'),
('Strategy'),
('Sports'),
('Racing'),
('Puzzle'),
('Horror'),
('Simulation'),
('Platform');

-- Insert sample games
INSERT INTO games (title, category_id, release_date, developer, rating, description) VALUES 
('The Witcher 3: Wild Hunt', 3, '2015-05-19', 'CD Projekt Red', 9.3, 'An epic RPG adventure in a fantasy world filled with meaningful choices and impactful consequences.'),
('Grand Theft Auto V', 1, '2013-09-17', 'Rockstar Games', 9.0, 'An action-adventure game set in the fictional state of San Andreas.'),
('Portal 2', 7, '2011-04-19', 'Valve Corporation', 9.5, 'A mind-bending puzzle game with innovative mechanics and hilarious writing.'),
('Civilization VI', 4, '2016-10-21', 'Firaxis Games', 8.5, 'Build an empire to stand the test of time in this turn-based strategy game.'),
('Super Mario Odyssey', 10, '2017-10-27', 'Nintendo', 9.7, 'Join Mario on a massive, globe-trotting 3D adventure.'),
('Resident Evil 4', 8, '2005-01-11', 'Capcom', 9.0, 'Survival horror at its finest with intense action and atmospheric scares.'),
('FIFA 23', 5, '2022-09-30', 'EA Sports', 7.8, 'The world\'s most popular football simulation game.'),
('Forza Horizon 5', 6, '2021-11-09', 'Playground Games', 8.9, 'Open-world racing at its best set in beautiful Mexico.'),
('Cities: Skylines', 9, '2015-03-10', 'Colossal Order', 8.2, 'Build and manage your own modern city in this simulation game.'),
('Stardew Valley', 9, '2016-02-26', 'ConcernedApe', 9.1, 'A farming simulation game with RPG elements and charming pixel art.');

-- Insert sample comments
INSERT INTO comments (game_id, user_id, comment) VALUES 
(1, 1, 'Amazing game! The story and characters are incredible.'),
(1, 1, 'Best RPG I have ever played. Highly recommended!'),
(2, 1, 'Great open-world game with lots of activities.'),
(3, 1, 'Brilliant puzzle design and great co-op mode.'),
(4, 1, 'Perfect strategy game for history lovers.'),
(5, 1, 'Nintendo at their best! So much fun and creativity.');