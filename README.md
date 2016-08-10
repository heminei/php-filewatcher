The PHP File Watcher
=========================

This PHP class detects file changes and return it in json format. This class uses SQLite to detect file changes.

How to use:
-----------

- Copy and upload "fileWatcher" folder to the web root.
- Change folder permissions to 777.
- Open your-site.com/fileWatcher/index.php


Example response
----------------

```json
{
	"version": "1.1",
	"notifications": [
		{
            "type": "add",
            "file": "php-filewatcher\\src\\fileWatcher\\FileWatcher.php"
        },
        {
            "type": "edit",
            "file": "php-filewatcher\\src\\fileWatcher\\index.php"
        }
    ]
}
```