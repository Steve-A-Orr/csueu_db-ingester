#!/usr/bin/php
<?php
/**
 * A command-line script to intake a CSV file and create a database file.
 *
 * PHP version 5
 *
 * @category  CSUEUOrganizingApplication
 * @package   PIMsApp
 * @author    Steven Orr <steven.a.orr@gmail.com>
 * @copyright 2016 Steven Orr
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 * @link      http://www.union-support.com/
 */

/*
 *---------------------------------------------------------------
 * APPLICATION ENVIRONMENT FOR COMMAND-LINE INTERFACE (CLI)
 *---------------------------------------------------------------
 *
 * This file requires the use of the PHP CLI and has no Web interface. Call the
 * file using this type of format:
 *  /path/to/bin/php -q /path/to/cli.php environment action srcfilename
 * For example:
 *  /usr/bin/php -q /Users/sorr/Sites/devdesktop/drupal8/code/ingester/ingest.php dev create 2016-11_CSUEU_PIMS.csv
 *
 * For more information on using PHP from the command line, see:
 *  http://us.php.net/features.commandline
 */

/*
 * --------------------------------------------------------------------
 * LIMIT SCRIPT ACCESS
 * --------------------------------------------------------------------
 *
 * We do NOT want this to be called from a web visitor or browser. This
 * is strictly accomplished through manual or CRON CLI activation.
 *
 */
if (isset($_SERVER['REMOTE_ADDR'])) {
    die('Permission denied.');
}

/*
 * --------------------------------------------------------------------
 * PROVIDE SCRIPT ASSISTANCE
 * --------------------------------------------------------------------
 *
 * Before the script goes further, check if 'help' or assistance is requested.
 */
if (!isset($argv[1]) || !isset($argv[2]) || !isset($argv[3]) || 'help' == strtolower($argv[1])) {
    echo "\n\n This script parses a PIMs CSV file and creates a database table from it. \n";
    echo "\n   Call the script with this way:\n ";
    echo "         /path/to/bin/php -q /path/to/cli.php environment action srcfilename \n\n";
    echo "   Environments:  dev, test, prod, help (to get this output) \n";
    echo "   Actions:       create, replace \n";
    echo "   CSV File:      must exist in the same directory as the script with Unix line-endings.\n\n";
    exit;
}

/*
 *---------------------------------------------------------------
 * APPLICATION ENVIRONMENT, SCRIPT LIMITS AND CONSTANTS
 *---------------------------------------------------------------
 * Options are 'dev', 'test', 'prod'
 *
 * NOTE: Currently, this is being set by first script argument.
 *
 */
$config_app = parse_ini_file("_app.ini.php", true);

define('ENVIRONMENT', $argv[1]);
define('ACTION', $argv[2]);
define('DATAFILE', $argv[3]);
$dt = substr(DATAFILE, 0, 7);
define('DATATABLE', $dt."_pims");
define('CLI', 1);
define('LOG', "log.txt");
$ch = (int) $config_app['common']['chapter'];
define('CHAPTER', $ch);
define('DIR', '/'.$config_app['common']['appdir']);
define('PATH_PARENT', $config_app[ENVIRONMENT]['parentdir']);
define('PATH_APP', PATH_PARENT.DIR);
define('MYSQLI_SVR', $config_app[ENVIRONMENT]['mysqlihst']);
define('MYSQLI_PRT', $config_app[ENVIRONMENT]['mysqliprt']);
define('MYSQLI_USR', $config_app[ENVIRONMENT]['mysqliusr']);
define('MYSQLI_PWD', $config_app[ENVIRONMENT]['mysqlipwd']);
define('MYSQLI_DBS', $config_app[ENVIRONMENT]['mysqlidbs']);

date_default_timezone_set('America/Los_Angeles');
ini_set('memory_limit', '256M');
set_time_limit(0);

/*
 * --------------------------------------------------------------------
 * OPEN THE FILE
 * --------------------------------------------------------------------
 */
$rows = 0;
if (($handle = fopen(DATAFILE, "r")) !== FALSE) {
    $data_array = array();
    echo "  ## Chapter ".CHAPTER.": Opened CSV\n";
    while (($col = fgetcsv($handle, 0, ",")) !== FALSE)
    {
        $data_row = array();
        $num = count($col);

        // Grab top row for conversion to column names.
        if ($rows === 0) {
            for ($c = 0; $c < $num; $c++) {
                $data_row[] = strtolower(preg_replace('/[^a-zA-Z0-9\']/', '_', $col[$c]));
            }
            $data_array[] = $data_row;
            $rows++;
        } else if (CHAPTER == (int) $col[3]) { 
            for ($c = 0; $c < $num; $c++) {
              if (($c == 1) || ($c == 2)) {
                  $data_row[] = htmlentities($col[$c], ENT_QUOTES, "UTF-8");
              } else {
                  $data_row[] = (!empty($col[$c]))? $col[$c]: " ";
              }
            }
            $data_array[] = $data_row;
            $rows++;
        }
    }
    fclose($handle);
    $rows--;
    echo "\n  ## {$rows} rows: Closed CSV\n";
}

