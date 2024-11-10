#!/bin/bash

# Exit on any error
set -e

# Update system
sudo apt update && sudo apt upgrade -y

# Install required software
sudo apt install -y apache2 php php-cli php-curl php-mbstring php-xml php-mysql php-zip

# Install Composer
if ! command -v composer &> /dev/null; then
    sudo apt install -y composer
fi

# Install PHP dependencies
composer install

# Set up Apache configuration
sudo cp /home/ashleys/IT-490/frontend/apache.conf /etc/apache2/sites-available/000-default.conf
sudo systemctl restart apache2

echo "Frontend setup completed successfully."

