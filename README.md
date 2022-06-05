
# JW Public Ministry Organizer App

This is a great tool to organize your congregation public ministry service.


## Demo

Please see my videos [here](https://www.youtube.com/channel/UC98z7F9PB8AF-ZPcgIz4FNw/videos).


## Basic features

- You can create multiple groups / places where your congregation will make public ministry
- Custom service day and time for each groups
- Easy to use for publishers
- Four right privilige in all group
- Mobile friendly
- Multi language support

Based on [Laravel 8](https://laravel.com/)


## Supported languages

- Hungarian
- English

Any help are welcomed! :)
## Requirements

- A webserver, running PHP 8.0.7 or later
- An existing email address, for email notifications. (You can use smtp, php mail or sendmail)
- You need to run cron for scheduled jobs. [Check documentation](https://laravel.com/docs/8.x/scheduling#running-the-scheduler)
- Mysql / MariaDB database
- You must set your domain's root path to "/public" folder.
- Public domain name

## PHP REQUIMENTS
- Minimum PHP 8.0.7 
- Allow URL fopen
- Enable intl extension
- BCMath PHP Extension
- Ctype PHP Extension
- Fileinfo PHP Extension
- JSON PHP Extension
- Mbstring PHP Extension
- OpenSSL PHP Extension
- PDO PHP Extension
- Tokenizer PHP Extension
- XML PHP Extension
## Security & protection

There are some basic protection in login and registration system.
1. Basic rate limit
2. Basic spam protection that try to catch robots
3. You can enable google recaptcha (not neccessary if you not want to)

- User's name and phone number stored in encrypted format in database.
- User's can use 2FA login (you can set this in the "Profile" menu.

We recommend to use https connection. You can enable it into "Administration/Settings" menu.
## Installation

1. Upload all files to your webserver.
2. Set your public path root folder to "/public" directory. (This is important!)
3. Open your site url, and make the step-by-step setup to install.

    
## FAQ

#### Can I use this software on shared hosting system?

Yes, just see requirements.

#### Can I translate this software into other languages?

YES! Please contact us, or fork this repo and send your translation.
Any help are welcomed. :)

#### I have an error, what can I do?

Please send us your laravel.log file from "/storage/logs" folder, to analyze your problem.

## About me

I'm a pioneer and love public ministry service! I'm living in Hungary.


## License

[MIT](https://choosealicense.com/licenses/mit/)

