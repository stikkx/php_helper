<?php

/* connect to gmail with your credentials */

use Classes\EmailCsvBridge;

require "src/Classes/EmailCsvBridge.php";

$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'user@email.tdl';
$password = 'pw';

$EmailCsvBridge = new EmailCsvBridge($username, $password, $hostname, "mail@postfach.tdl","mail@receiver.tdl");

$path_array = $EmailCsvBridge->getSourceCSV("Notes");
foreach ($path_array as $path)
{
    $rename_array = [
        "Kategorie" => //search_string
        "category" //replace_string
    ];

    $EmailCsvBridge->saveToCSV($path,$EmailCsvBridge->editCSVHeader($path, $rename_array));
    $EmailCsvBridge->sendEmail($path);
}