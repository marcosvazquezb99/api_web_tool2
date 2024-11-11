<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class WordPressMigrationController extends Controller
{
    protected $commonPaths = [
        '',
        '/public_html',
        '/public',
        '/htdocs',
        '/httpdocs',
        '/www',
        '/web',
    ];

    public function migrate(Request $request)
    {
        $sourceDisk = $request->input('ftp_source'); // Puede ser 'ftp_source' o 'sftp_source'
        $destinationDisk = $request->input('ftp_destination');
        $this->setFtpDiskConfig('source', $sourceDisk['domain'], $sourceDisk['user'], $sourceDisk['password'], $sourceDisk['port'], '/');
        $this->setFtpDiskConfig('destination', $destinationDisk['domain'], $destinationDisk['user'], $destinationDisk['password'], $destinationDisk['port'], '/');
        try {
            // Buscar wp-config.php en ubicaciones comunes
            $sourceWpConfigPath = $this->findWpConfig('source');

            if (!$sourceWpConfigPath) {
                throw new \Exception("No se encontró wp-config.php de origen en las rutas comunes.");
            }

            $destinationWpConfigPath = $this->findWpConfig('destination');

            if (!$destinationWpConfigPath) {
                throw new \Exception("No se encontró wp-config.php de destino en las rutas comunes.");
            }

            // Extraer credenciales de base de datos del archivo wp-config.php encontrado

            $sourceDbConfig = $this->extractDbConfig($sourceWpConfigPath, 'source');

            // Modificar host si es 'localhost'
            if ($sourceDbConfig['DB_HOST'] === 'localhost') {
                $sourceDbConfig['DB_HOST'] = $sourceDisk['domain']; // IP o dominio del servidor de origen
            }

            // Exportar la base de datos de origen
            $sqlFile = storage_path('app\source_db.sql');
            $this->exportDatabase($sourceDbConfig, $sqlFile);

            // Buscar wp-config.php en ubicaciones comunes
            $sourceWpConfigPath = $this->findWpConfig($sourceDisk);

            if (!$sourceWpConfigPath) {
                throw new \Exception("No se encontró wp-config.php en las rutas comunes.");
            }

            // Extraer credenciales del archivo wp-config.php en el servidor de destino
            $destinationDbConfig = $this->extractDbConfig($destinationWpConfigPath, 'destination');

            // Importar la base de datos en el servidor de destino
            $this->importDatabase($destinationDbConfig, $sqlFile);

            // Copiar archivos de WordPress, excluyendo wp-config.php
            $this->copyFiles($sourceDisk, $destinationDisk, $sourceWpConfigPath);

            return response()->json(['message' => 'Migration completed successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function findWpConfig($origin)
    {
        foreach ($this->commonPaths as $path) {
            if (Storage::disk("ftp_$origin")->exists("$path/wp-config.php")) {
                return "$path/wp-config.php";
            }
        }
        return null;
    }

    protected function extractDbConfig($wpConfigPath, $origin): array
    {

        $wpConfigContent = Storage::disk("ftp_$origin")->get($wpConfigPath);

        $dbConfig = [];
        preg_match("/define\( ?'DB_NAME', ?'(.*) ?'/", $wpConfigContent, $dbName);
        preg_match("/define\( ?'DB_USER', '(.*?)'/", $wpConfigContent, $dbUser);
        preg_match("/define\( ?'DB_PASSWORD', '(.*?)'/", $wpConfigContent, $dbPassword);
        preg_match("/define\( ?'DB_HOST', '(.*?)'/", $wpConfigContent, $dbHost);

        $dbConfig['DB_NAME'] = $dbName[1] ?? null;
        $dbConfig['DB_USER'] = $dbUser[1] ?? null;
        $dbConfig['DB_PASSWORD'] = $dbPassword[1] ?? null;
        $dbConfig['DB_HOST'] = $dbHost[1] ?? null;

        //saca el puerto a partir del host
        if (strpos($dbConfig['DB_HOST'], ':') !== false) {
            $hostParts = explode(':', $dbConfig['DB_HOST']);
            $dbConfig['DB_HOST'] = $hostParts[0];
            $dbConfig['DB_PORT'] = $hostParts[1];
        } else {
            $dbConfig['DB_PORT'] = 3306;
        }
        return $dbConfig;
    }

    protected function exportDatabase($dbConfig, $sqlFile)
    {
        $dumpCommand = "C:\\xampp_8.2.12222\mysql\bin\mysqldump -h {$dbConfig['DB_HOST']} -P {$dbConfig['DB_PORT']} -u {$dbConfig['DB_USER']} -p{$dbConfig['DB_PASSWORD']} {$dbConfig['DB_NAME']} > $sqlFile";
        $process = Process::fromShellCommandline($dumpCommand);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception('Database export failed: ' . $process->getErrorOutput());
        }
        dd($dumpCommand);
    }

    protected function importDatabase($dbConfig, $sqlFile)
    {
        $importCommand = "mysql -h {$dbConfig['DB_HOST']} -u {$dbConfig['DB_USER']} -p{$dbConfig['DB_PASSWORD']} {$dbConfig['DB_NAME']} < $sqlFile";
        $process = Process::fromShellCommandline($importCommand);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception('Database import failed: ' . $process->getErrorOutput());
        }
    }

    protected function copyFiles($sourceDisk, $destinationDisk, $wpConfigPath)
    {
        $this->info('Copying WordPress files...');

        $basePath = dirname($wpConfigPath);
        $this->setFtpDiskConfig('source', $sourceDisk['domain'], $sourceDisk['user'], $sourceDisk['password'], $sourceDisk['port']);
        $this->setFtpDiskConfig('destination', $destinationDisk['domain'], $destinationDisk['user'], $destinationDisk['password'], $destinationDisk['port']);

        $files = Storage::disk('ftp_source')->allFiles($basePath);

        foreach ($files as $file) {
            if (basename($file) === 'wp-config.php') {
                continue;
            }
            $relativePath = str_replace("$basePath/", '', $file);
            $contents = Storage::disk('ftp_source')->get($file);
            Storage::disk('ftp_destination')->put($relativePath, $contents);
        }

        $this->info('Files copied successfully.');
    }


    public function setFtpDiskConfig($destination, $host, $username, $password, $port = 22, $root = '/')
    {
        config([
            "filesystems.disks.ftp_$destination.host" => $host,
            "filesystems.disks.ftp_$destination.username" => $username,
            "filesystems.disks.ftp_$destination.password" => $password,
            "filesystems.disks.ftp_$destination.root" => $root,
            "filesystems.disks.ftp_$destination.port" => $port,
        ]);
    }
}
