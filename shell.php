<?php
class CsvParser
{
    protected $databaseConf = [
        'host'     => 'localhost',
        'dbname'   => 'users',
        'username' => 'root',
        'password' => 'root'
    ];
    protected $requiredColumns = [
        'lastname',
        'firstname',
        'pesel',
        'phone'
    ];
    protected $conn = null;
    protected $peselMap = [];

    public function getFilename()
    {
        global $argv;

        if (!isset($argv[1])) {
            echo 'Please specify csv filename' . PHP_EOL;
            exit;
        }

        if (!file_exists($argv[1])) {
            echo "CSV file doesn't exists" . PHP_EOL;
            exit;
        }

        return $argv[1];
    }

    public function importData()
    {
        $headers = [];
        $handle = fopen($this->getFilename(), "r");

        if (!$handle) {
            echo "Can't open csv file!";
            exit;
        }

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            if (empty($headers)) {
                $this->verifyHeaders($data);
                $headers = $data;
                continue;
            }

            for ($c=0; $c < count($data); $c++) {
                $data[$headers[$c]] = trim($data[$c]);
                unset($data[$c]);
            }


            $this->addPeselToMap($data['pesel']);
            $data['phone'] = $this->cleanPhone($data['phone']);

            if ($this->checkIfUserExists($data['pesel'])) {
                $this->updateUser($data);
            } else {
                $this->addUser($data);
            }
        }

        fclose($handle);

        if (!empty($this->peselMap)) {
            $this->removeUsers();
        }

        echo 'Finished' . PHP_EOL;
    }

    protected function verifyHeaders($data)
    {
        foreach ($this->requiredColumns as $column) {
            if (!in_array($column, $data)) {
                echo 'Missing column: ' . $column . PHP_EOL;
                exit;
            }
        }
    }

    protected function addPeselToMap($pesel)
    {
        if (!in_array($pesel, $this->peselMap)) {
            $this->peselMap[] = $pesel;
        }
    }

    protected function cleanPhone($value)
    {
        $value = str_replace('+', '00', $value);
        $value = preg_replace('/\D/', '', $value);

        return $value;
    }

    protected function getConnection()
    {
        if (is_null($this->conn)) {
            try {
                $this->conn = new PDO(
                    sprintf("mysql:host=%s;dbname=%s;charset=utf8", $this->databaseConf['host'], $this->databaseConf['dbname']),
                    $this->databaseConf['username'],
                    $this->databaseConf['password']
                );
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(PDOException $e) {
                echo "Can't connect to MySQL. Error: ". PHP_EOL . $e->getMessage() . PHP_EOL;
                exit;
            }
        }

        return $this->conn;
    }

    public function checkIfUserExists($pesel)
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE pesel = :pesel";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindParam(':pesel', $pesel);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    public function addUser($data)
    {
        $sql = "INSERT INTO users (pesel, firstname, lastname, phone) VALUES (:pesel, :firstname, :lastname, :phone)";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindParam(':pesel', $data['pesel']);
        $stmt->bindParam(':firstname', $data['firstname']);
        $stmt->bindParam(':lastname', $data['lastname']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->execute();

        echo sprintf('Added user: %s %s %s %s', $data['pesel'], $data['firstname'], $data['lastname'], $data['phone']). PHP_EOL;
    }

    public function removeUsers()
    {
        $sql = sprintf("DELETE FROM users WHERE pesel NOT IN (%s)", implode(',', $this->peselMap));
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute();

        if ($stmt->rowCount()) {
            echo sprintf('Deleted %s users', $stmt->rowCount()). PHP_EOL;
        }
    }

    public function updateUser($data)
    {
        $sql = "UPDATE users SET firstname = :firstname, lastname = :lastname, phone = :phone, update_counter = update_counter + 1 WHERE pesel = :pesel";
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->bindParam(':firstname', $data['firstname']);
        $stmt->bindParam(':lastname', $data['lastname']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':pesel', $data['pesel']);
        $stmt->execute();

        echo sprintf('Updated user: %s %s %s %s', $data['pesel'], $data['firstname'], $data['lastname'], $data['phone']). PHP_EOL;
    }
}

$object = new CsvParser();
$object->importData();