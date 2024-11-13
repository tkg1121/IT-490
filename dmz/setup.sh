#!/bin/bash

# Variables
USER_HOME="/home/alisa-maloku"
PACKAGE_DIR="/tmp/package"  # Assuming the package is extracted to /tmp/package
DMZ_DIR="$USER_HOME/Documents/GitHub/IT-490/dmz"

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

# Set up firewall rules
sudo ufw --force reset

# Deny incoming HTTP and HTTPS
sudo ufw deny in 80/tcp
sudo ufw deny in 443/tcp

# Allow SSH from specific IP
sudo ufw allow from 68.197.69.8 to any port 22 proto tcp

# Allow RabbitMQ ports from 192.168.2.0/24 to 10.116.0.2
sudo ufw allow from 192.168.2.0/24 to 10.116.0.2 port 5672 proto tcp
sudo ufw allow from 192.168.2.0/24 to 10.116.0.2 port 15672 proto tcp

# Deny incoming HTTP and HTTPS for IPv6
sudo ufw deny in 80/tcp comment 'IPv6 HTTP deny'  # UFW will apply to both IPv4 and IPv6 by default
sudo ufw deny in 443/tcp comment 'IPv6 HTTPS deny'

# Allow outgoing HTTP and HTTPS
sudo ufw allow out 80/tcp
sudo ufw allow out 443/tcp

# Allow outgoing to specific IP
sudo ufw allow out to 24.185.203.96 port 80 proto tcp

# Enable UFW
sudo ufw --force enable

echo "Firewall rules configured."

echo "Setup completed successfully."
