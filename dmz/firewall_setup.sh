#!/bin/bash

# ===========================
# firewall_setup.sh
# ===========================

# Description:
# This script configures UFW firewall rules based on the machine's role and environment.
# It detects the IP address from the eth1 interface and applies the rules accordingly.
# Environments:
#   - PRODUCTION
#   - QA
#   - STANDBY
# Roles:
#   - DATABASE
#   - FRONTEND
#   - DMZ

# Exit immediately if a command exits with a non-zero status
set -e

# ===========================
# Usage Information
# ===========================

usage() {
    echo "Usage: sudo ./firewall_setup.sh [ENVIRONMENT]"
    echo "Environments:"
    echo "  PRODUCTION"
    echo "  QA"
    echo "  STANDBY"
    exit 1
}

# ===========================
# Check for Environment Argument
# ===========================

if [ "$#" -ne 1 ]; then
    echo "Error: Missing environment argument."
    usage
fi

ENVIRONMENT_INPUT=$(echo "$1" | tr '[:lower:]' '[:upper:]')

if [[ "$ENVIRONMENT_INPUT" != "PRODUCTION" && "$ENVIRONMENT_INPUT" != "QA" && "$ENVIRONMENT_INPUT" != "STANDBY" ]]; then
    echo "Error: Invalid environment specified."
    usage
fi

ENVIRONMENT="$ENVIRONMENT_INPUT"

# ===========================
# Get IP Address of eth1
# ===========================

IP_ADDRESS=$(ip addr show eth1 | grep "inet " | awk '{print $2}' | cut -d/ -f1)

if [ -z "$IP_ADDRESS" ]; then
    echo "Error: Could not detect IP address on eth1 interface."
    exit 1
fi

echo "Detected IP address: $IP_ADDRESS"

# ===========================
# Determine Role Based on IP Address and Environment
# ===========================

# Define IP mappings for each environment
declare -A PRODUCTION_IPS=(
    ["10.116.0.2"]="DATABASE"
    ["10.116.0.3"]="DMZ"
    ["10.116.0.4"]="FRONTEND"
)

declare -A QA_IPS=(
    ["10.108.0.2"]="FRONTEND"
    ["10.108.0.3"]="DMZ"
    ["10.108.0.4"]="DATABASE"
)

declare -A STANDBY_IPS=(
    ["10.116.0.2"]="DMZ"
    ["10.116.0.3"]="DATABASE"
    ["10.116.0.4"]="FRONTEND"
)

case "$ENVIRONMENT" in
    "PRODUCTION")
        ROLE="${PRODUCTION_IPS[$IP_ADDRESS]}"
        DATABASE_IP="10.116.0.2"
        FRONTEND_IP="10.116.0.4"
        DMZ_IP="10.116.0.3"
        ;;
    "QA")
        ROLE="${QA_IPS[$IP_ADDRESS]}"
        DATABASE_IP="10.108.0.4"
        FRONTEND_IP="10.108.0.2"
        DMZ_IP="10.108.0.3"
        ;;
    "STANDBY")
        ROLE="${STANDBY_IPS[$IP_ADDRESS]}"
        DATABASE_IP="10.116.0.3"
        FRONTEND_IP="10.116.0.4"
        DMZ_IP="10.116.0.2"
        ;;
    *)
        echo "Error: Unknown environment."
        exit 1
        ;;
esac

if [ -z "$ROLE" ]; then
    echo "Error: IP address $IP_ADDRESS does not match any known role in environment $ENVIRONMENT."
    exit 1
fi

echo "Detected Role: $ROLE"

echo "Configuring firewall for the $ROLE role in $ENVIRONMENT environment (IP: $IP_ADDRESS)."

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
sudo ufw allow from 128.235.0.0/16 to any port 22 proto tcp

# Allow HTTP (80/tcp) and HTTPS (443/tcp) from specific network
sudo ufw allow from 128.235.0.0/16 to any port 80 proto tcp
sudo ufw allow from 128.235.0.0/16 to any port 443 proto tcp

# ===========================
# Role-Specific Allow Rules
# ===========================

case "$ROLE" in
    "FRONTEND")
        # Allow HTTP (80/tcp) and HTTPS (443/tcp) from specific external IPs
        sudo ufw allow from 209.120.218.21 to any port 80,443 proto tcp
        sudo ufw allow from 68.197.69.8 to any port 80,443 proto tcp
        sudo ufw allow from 24.185.203.96 to any port 80 proto tcp

        # Allow connections to Database server on MySQL port from specific servers
        sudo ufw allow out to $DATABASE_IP port 3306 proto tcp
        sudo ufw allow from $DATABASE_IP to any port 3306 proto tcp

        # Allow RabbitMQ connections to DATABASE server
        sudo ufw allow out to $DATABASE_IP port 5672 proto tcp
        sudo ufw allow out to $DATABASE_IP port 15672 proto tcp
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

        # Allow RabbitMQ ports from localhost
        sudo ufw allow from 127.0.0.1 to any port 5672 proto tcp
        sudo ufw allow from 127.0.0.1 to any port 15672 proto tcp

        # Allow outgoing RabbitMQ connections to localhost
        sudo ufw allow out to 127.0.0.1 port 5672 proto tcp
        sudo ufw allow out to 127.0.0.1 port 15672 proto tcp
        ;;
    "DMZ")
        # Allow connections to Database on RabbitMQ ports
        sudo ufw allow out to $DATABASE_IP port 5672 proto tcp
        sudo ufw allow out to $DATABASE_IP port 15672 proto tcp

        # Allow outgoing HTTP and HTTPS to anywhere
        sudo ufw allow out 80/tcp
        sudo ufw allow out 443/tcp

        # Allow outgoing HTTP to specific IP
        sudo ufw allow out to 24.185.203.96 port 80 proto tcp
        ;;
    *)
        echo "Error: Unknown role."
        exit 1
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

# ===========================
# Deny Rules (After Allow Rules)
# ===========================

# Deny incoming HTTP and HTTPS from all other sources
sudo ufw deny in 80/tcp
sudo ufw deny in 443/tcp

# Deny incoming HTTP and HTTPS for IPv6
sudo ufw deny in 80/tcp comment 'IPv6 HTTP deny'
sudo ufw deny in 443/tcp comment 'IPv6 HTTPS deny'

# ===========================
# Enable UFW and Display Status
# ===========================

# Enable UFW without confirmation
sudo ufw --force enable

# Display the UFW status
sudo ufw status numbered

echo "Firewall configuration for the $ROLE role in $ENVIRONMENT environment has been applied successfully."
