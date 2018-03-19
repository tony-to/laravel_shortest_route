# Laravel Shortest Route


## Required packages:

Required packages:
Laravel UUID (https://github.com/webpatser/laravel-uuid)
Google Map API for Laravel (https://github.com/alexpechkarev/google-maps)

## Documentation

#### Preparation

1. Create Google Map Direction API key in Google API console
2. Update the API key in config/googlemaps.php
3. Use Docker container to create the environment and create required table by following command

```bash
$ sudo docker-compose up --build
$ sudo docker-compose exec app php artisan migrate
```

#### Result

Please refer to the API document (https://documenter.getpostman.com/view/3899550/laravel-shortest-route/RVnZgd9X)
