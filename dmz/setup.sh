#!/bin/bash

# Exit on any error
set -e

# Variables
USER_HOME="/home/alisa-maloku"
DMZ_DIR="$USER_HOME/Documents/GitHub/IT-490/dmz"

# Determine the directory where the script is located
PACKAGE_DIR="$(dirname "$(realpath "$0")")"

# User credentials
USERNAME="alisa-maloku"
USER_PASSWORD="wg28SKqtU3R72Y"

# Update system
sudo apt update && sudo apt upgrade -y

# Install required software
sudo apt install -y php php-cli php-mbstring php-curl php-xml composer unzip ufw

# Create user if it doesn't exist
if id "$USERNAME" &>/dev/null; then
    echo "User '$USERNAME' already exists."
else
    sudo adduser --gecos "" --disabled-password "$USERNAME"
    echo "$USERNAME:$USER_PASSWORD" | sudo chpasswd
    sudo usermod -aG sudo "$USERNAME"
    echo "User '$USERNAME' created and added to sudoers."
fi

# Create DMZ directory
if [ ! -d "$DMZ_DIR" ]; then
    mkdir -p "$DMZ_DIR"
    sudo chown "$USERNAME":"$USERNAME" "$DMZ_DIR"
    echo "Created directory $DMZ_DIR"
else
    echo "Directory $DMZ_DIR already exists."
fi

# Copy all files from the package to the DMZ directory
cp -r "$PACKAGE_DIR/"* "$DMZ_DIR/"
sudo chown -R "$USERNAME":"$USERNAME" "$DMZ_DIR"
echo "Copied DMZ files to $DMZ_DIR"

# Install PHP dependencies using Composer
cd "$DMZ_DIR"
sudo -u "$USERNAME" composer install
echo "PHP dependencies installed using Composer."

# Move .service files to /etc/systemd/system/
sudo cp "$DMZ_DIR/"*.service /etc/systemd/system/

# Reload systemd daemon
sudo systemctl daemon-reload

# Enable and start services
sudo systemctl enable dmz_consumer.service
sudo systemctl restart dmz_consumer.service

sudo systemctl enable favorites_consumer.service
sudo systemctl restart favorites_consumer.service

sudo systemctl enable trivia_consumer.service
sudo systemctl restart trivia_consumer.service

sudo systemctl enable where_to_watch_consumer.service
sudo systemctl restart where_to_watch_consumer.service

echo "Consumer services enabled and started."

# Execute firewall setup
if [ -f "$PACKAGE_DIR/firewall_setup.sh" ]; then
    chmod +x "$PACKAGE_DIR/firewall_setup.sh"
    sudo "$PACKAGE_DIR/firewall_setup.sh" DMZ
    echo "Firewall configured using firewall_setup.sh"
else
    echo "firewall_setup.sh not found in package. Skipping firewall configuration."
fi

echo "Setup completed successfully."
