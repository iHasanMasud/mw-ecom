# Getting started

## Installation



Clone the repository

    git clone https://github.com/iHasanMasud/mw-ecom.git

Switch to the repo folder

    cd mw-ecom

Copy the example env file and make the required configuration changes in the .env file

    cp .env.example .env

Install application dependencies using composer

    composer install

Generate a new application key

    php artisan key:generate

Clear configuration cache

    php artisan config:clear

Cache configuration

    php artisan config:cache

Link Storage/app/public to public folder

    php artisan storage:link

Run the database migrations

    php artisan migrate
    
*Import the SQL file located project root directory


Install frontend dependencies using npm

    npm install

Watch for changes in the frontend code and run the build process

    npm run watch

Start the local development server

    php artisan serve

You can now access the server at http://127.0.0.1:8000

