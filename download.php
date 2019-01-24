<?php
/**
 * Uses https://github.com/jolicode/slack-php-api
*/

declare(strict_types=1);

// Import slack-php-api classes into the global namespace
use JoliCode\Slack\Api\Client;
use JoliCode\Slack\Api\Model\ObjsFile;
use JoliCode\Slack\Api\Model\ObjsUser;
use JoliCode\Slack\ClientFactory;

// Load Composer's autoloader
require_once 'vendor/autoload.php';

/** @var ObjsFile $files */
$files = [];

/** @var Array $channels */
$channels = [];

/** @var Array $users */
$users = [];


/**
 * Creates a normalized filename
 */
function createFilename(ObjsFile $file): string
{
    // Use title if set
    if (strlen($file->getTitle()) > 0) {
        $name = normalize($file->getTitle(), $file->getFiletype());
    } else {
        $name = "";
    }

    // Use filename if title was not set or normalization returned an empty string
    if ((strlen($name) == 0) and (strlen($file->getName()) > 0)) {
        $name = normalize($file->getName(), $file->getFiletype());
    }

    // Use fallback name if normalization returned an empty string
    if (strlen($name) == 0) {
        $name = "unnamed";
    }

    // Prepend date
    $name = date('Y-m-d H-i-s', $file->getCreated()) . ' ' . $name;
    // Limit length to 255 characters and add file extension
    $name = substr($name, 0, 253);
    return $name;
}

/**
 * Normalizes and sanitizes any string
 */
function normalize(string $text, $filetype = false): string
{
    // Get rid of URL's in the name
    $normalized = preg_replace("/http[s]\:[^[:space:]]+/i", '', $text);
    // Remove file extension
    if (is_string($filetype)) {
        $normalized = preg_replace("/\." . $filetype . "$/i", '', $normalized);
    }
    // Remove all emoticons
    $normalized = preg_replace("/:[[:alnum:]_]+:/u", '', $normalized);
    // Remove all non-alphanumeric and non-space characters
    $normalized = preg_replace("/[^[:alnum:][:space:]-_]/u", '', $normalized);

    // Remove duplicate characters
    $normalized = preg_replace("/-{2,}/u", '-', $normalized);
    $normalized = preg_replace("/_{2,}/u", '_', $normalized);
    $normalized = preg_replace("/[[:space:]]{2,}/u", ' ', $normalized);
    // Remove all emojis
    $normalized = preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', $normalized);
    $normalized = trim($normalized);
    return $normalized;
}

/**
 * Downloads the file
 */
function downloadFile(string $url, string $destination, string $token)
{
    $cURL = curl_init($url);
    $target = fopen($destination, "w+");
    curl_setopt_array($cURL, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer " .$token,
        ],
        CURLOPT_FILE           => $target,
    ]);
    curl_exec($cURL);
    curl_close($cURL);
    fclose($target);
}

/**
 * Show error message and aborts execution
 */
function output(bool $quiet, string $message)
{
    if (!$quiet) {
        echo $message;
    }
}

/**
 * Show error message and aborts execution
 */
function error(string $message, $exit = 1)
{
    echo "Error: " . $message . PHP_EOL;
    exit($exit);
}

/**
 * Resolves the Slack channel name using caching
 *
 * Caching this data saves a lot of API calls and speeds things up significantly!
 */
function getChannel(string $type, string $id, array &$channels, string $token): array
{
    if (!array_key_exists($id, $channels)) {
        // Channel not cached, retreive from API
        $client  = ClientFactory::create($token);
        if ($type == 'channel') {
            $temp = $client->channelsInfo(['channel' => $id])->getChannel();
            $channels[$id] = [
                "name"            => $temp->getName(),
                "name_normalized" => $temp->getNameNormalized(),
                "purpose"         => $temp->getPurpose()->getValue(),
            ];
        } elseif ($type == 'conversation') {
            $channels[$id] = $client->conversationsMembers(['channel' => $id])->getMembers();
        } elseif ($type == 'group') {
            $temp = $client->groupsInfo(['channel' => $id])->getGroup();
            $channels[$id] = [
                "name"            => $temp->getName(),
                "name_normalized" => $temp->getNameNormalized(),
                "purpose"         => $temp->getPurpose()->getValue(),
            ];
        } else {
            error("invalid channel type.");
        }
    }

    return $channels[$id];
}

/**
 * Resolves the Slack user name using caching
 *
 * Caching this data saves a lot of API calls and speeds things up significantly!
 */
