#!/bin/bash
# Gera certificados auto-assinados para desenvolvimento/intranet
# Para producao, substitua por certificados validos (Let's Encrypt, etc.)

CERT_DIR="$(dirname "$0")/certs"
mkdir -p "$CERT_DIR"

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout "$CERT_DIR/key.pem" \
    -out "$CERT_DIR/cert.pem" \
    -subj "/C=BR/ST=Parana/L=Londrina/O=GML/CN=sistema-cameras.local" \
    -addext "subjectAltName=DNS:sistema-cameras.local,DNS:localhost,IP:127.0.0.1"

chmod 600 "$CERT_DIR/key.pem"
echo "Certificados gerados em $CERT_DIR/"
