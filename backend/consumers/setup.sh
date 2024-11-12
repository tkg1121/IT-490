#!/bin/bash

# Exit on any error
set -e

# Variables
USER_HOME="/home/stanley"
PACKAGE_DIR="/tmp/package"  # Assuming the package is extracted to /tmp/package
CONSUMERS_DIR="$USER_HOME/Documents/GitHub/IT-490/backend/consumers"

# MySQL and RabbitMQ credentials
MYSQL_USER="dbadmin"
MYSQL_PASSWORD="dbadmin"
RABBITMQ_USER="T"
RABBITMQ_PASSWORD="dev1121!!@@"

# Update system
sudo apt update && sudo apt upgrade -y

# Install required software
sudo apt install -y mysql-server rabbitmq-server php php-cli php-mbstring php-curl php-xml composer unzip ufw

# Create MySQL user 'dbadmin' accessible from any host with password 'dbadmin'
sudo mysql -u root -e "CREATE USER IF NOT EXISTS '$MYSQL_USER'@'%' IDENTIFIED BY '$MYSQL_PASSWORD';"
sudo mysql -u root -e "GRANT ALL PRIVILEGES ON *.* TO '$MYSQL_USER'@'%' WITH GRANT OPTION;"
sudo mysql -u root -e "FLUSH PRIVILEGES;"

echo "MySQL user '$MYSQL_USER' created with all privileges."

# Execute SQL script to create database structure
sudo mysql -u $MYSQL_USER -p$MYSQL_PASSWORD <<EOF
-- Create the user_auth database
CREATE DATABASE IF NOT EXISTS user_auth;
USE user_auth;

-- Drop and recreate the users table
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    session_token VARCHAR(255),
    last_activity DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create the movie_reviews_db database
CREATE DATABASE IF NOT EXISTS movie_reviews_db;
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

-- Create the social_media database
CREATE DATABASE IF NOT EXISTS social_media;
USE social_media;

-- Drop and recreate the posts table
DROP TABLE IF EXISTS posts;
CREATE TABLE posts (
    post_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Drop and recreate the comments table
DROP TABLE IF EXISTS comments;
CREATE TABLE comments (
    comment_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Drop and recreate the post_likes_dislikes table
DROP TABLE IF EXISTS post_likes_dislikes;
CREATE TABLE post_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    like_dislike ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);

-- Drop and recreate the comment_likes_dislikes table
DROP TABLE IF EXISTS comment_likes_dislikes;
CREATE TABLE comment_likes_dislikes (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    like_dislike ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
);
EOF

echo "MySQL databases and tables created successfully."

# Configure RabbitMQ
sudo rabbitmqctl add_user $RABBITMQ_USER $RABBITMQ_PASSWORD
sudo rabbitmqctl set_user_tags $RABBITMQ_USER administrator
sudo rabbitmqctl set_permissions -p / $RABBITMQ_USER ".*" ".*" ".*"

echo "RabbitMQ user '$RABBITMQ_USER' created and configured."

# Create consumers directory
if [ ! -d "$CONSUMERS_DIR" ]; then
    mkdir -p "$CONSUMERS_DIR"
    chown stanley:stanley "$CONSUMERS_DIR"
    echo "Created directory $CONSUMERS_DIR"
fi

# Copy all files from the package to the consumers directory
cp -r "$PACKAGE_DIR/"* "$CONSUMERS_DIR/"
chown -R stanley:stanley "$CONSUMERS_DIR"
echo "Copied consumer files to $CONSUMERS_DIR"

# Move .service files to /etc/systemd/system/
sudo cp "$CONSUMERS_DIR/"*.service /etc/systemd/system/

# Reload systemd daemon
sudo systemctl daemon-reload

# Enable and start services
sudo systemctl enable auth_consumer.service
sudo systemctl start auth_consumer.service

sudo systemctl enable movies_consumer.service
sudo systemctl start movies_consumer.service

sudo systemctl enable social_media_consumer.service
sudo systemctl start social_media_consumer.service

echo "Consumer services enabled and started."

# Set up firewall rules
sudo ufw reset

# Allow incoming connections
sudo ufw allow from 68.197.69.8 to any port 22 proto tcp
sudo ufw allow from 10.116.0.3 to any port 5672 proto tcp
sudo ufw allow from 10.116.0.3 to any port 15672 proto tcp
sudo ufw allow from 127.0.0.1 to any port 3306 proto tcp
sudo ufw allow from 10.116.0.4 to any port 15672 proto tcp
sudo ufw allow from 10.116.0.4 to any port 5672 proto tcp

# Allow outgoing connections
sudo ufw allow out to 10.116.0.3 port 15672 proto tcp
sudo ufw allow out to 10.116.0.3 port 5672 proto tcp
sudo ufw allow out to 10.116.0.3 port 22 proto tcp
sudo ufw allow out to 127.0.0.1 port 3306 proto tcp
sudo ufw allow out to 10.116.0.4 port 15672 proto tcp
sudo ufw allow out to 10.116.0.4 port 5672 proto tcp

# Enable UFW
echo "y" | sudo ufw enable

echo "Firewall rules configured."

echo "Setup completed successfully."
