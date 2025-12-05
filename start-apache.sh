#!/bin/bash
set -e
# Ajusta Apache a $PORT si Render lo define; si no, usa 80.
PORT_TO_USE=${PORT:-80}
# Solo tocar si es distinto de 80
if [ "$PORT_TO_USE" != "80" ]; then
  sed -ri "s/^Listen 80$/Listen ${PORT_TO_USE}/" /etc/apache2/ports.conf || true
  sed -ri "s#<VirtualHost \*:80>#<VirtualHost *:${PORT_TO_USE}>#g" /etc/apache2/sites-available/000-default.conf || true
fi
exec apache2-foreground
