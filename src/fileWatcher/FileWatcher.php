<?php

/**
 * @author heminei
 * @copyright (c) 2016, heminei
 * @link https://github.com/heminei/php-filewatcher
 * @version 1.3
 */
class FileWatcher {

	private $version = "1.1";
	private $path = null;
	private $sqlLiteDbFile = null;
	private $fileTypes = ["php", "html", "js", "css", "json", "htaccess", "jpg", "png", "gif"];
	private $firstRun = false;
	private $notifications = [];

	/**
	 *
	 * @var \PDO
	 */
	private $pdo = null;

	public function __construct($path = null, $sqlLiteDbFile = null) {
		if (!empty($path)) {
			$this->setPath($path);
		}
		if (!empty($sqlLiteDbFile)) {
			$this->setSqlLiteDbFile($sqlLiteDbFile);
		}
	}

	/**
	 *
	 * @return string
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 *
	 * @param string $path
	 */
	public function setPath($path) {
		$this->path = $path;
	}

	/**
	 *
	 * @return string
	 */
	public function getSqlLiteDbFile() {
		return $this->sqlLiteDbFile;
	}

	/**
	 *
	 * @param type $sqlLiteDbFile
	 * @return self
	 */
	public function setSqlLiteDbFile($sqlLiteDbFile) {
		$this->sqlLiteDbFile = $sqlLiteDbFile;

		if (!file_exists($this->sqlLiteDbFile)) {
			file_put_contents($this->sqlLiteDbFile, "");
			chmod($this->sqlLiteDbFile, 0777);
		}

		$this->pdo = new \PDO('sqlite:' . $this->sqlLiteDbFile);
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo->exec('CREATE TABLE IF NOT EXISTS files (`path` varchar(1024), `md5` varchar(255), `checkDate` datetime)');

		$statement = $this->pdo->prepare("SELECT COUNT(*) AS count FROM files");
		$statement->execute();
		if ($statement->fetchObject()->count == 0) {
			$this->firstRun = true;
		}

		return $this;
	}

	/**
	 *
	 * @return array
	 */
	public function getFileTypes() {
		return $this->fileTypes;
	}

	/**
	 *
	 * @return \PDO
	 */
	public function getPDO() {
		return $this->pdo;
	}

	/**
	 *
	 * @param array $fileTypes
	 */
	public function setFileTypes($fileTypes) {
		$this->fileTypes = $fileTypes;
	}

	/**
	 *
	 * @return array
	 */
	public function getNotifications() {
		return $this->notifications;
	}

	/**
	 *
	 * @return self
	 */
	public function check() {
		$files = $this->getDirFiles($this->path);
		$checkDate = date("Y-m-d H:i:s");
		$newFiles = [];
		$deletedFiles = [];

		foreach ($files as $file) {
			$statement = $this->pdo->prepare("SELECT * FROM files WHERE path=:path");
			$statement->bindValue(":path", $file);
			$statement->execute();
			$row = $statement->fetchObject();
			if (!empty($row)) {
				if ($row->md5 != md5_file($file)) {
					$this->notifications[] = [
						"type" => "edit",
						"file" => $file,
					];
					$statement = $this->pdo->prepare("UPDATE files SET md5=:md5, checkDate=:checkDate WHERE path=:path");
					$statement->bindValue(":md5", md5_file($file));
					$statement->bindValue(":checkDate", $checkDate);
					$statement->bindValue(":path", $file);
					$statement->execute();
				}
			} else {
				$newFiles[] = $file;
				$this->notifications[] = [
					"type" => "add",
					"file" => $file,
				];
			}
		}

		if (!empty($newFiles)) {
			$newFilesChunks = array_chunk($newFiles, 200);
			foreach ($newFilesChunks as $filesChunk) {
				$sqlValues = [];
				foreach ($filesChunk as $file) {
					$md5File = md5_file($file);
					$sqlValues[] = "(:file" . md5($file) . ", :md5" . md5($file) . ", :checkDate" . md5($file) . ")";
				}
				$statement = $this->pdo->prepare("INSERT INTO files (path, md5, checkDate) VALUES " . implode(",", $sqlValues));
				foreach ($filesChunk as $file) {
					$md5File = md5_file($file);
					$statement->bindValue(":file" . md5($file), $file);
					$statement->bindValue(":md5" . md5($file), $md5File);
					$statement->bindValue(":checkDate" . md5($file), $checkDate);
				}
				$statement->execute();
			}
		}


		$statement = $this->pdo->prepare("SELECT * FROM files");
		$statement->execute();
		$rows = $statement->fetchAll(PDO::FETCH_OBJ);

		foreach ($rows as $row) {
			if (!file_exists($row->path)) {
				$deletedFiles[] = $row->path;
				$this->notifications[] = [
					"type" => "delete",
					"file" => $row->path,
				];

				$statement = $this->pdo->prepare("DELETE FROM files WHERE path=:path");
				$statement->bindValue(":path", $row->path);
				$statement->execute();
			}
		}

		return $this;
	}

	public function sendNotification() {
		$json = [];
		$json['version'] = $this->version;
		$json['notifications'] = $this->notifications;

		if (!empty($this->notifications) && $this->firstRun == false) {
			http_response_code(201);
		}
		header("Content-Type: application/json");
		echo json_encode($json);
	}

	private function getDirFiles($dir, $results = []) {
		if (!is_readable($dir)) {
			return $results;
		}

		$files = scandir($dir);
		foreach ($files as $value) {
			$path = realpath($dir . DIRECTORY_SEPARATOR . $value);
			if (!is_dir($path)) {
				$pathinfo = pathinfo($path);
				if (isset($pathinfo['extension']) && in_array($pathinfo['extension'], $this->fileTypes)) {
					$results[] = $path;
				}
			} else if ($value != "." && $value != "..") {
				$results = $this->getDirFiles($path, $results);
			}
		}
		return $results;
	}

	private function getDirContentsMd5($dir) {
		$md5Files = [];
		foreach ($this->getDirFiles($dir) as $file) {
			$md5Files[] = md5_file($file);
		}
		return md5(implode("", $md5Files));
	}

}
