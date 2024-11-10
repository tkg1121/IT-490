#!/bin/bash

# Define package name and version
PACKAGE_NAME="frontend"  # Change as needed
VERSION=$(date +%s)

# Create package directory
mkdir -p /home/ashleys/IT-490/deployment-server/packages/${PACKAGE_NAME}_v${VERSION}/files

# Copy files into package
cp -r /home/ashleys/IT-490/frontend/* packages/${PACKAGE_NAME}_v${VERSION}/files/

# Copy setup script into package
cp setup.sh packages/${PACKAGE_NAME}_v${VERSION}/

# Zip the package
cd packages
zip -r ${PACKAGE_NAME}_v${VERSION}.zip ${PACKAGE_NAME}_v${VERSION}/
cd ..

# Send the package via RabbitMQ
php send_package.php packages/${PACKAGE_NAME}_v${VERSION}.zip ${PACKAGE_NAME}

