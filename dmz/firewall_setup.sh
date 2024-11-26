#!/bin/bash

# ===========================
# firewall_setup.sh
# ===========================

# Description:
# This script configures UFW firewall rules based on the machine's role.
# Roles:
#   - DATABASE: For Database and RabbitMQ server (IP: 10.108.0.4)
#   - FRONTEND: For Frontend/Webserver (IP: 10.108.0.2)
#   - DMZ:      For DMZ server (IP: 10.108.0.3)

# Exit immediately if a command exits with a non-zero status
set -e

# ===========================
# Usage Information
# ===========================

usage() {
    echo "Usage: sudo ./firewall_setup.sh [ROLE]"
    echo "Roles:"
    echo "  DATABASE - for Database and RabbitMQ server (IP: 10.108.0.4)"
    echo "  FRONTEND - for Frontend/Webserver (IP: 10.108.0.2)"
    echo "  DMZ      - for DMZ server (IP: 10.108.0.3)"
    exit 1
}

# ===========================
# Check for Role Argument
# ===========================

if [ "$#" -ne 1 ]; then
    echo "Error: Missing role argument."
    usage
fi

ROLE_INPUT=$(echo "$1" | tr '[:lower:]' '[:upper:]')

if [[ "$ROLE_INPUT" != "DATABASE" && "$ROLE_INPUT" != "FRONTEND" && "$ROLE_INPUT" != "DMZ" ]]; then
    echo "Error: Invalid role specified."
    usage
fi

ROLE="$ROLE_INPUT"

# ===========================
# Variables
# ===========================

# Define the roles and their corresponding IP addresses in 10.108.0.0/20
DATABASE_IP="10.108.0.4"
FRONTEND_IP="10.108.0.2"
DMZ_IP="10.108.0.3"

# Assign Internal IP based on Role
case "$ROLE" in
    "DATABASE")
        INTERNAL_IP="$DATABASE_IP"
        ;;
    "FRONTEND")
        INTERNAL_IP="$FRONTEND_IP"
        ;;
    "DMZ")
        INTERNAL_IP="$DMZ_IP"
        ;;
    *)
        echo "Error: Unknown role."
        exit 1
        ;;
esac

echo "Configuring firewall for the $ROLE role (IP: $INTERNAL_IP)."

# ===========================
# Reset UFW and Set Default Policies
# ===========================

# Reset UFW to default settings (non-interactive)
sudo ufw --force reset

# Set default policies
sudo ufw default deny incoming
sudo ufw default allow outgoing

# ===========================
# Common Allow Rules
# ===========================

# Allow SSH from specific IPs
sudo ufw allow from 68.197.69.8 to any port 22 proto tcp
sudo ufw allow from 128.235.13.72 to any port 22 proto tcp

# ===========================
# Role-Specific Allow Rules
# ===========================

case "$ROLE" in
    "FRONTEND")
        # Allow HTTP (80/tcp) and HTTPS (443/tcp) from specific external IPs
        sudo ufw allow from 209.120.218.21 to any port 80,443 proto tcp
        sudo ufw allow from 68.197.69.8 to any port 80,443 proto tcp
        sudo ufw allow from 24.185.203.96 to any port 80 proto tcp
        ;;

    "DATABASE")
        # Allow RabbitMQ ports from Frontend and DMZ
        sudo ufw allow from $FRONTEND_IP to any port 5672 proto tcp
        sudo ufw allow from $FRONTEND_IP to any port 15672 proto tcp
        sudo ufw allow from $DMZ_IP to any port 5672 proto tcp
        sudo ufw allow from $DMZ_IP to any port 15672 proto tcp

        # Allow MySQL from localhost
        sudo ufw allow from 127.0.0.1 to any port 3306 proto tcp

        # Allow outgoing connections to Frontend and DMZ on RabbitMQ ports
        sudo ufw allow out to $FRONTEND_IP port 5672 proto tcp
        sudo ufw allow out to $FRONTEND_IP port 15672 proto tcp
        sudo ufw allow out to $DMZ_IP port 5672 proto tcp
        sudo ufw allow out to $DMZ_IP port 15672 proto tcp

        # Allow outgoing MySQL connections to localhost
        sudo ufw allow out to 127.0.0.1 port 3306 proto tcp
        ;;

    "DMZ")
        # Allow connections to Database on RabbitMQ ports from specific network
        sudo ufw allow from 192.168.2.0/24 to $DATABASE_IP port 5672 proto tcp
        sudo ufw allow from 192.168.2.0/24 to $DATABASE_IP port 15672 proto tcp

        # Allow outgoing HTTP and HTTPS to anywhere
        sudo ufw allow out 80/tcp
        sudo ufw allow out 443/tcp

        # Allow outgoing HTTP to specific IP
        sudo ufw allow out to 24.185.203.96 port 80 proto tcp
        ;;
esac

# ===========================
# Additional Allow-In Rules for Specific Ports
# ===========================

if [[ "$ROLE" == "DATABASE" ]]; then
    # Ensure incoming on 5672/tcp and 15672/tcp from Frontend and DMZ
    sudo ufw allow from $FRONTEND_IP to any port 5672 proto tcp
    sudo ufw allow from $FRONTEND_IP to any port 15672 proto tcp
    sudo ufw allow from $DMZ_IP to any port 5672 proto tcp
    sudo ufw allow from $DMZ_IP to any port 15672 proto tcp
fi


#============================
# General Allow Rules
#============================

sudo ufw allow from 128.235.0.0/16 to any port 443
sudo ufw allow from 128.235.0.0/16 to any port 80

# ===========================
# Deny Rules (After Allow Rules)
# ===========================

# Deny incoming HTTP and HTTPS from all other sources
sudo ufw deny in 80/tcp
sudo ufw deny in 443/tcp

# Deny incoming HTTP and HTTPS for IPv6
sudo ufw deny in 80/tcp comment 'IPv6 HTTP deny'
sudo ufw deny in 443/tcp comment 'IPv6 HTTPS deny'

sudo ufw insert 1 allow from 128.235.0.0/16 to any port 22


# ===========================
# Enable UFW and Display Status
# ===========================

# Enable UFW without confirmation
sudo ufw --force enable

# Display the UFW status
sudo ufw status numbered

echo "Firewall configuration for the $ROLE role has been applied successfully."