function getUser(string $id, array &$users, string $token): array
{
    if (!array_key_exists($id, $users)) {
        // User not cached, retreive from API
        $client  = ClientFactory::create($token);
        $user = $client->usersInfo(['user' => $id])->getUser();
        $users[$id] = [
            "name"                    => $user->getName(),
            "realname"                => $user->getRealName(),
            "title"                   => $user->getProfile()->getTitle(),
            "real_name"               => $user->getProfile()->getRealName(),
            "real_name_normalized"    => $user->getProfile()->getRealNameNormalized(),
            "display_name"            => $user->getProfile()->getDisplayName(),
            "display_name_normalized" => $user->getProfile()->getDisplayNameNormalized(),
        ];
    }

    return $users[$id];
}

/**
 * Determines the best available username
 */
function getUserName(Array $user): String
{
    if (is_string($user["real_name_normalized"])) {
        return $user["real_name_normalized"];
    } elseif (is_string($user["realname"])) {
        return $user["realname"];
    } elseif (is_string($user["real_name"])) {
        return $user["real_name"];
    } elseif (is_string($user["real_name_normalized"])) {
        return $user["real_name_normalized"];
    } elseif (is_string($user["display_name"])) {
        return $user["display_name"];
    } else {
        return $user["name"];
    }
}

/**
 * Show command line usage
 */
function usage()
{
    echo "php download.php -t [token] [options]" . PHP_EOL . PHP_EOL;
    echo "  -d [path]     Download destination. Defaults to the current working directory." . PHP_EOL . PHP_EOL;
    echo "  -h            Show this help." . PHP_EOL . PHP_EOL;
    echo "  -i            Include files belonging to private instant messages between users." . PHP_EOL . PHP_EOL;
    echo "  -r            Remove files from Slack." . PHP_EOL . PHP_EOL;
    echo "  -s            Simulation mode, do not actually download and remove files from Slack." . PHP_EOL . PHP_EOL;
    echo "  -t [token]    Slack team token." . PHP_EOL . PHP_EOL;
    echo "  -w [number]   Number of weeks to retain. Defaults to 26 (e.g. half a year)." . PHP_EOL . PHP_EOL;
    echo "  -q            Quiet mode. Suppresses all output except errors." . PHP_EOL;
    exit(0);
}

/**
 * Handle command line arguments
 */
$arguments = getopt("hirqsd:t:w:");

if ((count($arguments) == 0) or (array_key_exists('h', $arguments))) {
    usage();
}

if (array_key_exists('t', $arguments)) {
    $token = $arguments['t'];
} else {
    error("providing a token is required.");
}

$destination = array_key_exists('d', $arguments) ? $arguments['d'] : __DIR__ . '/downloads';
$get_ims     = array_key_exists('i', $arguments) ? is_bool($arguments['i']) : false;
$remove      = array_key_exists('r', $arguments) ? is_bool($arguments['r']) : false;
$simulation  = array_key_exists('s', $arguments) ? is_bool($arguments['s']) : false;
$quiet       = array_key_exists('q', $arguments) ? is_bool($arguments['q']) : false;
$weeks       = array_key_exists('w', $arguments) ? $arguments['w'] : 26;
$timestamp   = strtotime("-" . $weeks . " weeks");


/**
 * Show some start information
 */
$action_text = ($remove) ? "Archiving" : "Downloading";

if ($weeks == 0) {
    $week_text = "";
} else {
    $week_text = ($weeks == 1) ? "older than " . $weeks . " week" : "older than " . $weeks . " weeks";
}

output($quiet, $action_text . " files " . $week_text . " to '" . $destination . "'." . PHP_EOL);
if ($simulation) {
    output($quiet, "Running in simulation mode, not actually performing the actions!" . PHP_EOL);
}

/**
 * Start processing
 */
$client  = ClientFactory::create($token);
$counter = 1;
$page    = 1;

/**
 * Get file list
 *
 * Excludes Google Docs files because they do not come with a private download url
 */
while (true) {
    $response = $client->filesList([
        'count' => '200',
        'page'  => (string)$page,
        'ts_to' => (float)sprintf("%.2f", $timestamp),
        'types' => 'spaces,snippets,images,zips,pdfs',
    ]);


    if ($response->getOk()) {
        $total = $response->getPaging()->getTotal();

        if ($page == 1) {
            output($quiet, "Found " . $total . " files" . PHP_EOL);
        }

        $files = array_merge($files, $response->getFiles());

        // End while-loop on final page
        if ($page == $response->getPaging()->getPages()) {
            break;
        } else {
            $page++;
        }
    } else {
        error('could not retrieve the file list from Slack.');
    }
}

