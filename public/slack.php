<?php declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// so phpstan knows these come from config
global $slack_owner_id;
global $slack_token;
global $slack_authorized_users;
global $slack_gh_org;
global $slack_channel_repos;
global $environments;
global $which_github;

$is_slack = true;

// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found

$more_than_one_repo = 'More than one repository can be deployed from this channel. Please specify a repository, '
    . 'then optionally a git ref and/or environment.';

// phpcs:enable

// Make sure this workspace has an owner
if (!array_key_exists($_POST['team_id'], $slack_owner_id)) {
    die(
        "This workspace isn't configured for deployments yet. Contact your DevOps lead.\n\nWorkspace ID: "
        . $_POST['team_id']
    );
}

// Make sure the signature matches
if ($_POST['token'] !== $slack_token[$_POST['team_id']]) {
    if ($_POST['user_id'] === $slack_owner_id[$_POST['team_id']]) {
        die(
            'Slack sent a bad token. Check to make sure the token matches between Slack and your server.'
            . "\nWorkspace ID: " . $_POST['team_id']
        );
    }

    die(
        'Slack sent a bad token. Contact <@' . $slack_owner_id[$_POST['team_id']] . '> for further assistance.'
    );
}

if ('config' === $_POST['text']) {
    if ($_POST['user_id'] === $slack_owner_id[$_POST['team_id']]) {
        echo "*Basic checks passed:* :heavy_check_mark:\n*Authorized users:*";
        foreach ($slack_authorized_users[$_POST['team_id']] as $user) {
            echo ' <@' . $user . '>';
        }
        echo "\n*GitHub account:* " . $slack_gh_org[$_POST['team_id']] . "\n*Repositories for this channel:*";
        foreach ($slack_channel_repos[$_POST['team_id']][$_POST['channel_id']] as $repo) {
            echo ' ' . $repo;
            echo '@' . ($which_github[$slack_gh_org[$_POST['team_id']] . '/' . $repo] ?? 'missing which github');
        }
        die;
    }

    die(
        '`config` is only available to the owner registered for this workspace.'
    );
}

if (!array_key_exists($_POST['channel_id'], $slack_channel_repos[$_POST['team_id']])) {
    die(
        "This command can't be used in this channel."
    );
}

if (!in_array($_POST['user_id'], $slack_authorized_users[$_POST['team_id']])) {
    die("You're not authorized to use this slash command.");
}

$repos_for_channel = $slack_channel_repos[$_POST['team_id']][$_POST['channel_id']];

if ('help' === $_POST['text']) {
    if (0 === count($repos_for_channel)) {
        die('No repositories can be deployed from this channel.');
    }
    die(
        'The following repositories can be deployed from this channel: *' .
        implode(', ', $repos_for_channel) .
        "*\n\nTo trigger a deployment, use */deploy" . (count($repos_for_channel) > 1 ? ' [repository]' : '')
        . ' [git ref] [environment]*'
    );
}

$input = explode(' ', $_POST['text']);

if (0 === count($repos_for_channel)) {
    die('No repositories can be deployed from this channel.');
}

if (0 === count($input)) {
    if (1 !== count($repos_for_channel)) {
        die($more_than_one_repo);
    }

    $payload = [];
    $payload['repository'] = [];
    $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0];
    $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
    $token = user_token($which_github[$payload['repository']['full_name']]);
    github(
        api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0] . '/deployments',
        [
            'ref' => 'master',
            'environment' => 'production',
            'auto_merge' => false,
        ],
        'triggering deployment'
    );
} elseif (1 === count($input)) {
    if (in_array($input[0], $repos_for_channel)) {
        $payload = [];
        $payload['repository'] = [];
        $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $input[0];
        $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
        $token = user_token($which_github[$payload['repository']['full_name']]);
        github(
            api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $input[0] . '/deployments',
            [
                'ref' => 'master',
                'environment' => 'production',
                'auto_merge' => false,
            ],
            'triggering deployment'
        );
    } elseif (in_array($input[0], $environments)) {
        if (1 !== count($repos_for_channel)) {
            die($more_than_one_repo);
        }

        $payload = [];
        $payload['repository'] = [];
        $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0];
        $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
        $token = user_token($which_github[$payload['repository']['full_name']]);
        github(
            api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0] . '/deployments',
            [
                'ref' => 'master',
                'environment' => $input[0],
                'auto_merge' => false,
            ],
            'triggering deployment'
        );
    } else {
        if (1 !== count($repos_for_channel)) {
            die($more_than_one_repo);
        }

        $payload = [];
        $payload['repository'] = [];
        $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0];
        $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
        $token = user_token($which_github[$payload['repository']['full_name']]);
        github(
            api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0] . '/deployments',
            [
                'ref' => $input[0],
                'environment' => 'production',
                'auto_merge' => false,
            ],
            'triggering deployment'
        );
    }
} elseif (2 === count($input)) {
    if (1 === count($repos_for_channel)) {
        if (!in_array($input[1], $environments)) {
            die(
                'Please provide a git ref and environment. Environment must be one of *'
                    . implode(', ', $environments) . '*'
            );
        }

        $payload = [];
        $payload['repository'] = [];
        $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0];
        $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
        $token = user_token($which_github[$payload['repository']['full_name']]);
        github(
            api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $repos_for_channel[0] . '/deployments',
            [
                'ref' => $input[0],
                'environment' => $input[1],
                'auto_merge' => false,
            ],
            'triggering deployment'
        );
    } else {
        if (!in_array($input[0], $repos_for_channel)) {
            die('Please specify a repository, then optionally a git ref and/or environment.');
        }

        if (in_array($input[1], $environments)) {
            $payload = [];
            $payload['repository'] = [];
            $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $input[0];
            $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
            $token = user_token($which_github[$payload['repository']['full_name']]);
            github(
                api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $input[0] . '/deployments',
                [
                    'ref' => 'master',
                    'environment' => $input[1],
                    'auto_merge' => false,
                ],
                'triggering deployment'
            );
        } else {
            $payload = [];
            $payload['repository'] = [];
            $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $input[0];
            $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
            $token = user_token($which_github[$payload['repository']['full_name']]);
            github(
                api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $input[0] . '/deployments',
                [
                    'ref' => $input[1],
                    'environment' => 'production',
                    'auto_merge' => false,
                ],
                'triggering deployment'
            );
        }
    }
} elseif (3 === count($input)) {
    if (!in_array($input[0], $repos_for_channel)) {
        die('Repository must be one of *' . implode(', ', $repos_for_channel) . '*');
    }
    if (!in_array($input[2], $environments)) {
        die('Environment must be one of *' . implode(', ', $environments) . '*');
    }
    $payload = [];
    $payload['repository'] = [];
    $payload['repository']['full_name'] = $slack_gh_org[$_POST['team_id']] . '/' . $input[0];
    $payload['repository']['clone_url'] = 'https://' . $which_github[$payload['repository']['full_name']];
    $token = user_token($which_github[$payload['repository']['full_name']]);
    github(
        api_base() . '/repos/' . $slack_gh_org[$_POST['team_id']] . '/' . $input[0] . '/deployments',
        [
            'ref' => $input[1],
            'environment' => $input[2],
            'auto_merge' => false,
        ],
        'triggering deployment'
    );
} else {
    die('Too many parameters specified.');
}

echo '{"response_type": "in_channel"}';
