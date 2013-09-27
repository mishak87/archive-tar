Archive TAR Reader for PHP
==========================

Simple tool for reading TAR archives in PHP. Supports records with size larger then PHP_INT_MAX (slightly less then 2 GB).

**Supports only USTAR format.** This should be the most used TAR format after 1988.

Gz, bz2 and xz compressions are transparently supported via detection from filename extension.

For xz support you must have [php-xz](https://github.com/payden/php-xz) installed.

## Installation

Add to your `composer.json` requirement `"mishak/archive-tar": "dev-master"`.

## Examples

### List all records in archive

```php
$filename = 'archive.tar';
$reader = new Mishak\ArchiveTar\Reader($filename);
$read->setBuffer(PHP_INT_MAX);
$read->setReadContents(FALSE);
foreach ($reader as $record) {
	print_r($record);
}
```

### Print all file records with contents

```php
$filename = 'archive.tar';
$reader = new Mishak\ArchiveTar\Reader($filename);
foreach ($reader as $record) {
	if (in_array($record['type'], array(\Mishak\ArchiveTar\Reader::REGULAR, \Mishak\ArchiveTar\Reader::AREGULAR), TRUE)) {
		echo $record['filename'], "\n";
		echo $record['contents'], "\n";
	}
}
```

### Print all file records via function callback

This will produce exactly same output as previous example.

```php
$filename = 'archive.tar';
$reader = new Mishak\ArchiveTar\Reader($filename);
$lastRecord = NULL;
$read->setReadContents(function ($record, $chunk, $left, $read) use ($lastRecord) {
	if (!in_array($record['type'], array(\Mishak\ArchiveTar\Reader::REGULAR, \Mishak\ArchiveTar\Reader::AREGULAR), TRUE)) {
		continue;
	}
	if (NULL === $lastRecord || $record['filename'] !== $lastRecord['filename']) {
		if (NULL !== $lastRecord) {
			echo "\n";
		}
		echo $record['filename'], "\n";
	}
	echo $chunk;
	if (!$left) {
		echo "\n";
	}
}
});
foreach ($reader as $record) {
	// don't mind just walking thru...
}
```
