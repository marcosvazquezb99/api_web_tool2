<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddEventStatusPending extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get the current enum values
        $enumValues = DB::select("SHOW COLUMNS FROM events WHERE Field = 'status'")[0]->Type;

        // Parse the enum values
        preg_match('/^enum\((.*)\)$/', $enumValues, $matches);
        $currentValues = str_getcsv($matches[1], ',', "'");

        // Add 'Pending' if it doesn't already exist
        if (!in_array('pending', $currentValues)) {
            $currentValues[] = 'pending';
        }

        // Format the values for the SQL query
        $newValues = implode("','", $currentValues);

        // Alter the table to update the enum
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('$newValues') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Get the current enum values
        $enumValues = DB::select("SHOW COLUMNS FROM events WHERE Field = 'status'")[0]->Type;

        // Parse the enum values
        preg_match('/^enum\((.*)\)$/', $enumValues, $matches);
        $currentValues = str_getcsv($matches[1], ',', "'");

        // Remove 'Pending' value
        $currentValues = array_filter($currentValues, function ($value) {
            return $value !== 'pending';
        });

        // Format the values for the SQL query
        $newValues = implode("','", $currentValues);

        // Alter the table to update the enum
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('$newValues') NOT NULL");
    }
}
