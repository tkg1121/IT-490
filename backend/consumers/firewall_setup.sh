#!/bin/bash

# ===========================
# firewall_setup.sh
# ===========================

# Description:
# This script configures UFW firewall rules automatically based on the machine's IP address.
# It detects the IP address from the eth1 interface, determines the environment and role,
# and applies the appropriate firewall rules.

# Exit immediately if a command exits with a non-zero status
set -e

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
# Determine Environment and Role Based on IP Address
# ===========================

# Define IP mappings for each environment
declare -A IP_ENV_ROLE_MAP=(
    ["10.116.0.2"]="PRODUCTION:DATABASE"
    ["10.116.0.3"]="PRODUCTION:DMZ"
    ["10.116.0.4"]="PRODUCTION:FRONTEND"
    ["10.108.0.2"]="QA:FRONTEND"
    ["10.108.0.3"]="QA:DMZ"
    ["10.108.0.4"]="QA:DATABASE"
    ["10.116.0.2"]="STANDBY:DMZ"
    ["10.116.0.3"]="STANDBY:DATABASE"
    ["10.116.0.4"]="STANDBY:FRONTEND"
)

ENV_ROLE="${IP_ENV_ROLE_MAP[$IP_ADDRESS]}"

if [ -z "$ENV_ROLE" ]; then
    echo "Error: IP address $IP_ADDRESS does not match any known environment and role."
    exit 1
fi

ENVIRONMENT=$(echo "$ENV_ROLE" | cut -d: -f1)
ROLE=$(echo "$ENV_ROLE" | cut -d: -f2)

echo "Detected Environment: $ENVIRONMENT"
echo "Detected Role: $ROLE"

# ===========================
# Set IP Addresses Based on Environment
# ===========================

case "$ENVIRONMENT" in
    "PRODUCTION")
        DATABASE_IP="10.116.0.2"
        FRONTEND_IP="10.116.0.4"
        DMZ_IP="10.116.0.3"
        ;;
    "QA")
        DATABASE_IP="10.108.0.4"
        FRONTEND_IP="10.108.0.2"
        DMZ_IP="10.108.0.3"
        ;;
    "STANDBY")
        DATABASE_IP="10.116.0.3"
        FRONTEND_IP="10.116.0.4"
        DMZ_IP="10.116.0.2"
        ;;
    *)
        echo "Error: Unknown environment."
        exit 1
        ;;
esac

echo "Configured IPs:"
echo "  DATABASE_IP: $DATABASE_IP"
echo "  FRONTEND_IP: $FRONTEND_IP"
echo "  DMZ_IP: $DMZ_IP"

echo "Configuring firewall for the $ROLE role in $ENVIRONMENT environment (IP: $IP_ADDRESS)."

# ===========================
# Reset UFW and Set Default Policies
# ===========================


#BS firewall rules
sudo ufw allow from 1
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

#============================
#Common internal Allows
#============================
#internal rules

sudo ufw allow out to 10.116.0.2
sudo ufw allow from 10.116.0.2
sudo ufw allow from 10.116.0.3
sudo ufw allow out to 10.116.0.3
sudo ufw allow out to 10.116.0.4
sudo ufw allow from 10.116.0.4

#qa

sudo ufw allow from 10.108.0.4
sudo ufw allow out to 10.108.0.4

sudo ufw allow from 10.108.0.2
sudo ufw allow to 10.108.0.2

sudo ufw allow from 10.108.0.3
sudo ufw allow to 10.108.0.3

#HSB allow

sudo ufw allow from 64.227.3.148
sudo ufw allow from 192.168.193.0/24

#DB replicate

sudo ufw allow from 206.189.198.22
sudo ufw allow from 159.223.115.151

#OSSIM and Nagios panel
sudo ufw allow from 10.147.18.197
sudo ufw allow from 192.168.193.197
sudo ufw allow from 192.168.193.167
# ===========================
# Role-Specific Allow Rules
# ===========================

case "$ROLE" in
    "FRONTEND")
        # Allow HTTP (80/tcp) and HTTPS (443/tcp) from specific external IPs
        sudo ufw allow from 209.120.218.21 to any port 80,443 proto tcp
        sudo ufw allow from 68.197.69.8 to any port 80,443 proto tcp
        sudo ufw allow from 24.185.203.96 to any port 80 proto tcp

        # Allow connections to Database server on MySQL port
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
