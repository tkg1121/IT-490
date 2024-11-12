#!/bin/bash

# Exit on any error
set -e

# Variables
USER_HOME="/home/ashleys"
PROJECT_DIR="$USER_HOME/IT-490/frontend"
PACKAGE_DIR="/tmp/package"  # Assuming the package is extracted to /tmp/package
APACHE_CONF_SOURCE="$PACKAGE_DIR/000-default.conf"
APACHE_CONF_DEST="/etc/apache2/sites-available/000-default.conf"

# Create the IT-490/frontend directory if it doesn't exist
if [ ! -d "$PROJECT_DIR" ]; then
    mkdir -p "$PROJECT_DIR"
    chown ashleys:ashleys "$USER_HOME/IT-490" "$PROJECT_DIR"
    echo "Created directory $PROJECT_DIR"
fi

# Copy all files from the package's 'frontend' directory to the frontend directory
if [ -d "$PACKAGE_DIR/frontend/" ]; then
    cp -r "$PACKAGE_DIR/frontend/"* "$PROJECT_DIR/"
    chown -R ashleys:ashleys "$PROJECT_DIR"
    echo "Copied frontend files to $PROJECT_DIR"
else
    echo "Error: 'frontend' directory not found in package"
    exit 1
fi

# Install required software
sudo apt update && sudo apt install -y apache2 php php-cli php-curl php-mbstring php-xml php-mysql php-zip

# Install PHP dependencies using Composer (if composer.json exists)
if [ -f "$PROJECT_DIR/composer.json" ]; then
    sudo -u ashleys composer install -d "$PROJECT_DIR"
    echo "PHP dependencies installed"
else
    echo "composer.json not found in $PROJECT_DIR, skipping Composer install"
fi

# Copy Apache configuration file
if [ -f "$APACHE_CONF_SOURCE" ]; then
    sudo cp "$APACHE_CONF_SOURCE" "$APACHE_CONF_DEST"
    echo "Copied Apache configuration file"
else
    echo "Apache configuration file not found in package"
    exit 1
fi

# Restart Apache
sudo systemctl restart apache2
echo "Apache restarted"

# Set up firewall rules
sudo ufw allow from 68.197.69.8 to any port 22 proto tcp
sudo ufw allow from 68.197.69.8 to any port 80 proto tcp
sudo ufw allow from 68.197.69.8 to any port 443 proto tcp
sudo ufw allow from 24.185.203.96 to any port 80 proto tcp
sudo ufw allow from 127.0.0.1 to any port 80 proto tcp  # Allow localhost access
sudo ufw deny 80/tcp
sudo ufw deny 443/tcp
sudo ufw reload
echo "Firewall rules updated"

echo "Frontend setup completed successfully."

