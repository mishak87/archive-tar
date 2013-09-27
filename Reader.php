<?php

namespace Mishak\ArchiveTar;

class Reader implements \Iterator {


	private $filename;

	public function __construct($filename)
	{
		$this->filename = $filename;
		$this->detectCompression();
		$this->open();
	}

	public function __destruct()
	{
		$this->close();
	}


	const GZIP = 'gz',
		BZIP2 = 'bz2',
		NONE = 'none';

	private $compression;

	private function detectCompression()
	{
		if (preg_match('/\.(tar\.gz|tgz)$/', $this->filename)) {
			$this->compression = self::GZIP;
		} elseif (preg_match('/\.(tar\.bz2|tbz|tb2)$/', $this->filename)) {
			$this->compression = self::BZIP2;
		} elseif (preg_match('/\.tar$/', $this->filename)) {
			$this->compression = self::NONE;
		} else {
			throw new ReaderException("Unsupported compression '$this->filename'.");
		}
	}


	const MANIPULATION_OPEN = 0,
		MANIPULATION_CLOSE = 1;

	private $manipulation = array(
		self::GZIP => array('gzopen', 'gzclose'),
		self::BZIP2 => array('bzopen', 'bzclose'),
		self::NONE => array('fopen', 'fclose'),
	);

	private $file;

	private function open()
	{
		$this->file = $this->manipulation[$this->compression][self::MANIPULATION_OPEN]($this->filename, 'rb');
		if (!$this->file) {
			throw new ReaderException("Cannot open file '$this->filename'.");
		}
	}


	private function close()
	{
		$this->manipulation[$this->compression][self::MANIPULATION_CLOSE]($this->file);
		$this->file = NULL;
	}


	private $record;

	private $index;

	public function current()
	{
		return $this->record;
	}

	public function key()
	{
		return $this->index;
	}

	public function next()
	{
		return $this->record = $this->readRecord();
	}

	public function rewind()
	{
		fseek($this->file, 0);
		$this->next();
	}

	public function valid()
	{
		return $this->record !== NULL;
	}


	private $readContents = TRUE;

	/**
	 * Sets read mode of file contents.
	 *
	 * @param bool|callback $readContents
	 *	FALSE disables reading of contents
	 *	TRUE will read all contents and return them with record
	 *	callback should expects parameters $record, $chunk, $bytesLeft and $bytesRead. Chunk will be of size of buffer or smaller.
	 */
	public function setReadContents($readContents = TRUE)
	{
		$this->readContents = $readContents;
	}

	private $buffer = 8195;

	/**
	 * Sets buffer size for reading file contents
	 *
	 * @param int
	 */
	public function setBuffer($buffer)
	{
		if (0 < $buffer && $buffer <= PHP_INT_MAX) {
			$this->buffer = $buffer;
		} else {
			throw new ReaderException("Buffer must be greater than 0 and less or equal to " . PHP_INT_MAX . " (PHP_INT_MAX).");
		}
	}


	const REGULAR = '0',
		AREGULAR = "\0", // backward compatibility
		HARDLINK = '1',
		SYMLINK = '2',
		CHARACTER = '2',
		BLOCK = '2',
		DIRECTORY = '2',
		FIFO = '2',
		CONTIGUOUS = '2',
		GLOBALHEADER = 'g',
		EXTENDEDHEADER = 'x',
		LONGLINK = 'K';

	private function readRecord()
	{
		$block = $this->readBlock();
		$this->index++;
		return $block;
	}

	const BLOCK_SIZE = 512;

	private function readBlock()
	{
		if (feof($this->file)) {
			return NULL;
		} else {
			$header = fread($this->file, self::BLOCK_SIZE);
			$record = unpack("a100name/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100symlink/a6magic/a2version/a32owner/a32group/a8deviceMajor/a8deviceMinor/a155prefix/a12unpacked", $header);
			$record['filename'] = $record['prefix'] . $record['name'];
			// convert to decimal values
			foreach (array('uid', 'gid', 'size', 'mtime', 'checksum') as $key) {
				$record[$key] = octdec($record[$key]);
			}
			if ($record['checksum'] == 0x00000000) {
				return NULL;
			} elseif (0 !== strpos($record['magic'], 'ustar')) {
				throw new ReaderException('Unsupported archive type.');
			}

			$checksum = 0;
			for ($i = 0; $i < self::BLOCK_SIZE; $i++) {
				$checksum += 148 <= $i && $i < 156 ? 32 : ord($header[$i]);
			}
			if ($record['checksum'] != $checksum) {
				throw new ReaderException('Archive is corrupted.');
			}

			$length = $record['size'];
			if (is_float($length)) {
				$padding = self::BLOCK_SIZE - fmod($length, self::BLOCK_SIZE);
			} else {
				$padding = self::BLOCK_SIZE - $length % self::BLOCK_SIZE;
			}

			$file['contents'] = NULL;
			if ($length == 0 && is_callable($this->readContents)) {
				call_user_func($this->readContents, $record, '', 0, 0);
			}
			while ($length > 0) {
				if ($length > $this->buffer) {
					$read = $this->buffer;
					$length -= $this->buffer;
				} else {
					$read = $length;
					$length = 0;
				}

				if ($this->readContents === FALSE) {
					fseek($this->file, $read, SEEK_CUR);
				} else {
					$chunk = fread($this->file, $read);
					if (is_callable($this->readContents)) {
						call_user_func($this->readContents, $record, $chunk, $length, $read);
					} else {
						$file['contents'] .= $read;
					}
				}
			}
			if ($padding !== self::BLOCK_SIZE) {
				fseek($this->file, $padding, SEEK_CUR);
			}
			return $record;
		}
	}

}