/**
 * Process files one at a time
 */
foreach ($files as $file) {
    $filename = createFilename($file);
    output($quiet, "[" . str_pad(strval($counter), 4) . "/" . str_pad(strval($total), 4) . "] " . str_pad($filename, 80) . ": ");

    if (is_string($file->getUrlPrivateDownload())) {

        $user     = getUser($file->getUser(), $users, $token);
        $metadata = [
            "created"             => date('Y-m-d H:i:s', $file->getCreated()),
            "timestamp"           => $file->getTimestamp(),
            "name"                => $file->getName(),
            "title"               => $file->getTitle(),
            "mimetype"            => $file->getMimetype(),
            "filetype"            => $file->getFiletype(),
            "pretty_type"         => $file->getPrettyType(),
            "size"                => $file->getSize(),
            "image_exif_rotation" => $file->getImageExifRotation(),
            "original_w"          => $file->getOriginalW(),
            "original_h"          => $file->getOriginalH(),
            "channels"            => [],
            "groups"              => [],
            "ims"                 => [],
            "user"                => $user,
        ];

        if ($get_ims) {
            foreach ($file->getIms() as $im) {
                $info = getChannel('conversation', $im, $channels, $token);

                if ($info[0] !== $file->getUser()) {
                    $partner = getUser($info[0], $users, $token, $token);
                } elseif ($info[1] !== $file->getUser()) {
                    $partner = getUser($info[1], $users, $token, $token);
                } else {
                    error("cannot retrieve instant message counterpart user.");
                }

                $partnername = getUserName($partner);
                $metadata["ims"] = array_merge($metadata["ims"], ["with" => $partner]);

                // Write file to the directory name of the last encountered conversation
                $dirname1 = substr(normalize("im " . getUserName($user) . " with " . $partnername), 0, 254);
                $target1 = $destination . DIRECTORY_SEPARATOR . $dirname1;
                $dirname2 = substr(normalize("im " . $partnername . " with " . getUserName($user)), 0, 254);
                $target2 = $destination . DIRECTORY_SEPARATOR . $dirname2;

                /**
                 * Use "im [user] with [partner]" unless "im [partner] with [user]" already exists
                 * to prevent lots of duplicate directories for IM's
                 */
                if (is_dir($target2)) {
                    $dirname = $dirname2;
                    $target  = $target2;
                } else {
                    $dirname = $dirname1;
                    $target  = $target1;
                }
            }
            $process = true;
        } else {
            $process = false;
        }

        foreach ($file->getGroups() as $group) {
            $info = getChannel('group', $group, $channels, $token);
            // Write file to the directory name of the last encountered group
            $process = true;
            $target = $destination . DIRECTORY_SEPARATOR . substr($info["name"], 0, 254);
            $metadata["groups"] = array_merge($metadata["groups"], $info);
        }

        foreach ($file->getChannels() as $channel) {
            $info = getChannel('channel', $channel, $channels, $token);
            // Write file to the directory name of the last encountered channel
            $process = true;
            $target = $destination . DIRECTORY_SEPARATOR . substr($info["name"], 0, 254);
            $metadata["channels"] = array_merge($metadata["channels"], $info);
        }

        if ($process) {
            // Ensure directory presence
            if (!$simulation and (!is_dir($target))) {
                mkdir($target, 0777, true);
            }

            // Download the file
            if (!$simulation and (!file_exists($target . DIRECTORY_SEPARATOR . $filename . '.' . $file->getFiletype()))) {
                downloadFile($file->getUrlPrivateDownload(), $target . DIRECTORY_SEPARATOR . $filename . '.' . $file->getFiletype(), $token);
                output($quiet, "DL ");
            } else {
                output($quiet, "   ");
            }

            // Write metadata to file
            if (!$simulation and (!file_exists($target . DIRECTORY_SEPARATOR . $filename . '.json'))) {
                $metadata_file = fopen($target . DIRECTORY_SEPARATOR . $filename . '.json', 'w');
                fwrite($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
                fclose($metadata_file);
                output($quiet, "MD ");
            } else {
                output($quiet, "   ");
            }

            // Delete the file
            if (!$simulation and ($remove)) {
                $delete = $client->filesDelete(['file' => $file->getId()]);
                output($quiet, "R ");
            } else {
                output($quiet, "  ");
            }

            output($quiet, ": Done!" . PHP_EOL);
        } else {
            output($quiet, "        : Skipped private file!" . PHP_EOL);
        }
    } else {
        output($quiet, "        : Skipped non-downloadable file!" . PHP_EOL);
    }
    $counter++;
}
?>
