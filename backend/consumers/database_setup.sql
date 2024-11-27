-- Create the user_auth database
CREATE DATABASE IF NOT EXISTS user_auth;
USE user_auth;

-- Table for storing user information
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    session_token VARCHAR(255),
    two_factor_code VARCHAR(255),
    two_factor_expires DATETIME,
    last_activity DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- Table for managing friendships between users
CREATE TABLE IF NOT EXISTS friends (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (user_id, friend_id)
);

-- Create the movie_reviews_db database
CREATE DATABASE IF NOT EXISTS movie_reviews_db;
USE movie_reviews_db;

-- Create the movies table
CREATE TABLE IF NOT EXISTS movies (
    imdb_id VARCHAR(20) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    year INT,
    genre VARCHAR(255),
    plot TEXT,
    poster VARCHAR(255),
    rating VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the reviews table
CREATE TABLE IF NOT EXISTS reviews (
    review_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    imdb_id VARCHAR(20) NOT NULL,
    user_id INT NOT NULL,
    review_text TEXT,
    rating INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imdb_id) REFERENCES movies(imdb_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Create the watchlist table
CREATE TABLE IF NOT EXISTS watchlist (
    watchlist_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    imdb_id VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE,
    FOREIGN KEY (imdb_id) REFERENCES movies(imdb_id) ON DELETE CASCADE,
    UNIQUE KEY unique_watchlist (user_id, imdb_id)
);

-- Create the social_media database
CREATE DATABASE IF NOT EXISTS social_media;
USE social_media;

-- Table for storing posts
CREATE TABLE IF NOT EXISTS posts (
    post_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Table for storing comments related to posts
CREATE TABLE IF NOT EXISTS comments (
    comment_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Table for storing likes and dislikes for posts
CREATE TABLE IF NOT EXISTS post_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    like_dislike ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_like_dislike (post_id, user_id)
);

-- Table for storing likes and dislikes for comments
CREATE TABLE IF NOT EXISTS comment_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    like_dislike ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_like_dislike (comment_id, user_id)
);
