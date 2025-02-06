<?php

// Directory to scan (adjust this path as needed)
$rootDirectory = '/Users/Reess/Desktop/ReessDB/repos/Wordpress';  // Example path, modify as needed
$orderBy = "domain"; // Can be "default", "creation_newest", "modified_newest", or "domain"

$linkType = "external"; // Can be "internal" or "external"
$outputFile = __DIR__ . '/links_external.csv';

// Create directory if it doesn't exist
if (!file_exists($rootDirectory)) {
    mkdir($rootDirectory, 0755, true);
    echo "Created directory: $rootDirectory\n";
    echo "Please add your markdown files to this directory and run the script again.\n";
    exit;
}

// Function to recursively get all markdown files
function findMarkdownFiles($dir) {
    if (!is_dir($dir)) {
        throw new Exception("Directory '$dir' does not exist or is not accessible.");
    }
    
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['md', 'markdown'])) {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Function to extract links from markdown content
function extractLinks($content, $linkType) {
    $links = [];
    
    // Match markdown links [text](url)
    preg_match_all('/\[([^\]]*)\]\(([^\)]+)\)/', $content, $markdownLinks, PREG_SET_ORDER);
    foreach ($markdownLinks as $match) {
        $url = trim($match[2]);
        $name = trim($match[1]);
        
        // Check if the link matches the requested type
        $isExternal = preg_match('/^https?:\/\//', $url);
        
        if (($linkType === "external" && $isExternal) || 
            ($linkType === "internal" && !$isExternal)) {
            $links[] = [
                'url' => $url,
                'name' => ($name !== $url) ? $name : ''
            ];
        }
    }
    
    // Match plain URLs (only for external links)
    if ($linkType === "external") {
        preg_match_all('/(?<![\(\[])(https?:\/\/[^\s\)]+)/', $content, $plainUrls);
        foreach ($plainUrls[1] as $url) {
            $links[] = [
                'url' => trim($url),
                'name' => ''
            ];
        }
    }
    
    return $links;
}

// Function to get real creation time on macOS
function getMacCreationTime($file) {
    if (PHP_OS === 'Darwin') { // Check if running on macOS
        $stat = shell_exec('stat -f %B ' . escapeshellarg($file));
        return trim($stat) ? date('Y-m-d H:i:s', trim($stat)) : date('Y-m-d H:i:s', filectime($file));
    }
    return date('Y-m-d H:i:s', filectime($file));
}

// Function to extract domain from URL
function extractDomain($url) {
    // Remove protocol (http, https, etc.)
    $domain = preg_replace('(^https?://)', '', $url);
    // Remove path, query string, and fragment
    $domain = strtok($domain, '/');
    // Remove www. if present
    $domain = preg_replace('/^www\./', '', $domain);
    return $domain;
}

// Main process
$markdownFiles = findMarkdownFiles($rootDirectory);
$allLinks = []; // Array to store all links before writing

foreach ($markdownFiles as $file) {
    $content = file_get_contents($file);
    $links = extractLinks($content, $linkType);
    
    // Get file creation and modification times
    $creationTime = getMacCreationTime($file);
    $modifiedTime = date('Y-m-d H:i:s', filemtime($file));
    $filename = basename($file);
    
    // Store all link information
    foreach ($links as $link) {
        $allLinks[] = [
            'domain' => $linkType === "external" ? extractDomain($link['url']) : "",
            'filename' => $filename,
            'url' => $link['url'],
            'name' => $link['name'],
            'fullpath' => $file,
            'creation_time' => $creationTime,
            'modified_time' => $modifiedTime
        ];
    }
}

// Sort links based on orderBy setting
if ($orderBy === "creation_newest") {
    usort($allLinks, function($a, $b) {
        // Reverse the comparison to get newest first
        return strtotime($b['creation_time']) - strtotime($a['creation_time']);
    });
} elseif ($orderBy === "modified_newest") {
    usort($allLinks, function($a, $b) {
        // Sort by modified time, newest first
        return strtotime($b['modified_time']) - strtotime($a['modified_time']);
    });
} elseif ($orderBy === "domain") {
    usort($allLinks, function($a, $b) {
        // Sort by domain alphabetically
        // If domains are equal, sort by URL
        $domainCompare = strcasecmp($a['domain'], $b['domain']);
        return $domainCompare === 0 ? 
            strcasecmp($a['url'], $b['url']) : 
            $domainCompare;
    });
}

// Initialize CSV file with headers
$csvHeaders = ['Domain', 'File', 'URL', 'Link Name', 'Source File', 'Creation Date', 'Last Modified Date'];
$fp = fopen($outputFile, 'w');
fputcsv($fp, $csvHeaders);

// Write sorted links to CSV
foreach ($allLinks as $link) {
    fputcsv($fp, [
        $link['domain'],
        $link['filename'],
        $link['url'],
        $link['name'],
        $link['fullpath'],
        $link['creation_time'],
        $link['modified_time']
    ]);
}

fclose($fp);
echo "Link extraction completed. Results saved to: $outputFile\n";