#!/bin/bash

# Exit on any error
set -e

# Variables
USER_HOME="/home/ashleys"
PROJECT_DIR="$USER_HOME/IT-490/frontend"

# Determine the directory where the script is located
PACKAGE_DIR="$(dirname "$(realpath "$0")")"

APACHE_CONF_SOURCE="$PACKAGE_DIR/000-default.conf"
APACHE_CONF_DEST="/etc/apache2/sites-available/000-default.conf"

# Create the IT-490/frontend directory if it doesn't exist
if [ ! -d "$PROJECT_DIR" ]; then
    mkdir -p "$PROJECT_DIR"
    chown ashleys:ashleys "$USER_HOME/IT-490" "$PROJECT_DIR"
    echo "Created directory $PROJECT_DIR"
fi

# Copy all files from the package to the frontend directory
cp -r "$PACKAGE_DIR/"* "$PROJECT_DIR/"
chown -R ashleys:ashleys "$PROJECT_DIR"
echo "Copied package files to $PROJECT_DIR"

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

# Execute firewall setup
if [ -f "$PACKAGE_DIR/firewall_setup.sh" ]; then
    chmod +x "$PACKAGE_DIR/firewall_setup.sh"
    sudo "$PACKAGE_DIR/firewall_setup.sh" FRONTEND
    echo "Firewall configured using firewall_setup.sh"
else
    echo "firewall_setup.sh not found in package. Skipping firewall configuration."
fi

echo "Frontend setup completed successfully."
