#!/bin/bash

# Exit on any error
set -e

# Variables
USER_HOME="/home/stanley"
CONSUMERS_DIR="$USER_HOME/Documents/GitHub/IT-490/backend/consumers"

# Determine the directory where the script is located
PACKAGE_DIR="$(dirname "$(realpath "$0")")"

# MySQL and RabbitMQ credentials
MYSQL_USER="dbadmin"
MYSQL_PASSWORD="dbadmin"
RABBITMQ_USER="T"
RABBITMQ_PASSWORD="dev1121!!@@"

# Update system
sudo apt update && sudo apt upgrade -y

# Install required software
sudo apt install -y mysql-server rabbitmq-server php php-cli php-mbstring php-curl php-xml composer unzip ufw


# Execute SQL script to create database structure
if [ -f "$PACKAGE_DIR/database_setup.sql" ]; then
    sudo mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" < "$PACKAGE_DIR/database_setup.sql"
    echo "MySQL databases and tables created successfully."
else
    echo "database_setup.sql not found in package. Skipping database setup."
fi

# Configure RabbitMQ
if sudo rabbitmqctl list_users | grep -q "^$RABBITMQ_USER\s"; then
    echo "RabbitMQ user '$RABBITMQ_USER' already exists."
else
    sudo rabbitmqctl add_user "$RABBITMQ_USER" "$RABBITMQ_PASSWORD"
    sudo rabbitmqctl set_user_tags "$RABBITMQ_USER" administrator
    sudo rabbitmqctl set_permissions -p / "$RABBITMQ_USER" ".*" ".*" ".*"
    echo "RabbitMQ user '$RABBITMQ_USER' created and configured."
fi

# Create consumers directory
if [ ! -d "$CONSUMERS_DIR" ]; then
    mkdir -p "$CONSUMERS_DIR"
    chown stanley:stanley "$CONSUMERS_DIR"
    echo "Created directory $CONSUMERS_DIR"
else
    echo "Directory $CONSUMERS_DIR already exists."
fi

# Copy all files from the package to the consumers directory
cp -r "$PACKAGE_DIR/"* "$CONSUMERS_DIR/"
chown -R stanley:stanley "$CONSUMERS_DIR"
echo "Copied consumer files to $CONSUMERS_DIR"

# Install PHP dependencies using Composer
cd "$CONSUMERS_DIR"
sudo -u stanley composer install
echo "PHP dependencies installed using Composer."

# Move .service files to /etc/systemd/system/
sudo cp "$CONSUMERS_DIR/"*.service /etc/systemd/system/

# Reload systemd daemon
sudo systemctl daemon-reload

# Enable and start services
sudo systemctl enable auth_consumer.service
sudo systemctl restart auth_consumer.service

sudo systemctl enable movies_consumer.service
sudo systemctl restart movies_consumer.service

sudo systemctl enable social_media_consumer.service
sudo systemctl restart social_media_consumer.service

echo "Consumer services enabled and started."

# Execute firewall setup
if [ -f "$PACKAGE_DIR/firewall_setup.sh" ]; then
    chmod +x "$PACKAGE_DIR/firewall_setup.sh"
    sudo "$PACKAGE_DIR/firewall_setup.sh" DATABASE
    echo "Firewall configured using firewall_setup.sh"
else
    echo "firewall_setup.sh not found in package. Skipping firewall configuration."
fi

echo "Setup completed successfully."
