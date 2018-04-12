# Integrations app
Convertful Integrations app

# Install
    $ sudo apt install nginx php-fpm php-mbstring phpunit
    $ composer install
    $ cp nginx.vhost /etc/nginx/sites-enabled/integrations.conf
    
 Add to hosts 127.0.0.1 integrations.convertful.local 

# Tests

    cd tests
    phpunit

# Data format

    {
        "email": "value",
        "first_name": "value",
        "last_name": "value",
        "name": "value",
        "site": "value",
        "company": "value",
        "phone": "value",
        "meta": {
            "Custom Field": "custom value",
            "Hidden Field": "hidden value",
            "Hidden Field2": "",
            "Название еще одного поля": "Значение поля",
        }
    }