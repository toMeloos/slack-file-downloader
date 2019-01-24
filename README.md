# slack-file-downloader

This is a simple tool to bulk download files from Slack. It allows easy downloading and deleting of  file archives of Slack teams. Practical use cases can be making local/offline archive copies of your Slack files or cleaning up the Slack file archive of a team that exceded the Slack file limit for free team accounts.

## Features

The tool uses the Slack API to download and (optionally) delete files from Slack. It allows you to:

  - Bulk-download files from a Slack team.
  - Adds a JSON metadata file for each downloaded Slack file containing information from Slack about the file, the conversation it was (originally) shared in and the user that uploaded it.
  - Optionally delete downloaded files from Slack.
  - Set how many months to retain in Slack (defaults to retaining the last 6 months).

## Installation

1. Make sure you have (https://getcomposer.org)[composer] and php-cli 7.0 or higher installed.
1. Run `composer install`
1. In order to use slack-file-downloader, you need to [create an application](https://api.slack.com/apps/new). Once created, go to the _OAuth & Permissions_ page of your application and grant it the following permission scopes:

  - channels:read
  - files:read
  - files:write:user
  - groups:read
  - im:read
  - mpim:read
  - users:read

1. After these permission scopes are set, (re)install your application inside your Slack team.
1. Copy the OAuth Access Token generated for the slack-file-downloader application for your team, and save it somewhere secure for future use. You can now use the download tool, for which you will need the token that you just obtained.


## Usage

Run this script from the command line using PHP:

`php download.php -t [xoxp-xxxxxxxxxxx-xxxxxxxxxxx-xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx]`

More options are available and can be retrieved using the built-in help: `php download.php -h`.

Note: This tool is provided as-is, so use it at your own risk. A trial run without the `-r` delete parameter is recommended to ensure correct processing before losing any important files.

## Contributing

This tool is by no means feature complete. Please feel free to contribute bug fixes, new features and other improvements.

## License

This software is licensed under the GNU General Public License v3. See the [LICENSE](LICENSE) file for license rights and limitations. Copyright (c) 2017-2019 Tom Verdaat and contributors.
