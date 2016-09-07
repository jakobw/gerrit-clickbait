<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

// load config and db
$config = Yaml::parse(file_get_contents(__DIR__ . '/config.yml'));
$dbFile = __DIR__ . '/db.json';
$db = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

$gerritUrl = $config['endpoint'];
$users = $config['users'];

foreach ($users as $user) {
    // get open changes where the user is a reviewer
    $gerritData = file_get_contents($gerritUrl . "changes/?q=is:open+reviewer:${user['email']}&o=DETAILED_ACCOUNTS");
    $gerritData = substr($gerritData, strpos($gerritData, "\n") + 1); // first line is weird
    $openChanges = json_decode($gerritData, true);

    foreach ($openChanges as $change) {
        // filter out changes that the user has been notified for before
        if (!in_array($change['change_id'], @$db['known'][$user['name']] ?: [])) {
            echo "Send mail to ${user['email']}\n";
            echo shell_exec(__DIR__ . '/rmutt/bin/clickbait "' . $change['owner']['name'] . '" "' . $change['project'] . '"');
            echo "####################################\n";

            $db['known'][$user['name']][] = $change['change_id'];
        }
    }
}

// write known changes back to the db
file_put_contents($dbFile, json_encode($db));
