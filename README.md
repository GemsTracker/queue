# Gemstracker Queue worker
Project to add queue items to the database and process them from a daemon worker

**Requires** PHP 7.0+

## Intallation
1. Add to composer.json of project, including adding the repository
2. composer update
3. Register your module in your Projects Escort by adding the following static property:
```PHP
    public static $modules = [
        'Gems\Queue' => \Gems\Queue\ModuleSettings::class,
    ];
```

## Bin
The composer package installs a bin file in ```vendor/bin/queue``` that you can use to start the listener

