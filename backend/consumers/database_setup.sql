CREATE USER 'dbadmin'@'%' IDENTIFIED BY 'dbadmin';


GRANT ALL PRIVILEGES ON *.* TO 'dbadmin'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;

CREATE DATABASE IF NOT EXISTS movie_reviews_db;
-- Switch to the movie_reviews_db database
USE movie_reviews_db;

-- Drop and recreate the movies table
DROP TABLE IF EXISTS movies;
CREATE TABLE movies (
    imdb_id VARCHAR(20) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    year INT,
    genre VARCHAR(255),
    plot TEXT,
    poster VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rating VARCHAR(10)
);

-- Drop and recreate the review_likes_dislikes table
DROP TABLE IF EXISTS review_likes_dislikes;
CREATE TABLE review_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    review_id INT,
    user_id INT,
    like_dislike ENUM('like', 'dislike'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drop and recreate the reviews table
DROP TABLE IF EXISTS reviews;
CREATE TABLE reviews (
    review_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    movie_id VARCHAR(20),
    imdb_id VARCHAR(20),
    user_id INT,
    review_text TEXT,
    rating INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Drop and recreate the watchlist table
DROP TABLE IF EXISTS watchlist;
CREATE TABLE watchlist (
    watchlist_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    imdb_id VARCHAR(20),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the social_media database if it doesn't exist
CREATE DATABASE IF NOT EXISTS social_media;

-- Use the social_media database
USE social_media;

-- Table for storing posts
CREATE TABLE IF NOT EXISTS posts (
    post_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id),
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Table for storing comments related to posts
CREATE TABLE IF NOT EXISTS comments (
    comment_id INT NOT NULL AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (comment_id),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Table for storing likes and dislikes for posts
CREATE TABLE IF NOT EXISTS post_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    like_dislike ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Table for storing likes and dislikes for comments
CREATE TABLE IF NOT EXISTS comment_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    like_dislike ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Create the user_auth database if it doesn't exist (reference database)
CREATE DATABASE IF NOT EXISTS user_auth;

-- Use the user_auth database
USE user_auth;

-- Table for storing user information
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    session_token VARCHAR(255),
    last_activity DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
