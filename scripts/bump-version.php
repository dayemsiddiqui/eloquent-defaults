#!/usr/bin/env php
<?php

function getCurrentVersion()
{
    $output = shell_exec('git tag --sort=-version:refname | head -1');
    $version = trim($output);

    if (empty($version)) {
        return '0.0.0';
    }

    return ltrim($version, 'v');
}

function bumpVersion($currentVersion, $type)
{
    $parts = explode('.', $currentVersion);
    $major = (int) $parts[0];
    $minor = (int) $parts[1];
    $patch = (int) $parts[2];

    switch ($type) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        case 'patch':
        default:
            $patch++;
            break;
    }

    return "$major.$minor.$patch";
}

function showUsage()
{
    echo "Usage: php scripts/bump-version.php [--major|--minor|--patch] [--no-release]\n";
    echo "       composer run bump-version [-- --major|--minor|--patch] [-- --no-release]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --major       Bump major version (X.0.0)\n";
    echo "  --minor       Bump minor version (x.X.0)\n";
    echo "  --patch       Bump patch version (x.x.X) [default]\n";
    echo "  --no-release  Skip creating GitHub release\n";
    echo "  --help        Show this help message\n";
}

function executeCommand($command, $description = '')
{
    echo ($description ? "→ $description\n" : '')."  Running: $command\n";
    $output = shell_exec($command.' 2>&1');

    if ($output) {
        echo '  '.str_replace("\n", "\n  ", trim($output))."\n";
    }

    return $output;
}

// Parse command line arguments
$type = 'patch';
$showHelp = false;
$createRelease = true;

foreach ($argv as $arg) {
    if ($arg === '--major') {
        $type = 'major';
    } elseif ($arg === '--minor') {
        $type = 'minor';
    } elseif ($arg === '--patch') {
        $type = 'patch';
    } elseif ($arg === '--no-release') {
        $createRelease = false;
    } elseif ($arg === '--help' || $arg === '-h') {
        $showHelp = true;
    }
}

if ($showHelp) {
    showUsage();
    exit(0);
}

// Check if we're in a git repository
if (! is_dir('.git')) {
    echo "Error: Not in a git repository\n";
    exit(1);
}

// Get current version
$currentVersion = getCurrentVersion();
echo "Current version: v$currentVersion\n";

// Calculate new version
$newVersion = bumpVersion($currentVersion, $type);
echo "New version: v$newVersion\n";

// Confirm with user
echo "\nThis will:\n";
echo "1. Create tag v$newVersion\n";
echo "2. Push tag to origin\n";
if ($createRelease) {
    echo "3. Create GitHub release\n";
}
echo "\nContinue? (y/N): ";

$handle = fopen('php://stdin', 'r');
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'y' && strtolower($confirmation) !== 'yes') {
    echo "Cancelled.\n";
    exit(0);
}

echo "\n";

// Create and push tag
executeCommand("git tag v$newVersion", "Creating tag v$newVersion");
executeCommand("git push origin v$newVersion", 'Pushing tag to origin');

// Create GitHub release if requested
if ($createRelease) {
    // Check if gh CLI is available
    $ghOutput = shell_exec('command -v gh 2>/dev/null');
    if (empty($ghOutput)) {
        echo "⚠️  GitHub CLI (gh) not found. Skipping GitHub release creation.\n";
        echo "   Install with: brew install gh\n";
    } else {
        echo "\n";
        executeCommand("gh release create v$newVersion --generate-notes", 'Creating GitHub release');
    }
}

echo "\n✅ Version bumped to v$newVersion and pushed to origin!\n";
if ($createRelease && ! empty($ghOutput)) {
    echo "✅ GitHub release created!\n";
}
echo "The new version should be available on Packagist shortly.\n";
