#!/bin/bash

# Update system packages
echo "Updating system packages..."
sudo apt update

# Install PHP and common modules
echo "Installing PHP and necessary modules..."
sudo apt install -y php php-cli php-curl php-mbstring php-xml php-mysql php-zip

# Check if Composer is installed
if ! command -v composer &> /dev/null
then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
else
    echo "Composer is already installed."
fi

# Verify Composer installation
composer --version

# Install PhpAmqpLib and Dotenv in the dmz project
echo "Navigating to the dmz directory and installing required libraries..."
cd /home/alisa-maloku/Documents/GitHub/IT-490/dmz

echo "Installing PhpAmqpLib for RabbitMQ and Dotenv for environment variables..."
composer require php-amqplib/php-amqplib vlucas/phpdotenv

echo "DMZ dependencies installed."

echo "Installation complete. You can now run your RabbitMQ consumer scripts."

