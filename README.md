# Shopifyapp
Convertful Integrations app

# Install
sudo apt install php-fpm php-mbstring phpunit

composer install

cp nginx.vhost /etc/nginx/sites-enabled/integrations.conf

Add to hosts 127.0.0.1 integrations.convertful.local 

# Tests

cd tests
phpunit