/*
 * --------------------------------------------------------------------
 * PERFORM DATABASE WORK
 * --------------------------------------------------------------------
 */
$mysqli = new mysqli(MYSQLI_SVR, MYSQLI_USR, MYSQLI_PWD, MYSQLI_DBS, MYSQLI_PRT);
if ($mysqli->connect_errno) {
    echo "\n  >>>>>  Script could not connect to database  <<<<<  \n\n";
    exit;
} else {
    echo "\n  <<<<<  Script connected to database  >>>>>  \n\n";
} 

// Does table already exist?
if ($result = $mysqli->query("SHOW TABLES LIKE '".DATATABLE."'")) {
    if($result->num_rows == 1) {
        if (ACTION === "replace") {
            $result = $mysqli->query("DROP TABLE `".DATATABLE."`");
        } else {
            echo "\n !!!!! Table exists, stop the script. !!!!!\n";
            exit;
        }
    }
}

// Create table.
$table_def_sql = "CREATE TABLE `".DATATABLE."` (
  `id` int(51) NOT NULL AUTO_INCREMENT,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `{$data_array[0][0]}` int(25) NOT NULL,
  `{$data_array[0][1]}` varchar(60) NOT NULL,
  `{$data_array[0][2]}` varchar(60) NOT NULL,
  `{$data_array[0][3]}` int(3) NOT NULL,
  `{$data_array[0][4]}` varchar(20) NOT NULL,
  `{$data_array[0][5]}` int(25) NOT NULL,
  `{$data_array[0][6]}` varchar(3) NOT NULL,
  `{$data_array[0][7]}` char(2) NOT NULL,
  `{$data_array[0][8]}` varchar(10) NOT NULL,
  `{$data_array[0][9]}` varchar(100) NOT NULL,
  `{$data_array[0][10]}` varchar(2) NOT NULL,
  `{$data_array[0][11]}` varchar(50) NOT NULL,
  `{$data_array[0][12]}` varchar(8) NOT NULL,
  `{$data_array[0][13]}` varchar(10) NOT NULL,
  `{$data_array[0][14]}` varchar(10) NOT NULL,
  `{$data_array[0][15]}` varchar(10) NOT NULL,
  `{$data_array[0][16]}` varchar(10) NOT NULL,
  `{$data_array[0][17]}` varchar(9) NOT NULL,
  `{$data_array[0][18]}` varchar(9) NOT NULL,
  `{$data_array[0][19]}` varchar(5) NOT NULL,
  `{$data_array[0][20]}` varchar(100) NOT NULL,
  `{$data_array[0][21]}` varchar(30) NOT NULL,
  `{$data_array[0][22]}` varchar(3) NOT NULL,
  `{$data_array[0][23]}` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";
if (!$result = $mysqli->query($table_def_sql)) {
    echo "\n !!!!! Failed to create the table, stop the script. !!!!!\n";
    exit;
}

// Populate the table.
$insert_errors = 0;
for ($r = 1; $r <= $rows; $r++) {
    $insert_sql  = "INSERT INTO `".DATATABLE."` ";
    $insert_sql .= "(`{$data_array[0][0]}`, `{$data_array[0][1]}`, `{$data_array[0][2]}`, `{$data_array[0][3]}`, `{$data_array[0][4]}`, `{$data_array[0][5]}`, `{$data_array[0][6]}`, `{$data_array[0][7]}`, `{$data_array[0][8]}`, `{$data_array[0][9]}`, `{$data_array[0][10]}`, `{$data_array[0][11]}`, `{$data_array[0][12]}`, `{$data_array[0][13]}`, `{$data_array[0][14]}`, `{$data_array[0][15]}`, `{$data_array[0][16]}`, `{$data_array[0][17]}`, `{$data_array[0][18]}`, `{$data_array[0][19]}`, `{$data_array[0][20]}`, `{$data_array[0][21]}`, `{$data_array[0][22]}`, `{$data_array[0][23]}`)";
    $insert_sql .= " VALUES ";
    $insert_sql .= "('{$data_array[$r][0]}', '{$data_array[$r][1]}', '{$data_array[$r][2]}', '{$data_array[$r][3]}', '{$data_array[$r][4]}', '{$data_array[$r][5]}', '{$data_array[$r][6]}', '{$data_array[$r][7]}', '{$data_array[$r][8]}', '{$data_array[$r][9]}', '{$data_array[$r][10]}', '{$data_array[$r][11]}', '{$data_array[$r][12]}', '{$data_array[$r][13]}', '{$data_array[$r][14]}', '{$data_array[$r][15]}', '{$data_array[$r][16]}', '{$data_array[$r][17]}', '{$data_array[$r][18]}', '{$data_array[$r][19]}', '{$data_array[$r][20]}', '{$data_array[$r][21]}', '{$data_array[$r][22]}', '{$data_array[$r][23]}')";

    if (!$result = $mysqli->query($insert_sql)) {
        echo "    !!!!! Insert error occurred for #{$data_array[$r][0]}.\n";
        $insert_errors++;
    }
}

$mysqli->close();
echo "\n  ## {$insert_errors} insert errors: {$rows} rows inserted and Script finished.\n";
/* End of file ingest.php */