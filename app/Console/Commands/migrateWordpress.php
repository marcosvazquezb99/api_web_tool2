<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class migrateWordpress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:wordpress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Wordpress data to other Wordpress site';

    /**
     * The common paths to look for the public directory.
     *
     * @var array
     */
    protected $commonPaths = [
        '',
        '/public_html',
        '/public',
        '/htdocs',
        '/httpdocs',
        '/www',
        '/web',
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->migrateWordpress();
    }

    public function migrateWordpress()
    {
        $migrations = $this->getMigrations();
        foreach ($migrations as $migration) {

            dd($migration);

            // Set the FTP disk config
            $this->setFtpDiskConfig("source", $migration->source_domain, $migration->source_user, $migration->source_password, $migration->source_port);
            $this->setFtpDiskConfig("destination", $migration->destination_domain, $migration->destination_user, $migration->destination_password, $migration->destination_port);

            // Search for wp-config.php in common locations
            $sourceWpConfigPath = $this->findWpConfig("source");
            if (!$sourceWpConfigPath) {
                throw new \Exception("No se encontró wp-config.php de origen en las rutas comunes.");
            }
            $destinationWpConfigPath = $this->findWpConfig("destination");
            if (!$destinationWpConfigPath) {
                throw new \Exception("No se encontró wp-config.php de destino en las rutas comunes.");
            }

            // Extract source database credentials from the found wp-config.php file
            $sourceDbConfig = $this->extractDbConfig($sourceWpConfigPath, "source");
            // Modify host if it is 'localhost'
            if ($sourceDbConfig['DB_HOST'] === 'localhost') {
                $sourceDbConfig['DB_HOST'] = $migration->source_domain; // IP or domain of the source server
            }
            $this->setWordpressDatabases("source", $sourceDbConfig['DB_HOST'], $sourceDbConfig['DB_USER'], $sourceDbConfig['DB_PASSWORD'], $sourceDbConfig['DB_NAME'], $sourceDbConfig['DB_PORT']);

            //Extract destination database credentials from the wp-config.php file on the destination server
            $destinationDbConfig = $this->extractDbConfig($destinationWpConfigPath, "destination");
            // Modify host if it is 'localhost'
            if ($destinationDbConfig['DB_HOST'] === 'localhost') {
                $destinationDbConfig['DB_HOST'] = $migration->destination_domain; // IP or domain of the destination server
            }
            $this->setWordpressDatabases("destination", $destinationDbConfig['DB_HOST'], $destinationDbConfig['DB_USER'], $destinationDbConfig['DB_PASSWORD'], $destinationDbConfig['DB_NAME'], $destinationDbConfig['DB_PORT']);
            // Migrate the database source to the destination
            $this->migrateDatabase();

            // change destination prefix wp-config.php
            $this->changePrefix($destinationWpConfigPath, $destinationDbConfig['DB_PREFIX']);


        }
    }

    public function changePrefix($destinationWpConfigPath, $prefix)
    {
        $wpConfigContent = Storage::disk('ftp_destination')->get($destinationWpConfigPath);
        $wpConfigContent = preg_replace("/table_prefix.*;/", "table_prefix = '$prefix';", $wpConfigContent);
        Storage::disk('ftp_destination')->put($destinationWpConfigPath, $wpConfigContent);
    }

    public function migrateDatabase()
    {
        $tables = DB::connection('wordpress_source')
            ->select('SHOW TABLES');
        $sourceDatabase = config('database.connections.wordpress_source.database');

        foreach ($tables as $table) {
            $tableName = $table->{'Tables_in_' . $sourceDatabase}; // Nombre de cada tabla en la base de datos de origen
            $this->info("Migrating table: $tableName");

            //get export sql of table
            $exportTable = DB::connection('wordpress_source')->select("SHOW CREATE TABLE $tableName");
            $exportTable = $exportTable[0]->{'Create Table'};
            //create table in destination
            DB::connection('wordpress_destination')->statement("DROP TABLE IF EXISTS $tableName");
            DB::connection('wordpress_destination')->statement($exportTable);


            /*// Obtener la instrucción CREATE TABLE de la base de datos de origen
            $createTableQuery = DB::connection('wordpress_source')->select("SHOW CREATE TABLE $tableName");
            $createTableSql = $createTableQuery[0]->{'Create Table'};

            // Crear la tabla en la base de datos de destino
            DB::connection('wordpress_destination')->statement("DROP TABLE IF EXISTS $tableName");
            DB::connection('wordpress_destination')->statement($createTableSql);

            // Truncar la tabla en la base de datos de destino si ya existe
            DB::connection('wordpress_destination')->statement("SET FOREIGN_KEY_CHECKS=0;");
            DB::connection('wordpress_destination')->table($tableName)->truncate();

            // Obtener todos los datos de la tabla en la base de datos de origen
            $data = DB::connection('wordpress_source')->table($tableName)->get();

            // Insertar los datos en la tabla de destino
            DB::connection('wordpress_destination')->table($tableName)->insert(
                json_decode(json_encode($data), true)
            );*/

            $this->info("Migrated table: $tableName");
        }

        DB::connection('wordpress_destination')->statement("SET FOREIGN_KEY_CHECKS=1;");
        $this->info('All tables migrated successfully!');
    }


    public function getMigrations()
    {
        $migrations = (new \App\Http\Controllers\WpMigrationController)->index();
        return $migrations->getData();
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

    public function setWordpressDatabases($destination, $host, $username, $password, $dbName, $port = 3306)
    {
        config([
            "database.connections.wordpress_$destination.database" => $dbName,
            "database.connections.wordpress_$destination.host" => $host,
            "database.connections.wordpress_$destination.username" => $username,
            "database.connections.wordpress_$destination.password" => $password,
            "database.connections.wordpress_$destination.port" => $port,
        ]);
    }

    protected function extractDbConfig($wpConfigPath, $origin): array
    {

        $wpConfigContent = Storage::disk("ftp_$origin")->get($wpConfigPath);

        $dbConfig = [];
        preg_match("/define\( ?'DB_NAME', ?'(.*) ?'/", $wpConfigContent, $dbName);
        preg_match("/define\( ?'DB_USER', '(.*?)'/", $wpConfigContent, $dbUser);
        preg_match("/define\( ?'DB_PASSWORD', '(.*?)'/", $wpConfigContent, $dbPassword);
        preg_match("/define\( ?'DB_HOST', '(.*?)'/", $wpConfigContent, $dbHost);
        preg_match("/.table_prefix ?= ?'(.*)';/", $wpConfigContent, $dbPrefix);
        $dbConfig['DB_NAME'] = $dbName[1] ?? null;
        $dbConfig['DB_USER'] = $dbUser[1] ?? null;
        $dbConfig['DB_PASSWORD'] = $dbPassword[1] ?? null;
        $dbConfig['DB_HOST'] = $dbHost[1] ?? null;
        $dbConfig['DB_PREFIX'] = $dbPrefix[1] ?? null;

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


    protected function findWpConfig($origin)
    {
        foreach ($this->commonPaths as $path) {
            if (Storage::disk("ftp_$origin")->exists("$path/wp-config.php")) {
                return "$path/wp-config.php";
            }
        }
        return null;
    }

}
